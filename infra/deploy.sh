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
# This script:
#   1. Creates the CloudFormation stack (VPC, RDS, ECS, S3, SQS, etc.)
#   2. Builds and pushes the Docker image to ECR
#   3. Runs migrations
#   4. Seeds the admin user
#   5. Builds and deploys the frontend to S3
# ==============================================================

REGION="eu-west-1"
STACK_NAME="photogallery-production"
ENVIRONMENT="production"

echo "============================================"
echo "  PhotoGallery Pro — AWS Deployment"
echo "  Region: $REGION"
echo "============================================"
echo ""

# Prompt for secrets
read -rsp "Enter DB password (min 12 chars): " DB_PASSWORD
echo ""
read -rsp "Enter Admin password: " ADMIN_PASSWORD
echo ""

# Generate APP_KEY if not provided
APP_KEY="${APP_KEY:-$(openssl rand -base64 32)}"
APP_KEY="base64:$APP_KEY"
echo "Generated APP_KEY: $APP_KEY"
echo "(Save this — you'll need it if you recreate the stack)"
echo ""

# Step 1: Deploy CloudFormation (skip ECR image for now — chicken-and-egg)
echo ">>> Step 1: Creating CloudFormation stack..."
echo "    This will take 10-15 minutes (RDS creation is slow)..."

# First create just the ECR repo so we can push an image
aws cloudformation deploy \
  --region "$REGION" \
  --stack-name "${STACK_NAME}" \
  --template-file "$(dirname "$0")/cloudformation.yml" \
  --capabilities CAPABILITY_IAM \
  --parameter-overrides \
    "Environment=$ENVIRONMENT" \
    "DBPassword=$DB_PASSWORD" \
    "AppKey=$APP_KEY" \
    "AdminPassword=$ADMIN_PASSWORD" \
    "ContainerImage=placeholder" \
  --no-fail-on-empty-changeset

echo ">>> Stack created. Fetching outputs..."

# Get stack outputs
ECR_URI=$(aws cloudformation describe-stacks --region "$REGION" --stack-name "$STACK_NAME" \
  --query "Stacks[0].Outputs[?OutputKey=='ECRRepositoryURI'].OutputValue" --output text)
FRONTEND_BUCKET=$(aws cloudformation describe-stacks --region "$REGION" --stack-name "$STACK_NAME" \
  --query "Stacks[0].Outputs[?OutputKey=='FrontendBucketName'].OutputValue" --output text)
API_URL=$(aws cloudformation describe-stacks --region "$REGION" --stack-name "$STACK_NAME" \
  --query "Stacks[0].Outputs[?OutputKey=='APIURL'].OutputValue" --output text)
FRONTEND_URL=$(aws cloudformation describe-stacks --region "$REGION" --stack-name "$STACK_NAME" \
  --query "Stacks[0].Outputs[?OutputKey=='FrontendURL'].OutputValue" --output text)
CF_DIST_ID=$(aws cloudformation describe-stacks --region "$REGION" --stack-name "$STACK_NAME" \
  --query "Stacks[0].Outputs[?OutputKey=='CloudFrontDistributionId'].OutputValue" --output text)

echo "  ECR:       $ECR_URI"
echo "  API:       $API_URL"
echo "  Frontend:  $FRONTEND_URL"
echo ""

# Step 2: Build and push Docker image
echo ">>> Step 2: Building Docker image..."
cd "$(dirname "$0")/../backend"

aws ecr get-login-password --region "$REGION" | docker login --username AWS --password-stdin "${ECR_URI%%/*}"

docker build -t "$ECR_URI:latest" .
docker push "$ECR_URI:latest"

echo ">>> Image pushed: $ECR_URI:latest"
echo ""

# Step 3: Update stack with real image
echo ">>> Step 3: Updating stack with real container image..."
cd "$(dirname "$0")"

aws cloudformation deploy \
  --region "$REGION" \
  --stack-name "${STACK_NAME}" \
  --template-file cloudformation.yml \
  --capabilities CAPABILITY_IAM \
  --parameter-overrides \
    "Environment=$ENVIRONMENT" \
    "DBPassword=$DB_PASSWORD" \
    "AppKey=$APP_KEY" \
    "AdminPassword=$ADMIN_PASSWORD" \
    "ContainerImage=${ECR_URI}:latest" \
  --no-fail-on-empty-changeset

echo ">>> Waiting for ECS service to stabilize..."
aws ecs wait services-stable \
  --region "$REGION" \
  --cluster "$STACK_NAME" \
  --services "${STACK_NAME}-api"

# Step 4: Run migrations + seed
echo ">>> Step 4: Running migrations..."

# Get networking info for run-task
PRIVATE_SUBNETS=$(aws cloudformation describe-stack-resources --region "$REGION" --stack-name "$STACK_NAME" \
  --query "StackResources[?LogicalResourceId=='PrivateSubnetA'].PhysicalResourceId" --output text)
ECS_SG=$(aws cloudformation describe-stack-resources --region "$REGION" --stack-name "$STACK_NAME" \
  --query "StackResources[?LogicalResourceId=='ECSSecurityGroup'].PhysicalResourceId" --output text)
PRIVATE_SUBNET_B=$(aws cloudformation describe-stack-resources --region "$REGION" --stack-name "$STACK_NAME" \
  --query "StackResources[?LogicalResourceId=='PrivateSubnetB'].PhysicalResourceId" --output text)

aws ecs run-task \
  --region "$REGION" \
  --cluster "$STACK_NAME" \
  --task-definition "${STACK_NAME}" \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[$PRIVATE_SUBNETS,$PRIVATE_SUBNET_B],securityGroups=[$ECS_SG],assignPublicIp=DISABLED}" \
  --overrides '{"containerOverrides":[{"name":"app","command":["sh","-c","php artisan migrate --force && php artisan db:seed --class=AdminUserSeeder --force"]}]}'

echo ">>> Migrations started (background). Waiting 30s..."
sleep 30

# Step 5: Build and deploy frontend
echo ">>> Step 5: Building frontend..."
cd "$(dirname "$0")/../frontend"

npm ci
VITE_API_BASE_URL="$API_URL" npm run build

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
  --paths "/*"

echo ""
echo "============================================"
echo "  Deployment Complete!"
echo "============================================"
echo ""
echo "  API:      $API_URL"
echo "  Frontend: $FRONTEND_URL"
echo ""
echo "  Admin login:"
echo "    Email:    admin@example.com (or ADMIN_EMAIL env)"
echo "    Password: (the one you entered above)"
echo ""
echo "  GitHub Actions secrets to set:"
echo "    AWS_DEPLOY_ROLE_ARN    — IAM role ARN for OIDC"
echo "    PRIVATE_SUBNET_A       — $PRIVATE_SUBNETS"
echo "    PRIVATE_SUBNET_B       — $PRIVATE_SUBNET_B"
echo "    ECS_SECURITY_GROUP     — $ECS_SG"
echo "    FRONTEND_BUCKET        — $FRONTEND_BUCKET"
echo "    CLOUDFRONT_DISTRIBUTION_ID — $CF_DIST_ID"
echo "    API_URL                — $API_URL"
echo "============================================"
