# Deployment

PhotoGallery Pro deploys to AWS in `eu-west-1`. The whole stack — VPC, RDS, ECS Fargate, S3, CloudFront, SQS — is one CloudFormation template (`infra/cloudformation.yml`). ECR is intentionally outside CloudFormation; see [§ ECR is not in the CloudFormation stack](#ecr-is-not-in-the-cloudformation-stack).

---

## Architecture

```
                                  ┌────────────────────────────────────────┐
                                  │         CloudFront Distribution        │
   Browser ──HTTPS──▶  CloudFront ┤  /          → S3 Frontend (SPA)        │
                                  │  /api/*     → ALB → ECS API task       │
                                  │  /admin*    → ALB → ECS API task       │
                                  │  /livewire* → ALB → ECS API task       │
                                  │  /css|js|fonts|storage/* → ALB         │
                                  └────────────────────────────────────────┘
                                                │
                                                │ HTTP (internal)
                                                ▼
                              ┌─────────────────────────────────────────┐
                              │          ALB (public subnets)           │
                              └─────────────────────────────────────────┘
                                                │
                                                ▼
                       ┌───────────────────────────────────────────────────┐
                       │         ECS Fargate cluster                       │
                       │  ┌──────────────────┐  ┌────────────────────┐    │
                       │  │  API service     │  │  Worker service    │    │
                       │  │  (nginx + PHP)   │  │  (queue:work sqs)  │    │
                       │  └────────┬─────────┘  └────────┬───────────┘    │
                       └───────────┼─────────────────────┼────────────────┘
                                   │                     │
                          ┌────────┴────────┬────────────┴──┬──────────────┐
                          ▼                 ▼               ▼              ▼
                  ┌─────────────┐    ┌─────────────┐  ┌──────────┐   ┌──────────┐
                  │ RDS MariaDB │    │ S3 (public) │  │ S3 (priv)│   │   SQS    │
                  │ db.t4g.micro│    │  variants   │  │ originals│   │   queue  │
                  └─────────────┘    └─────────────┘  └──────────┘   └──────────┘
```

**Key facts**

- Single CloudFront distribution serves both the SPA and the API → no CORS issues, no mixed content.
- ECS tasks run in **public subnets** with `AssignPublicIp: ENABLED` so they can pull from ECR without a NAT Gateway. The ALB security group is the only inbound path; tasks aren't directly reachable from the internet.
- RDS lives in **private subnets**, only the ECS security group can reach port 3306.
- The same Docker image runs both the API and the Worker — the Worker's task definition just overrides the command to `php artisan queue:work sqs`.

---

## Prerequisites

Local tools:

- AWS CLI v2 (`brew install awscli`)
- Docker Desktop or OrbStack (must support `--platform linux/amd64`)
- Node 20+, PHP 8.4+, Composer
- An AWS account with credentials configured (`aws configure` or `AWS_PROFILE`)

Required IAM permissions for the deploying user/role:

- `cloudformation:*` on `photogallery-*` stacks
- `ecr:*` on `photogallery-*` repos
- `ecs:*`, `iam:PassRole` for task roles
- `s3:*` on the three buckets created by the stack
- `cloudfront:CreateInvalidation`, `cloudfront:GetDistribution`
- `rds:*` on the `photogallery-*` instance

The simplest setup for solo deploys is to use AdministratorAccess. For CI/CD, scope down — see [§ GitHub Actions CD](#github-actions-cd).

---

## First-time deploy

Run the script from the repo root:

```bash
./infra/deploy.sh
```

You'll be prompted for:

1. **DB password** (min 12 chars) — sets the RDS master password. Store it.
2. **Admin password** — used to seed the Filament admin user.

The script generates an `APP_KEY` for you and prints it. Save all three secrets — the script does not persist them.

The first run takes **~10–15 minutes**, mostly RDS provisioning. The script:

1. Creates the ECR repo (idempotent — skipped if it already exists).
2. Builds the Laravel image (`backend/Dockerfile`) for `linux/amd64` and pushes it to ECR.
3. Deploys the CloudFormation stack with the real image URI.
4. Forces a rolling deploy of the API + Worker services.
5. Runs `php artisan migrate --force && db:seed --class=AdminUserSeeder` as a one-shot ECS task.
6. Builds the React frontend (`VITE_API_BASE_URL` set from the stack's APIURL output).
7. Syncs the build to S3 and invalidates CloudFront's `/index.html`.

When it finishes, the script prints the live URLs and the GitHub Actions secrets you need to wire up.

---

## Updating the deployment

Re-running `./infra/deploy.sh` is the single command for routine updates. The script detects the existing stack and:

- Skips the secret prompts (re-uses previous CloudFormation parameter values).
- Builds + pushes a new image with the same `:latest` tag.
- Deploys CloudFormation with `--no-fail-on-empty-changeset` so it's a no-op if the template hasn't changed.
- Forces a new ECS deployment so tasks pull the new image.
- Runs migrations.
- Rebuilds + redeploys the frontend.

Idempotency:

- ECR `:latest` tag is always overwritten with the new image.
- ECR lifecycle policy keeps the last 10 images, so old ones are pruned automatically.
- CloudFront invalidates only `/index.html` on update (versioned hashed assets don't need invalidation).

Re-running is safe at any time. If the script fails partway through (e.g., docker push timeout), re-run and it'll pick up where it left off.

---

## GitHub Actions CD

`.github/workflows/deploy.yml` does the same thing as `deploy.sh` but on every push to `main`. Set up:

### 1. Create an OIDC role for GitHub Actions

GitHub uses OIDC instead of long-lived AWS keys. Create an IAM role that trusts `token.actions.githubusercontent.com` and has the permissions listed in [§ Prerequisites](#prerequisites).

A starter trust policy:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": {
      "Federated": "arn:aws:iam::<ACCOUNT_ID>:oidc-provider/token.actions.githubusercontent.com"
    },
    "Action": "sts:AssumeRoleWithWebIdentity",
    "Condition": {
      "StringEquals": {
        "token.actions.githubusercontent.com:aud": "sts.amazonaws.com"
      },
      "StringLike": {
        "token.actions.githubusercontent.com:sub": "repo:<OWNER>/<REPO>:ref:refs/heads/main"
      }
    }
  }]
}
```

### 2. Set GitHub repo secrets

Settings → Secrets and variables → Actions → New repository secret:

| Secret | Value |
|---|---|
| `AWS_DEPLOY_ROLE_ARN` | The IAM role ARN from step 1 |
| `PRIVATE_SUBNET_A` | Output from `deploy.sh` (e.g. `subnet-0f1f8...`) |
| `PRIVATE_SUBNET_B` | Output from `deploy.sh` |
| `ECS_SECURITY_GROUP` | Output from `deploy.sh` |
| `FRONTEND_BUCKET` | Output from `deploy.sh` |
| `CLOUDFRONT_DISTRIBUTION_ID` | Output from `deploy.sh` |
| `API_URL` | Output from `deploy.sh` (CloudFront-rooted) |

(The "PRIVATE_SUBNET_*" naming is historical — the subnets are now public.)

### 3. Push to main

```bash
git push origin main
```

The workflow runs `deploy-backend` (build → ECR push → migrations → ECS rolling deploy) and `deploy-frontend` (build → S3 sync → CloudFront invalidation) in parallel.

The workflow does **not** run the CloudFormation deploy — only image push + ECS update. Infrastructure changes (template edits) still require `./infra/deploy.sh` from a developer machine.

---

## Operations

### Rotating secrets

`DBPassword`, `AppKey`, `AdminPassword` are CloudFormation parameters. To rotate:

```bash
aws cloudformation deploy \
  --region eu-west-1 \
  --stack-name photogallery-production \
  --template-file infra/cloudformation.yml \
  --capabilities CAPABILITY_IAM \
  --parameter-overrides \
    "Environment=production" \
    "DBPassword=<new-db-password>" \
    "ParameterKey=AppKey,UsePreviousValue=true" \
    "ParameterKey=AdminPassword,UsePreviousValue=true" \
    "ContainerImage=<account>.dkr.ecr.eu-west-1.amazonaws.com/photogallery-production:latest"
```

**Important**: the syntax for re-using existing values with `aws cloudformation deploy` is `ParameterKey=Foo,UsePreviousValue=true` (not `Foo=UsePreviousValue=true` — that sets the literal string as the value).

DB password rotation triggers an RDS `ModifyDBInstance` which takes ~5 minutes and may briefly interrupt connectivity (RDS applies the change without restart, but ECS tasks may need to reconnect).

After rotation, force a new ECS deployment so tasks pick up the new password env var:

```bash
aws ecs update-service --region eu-west-1 \
  --cluster photogallery-production \
  --service photogallery-production-api \
  --force-new-deployment
```

### Running migrations manually

`deploy.sh` runs migrations automatically. For ad-hoc:

```bash
PUBLIC_SUBNET_A=subnet-...
PUBLIC_SUBNET_B=subnet-...
ECS_SG=sg-...

aws ecs run-task \
  --region eu-west-1 \
  --cluster photogallery-production \
  --task-definition photogallery-production \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[$PUBLIC_SUBNET_A,$PUBLIC_SUBNET_B],securityGroups=[$ECS_SG],assignPublicIp=ENABLED}" \
  --overrides '{"containerOverrides":[{"name":"app","command":["php","artisan","migrate","--force"]}]}'
```

### Tailing logs

```bash
aws logs tail /ecs/photogallery-production --region eu-west-1 --follow
```

Filter for application errors only:

```bash
aws logs tail /ecs/photogallery-production --region eu-west-1 --follow \
  --filter-pattern '"production.ERROR"'
```

### Scaling

Edit `desiredCount` on the relevant ECS service:

```bash
aws ecs update-service --region eu-west-1 \
  --cluster photogallery-production \
  --service photogallery-production-api \
  --desired-count 2
```

For autoscaling, use `aws application-autoscaling` to register the service as a scalable target and create a target tracking policy on CPU. Not configured by default — single-task setup is enough for low traffic.

### Filament admin

The admin panel is at `https://<cloudfront-domain>/admin`.

- Email: `admin@example.com` (or whatever `ADMIN_EMAIL` was set to in CloudFormation)
- Password: the value of the `AdminPassword` stack parameter

The admin user is seeded by `database/seeders/AdminUserSeeder.php`. To re-seed (e.g., after rotating `AdminPassword`):

```bash
aws ecs run-task \
  --region eu-west-1 \
  --cluster photogallery-production \
  --task-definition photogallery-production \
  --launch-type FARGATE \
  --network-configuration "..." \
  --overrides '{"containerOverrides":[{"name":"app","command":["php","artisan","db:seed","--class=AdminUserSeeder","--force"]}]}'
```

---

## Configuration

### Environment variables (production)

Set in `infra/cloudformation.yml` task definition. Notable values:

| Var | Value | Purpose |
|---|---|---|
| `APP_ENV` | `production` | Triggers `URL::forceScheme('https')` and other prod-only behaviors |
| `APP_DEBUG` | `false` | Hides stack traces |
| `APP_URL` | `https://<cloudfront-domain>` | Used for signed routes, etc. |
| `DB_CONNECTION` | `mariadb` | Stack creates RDS MariaDB |
| `QUEUE_CONNECTION` | `sqs` | Worker reads from the SQS queue |
| `FILESYSTEM_DISK` / `PHOTOS_DRIVER` | `s3` | Toggles photo disks to S3 |
| `AWS_BUCKET_PUBLIC` / `AWS_BUCKET_PRIVATE` | bucket names | Photo storage |
| `TRUSTED_PROXIES` | `*` | Lets Laravel trust CloudFront's `X-Forwarded-*` headers |
| `SESSION_SECURE_COOKIE` | `true` | Required for the admin panel cookie to work over HTTPS |
| `SESSION_SAME_SITE` | `lax` | Standard for first-party flows |
| `LOG_CHANNEL` | `errorlog` | Errors go to PHP-FPM stderr → CloudWatch Logs |
| `EMAIL_VERIFICATION_ENABLED` | not set (defaults `false`) | When `true`, registration sends a verification email and mutating routes require a verified user. Requires real mail driver to be useful. |

### Image registry

Single tag: `<account>.dkr.ecr.eu-west-1.amazonaws.com/photogallery-production:latest`. Both API and Worker services pull this same image. ECR lifecycle policy keeps the last 10 layers' worth of images.

### Cache invalidation

Static assets (JS/CSS bundles) have content-hashed filenames produced by Vite, so browsers re-fetch automatically when the hash changes. Only `index.html` needs CloudFront invalidation. The deploy script does this; the GitHub Actions workflow also does this.

---

## Troubleshooting

### Stack creation hangs

If `deploy.sh` is stuck after "Deploying CloudFormation stack" for more than 20 minutes, ECS may be stuck pulling an image that doesn't exist. Check:

```bash
aws ecs describe-services --region eu-west-1 \
  --cluster photogallery-production \
  --services photogallery-production-api \
  --query "services[0].events[0:3]"
```

If you see `CannotPullContainerError`, verify the image was pushed to ECR with the right architecture (`linux/amd64`) before re-running.

### Admin panel redirects in a loop

Almost always cookies. Check:

1. `SESSION_SECURE_COOKIE=true` is set in the task definition env.
2. Browser cookies for the CloudFront domain don't include stale entries from before — clear them.
3. `APP_KEY` is exactly 32 bytes when base64-decoded. If you see *"Unsupported cipher or incorrect key length"* in logs, the key is malformed (typically because of CloudFormation parameter quirks — see [§ Rotating secrets](#rotating-secrets)).

### Frontend shows 500 errors on legitimate API errors

CloudFront's distribution-level `CustomErrorResponses` would rewrite all 4xx responses to `/index.html`. Don't add them — use a CloudFront Function on the default behavior only. The current `SpaRoutingFunction` rewrites extension-less paths to `/index.html` *but skips `/api/*`* so API errors pass through with their real status codes.

### Image upload returns 500

Most common causes:

- **Worker isn't running**: check `aws ecs describe-services --services photogallery-production-worker`.
- **S3 permissions**: ECS task role needs `s3:PutObject` on both buckets. The CloudFormation `ECSTaskRole` already has this.
- **Bucket ACL**: `'visibility' => 'public'` in `config/filesystems.php` causes `AccessControlListNotSupported`. The repo's config doesn't set this; if you change it, public access should come from the bucket policy.

### S3 image URLs blocked in browser

Check the CSP `<meta>` tag in `frontend/index.html`. CSP source expressions only support **one** wildcard at the leftmost position of the host. `https://*.s3.*.amazonaws.com` is invalid. Use `https://*.s3.eu-west-1.amazonaws.com` (region-specific) or `https://*.amazonaws.com` (looser).

---

## ECR is not in the CloudFormation stack

Originally the ECR repository was in `cloudformation.yml`. This caused a chicken-and-egg problem on first deploy:

1. CloudFormation creates ECS service with `ContainerImage=placeholder`.
2. ECS task can't start (image doesn't exist).
3. CloudFormation waits for service stability — forever.

Moving ECR to `deploy.sh` (idempotent `aws ecr create-repository`) fixes this. Side benefit: deleting the stack doesn't accidentally nuke production images.

If you're migrating an existing stack that had ECR in CloudFormation, the safe procedure is:

1. Add `DeletionPolicy: Retain` and `UpdateReplacePolicy: Retain` to the ECR resource. Deploy.
2. Remove the ECR resource block. Deploy again — CloudFormation detaches the resource without deleting it.

---

## Cost (eu-west-1, baseline)

| Service | ~Monthly cost |
|---|---|
| RDS db.t4g.micro (20 GB gp3) | $13 |
| ECS Fargate (2 × 0.5 vCPU / 1 GB) | $30 |
| ALB | $18 |
| S3 + CloudFront (low traffic) | $2–5 |
| SQS | <$1 |
| **Total** | **~$64** |

NAT Gateway was removed (saved $33/mo) by running ECS tasks in public subnets with `AssignPublicIp: ENABLED`. Tasks aren't reachable from the internet — the ALB security group is the only inbound path.

---

## Rollback

The simplest rollback is re-tagging an older image as `:latest` and forcing a new ECS deployment:

```bash
# Pull the desired older image (find its digest in ECR console)
aws ecr describe-images --region eu-west-1 \
  --repository-name photogallery-production \
  --query "imageDetails[?imageTags!=null].[imagePushedAt,imageTags]" \
  --output text

# Re-tag and push
docker pull <account>.dkr.ecr.eu-west-1.amazonaws.com/photogallery-production@<digest>
docker tag ... <account>.dkr.ecr.eu-west-1.amazonaws.com/photogallery-production:latest
docker push <account>.dkr.ecr.eu-west-1.amazonaws.com/photogallery-production:latest

# Force redeploy
aws ecs update-service --region eu-west-1 \
  --cluster photogallery-production \
  --service photogallery-production-api \
  --force-new-deployment
```

For RDS rollback: snapshots are retained for 7 days (`BackupRetentionPeriod`). Restore via `aws rds restore-db-instance-from-db-snapshot` and update the stack to point at the new instance.

For frontend rollback: re-run `aws s3 sync` with an older `dist/` build and invalidate CloudFront.
