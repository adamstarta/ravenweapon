# Product Weight Classification - Design Document

**Date:** 2026-01-09
**Status:** Ready for Implementation

## Problem

Many products in the Raven Weapon AG shop have no weight assigned (weight = 0). Shopware uses product weight to determine shipping tier:
- 0-2 kg: CHF 10.50 (Standard)
- 2-10 kg: CHF 13.50 (Express)
- 10-30 kg: CHF 22.50 (Heavy)

Products without weight default to the lowest tier, causing incorrect shipping charges for heavy items like firearms.

## Solution

Create a script that assigns weights to products based on their Shopware category. Uses three weight classes aligned with shipping tiers:

| Weight Class | Weight | Shipping Tier | Example Products |
|--------------|--------|---------------|------------------|
| Heavy | 15 kg | CHF 22.50 | Rifles, Pistols, Caliber Kits |
| Medium | 5 kg | CHF 13.50 | Optics, Scopes, Clothing, Ammo |
| Light | 1.5 kg | CHF 10.50 | Grips, Magazines, Patches |
| Digital | 0 kg | No shipping | Training courses |

## Category Mapping

### HEAVY (15 kg) - Firearms & Kits
- Waffen
- Raven Weapons
- Raven Caliber Kit
- RAPAX
- Caracal Lynx
- LYNX COMPACT, LYNX OPEN, LYNX SPORT
- RX Compact, RX Sport, RX Tactical

### MEDIUM (5 kg) - Optics, Gear & Clothing
- Zielfernrohre (Scopes)
- Zielfernrohrmontagen (Scope mounts)
- Rotpunktvisiere (Red dots)
- Ferngläser (Binoculars)
- Spektive (Spotting scopes)
- Zweibeine (Bipods)
- Bekleidung & Tragen (Clothing)
- Körperschutz (Body protection)
- Ballistischer Schutz
- Westen & Chest Rigs
- Taschen & Transport / Taschen & Rucksäcke
- Munition

### LIGHT (1.5 kg) - Small Accessories
- Zubehör & Sonstiges
- Griffe & Handschutz (Grips)
- Magazine
- Mündungsaufsätze (Muzzle devices)
- Schienen & Zubehör (Rails)
- Patches
- Tragegurte & Holster
- Gürtel
- Halter & Taschen

### DIGITAL (0 kg) - Skip
- Schiesskurse
- Basic-Kurse
- Privatunterricht

## Script Behavior

**File:** `scripts/update-product-weights.js`

### Logic Flow
1. Authenticate with Shopware API
2. Fetch all products with their categories
3. Filter to main products only (skip variants)
4. For each product without weight:
   - Find matching category in mapping
   - Digital products → Skip
   - Mapped category → Use class weight
   - No match → Default to Medium (5 kg)
5. Apply updates (if --apply flag)
6. Generate report

### Safety Features
- **Dry-run by default** - Shows changes without applying
- **--apply flag** - Required to make actual changes
- **Preserves existing weights** - Only updates products with weight = 0
- **Detailed logging** - Every product processed is logged

### Usage
```bash
# Preview changes (dry run)
node scripts/update-product-weights.js

# Apply changes
node scripts/update-product-weights.js --apply
```

### Output Format
```
=================================================================
  PRODUCT WEIGHT UPDATE - DRY RUN
=================================================================

HEAVY (15 kg) - X products:
  [product list]

MEDIUM (5 kg) - X products:
  [product list]

LIGHT (1.5 kg) - X products:
  [product list]

DEFAULT (5 kg) - X products (review these):
  [product list]

SKIPPED - Already has weight: X products
SKIPPED - Digital products: X products

=================================================================
  SUMMARY - Would update: X products
  Run with --apply to make changes
=================================================================
```

## Implementation

Single script file: `scripts/update-product-weights.js`

No theme changes required - this is a one-time data migration script.
