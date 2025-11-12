# Google Cloud Run Deployment Guide for Magento 2

This guide provides comprehensive instructions for deploying Magento 2 to Google Cloud Run using Bitbucket Pipelines.

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Architecture](#architecture)
4. [Setup Instructions](#setup-instructions)
5. [Configuration](#configuration)
6. [Deployment](#deployment)
7. [Troubleshooting](#troubleshooting)
8. [Best Practices](#best-practices)

## Overview

This deployment solution enables Magento 2 to run on Google Cloud Run, a fully managed serverless platform. The implementation includes:

- **Multi-stage Dockerfile** (`Dockerfile.cloudrun`) optimized for Cloud Run
- **Nginx configuration** (`docker/nginx-cloudrun.conf`) for Cloud Run environment
- **Supervisord configuration** (`docker/supervisord-cloudrun.conf`) for process management
- **Entrypoint script** (`docker/cloudrun-entrypoint.sh`) for container initialization
- **Bitbucket Pipelines** (`bitbucket-pipelines.yml`) for automated CI/CD

## Prerequisites

### Required Accounts and Services

1. **Google Cloud Platform (GCP) Account**
   - Active GCP project with billing enabled
   - Cloud Run API enabled
   - Container Registry API enabled

2. **Bitbucket Account**
   - Repository with code access
   - Pipelines enabled

3. **Service Account**
   - GCP service account with appropriate permissions:
     - Cloud Run Admin
     - Service Account User
     - Storage Admin (for Container Registry)
     - Cloud Build Service Account (if using Cloud Build)

### Required Tools

- `gcloud` CLI (installed automatically in pipeline)
- Docker (for local testing)
- Git

## Architecture

### Container Structure

```
┌─────────────────────────────────────┐
│     Google Cloud Run Container     │
│                                     │
│  ┌──────────────┐                  │
│  │  Supervisord │                  │
│  │  (Process    │                  │
│  │   Manager)   │                  │
│  └──────┬───────┘                  │
│         │                           │
│    ┌────┴────┐                      │
│    │         │                      │
│  ┌─▼───┐  ┌─▼────┐                 │
│  │Nginx│  │PHP-  │                 │
│  │:8080│  │FPM   │                 │
│  │     │  │:9000 │                 │
│  └─────┘  └──────┘                 │
│                                     │
│  Magento Application Files          │
└─────────────────────────────────────┘
```

### Key Components

1. **Nginx**: Web server listening on port 8080 (Cloud Run requirement)
2. **PHP-FPM**: PHP FastCGI Process Manager handling PHP requests
3. **Supervisord**: Manages both Nginx and PHP-FPM processes
4. **Entrypoint Script**: Handles initialization and starts supervisord

## Setup Instructions

### Step 1: Create GCP Service Account

1. Navigate to [GCP Console](https://console.cloud.google.com/)
2. Go to **IAM & Admin** > **Service Accounts**
3. Click **Create Service Account**
4. Provide a name (e.g., `bitbucket-cloud-run-deployer`)
5. Grant the following roles:
   - Cloud Run Admin
   - Service Account User
   - Storage Admin
6. Click **Create Key** and download the JSON key file
7. Save the key securely (you'll need it for Bitbucket variables)

### Step 2: Enable Required APIs

Run the following commands or enable via GCP Console:

```bash
gcloud services enable run.googleapis.com
gcloud services enable containerregistry.googleapis.com
gcloud services enable cloudbuild.googleapis.com
```

### Step 3: Configure Bitbucket Repository Variables

In your Bitbucket repository, go to **Repository Settings** > **Pipelines** > **Repository variables** and add:

| Variable Name | Description | Example | Secure |
|--------------|-------------|---------|--------|
| `GCP_PROJECT_ID` | Your GCP project ID | `my-magento-project` | No |
| `GCP_REGION` | Cloud Run region | `us-central1` | No |
| `GCP_SERVICE_ACCOUNT_KEY` | Base64 encoded service account JSON key | `eyJ0eXAiOiJKV1QiLCJ...` | Yes |
| `DOCKER_IMAGE_NAME` | Docker image name | `magento-cloudrun` | No |
| `CLOUD_RUN_SERVICE_NAME` | Cloud Run service name | `magento-backend` | No |
| `CLOUD_RUN_MEMORY` | Memory allocation | `2Gi` | No |
| `CLOUD_RUN_CPU` | CPU allocation | `2` | No |
| `CLOUD_RUN_MIN_INSTANCES` | Minimum instances | `0` | No |
| `CLOUD_RUN_MAX_INSTANCES` | Maximum instances | `10` | No |
| `CLOUD_RUN_TIMEOUT` | Request timeout (seconds) | `300` | No |

**To encode the service account key:**

```bash
cat service-account-key.json | base64 -w 0
```

Copy the output and paste it as the value for `GCP_SERVICE_ACCOUNT_KEY`.

### Step 4: Configure Magento Environment Variables

For Cloud Run, you'll need to set Magento-specific environment variables. These can be set in the deployment step or via GCP Console:

**Required Variables:**
- Database connection details (use Secret Manager for sensitive data)
- Redis/Elasticsearch configuration
- Base URL
- Encryption key

**Example using Secret Manager:**

```bash
# Store secrets
gcloud secrets create magento-db-password --data-file=- <<< "your-password"
gcloud secrets create magento-encryption-key --data-file=- <<< "your-key"

# Reference in Cloud Run deployment
--set-secrets="MAGENTO_DB_PASSWORD=magento-db-password:latest,MAGENTO_ENCRYPTION_KEY=magento-encryption-key:latest"
```

## Configuration

### Dockerfile Configuration

The `Dockerfile.cloudrun` uses a multi-stage build:

1. **Composer Stage**: Installs PHP dependencies
2. **Base Stage**: Sets up PHP-FPM and Nginx
3. **App Stage**: Copies application files and configurations

### Nginx Configuration

The `docker/nginx-cloudrun.conf` is optimized for Cloud Run:
- Listens on port 8080 (Cloud Run requirement)
- Handles forwarded protocol headers
- Configured for Magento's URL structure
- Includes gzip compression

### Environment Variables

Key environment variables for Cloud Run:

| Variable | Description | Default |
|----------|-------------|---------|
| `PORT` | Port Cloud Run assigns (handled automatically) | `8080` |
| `MAGE_ROOT` | Magento root directory | `/var/www/html` |
| `RUN_MAGENTO_SETUP` | Run setup commands on startup | `false` |
| `DEPLOY_STATIC_CONTENT` | Deploy static content | `false` |
| `COMPILE_DI` | Compile dependency injection | `false` |

**Note**: Setup commands should typically run during build, not at runtime.

## Deployment

### Automatic Deployment via Bitbucket Pipelines

The pipeline automatically triggers on pushes to `main` or `master` branches:

1. **Install gcloud SDK**: Sets up Google Cloud SDK
2. **Build Docker Image**: Builds and pushes image to Container Registry
3. **Deploy to Cloud Run**: Deploys the service to Cloud Run

### Manual Deployment

#### Build Docker Image Locally

```bash
# Authenticate with GCP
gcloud auth login
gcloud auth configure-docker

# Build image
docker build -f Dockerfile.cloudrun -t gcr.io/PROJECT_ID/magento-cloudrun:latest .

# Push to Container Registry
docker push gcr.io/PROJECT_ID/magento-cloudrun:latest
```

#### Deploy to Cloud Run

```bash
gcloud run deploy magento-backend \
  --image gcr.io/PROJECT_ID/magento-cloudrun:latest \
  --platform managed \
  --region us-central1 \
  --allow-unauthenticated \
  --port 8080 \
  --memory 2Gi \
  --cpu 2 \
  --min-instances 0 \
  --max-instances 10 \
  --timeout 300
```

### Custom Pipeline Triggers

The pipeline supports custom triggers:

- **build-only**: Builds Docker image without deploying
- **deploy-only**: Deploys existing image without rebuilding
- **build-and-deploy**: Full build and deploy process

## Troubleshooting

### Common Issues

#### 1. Container Fails to Start

**Symptoms**: Container exits immediately or health check fails

**Solutions**:
- Check Cloud Run logs: `gcloud run services logs read SERVICE_NAME --region REGION`
- Verify nginx configuration syntax
- Ensure PHP-FPM is running: Check supervisord logs
- Verify port 8080 is exposed correctly

#### 2. Database Connection Errors

**Symptoms**: 500 errors, database connection timeouts

**Solutions**:
- Ensure database allows Cloud Run IP ranges
- Check database connection credentials
- Verify network connectivity (use Cloud SQL Proxy if needed)
- Review `app/etc/env.php` configuration

#### 3. Static Content Not Loading

**Symptoms**: CSS/JS files return 404

**Solutions**:
- Run `php bin/magento setup:static-content:deploy` during build
- Verify static content directory permissions
- Check nginx static file location configuration

#### 4. Permission Errors

**Symptoms**: File permission denied errors

**Solutions**:
- Ensure `var/` and `generated/` directories are writable
- Check file ownership (should be `www-data:www-data`)
- Review entrypoint script permissions

#### 5. Memory Limit Errors

**Symptoms**: PHP memory limit exceeded

**Solutions**:
- Increase Cloud Run memory allocation
- Adjust PHP memory_limit in `Dockerfile.cloudrun`
- Optimize Magento configuration

### Viewing Logs

```bash
# Cloud Run service logs
gcloud run services logs read SERVICE_NAME --region REGION

# Real-time log streaming
gcloud run services logs tail SERVICE_NAME --region REGION

# Filter logs
gcloud run services logs read SERVICE_NAME --region REGION --filter="severity>=ERROR"
```

### Debugging Container

To debug locally:

```bash
# Build image
docker build -f Dockerfile.cloudrun -t magento-cloudrun:local .

# Run container
docker run -p 8080:8080 \
  -e MAGE_ROOT=/var/www/html \
  magento-cloudrun:local

# Execute commands in running container
docker exec -it CONTAINER_ID bash
```

## Best Practices

### Performance Optimization

1. **Use Cloud CDN**: Enable Cloud CDN for static assets
2. **Enable OPcache**: Already configured in Dockerfile
3. **Optimize Images**: Use multi-stage builds to reduce image size
4. **Cache Static Content**: Configure appropriate cache headers
5. **Use Redis**: Configure Redis for session and cache storage

### Security

1. **Use Secret Manager**: Store sensitive data in GCP Secret Manager
2. **Enable HTTPS**: Cloud Run provides HTTPS by default
3. **Restrict Access**: Use IAM to control service access
4. **Regular Updates**: Keep base images and dependencies updated
5. **Security Scanning**: Enable Container Analysis API

### Cost Optimization

1. **Right-size Resources**: Start with minimum required memory/CPU
2. **Use Min Instances Wisely**: Set to 0 for development, 1+ for production
3. **Optimize Cold Starts**: Pre-warm with min instances if needed
4. **Monitor Usage**: Use Cloud Monitoring to track costs

### Monitoring and Alerts

1. **Set Up Alerts**: Configure alerts for errors and latency
2. **Monitor Metrics**: Track request count, latency, error rate
3. **Log Aggregation**: Use Cloud Logging for centralized logs
4. **Health Checks**: Ensure `/health_check.php` endpoint works

### Database Considerations

1. **Cloud SQL**: Use Cloud SQL for managed database
2. **Connection Pooling**: Configure appropriate connection limits
3. **Read Replicas**: Use read replicas for read-heavy workloads
4. **Backup Strategy**: Implement regular database backups

### Static Content Strategy

1. **Build-time Deployment**: Deploy static content during Docker build
2. **CDN Integration**: Serve static files from Cloud Storage + CDN
3. **Versioning**: Use Magento's static content versioning

## Additional Resources

- [Google Cloud Run Documentation](https://cloud.google.com/run/docs)
- [Magento 2 Deployment Guide](https://devdocs.magento.com/guides/v2.4/install-gde/bk-install-guide.html)
- [Bitbucket Pipelines Documentation](https://support.atlassian.com/bitbucket-cloud/docs/get-started-with-bitbucket-pipelines/)
- [Docker Best Practices](https://docs.docker.com/develop/dev-best-practices/)

## Support

For issues or questions:
1. Check Cloud Run logs
2. Review Bitbucket pipeline logs
3. Consult Magento documentation
4. Contact your DevOps team

---

**Last Updated**: $(date)
**Version**: 1.0.0
