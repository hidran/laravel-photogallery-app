#!/usr/bin/env bash
set -euo pipefail

# ==============================================================
# PhotoGallery Pro — Destroy Script
#
# Tears down everything that ./infra/deploy.sh created:
#   - CloudFormation stack (VPC, RDS, ECS, ALB, S3 buckets, SQS,
#     CloudFront, IAM roles)
#   - ECR repository + images (which live outside CFN)
#
# This is destructive. Run with extreme care.
#
# What is preserved:
#   - The final RDS snapshot (RDS DeletionPolicy: Snapshot). It
#     keeps accruing storage cost until manually deleted.
#   - CloudWatch log groups (retention configured separately).
#
# Usage:
#   ./infra/destroy.sh           # interactive, asks for confirmation
#   ./infra/destroy.sh --force   # skip confirmation (CI use only)
# ==============================================================

REGION="eu-west-1"
STACK_NAME="photogallery-production"
ECR_REPO_NAME="$STACK_NAME"

FORCE=false
if [ "${1:-}" = "--force" ]; then
    FORCE=true
fi

ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)

echo "============================================"
echo "  PhotoGallery Pro — Destroy"
echo "  Region:  $REGION"
echo "  Stack:   $STACK_NAME"
echo "  Account: $ACCOUNT_ID"
echo "============================================"
echo ""
echo "This will permanently delete:"
echo "  - CloudFormation stack '$STACK_NAME' and all its resources"
echo "  - ECR repository '$ECR_REPO_NAME' and all images"
echo "  - All photos in the photos-public and photos-private S3 buckets"
echo "  - The frontend S3 bucket and its contents"
echo "  - All RDS data (a final snapshot will be created and retained)"
echo ""
echo "Resources that will SURVIVE this script:"
echo "  - RDS final snapshot (delete manually if not needed; accrues storage cost)"
echo "  - CloudWatch log group /ecs/$STACK_NAME"
echo ""

if [ "$FORCE" = false ]; then
    echo "Type the stack name '$STACK_NAME' to confirm destruction:"
    read -r CONFIRM
    if [ "$CONFIRM" != "$STACK_NAME" ]; then
        echo "Aborted."
        exit 1
    fi
    echo ""
fi

# ============================================================
# Step 0: Tear down pipeline stack (if present)
# ============================================================
PIPELINE_STACK="${STACK_NAME}-pipeline"
if aws cloudformation describe-stacks --region "$REGION" --stack-name "$PIPELINE_STACK" >/dev/null 2>&1; then
    echo ">>> Step 0: Deleting pipeline stack '$PIPELINE_STACK'..."

    # Empty pipeline artifact bucket so CFN can delete it
    ARTIFACT_BUCKET="photogallery-${ACCOUNT_ID}-pipeline-artifacts"
    if aws s3api head-bucket --bucket "$ARTIFACT_BUCKET" --region "$REGION" 2>/dev/null; then
        aws s3 rm "s3://$ARTIFACT_BUCKET" --recursive --region "$REGION" --quiet || true
    fi

    aws cloudformation delete-stack --region "$REGION" --stack-name "$PIPELINE_STACK"
    aws cloudformation wait stack-delete-complete --region "$REGION" --stack-name "$PIPELINE_STACK"
    echo "    Pipeline stack deleted."
    echo ""
fi

# ============================================================
# Step 1: Empty S3 buckets (CloudFormation can't delete non-empty buckets)
# ============================================================
echo ">>> Step 1: Emptying S3 buckets..."

empty_bucket() {
    local bucket="$1"
    if aws s3api head-bucket --bucket "$bucket" --region "$REGION" 2>/dev/null; then
        echo "    Emptying s3://$bucket ..."

        # Delete all objects + all object versions (in case versioning was ever on)
        aws s3 rm "s3://$bucket" --recursive --region "$REGION" --quiet || true

        # Clean up any lingering versions / delete markers
        local versions
        versions=$(aws s3api list-object-versions \
            --bucket "$bucket" --region "$REGION" \
            --output json \
            --query '{Objects: Versions[].{Key: Key, VersionId: VersionId}}' 2>/dev/null || echo '{}')
        if [ "$versions" != '{}' ] && [ "$(echo "$versions" | grep -c Key)" -gt 0 ]; then
            echo "$versions" | aws s3api delete-objects \
                --bucket "$bucket" --region "$REGION" \
                --delete file:///dev/stdin >/dev/null 2>&1 || true
        fi

        local markers
        markers=$(aws s3api list-object-versions \
            --bucket "$bucket" --region "$REGION" \
            --output json \
            --query '{Objects: DeleteMarkers[].{Key: Key, VersionId: VersionId}}' 2>/dev/null || echo '{}')
        if [ "$markers" != '{}' ] && [ "$(echo "$markers" | grep -c Key)" -gt 0 ]; then
            echo "$markers" | aws s3api delete-objects \
                --bucket "$bucket" --region "$REGION" \
                --delete file:///dev/stdin >/dev/null 2>&1 || true
        fi
    else
        echo "    s3://$bucket — not found, skipping."
    fi
}

# Bucket names match the CloudFormation template format.
empty_bucket "photogallery-production-frontend-${ACCOUNT_ID}"
empty_bucket "photogallery-production-photos-public-${ACCOUNT_ID}"
empty_bucket "photogallery-production-photos-private-${ACCOUNT_ID}"

echo ""

# ============================================================
# Step 2: Delete CloudFormation stack
# ============================================================
echo ">>> Step 2: Deleting CloudFormation stack..."

if aws cloudformation describe-stacks --region "$REGION" --stack-name "$STACK_NAME" >/dev/null 2>&1; then
    aws cloudformation delete-stack --region "$REGION" --stack-name "$STACK_NAME"

    echo "    Waiting for stack delete to complete (~10-15 min for RDS snapshot)..."
    if ! aws cloudformation wait stack-delete-complete \
        --region "$REGION" --stack-name "$STACK_NAME"; then
        echo "    ERROR: stack-delete-complete waiter failed."
        echo "    Check the CloudFormation console for stuck resources, then re-run."
        exit 1
    fi
    echo "    Stack deleted."
else
    echo "    Stack '$STACK_NAME' not found — skipping."
fi

echo ""

# ============================================================
# Step 3: Delete ECR repository (managed outside CloudFormation)
# ============================================================
echo ">>> Step 3: Deleting ECR repository..."

if aws ecr describe-repositories --region "$REGION" --repository-names "$ECR_REPO_NAME" >/dev/null 2>&1; then
    aws ecr delete-repository \
        --region "$REGION" \
        --repository-name "$ECR_REPO_NAME" \
        --force >/dev/null
    echo "    ECR repo deleted."
else
    echo "    ECR repo '$ECR_REPO_NAME' not found — skipping."
fi

echo ""

# ============================================================
# Summary
# ============================================================
echo "============================================"
echo "  Destroy Complete"
echo "============================================"
echo ""
echo "  Surviving resources:"

# RDS snapshots
SNAPSHOTS=$(aws rds describe-db-snapshots \
    --region "$REGION" \
    --snapshot-type manual \
    --query "DBSnapshots[?contains(DBSnapshotIdentifier,'$STACK_NAME')].DBSnapshotIdentifier" \
    --output text 2>/dev/null || true)
if [ -n "$SNAPSHOTS" ]; then
    echo "    RDS snapshots:"
    for snap in $SNAPSHOTS; do
        echo "      - $snap"
    done
    echo "    Delete them with:"
    echo "      aws rds delete-db-snapshot --region $REGION --db-snapshot-identifier <id>"
fi

# CloudWatch log group
if aws logs describe-log-groups --region "$REGION" \
    --log-group-name-prefix "/ecs/$STACK_NAME" \
    --query "logGroups[].logGroupName" --output text 2>/dev/null | grep -q "$STACK_NAME"; then
    echo "    CloudWatch log group: /ecs/$STACK_NAME"
    echo "    Delete it with:"
    echo "      aws logs delete-log-group --region $REGION --log-group-name /ecs/$STACK_NAME"
fi

echo ""
echo "  To delete everything including the survivors above:"
echo "    ./infra/destroy.sh && ./infra/destroy-survivors.sh   (script not yet written)"
echo "============================================"
