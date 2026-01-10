# Sync Production to Staging Plan

## Goal
Make staging (developing.ravenweapon.ch) identical to production (ravenweapon.ch) so staging can be used as a proper preview environment.

## Current Architecture
```
SHARED: MySQL Database (products, orders, settings DATA)

SEPARATE: Application Files
- shopware-chf (production) → ravenweapon.ch
- shopware-dev (staging) → developing.ravenweapon.ch
```

## What Needs to Sync
- Shopware core files
- Plugin files (custom/plugins/)
- Theme compiled assets
- Admin panel files
- Public files and media
- Configuration files (.env, config/)

## Prerequisites
- SSH access to server 77.42.19.154
- Know the Docker volume/bind mount paths

---

## Step 1: SSH into Server
```bash
ssh user@77.42.19.154
```

## Step 2: Find Container File Paths
```bash
# Check where production files are mounted
docker inspect shopware-chf --format='{{json .Mounts}}' | jq

# Check where staging files are mounted
docker inspect shopware-dev --format='{{json .Mounts}}' | jq
```

## Step 3: Stop Staging Container (Prevent Conflicts)
```bash
docker stop shopware-dev
```

## Step 4: Sync Files from Production to Staging
Option A - If using bind mounts (files on host):
```bash
# Example paths - adjust based on Step 2 results
PROD_PATH=/path/to/shopware-chf/files
STAGING_PATH=/path/to/shopware-dev/files

# Sync everything except var/cache and var/log
rsync -av --delete \
  --exclude 'var/cache/*' \
  --exclude 'var/log/*' \
  --exclude '.env' \
  $PROD_PATH/ $STAGING_PATH/
```

Option B - If using Docker volumes:
```bash
# Create backup first
docker run --rm -v shopware-dev-data:/data -v $(pwd):/backup alpine tar czf /backup/staging-backup.tar.gz /data

# Copy from production volume to staging volume
docker run --rm \
  -v shopware-chf-data:/source:ro \
  -v shopware-dev-data:/dest \
  alpine sh -c "rm -rf /dest/* && cp -a /source/. /dest/"
```

## Step 5: Update Staging .env (Keep Staging-Specific Config)
```bash
# Make sure staging .env points to correct URLs
# Edit $STAGING_PATH/.env or the staging container's env file
APP_URL=https://developing.ravenweapon.ch
```

## Step 6: Clear Staging Cache
```bash
docker start shopware-dev
docker exec shopware-dev bin/console cache:clear
docker exec shopware-dev bin/console theme:compile
```

## Step 7: Verify
1. Visit https://developing.ravenweapon.ch
2. Visit https://developing.ravenweapon.ch/admin
3. Compare with production - should look identical

---

## Create Reusable Sync Script (Optional)
Save as `/root/sync-prod-to-staging.sh`:
```bash
#!/bin/bash
echo "Syncing production to staging..."
docker stop shopware-dev
rsync -av --delete \
  --exclude 'var/cache/*' \
  --exclude 'var/log/*' \
  --exclude '.env' \
  $PROD_PATH/ $STAGING_PATH/
docker start shopware-dev
docker exec shopware-dev bin/console cache:clear
echo "Sync complete!"
```

---

## Notes
- Database is SHARED, so no DB sync needed
- Only syncs Shopware application files
- Keeps staging .env separate (different APP_URL)
- Run this whenever staging gets behind production
