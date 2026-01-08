# Recalculate All Product Prices - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Recalculate all product prices so current Brutto becomes Netto, then add 8.1% Swiss MWST for new Brutto.

**Formula:**
```
New Netto  = Old Brutto
New Brutto = Old Brutto × 1.081
```

**Example:**
| Product | Old Brutto | Old Netto | → | New Netto | New Brutto |
|---------|------------|-----------|---|-----------|------------|
| 300 AAC KIT | 3050.00 | 2821.46 | → | 3050.00 | 3297.05 |

**Execution Order:**
1. Run on staging (developing.ravenweapon.ch)
2. Verify prices in admin
3. Run on production (ravenweapon.ch)

---

## API Configuration

**Staging:**
- API URL: `https://developing.ravenweapon.ch/api`
- Same credentials as production

**Production:**
- API URL: `https://ravenweapon.ch/api`
- Client ID: `SWIAC3HJVHFJMHQYRWRUM1E1SG`
- Client Secret: `RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg`

---

## Task 1: Create Recalculation Script

**File:** `scripts/recalculate-all-prices.js`

**Requirements:**
- Accept `--env=staging` or `--env=production` flag
- Create backup JSON before any changes: `scripts/backups/prices-backup-{env}-{timestamp}.json`
- Fetch all products with prices
- For each product:
  - New Netto = Old Brutto
  - New Brutto = Old Brutto × 1.081
  - PATCH product via API
- Show progress during execution
- Display summary at end

**Backup JSON structure:**
```json
{
  "environment": "staging",
  "timestamp": "2026-01-08T12:00:00Z",
  "productCount": 500,
  "products": [
    {
      "id": "uuid",
      "productNumber": "KIT-300AAC",
      "name": "300 AAC CALIBER KIT",
      "originalBrutto": 3050.00,
      "originalNetto": 2821.46
    }
  ]
}
```

---

## Task 2: Create Restore Script

**File:** `scripts/restore-prices.js`

**Requirements:**
- Accept `--file=path/to/backup.json` flag
- Read backup JSON
- For each product, restore original Brutto and Netto
- Show progress and summary

**Usage:**
```bash
node scripts/restore-prices.js --file=backups/prices-backup-staging-2026-01-08.json
```

---

## Task 3: Run on Staging

**Command:**
```bash
cd scripts && node recalculate-all-prices.js --env=staging
```

**Expected:**
- Backup file created in `scripts/backups/`
- All products updated
- Summary shows success count

---

## Task 4: Verify Staging

**Manual Steps:**
1. Log into https://developing.ravenweapon.ch/admin
2. Navigate to Products
3. Check 5-10 random products
4. Confirm: Netto = old Brutto value, Brutto = old Brutto × 1.081
5. Check storefront prices display correctly

---

## Task 5: Run on Production

**Command:**
```bash
cd scripts && node recalculate-all-prices.js --env=production
```

**Expected:**
- Backup file created
- All products updated
- Summary shows success count

---

## Task 6: Verify Production

**Manual Steps:**
1. Log into https://ravenweapon.ch/admin
2. Navigate to Products
3. Check same products verified on staging
4. Confirm prices are correct
5. Check live storefront

---

## Rollback Procedure

If prices need to be reverted:

```bash
# For staging
node scripts/restore-prices.js --file=backups/prices-backup-staging-{timestamp}.json

# For production
node scripts/restore-prices.js --file=backups/prices-backup-production-{timestamp}.json
```

---

## Notes

- Swiss MWST rate: 8.1% (as of January 2024)
- No products excluded from recalculation
- Backup created before any changes for safety
