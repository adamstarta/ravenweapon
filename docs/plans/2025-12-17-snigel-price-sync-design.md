# Snigel Price Sync Design

**Date:** 2025-12-17
**Status:** Approved
**Author:** Claude Code + Boss

## Overview

A tool to synchronize Snigel product prices between the B2B portal and ortak.ch Shopware store.

## Problem

- Snigel products in ortak.ch have incorrect prices
- Some prices are 10x too low (e.g., Flight suit CHF 32 instead of CHF 321)
- Some prices are swapped between similar products (34x64cm vs 34x100cm weapon bags)
- Manual checking is time-consuming and error-prone

## Solution

Automated price comparison and update tool with manual approval step.

## Pricing Formula

```
Retail CHF = B2B EUR × 1.5 (markup) × 1.07 (EUR/CHF rate)
```

Example:
- B2B price: €200.23 EUR
- Calculation: 200.23 × 1.5 × 1.07 = CHF 321.37

## Process Flow

```
┌─────────────────────────────────────────────────────────────┐
│  STEP 1: SCRAPE                                             │
├─────────────────────────────────────────────────────────────┤
│  Snigel B2B Portal ──→ Get all products with EUR prices    │
│  ortak.ch Storefront ──→ Get all Snigel products with CHF  │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  STEP 2: COMPARE                                            │
├─────────────────────────────────────────────────────────────┤
│  For each product:                                          │
│  - Calculate expected CHF (B2B EUR × 1.5 × 1.07)           │
│  - Compare with actual Shopware CHF price                   │
│  - Flag if difference > 5%                                  │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  STEP 3: GENERATE REPORT                                    │
├─────────────────────────────────────────────────────────────┤
│  Output:                                                    │
│  - Console report with all mismatches                       │
│  - CSV file for Excel review                                │
│  - JSON file with full details                              │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  STEP 4: USER REVIEW                                        │
├─────────────────────────────────────────────────────────────┤
│  User reviews the report and approves:                      │
│  - All changes, OR                                          │
│  - Selected changes only                                    │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  STEP 5: UPDATE SHOPWARE                                    │
├─────────────────────────────────────────────────────────────┤
│  Via Shopware Admin API:                                    │
│  - Dry-run first (show what would change)                   │
│  - Backup old prices                                        │
│  - Update approved products                                 │
│  - Generate update log                                      │
└─────────────────────────────────────────────────────────────┘
```

## Report Format

```
╔════════════════════════════════════════════════════════════╗
║           SNIGEL PRICE MISMATCH REPORT                     ║
╠════════════════════════════════════════════════════════════╣
║ Product: Flight suit, Pilot -09                            ║
║ B2B EUR:      €200.23                                      ║
║ Expected CHF: CHF 321.37  (200.23 × 1.5 × 1.07)           ║
║ Current CHF:  CHF 32.65   ❌                               ║
║ Difference:   -CHF 288.72 (-89.8%)                        ║
║ Status:       CRITICAL - 10x underpriced!                  ║
╚════════════════════════════════════════════════════════════╝
```

## Safety Features

1. **Review before update** - No automatic changes without approval
2. **Dry-run mode** - Shows what would change without making changes
3. **Price backup** - Saves old prices before updating
4. **Tolerance threshold** - Only flags prices >5% different
5. **Logging** - Full audit trail of all changes

## Technical Details

### Data Sources

| Source | URL | Auth | Data |
|--------|-----|------|------|
| Snigel B2B | products.snigel.se | Username/Password | EUR B2B prices |
| Shopware | ortak.ch/api | Admin API token | CHF retail prices |

### Matching Strategy

Products matched by:
1. Exact name match (normalized)
2. Partial name match (contains)
3. Word-based match (60% words match)

### Configuration

```javascript
const CONFIG = {
    markup: 1.5,           // 50% markup
    eurToChf: 1.07,        // Exchange rate
    tolerance: 5,          // % difference to flag
};
```

## Files

- `scripts/snigel-price-sync.js` - Main sync tool
- `scripts/snigel-comparison/` - Output directory for reports

## Usage

```bash
# Step 1: Generate comparison report
node scripts/snigel-price-sync.js --compare

# Step 2: Review the report (CSV/console)

# Step 3: Update prices (after approval)
node scripts/snigel-price-sync.js --update
```

## Known Issues Found

| Product | Current CHF | Should Be | Problem |
|---------|-------------|-----------|---------|
| Flight suit, Pilot -09 | 32.65 | 321.37 | 10x underpriced |
| 34×100 cm dual Weapon bag | 68.20 | 538.80 | Swapped with 34×64 |
| 34×64 cm dual Weapon bag | 467.55 | 78.57 | Swapped with 34×100 |
| Tactical coverall BG -10F | ~221 | ~1,534 | Major underpriced |

## Approved By

- Boss: Yes (2025-12-17)
