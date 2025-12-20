# Domain Migration Guide: ortak.ch → ravenweapon.ch
**Date:** 2025-12-19
**Prepared for:** RAVEN WEAPON AG

---

## Pre-Migration Checklist ✅

- [x] Database backup (47MB SQL dump)
- [x] RavenTheme plugin backup
- [x] Domain configuration documented
- [x] Payrexx config verified (already uses "ravenweapon" instance)
- [x] SEO URL status documented

---

## Step 1: DNS Configuration

Before changing Shopware, update DNS for ravenweapon.ch:

```
Type: A Record
Host: @
Points to: 77.42.19.154
TTL: 300 (5 minutes for testing)

Type: A Record
Host: www
Points to: 77.42.19.154
TTL: 300
```

Wait for DNS propagation (5-30 minutes).

---

## Step 2: Add New Domain to Shopware

### Option A: Via Admin Panel
1. Login to Shopware Admin
2. Go to **Settings → Sales Channels → Storefront**
3. In **Domains** section, click **Add domain**
4. Add: `https://ravenweapon.ch`
5. Set Language: Deutsch, Currency: CHF
6. Save

### Option B: Via SQL (Faster)
```bash
ssh root@77.42.19.154

docker exec shopware-chf bash -c "mysql -u root -proot shopware -e \"
INSERT INTO sales_channel_domain (id, url, sales_channel_id, language_id, currency_id, snippet_set_id, created_at)
SELECT UNHEX(REPLACE(UUID(), '-', '')), 'https://ravenweapon.ch', sales_channel_id, language_id, currency_id, snippet_set_id, NOW()
FROM sales_channel_domain WHERE url = 'https://ortak.ch';
\""
```

---

## Step 3: SSL Certificate

Setup Let's Encrypt for ravenweapon.ch:

```bash
ssh root@77.42.19.154

# Install certbot if not present
apt-get update && apt-get install -y certbot

# Get certificate
certbot certonly --standalone -d ravenweapon.ch -d www.ravenweapon.ch

# Or if web server is running:
certbot certonly --webroot -w /var/www/html/public -d ravenweapon.ch
```

---

## Step 4: Regenerate SEO URLs

**IMPORTANT:** Currently 0 product SEO URLs exist! Run this:

```bash
docker exec shopware-chf bash -c "cd /var/www/html && \
  bin/console dal:refresh:index && \
  bin/console cache:clear"
```

---

## Step 5: Update System Config

```bash
docker exec shopware-chf bash -c "mysql -u root -proot shopware -e \"
UPDATE system_config
SET configuration_value = '{\\\"_value\\\": \\\"https://ravenweapon.ch\\\"}'
WHERE configuration_key = 'core.newsletter.subscribeDomain';
\""
```

---

## Step 6: Clear All Caches

```bash
docker exec shopware-chf bash -c "cd /var/www/html && \
  bin/console cache:clear && \
  bin/console theme:compile && \
  bin/console assets:install"
```

---

## Step 7: Verify Migration

Test these URLs on ravenweapon.ch:
- [ ] Homepage loads
- [ ] Products display with images
- [ ] Add to cart works
- [ ] Checkout flow works
- [ ] Payrexx payment works
- [ ] Login/Register works
- [ ] Category navigation works

---

## Step 8: Remove Old Domain (After Verification!)

Only after confirming everything works:

```bash
docker exec shopware-chf bash -c "mysql -u root -proot shopware -e \"
DELETE FROM sales_channel_domain
WHERE url IN ('http://ortak.ch', 'https://ortak.ch');
\""

docker exec shopware-chf bash -c "cd /var/www/html && bin/console cache:clear"
```

---

## Rollback Procedure

If migration fails, restore from backup:

```bash
# Restore database
docker cp /path/to/shopware_backup_2025-12-19.sql shopware-chf:/tmp/
docker exec shopware-chf bash -c "mysql -u root -proot shopware < /tmp/shopware_backup_2025-12-19.sql"

# Clear cache
docker exec shopware-chf bash -c "cd /var/www/html && bin/console cache:clear"
```

---

## Important Notes

1. **Payrexx** is already configured with instance "ravenweapon" - should work automatically
2. **85 category SEO URLs** exist and will be preserved
3. **337 products** need SEO URLs regenerated
4. Keep IP domain (77.42.19.154) as fallback during testing

---

## Support Contacts

- Shopware Admin: https://ravenweapon.ch/admin
- Server: root@77.42.19.154
- Payrexx Dashboard: https://ravenweapon.payrexx.com
