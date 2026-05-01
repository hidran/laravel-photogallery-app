#!/usr/bin/env bash
set -euo pipefail

# ==============================================================
# PhotoGallery Pro — First-Time Deployment Script
#
# Prerequisites:
#   - AWS CLI configured with appropriate permissions
#   - Docker installed
#   - Region: eu-west-1
#
# Usage:
#   ./infra/deploy.sh
#
# Flow:
#   1. Create/verify ECR repo (idempotent)
#   2. Build and push Docker image to ECR
#   3. Deploy CloudFormation stack with the real image URI
#   4. Run migrations + seed admin
#   5. Build and deploy frontend to S3 + invalidate CloudFront
#
# Re-running this script is safe — it updates the existing stack and
# performs a rolling deploy of new container images.
#
# MIGRATION NOTE: if you deployed before this version (when ECR was
# managed inside CloudFormation), the new template removes ECR. Before
# applying it to an existing stack, detach ECR from the stack first:
#
#   1. Edit the OLD cloudformation.yml: add `DeletionPolicy: Retain`
#      under the ECRRepository resource. Deploy that change first.
#   2. Remove the ECRRepository resource block. Deploy again — CFN
#      will detach (not delete) the repo.
#   3. Then run this script normally.
# ==============================================================

REGION="eu-west-1"
STACK_NAME="photogallery-production"
ENVIRONMENT="production"
ECR_REPO_NAME="$STACK_NAME"

echo "============================================"
echo "  PhotoGallery Pro — AWS Deployment"
echo "  Region: $REGION"
echo "============================================"
echo ""

ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
ECR_URI="${ACCOUNT_ID}.dkr.ecr.${REGION}.amazonaws.com/${ECR_REPO_NAME}"
echo "Account: $ACCOUNT_ID"
echo "ECR:     $ECR_URI"
echo ""

# Detect first-time vs update by checking if the stack exists
STACK_EXISTS=false
if aws cloudformation describe-stacks --region "$REGION" --stack-name "$STACK_NAME" >/dev/null 2>&1; then
    STACK_EXISTS=true
    echo ">>> Existing stack detected — running update flow."
else
    echo ">>> No existing stack — running first-time deploy flow."
fi
echo ""

# Prompt for secrets only on first deploy
if [ "$STACK_EXISTS" = false ]; then
    read -rsp "Enter DB password (min 12 chars): " DB_PASSWORD
    echo ""
    read -rsp "Enter Admin password: " ADMIN_PASSWORD
    echo ""

    # Generate APP_KEY
    APP_KEY="base64:$(openssl rand -base64 32)"
    echo ""
    echo "Generated APP_KEY: $APP_KEY"
    echo "(Save this — you'll need it if you recreate the stack)"
    echo ""
else
    echo "Note: re-using existing stack parameters. To rotate secrets, "
    echo "delete the stack and re-run, or use 'aws cloudformation update-stack'."
    echo ""
    DB_PASSWORD=""
    ADMIN_PASSWORD=""
    APP_KEY=""
fi

# ============================================================
# Step 1: ECR repository (idempotent)
# ============================================================
echo ">>> Step 1: Ensuring ECR repository exists..."
if aws ecr describe-repositories --region "$REGION" --repository-names "$ECR_REPO_NAME" >/dev/null 2>&1; then
    echo "    ECR repo $ECR_REPO_NAME already exists — skipping create."
else
    aws ecr create-repository \
        --region "$REGION" \
        --repository-name "$ECR_REPO_NAME" \
        --image-scanning-configuration scanOnPush=true \
        --query 'repository.repositoryUri' --output text

    aws ecr put-lifecycle-policy \
        --region "$REGION" \
        --repository-name "$ECR_REPO_NAME" \
        --lifecycle-policy-text '{"rules":[{"rulePriority":1,"description":"Keep last 10 images","selection":{"tagStatus":"any","countType":"imageCountMoreThan","countNumber":10},"action":{"type":"expire"}}]}' \
        >/dev/null
fi
echo ""

# ============================================================
# Step 2: Build and push Docker image
# ============================================================
echo ">>> Step 2: Building Docker image..."
cd "$(dirname "$0")/../backend"

aws ecr get-login-password --region "$REGION" | \
    docker login --username AWS --password-stdin "${ECR_URI%%/*}"

# Build for linux/amd64 — Fargate doesn't support arm64 by default,
# and Mac builds default to arm64 which Fargate refuses.
docker build --platform linux/amd64 -t "$ECR_URI:latest" .
docker push "$ECR_URI:latest"

echo ""
echo ">>> Image pushed: $ECR_URI:latest"
echo ""

# ============================================================
# Step 3: Deploy CloudFormation stack
# ============================================================
echo ">>> Step 3: Deploying CloudFormation stack..."
echo "    First-time: ~10-15 min (RDS provisioning is slow)."
echo "    Updates: ~3-5 min."
echo ""

cd "$(dirname "$0")"

CFN_PARAMS=(
    "Environment=$ENVIRONMENT"
    "ContainerImage=${ECR_URI}:latest"
)
if [ "$STACK_EXISTS" = false ]; then
    CFN_PARAMS+=(
        "DBPassword=$DB_PASSWORD"
        "AppKey=$APP_KEY"
        "AdminPassword=$ADMIN_PASSWORD"
    )
else
    # Re-use previous parameter values (avoids re-prompting)
    CFN_PARAMS+=(
        "DBPassword=UsePreviousValue=true"
        "AppKey=UsePreviousValue=true"
        "AdminPassword=UsePreviousValue=true"
    )
fi

aws cloudformation deploy \
    --region "$REGION" \
    --stack-name "$STACK_NAME" \
    --template-file cloudformation.yml \
    --capabilities CAPABILITY_IAM \
    --parameter-overrides "${CFN_PARAMS[@]}" \
    --no-fail-on-empty-changeset

echo ""
echo ">>> Stack deployed. Fetching outputs..."

FRONTEND_BUCKET=$(aws cloudformation describe-stacks --region "$REGION" --stack-name "$STACK_NAME" \
    --query "Stacks[0].Outputs[?OutputKey=='FrontendBucketName'].OutputValue" --output text)
API_URL=$(aws cloudformation describe-stacks --region "$REGION" --stack-name "$STACK_NAME" \
    --query "Stacks[0].Outputs[?OutputKey=='APIURL'].OutputValue" --output text)
FRONTEND_URL=$(aws cloudformation describe-stacks --region "$REGION" --stack-name "$STACK_NAME" \
    --query "Stacks[0].Outputs[?OutputKey=='FrontendURL'].OutputValue" --output text)
CF_DIST_ID=$(aws cloudformation describe-stacks --region "$REGION" --stack-name "$STACK_NAME" \
    --query "Stacks[0].Outputs[?OutputKey=='CloudFrontDistributionId'].OutputValue" --output text)

echo "    API:       $API_URL"
echo "    Frontend:  $FRONTEND_URL"
echo ""

# ============================================================
# Step 4: Force ECS to pull the latest image and run migrations
# ============================================================
echo ">>> Step 4: Forcing ECS rolling deployment..."
aws ecs update-service --region "$REGION" --cluster "$STACK_NAME" \
    --service "${STACK_NAME}-api" --force-new-deployment >/dev/null
aws ecs update-service --region "$REGION" --cluster "$STACK_NAME" \
    --service "${STACK_NAME}-worker" --force-new-deployment >/dev/null

echo "    Waiting for API service to stabilize..."
aws ecs wait services-stable --region "$REGION" --cluster "$STACK_NAME" \
    --services "${STACK_NAME}-api"
echo "    API service stable."
echo ""

echo ">>> Running migrations..."
PUBLIC_SUBNET_A=$(aws cloudformation describe-stack-resources --region "$REGION" --stack-name "$STACK_NAME" \
    --query "StackResources[?LogicalResourceId=='PublicSubnetA'].PhysicalResourceId" --output text)
PUBLIC_SUBNET_B=$(aws cloudformation describe-stack-resources --region "$REGION" --stack-name "$STACK_NAME" \
    --query "StackResources[?LogicalResourceId=='PublicSubnetB'].PhysicalResourceId" --output text)
ECS_SG=$(aws cloudformation describe-stack-resources --region "$REGION" --stack-name "$STACK_NAME" \
    --query "StackResources[?LogicalResourceId=='ECSSecurityGroup'].PhysicalResourceId" --output text)

MIGRATE_TASK_ARN=$(aws ecs run-task \
    --region "$REGION" \
    --cluster "$STACK_NAME" \
    --task-definition "$STACK_NAME" \
    --launch-type FARGATE \
    --network-configuration "awsvpcConfiguration={subnets=[$PUBLIC_SUBNET_A,$PUBLIC_SUBNET_B],securityGroups=[$ECS_SG],assignPublicIp=ENABLED}" \
    --overrides '{"containerOverrides":[{"name":"app","command":["sh","-c","php artisan migrate --force && php artisan db:seed --class=AdminUserSeeder --force"]}]}' \
    --query "tasks[0].taskArn" --output text)

echo "    Migration task: ${MIGRATE_TASK_ARN##*/}"
echo "    Waiting for completion..."
aws ecs wait tasks-stopped --region "$REGION" --cluster "$STACK_NAME" --tasks "$MIGRATE_TASK_ARN"

EXIT_CODE=$(aws ecs describe-tasks --region "$REGION" --cluster "$STACK_NAME" --tasks "$MIGRATE_TASK_ARN" \
    --query "tasks[0].containers[0].exitCode" --output text)
if [ "$EXIT_CODE" != "0" ]; then
    echo "    ERROR: Migration task exited with code $EXIT_CODE"
    aws logs tail /ecs/$STACK_NAME --region "$REGION" --since 5m | grep "${MIGRATE_TASK_ARN##*/}" | tail -20
    exit 1
fi
echo "    Migrations complete."
echo ""

# ============================================================
# Step 5: Build and deploy frontend
# ============================================================
echo ">>> Step 5: Building frontend..."
cd "$(dirname "$0")/../frontend"

npm ci
VITE_API_BASE_URL="$API_URL" npm run build

echo ""
echo ">>> Deploying frontend to S3..."
aws s3 sync dist/ "s3://$FRONTEND_BUCKET/" \
    --delete \
    --cache-control "public, max-age=31536000, immutable" \
    --exclude "index.html" \
    --exclude "*.json"

aws s3 cp dist/index.html "s3://$FRONTEND_BUCKET/index.html" \
    --cache-control "no-cache, no-store, must-revalidate"

aws cloudfront create-invalidation \
    --distribution-id "$CF_DIST_ID" \
    --paths "/index.html" >/dev/null

echo ""
echo "============================================"
echo "  Deployment Complete!"
echo "============================================"
echo ""
echo "  API:      $API_URL"
echo "  Frontend: $FRONTEND_URL"
echo ""
if [ "$STACK_EXISTS" = false ]; then
    echo "  Admin login:"
    echo "    Email:    admin@example.com"
    echo "    Password: (the one you entered above)"
    echo ""
fi
echo "  GitHub Actions secrets to set:"
echo "    AWS_DEPLOY_ROLE_ARN        — IAM role ARN for OIDC"
echo "    PRIVATE_SUBNET_A           — $PUBLIC_SUBNET_A"
echo "    PRIVATE_SUBNET_B           — $PUBLIC_SUBNET_B"
echo "    ECS_SECURITY_GROUP         — $ECS_SG"
echo "    FRONTEND_BUCKET            — $FRONTEND_BUCKET"
echo "    CLOUDFRONT_DISTRIBUTION_ID — $CF_DIST_ID"
echo "    API_URL                    — $API_URL"
echo "============================================"
