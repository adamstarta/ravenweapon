# Snigel B2B Price Sync - Design Document

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Extract B2B prices from Snigel portal for ALL variants and sync to Shopware with correct pricing formula.

**Architecture:** Playwright scraper extracts B2B prices per variant, calculates selling prices using client's formula, updates Shopware via API.

**Tech Stack:** Node.js, Playwright, Shopware 6 API

---

## 1. Pricing Formula

```
Selling Price (CHF) = B2B Price (EUR) × 1.5 × 1.08 × Exchange Rate
```

| Step | Description | Example |
|------|-------------|---------|
| B2B Price | "1 and more" quantity discount price | €1,955.18 |
| × 1.5 | 50% markup | €2,932.77 |
| × 1.08 | 8% VAT | €3,167.39 |
| × Exchange Rate | EUR to CHF (ECB rate) | ×0.94 |
| Swiss Rounding | Round to nearest .05 | **CHF 2,977.35** |

### Important Notes:
- **B2B Price** = "Quantity Discount - 1 and more" price (NOT RRP!)
- **RRP** = Retail Recommended Price (ignore this)
- **Exchange Rate** = Live ECB rate (fetched at runtime)
- **Swiss Rounding** = Round to nearest 0.05 CHF

---

## 2. What to Extract from Snigel

### 2.1 Product Variant Types

| Variant Type | German Label | Example Products |
|--------------|--------------|------------------|
| Color | FARBE | Backpacks, Bags |
| Size | GRÖSSE | Jackets, Clothing |
| Parts | TEILE | Coveralls, Suits |

### 2.2 Data to Extract Per Product

```json
{
  "name": "Tactical Coverall 09F",
  "slug": "tactical-coverall-09f",
  "url": "https://products.snigel.se/product/tactical-coverall-09f/",
  "variantType": "Parts",
  "variantLabel": "TEILE",
  "variants": [
    {
      "name": "Complete",
      "value": "complete",
      "b2bPriceEUR": 1955.18,
      "sellingPriceCHF": 2977.35
    },
    {
      "name": "Briefs",
      "value": "briefs",
      "b2bPriceEUR": 156.72,
      "sellingPriceCHF": 238.25
    }
  ]
}
```

### 2.3 Where B2B Price is Located

```
Product Page Structure:
┌─────────────────────────────────────┐
│ Parts: [COMPLETE ▼]                 │  ← Variant dropdown
├─────────────────────────────────────┤
│ RRP 2 172,43 €                      │  ← IGNORE (retail price)
├─────────────────────────────────────┤
│ Quantity Discount                   │
│   1 and more : 1 955,18 €          │  ← EXTRACT THIS (B2B price)
│   5 and more : 1 846,56 €          │  ← (bulk discount, ignore)
└─────────────────────────────────────┘
```

---

## 3. Scraper Logic

### 3.1 Main Flow

```
1. Login to Snigel B2B portal
2. Get product list (from existing JSON or API)
3. For each product:
   a. Navigate to product page
   b. Detect variant dropdown (if exists)
   c. For each variant option:
      - Select the variant
      - Wait for price to update
      - Extract "1 and more" B2B price
      - Store variant + price
   d. If no variants, extract single B2B price
4. Calculate selling prices (formula)
5. Save to JSON file
6. Update Shopware via API
```

### 3.2 Variant Detection

```javascript
// Detect variant type from dropdown label
const variantSelectors = [
  { selector: 'select[name*="attribute_pa_parts"]', type: 'Parts', label: 'TEILE' },
  { selector: 'select[name*="attribute_pa_colour"]', type: 'Color', label: 'FARBE' },
  { selector: 'select[name*="attribute_pa_size"]', type: 'Size', label: 'GRÖSSE' },
  { selector: 'table.variations select', type: 'Generic', label: 'Varianten' }
];
```

### 3.3 B2B Price Extraction

```javascript
// Extract "1 and more" price from Quantity Discount section
function extractB2BPrice(page) {
  // Look for "1 and more" pattern
  const priceText = await page.evaluate(() => {
    const pageText = document.body.innerText;

    // Pattern: "1 and more : 1 955,18 €"
    const match = pageText.match(/1\s+and\s+more\s*:\s*([\d\s]+[,.][\d]+)\s*€/i);
    if (match) {
      return match[1];
    }

    // Fallback: "1 oder mehr" (German)
    const matchDE = pageText.match(/1\s+oder\s+mehr\s*:\s*([\d\s]+[,.][\d]+)\s*€/i);
    if (matchDE) {
      return matchDE[1];
    }

    return null;
  });

  return parseEuropeanPrice(priceText);
}
```

### 3.4 Price Calculation

```javascript
async function calculateSellingPrice(b2bPriceEUR) {
  // Get live exchange rate from ECB
  const exchangeRate = await getECBExchangeRate();

  // Apply formula: B2B × 1.5 × 1.08 × ExchangeRate
  const withMarkup = b2bPriceEUR * 1.5;
  const withVAT = withMarkup * 1.08;
  const inCHF = withVAT * exchangeRate;

  // Swiss rounding to nearest 0.05
  return swissRound(inCHF);
}

function swissRound(price) {
  return Math.round(price * 20) / 20;  // Round to nearest 0.05
}
```

---

## 4. ECB Exchange Rate

### 4.1 API Endpoint

```
https://api.exchangerate-api.com/v4/latest/EUR
```

Or ECB official:
```
https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml
```

### 4.2 Implementation

```javascript
async function getECBExchangeRate() {
  const response = await fetch('https://api.exchangerate-api.com/v4/latest/EUR');
  const data = await response.json();
  return data.rates.CHF;  // e.g., 0.9385
}
```

---

## 5. Shopware Update

### 5.1 Fields to Update

| Shopware Field | Value |
|----------------|-------|
| `price` | Calculated selling price (CHF) |
| `purchasePrices` | B2B price converted to CHF |
| Custom field: `snigel_variant_type` | "FARBE" / "GRÖSSE" / "TEILE" |
| Custom field: `snigel_variants` | JSON array of variants |

### 5.2 API Payload

```json
{
  "price": [{
    "currencyId": "CHF_CURRENCY_ID",
    "gross": 2977.35,
    "net": 2756.81,
    "linked": false
  }],
  "purchasePrices": [{
    "currencyId": "CHF_CURRENCY_ID",
    "gross": 1838.07,
    "net": 1702.10,
    "linked": false
  }],
  "customFields": {
    "snigel_variant_type": "TEILE",
    "snigel_variants": "[{\"name\":\"Complete\",\"b2bPrice\":1955.18}]"
  }
}
```

---

## 6. Output Files

| File | Purpose |
|------|---------|
| `snigel-b2b-prices.json` | All products with B2B prices per variant |
| `snigel-price-sync-report.json` | Comparison: current vs calculated prices |
| `snigel-price-sync-log.json` | Update log with timestamps |

---

## 7. Error Handling

| Scenario | Action |
|----------|--------|
| No "1 and more" price found | Log warning, skip product |
| Variant dropdown not loading | Retry 3 times with delay |
| Exchange rate API down | Use cached rate (max 24h old) |
| Shopware API error | Log error, continue with next product |

---

## 8. CLI Usage

```bash
# Compare prices (no changes)
node snigel-b2b-sync.js --compare

# Dry run (show what would change)
node snigel-b2b-sync.js --dry-run

# Update Shopware prices
node snigel-b2b-sync.js --update

# Update specific product
node snigel-b2b-sync.js --update --product "tactical-coverall-09f"
```

---

## 9. Summary

### What This Scraper Does:

1. **Extracts B2B prices** from "Quantity Discount - 1 and more" (NOT RRP)
2. **Extracts ALL variants** (colors, sizes, parts) with individual prices
3. **Calculates selling price**: `B2B × 1.5 × 1.08 × ExchangeRate`
4. **Applies Swiss rounding** to nearest CHF 0.05
5. **Updates Shopware** with both B2B (purchasePrices) and selling price (price)
6. **Stores variant labels** correctly (FARBE, GRÖSSE, TEILE)

### Formula Summary:

```
B2B EUR → ×1.5 → ×1.08 → ×ECB Rate → Swiss Round → CHF Selling Price
```

---

## 10. Implementation Tasks

### Task 1: Create Base Scraper Structure
- Setup Playwright with login
- Create config for credentials and settings
- Implement ECB exchange rate fetcher

### Task 2: Implement Variant Detection
- Detect variant dropdown type (Parts, Color, Size)
- Extract variant options from dropdown
- Map to German labels (FARBE, GRÖSSE, TEILE)

### Task 3: Implement B2B Price Extraction
- Select each variant option
- Wait for price update
- Extract "1 and more" quantity discount price
- Parse European number format

### Task 4: Implement Price Calculation
- Apply formula: B2B × 1.5 × 1.08 × ExchangeRate
- Implement Swiss rounding (nearest 0.05)
- Store both B2B and selling prices

### Task 5: Implement Shopware Update
- Get API token
- Find product by name/SKU
- Update price and purchasePrices
- Update custom fields for variants

### Task 6: Add CLI Commands
- --compare: Generate comparison report
- --dry-run: Show what would change
- --update: Apply changes to Shopware

### Task 7: Testing & Verification
- Test with sample products (different variant types)
- Verify prices match expected calculations
- Check Shopware admin shows correct B2B and selling prices
