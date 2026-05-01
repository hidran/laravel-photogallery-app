#!/usr/bin/env bash
set -euo pipefail

# ==============================================================
# PhotoGallery Pro — CodePipeline Setup
#
# Deploys the CI/CD pipeline (CodePipeline + CodeBuild) for the
# main app stack. Run this AFTER ./infra/deploy.sh has provisioned
# the app stack at least once.
#
# Prereqs (one-time, in the AWS console):
#   1. Developer Tools → Settings → Connections → Create connection
#      Provider: GitHub
#      Name: photogallery-github
#   2. Click "Install a new app" → authorize the AWS Connector for
#      GitHub on your repo. This moves the connection from PENDING
#      to AVAILABLE.
#   3. Copy the connection ARN.
#
# Usage:
#   ./infra/deploy-pipeline.sh <github-owner> <github-repo> <connection-arn>
#
# Re-running is safe — updates the existing pipeline stack in place.
# ==============================================================

if [ $# -lt 3 ]; then
    cat <<USAGE
Usage: $0 <github-owner> <github-repo> <connection-arn> [branch]

  github-owner   — GitHub username or org (e.g. hidran)
  github-repo    — repository name (e.g. photogallerypro)
  connection-arn — ARN of the AVAILABLE CodeStar Connection
  branch         — optional, defaults to 'main'
USAGE
    exit 1
fi

REGION="eu-west-1"
APP_STACK_NAME="photogallery-production"
PIPELINE_STACK_NAME="${APP_STACK_NAME}-pipeline"
GITHUB_OWNER="$1"
GITHUB_REPO="$2"
CONNECTION_ARN="$3"
BRANCH="${4:-main}"

echo "============================================"
echo "  PhotoGallery Pro — Pipeline Deploy"
echo "  App stack:   $APP_STACK_NAME"
echo "  GitHub:      $GITHUB_OWNER/$GITHUB_REPO ($BRANCH)"
echo "  Connection:  $CONNECTION_ARN"
echo "============================================"
echo ""

# Verify the app stack exists (pipeline imports values from it)
if ! aws cloudformation describe-stacks --region "$REGION" --stack-name "$APP_STACK_NAME" >/dev/null 2>&1; then
    echo "ERROR: app stack '$APP_STACK_NAME' not found."
    echo "Run ./infra/deploy.sh first."
    exit 1
fi

# Verify the connection is available
STATUS=$(aws codestar-connections get-connection --region "$REGION" \
    --connection-arn "$CONNECTION_ARN" \
    --query 'Connection.ConnectionStatus' --output text 2>&1 || echo "UNKNOWN")
if [ "$STATUS" != "AVAILABLE" ]; then
    echo "ERROR: connection is in state '$STATUS' (must be AVAILABLE)."
    echo "Open the AWS console → Developer Tools → Settings → Connections"
    echo "and click 'Update pending connection' to authorize the GitHub App."
    exit 1
fi

aws cloudformation deploy \
    --region "$REGION" \
    --stack-name "$PIPELINE_STACK_NAME" \
    --template-file "$(dirname "$0")/pipeline.yml" \
    --capabilities CAPABILITY_IAM \
    --parameter-overrides \
        "AppStackName=$APP_STACK_NAME" \
        "GitHubOwner=$GITHUB_OWNER" \
        "GitHubRepo=$GITHUB_REPO" \
        "GitHubBranch=$BRANCH" \
        "CodeStarConnectionArn=$CONNECTION_ARN" \
    --no-fail-on-empty-changeset

echo ""
echo "Pipeline deployed. URL:"
aws cloudformation describe-stacks --region "$REGION" --stack-name "$PIPELINE_STACK_NAME" \
    --query "Stacks[0].Outputs[?OutputKey=='PipelineUrl'].OutputValue" --output text
echo ""
echo "Push to '$BRANCH' on $GITHUB_OWNER/$GITHUB_REPO to trigger the pipeline."
