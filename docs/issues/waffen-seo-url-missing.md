# Issue: Waffen Category Missing SEO URL

## Status: Open

## Problem

The "Waffen" category at Level 2 has **NO SEO URL** configured. This blocks all child categories from having proper full-path URLs.

## Affected Categories

Without a SEO URL for "Waffen", these child categories cannot have correct full-path URLs:

| Category | Level | Current URL | Should Be |
|----------|-------|-------------|-----------|
| Waffen | L2 | NO URL | `/waffen/` |
| RAPAX | L3 | `/rapax/` | `/waffen/rapax/` |
| RX Sport | L4 | `/rapax/rx-sport/` | `/waffen/rapax/rx-sport/` |
| RX Tactical | L4 | `/rapax/rx-tactical/` | `/waffen/rapax/rx-tactical/` |
| RX Compact | L4 | `/rapax/rx-compact/` | `/waffen/rapax/rx-compact/` |
| Caracal Lynx | L3 | `/caracal-lynx/` | `/waffen/caracal-lynx/` |
| LYNX SPORT | L4 | `/caracal-lynx/lynx-sport/` | `/waffen/caracal-lynx/lynx-sport/` |
| LYNX OPEN | L4 | `/caracal-lynx/lynx-open/` | `/waffen/caracal-lynx/lynx-open/` |
| LYNX COMPACT | L4 | `/caracal-lynx/lynx-compact/` | `/waffen/caracal-lynx/lynx-compact/` |
| Raven Weapons | L3 | `/raven-weapons/` | `/waffen/raven-weapons/` |

## Root Cause

The "Waffen" category exists in the category tree but was never assigned an SEO URL. This may have been intentional (to hide the category from navigation) or an oversight.

## Fix Options

### Option 1: Add SEO URL via Shopware Admin (Recommended)

1. Login to https://ravenweapon.ch/admin
2. Go to **Kataloge** → **Kategorien**
3. Find "Waffen" category
4. In the category settings, add SEO URL: `waffen`
5. Save
6. Go to **Einstellungen** → **Caches & Indizes**
7. Click "Indizes aktualisieren"
8. Run `node scripts/fix-seo-urls.js` to update canonical URLs

### Option 2: Add SEO URL via API

```javascript
// Use the Shopware Admin API to add SEO URL for Waffen category
// Category ID needs to be found first
```

## Verification

After fixing, run:
```bash
node scripts/fix-seo-urls.js
```

All RAPAX and Caracal Lynx categories should then show "SUCCESS" instead of "WARNING".

## Notes

- This issue was discovered on 2026-01-08
- The "Waffen" category may have been intentionally left without a URL for business/legal reasons (Swiss firearms regulations?)
- Consult with business owner before adding the URL

## Related Files

- `scripts/fix-seo-urls.js` - Script to fix SEO URL canonical flags
- `scripts/check-caliber-prices.js` - Script to check product prices
