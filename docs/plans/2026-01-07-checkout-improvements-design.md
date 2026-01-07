# Checkout Page Improvements - Design Document

**Date:** 2026-01-07
**Status:** Implementation Complete - Admin Configuration Done

## Overview

Tasks for the ravenweapon.ch checkout page:
1. Fix shipping section - translate to German + configure shipping prices
2. Remove card payment options (no payment provider available)
3. Translate checkout text from English to German

---

## Task 1: Fix Shipping Section

### Implementation Status: DONE

**What was done:**
- Created `confirm-shipping.html.twig` override to hide duplicate English title
- Configured shipping prices in Shopware Admin:
  - Standard: CHF 10.50
  - Express: CHF 13.50

### Post.ch Reference Prices (2026)

**Domestic Switzerland (PostPac):**
| Weight | Priority (CHF) | Economy (CHF) |
|--------|----------------|---------------|
| Up to 2 kg | 10.50 | 9.00 |
| Up to 10 kg | 13.50 | 12.00 |
| Up to 30 kg | 22.50 | 21.00 |

**International (PostPac International) - Example: Asia/Far destinations:**
| Weight | Price (CHF) | Delivery |
|--------|-------------|----------|
| Up to 2 kg | 53.00 | 5-10 working days |
| Up to 5 kg | 78.00 | 5-10 working days |
| Up to 10 kg | 113.00 | 5-10 working days |
| Up to 20 kg | 193.00 | 5-10 working days |

*Note: International prices vary by destination country/zone. Use post.ch calculator for exact rates.*

---

## Task 2: Remove Card Payment Options

### Implementation Status: DONE

**What was done:**
- Removed Visa and Mastercard icons from footer.html.twig
- Deactivated "Kreditkarte | Payrexx Payment Gateway" in Shopware Admin
- Footer now only shows TWINT and PostFinance icons

---

## Task 3: Translate Checkout Text to German

### Implementation Status: DONE (Template)

**What was done:**
- Created `confirm-address.html.twig` to translate:
  - "Shipping address" → "Lieferadresse"
  - "Billing address" → "Rechnungsadresse"
  - "Same as shipping address" → "Gleich wie Lieferadresse"
  - "Change shipping address" → "Lieferadresse ändern"
  - "Change billing address" → "Rechnungsadresse ändern"
- Created `confirm-payment.html.twig` to translate:
  - "Payment method" → "Zahlungsart"

---

## Files Modified

| File | Change | Status |
|------|--------|--------|
| `layout/footer/footer.html.twig` | Removed Visa/Mastercard icons | DONE |
| `page/checkout/confirm/confirm-shipping.html.twig` | Hides duplicate English title | DONE |
| `page/checkout/confirm/confirm-address.html.twig` | NEW: German translations for address section | DONE |
| `page/checkout/confirm/confirm-payment.html.twig` | NEW: German translation for payment title | DONE |

## Admin Configuration Completed

| Location | Action | Status |
|----------|--------|--------|
| Settings > Shop > Payment methods | Deactivated "Kreditkarte" | DONE |
| Settings > Shop > Shipping | Standard: CHF 10.50, Express: CHF 13.50 | DONE |

---

## Deployment

Push to GitHub to deploy:
```bash
git add .
git commit -m "fix: translate checkout text to German"
git push origin main
```

---

## Testing Checklist

- [x] Footer shows only TWINT and PostFinance icons
- [x] No card payment options appear in checkout
- [x] Shipping prices are visible (Standard 10.50, Express 13.50)
- [ ] Checkout text displays in German (after deployment)
- [ ] Checkout flow completes successfully

---

## Rollback Plan

**If issues occur:**
1. Delete custom confirm-address.html.twig and confirm-payment.html.twig
2. Revert footer changes to restore payment icons (git revert)
3. Re-enable card payment methods in Admin if needed
