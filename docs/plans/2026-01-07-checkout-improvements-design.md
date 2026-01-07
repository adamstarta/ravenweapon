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

### Implementation Status: DONE (Deployed)

**What was done:**

1. **Template Overrides (Primary Approach):**
   - Created `confirm-address.html.twig` with correct Shopware block names:
     - `page_checkout_confirm_address_shipping_title` → "Lieferadresse"
     - `page_checkout_confirm_address_billing_title` → "Rechnungsadresse"
     - `page_checkout_confirm_address_billing_data_equal` → "Gleich wie Lieferadresse"
     - `page_checkout_confirm_address_shipping_actions` → "Lieferadresse ändern"
     - `page_checkout_confirm_address_billing_actions` → "Rechnungsadresse ändern"
   - Created `confirm-payment.html.twig`:
     - `page_checkout_confirm_payment_title` → "Zahlungsart"
   - Used correct CSS class `card-title` to match Shopware's styling

2. **Snippet File (Backup Approach):**
   - Created `snippet/de_DE/storefront.de-DE.json` with German translations
   - Deployed to server at `/var/www/html/custom/plugins/RavenTheme/src/Resources/snippet/de_DE/`

---

## Files Modified

| File | Change | Status |
|------|--------|--------|
| `layout/footer/footer.html.twig` | Removed Visa/Mastercard icons | DONE |
| `page/checkout/confirm/confirm-shipping.html.twig` | Hides duplicate English title | DONE |
| `page/checkout/confirm/confirm-address.html.twig` | German translations with correct block names | DONE |
| `page/checkout/confirm/confirm-payment.html.twig` | German translation for payment title | DONE |
| `snippet/de_DE/storefront.de-DE.json` | NEW: German snippet translations | DONE |
| `page/account/address/edit.html.twig` | Added CSRF token | DONE |
| `page/account/address/create.html.twig` | Added CSRF token | DONE |
| `page/account/addressbook/edit.html.twig` | Added CSRF token | DONE |
| `page/account/addressbook/create.html.twig` | Added CSRF token | DONE |

## Admin Configuration Completed

| Location | Action | Status |
|----------|--------|--------|
| Settings > Shop > Payment methods | Deactivated "Kreditkarte" | DONE |
| Settings > Shop > Shipping | Standard: CHF 10.50, Express: CHF 13.50 | DONE |
| Settings > Shop > Shipping | International: CHF 53.00 (up to 2kg) | DONE |
| Sales Channel > Versandarten | Added "International" shipping method | DONE |

---

## Task 4: International Shipping

### Implementation Status: DONE

**What was done:**
- Created new shipping method "International" with price CHF 53.00 (PostPac International up to 2kg)
- Created availability rule "Internationaler Versand (nicht CH)" - only shows for non-Swiss addresses
- Assigned International shipping to Sales Channel "Raven Weapon AG"
- Delivery time: 1-2 weeks

---

## Task 5: Address Edit Form Bug Fix

### Implementation Status: DONE (Pending Deployment)

**Problem:** Address changes not saving when customers edit their address in the account area.

**Root Cause:** Missing CSRF token in address forms. The forms had `data-form-csrf-handler="true"` but no actual `{{ sw_csrf() }}` token.

**Fix Applied:**
Added CSRF tokens to all 4 address form templates:
- `page/account/address/edit.html.twig` - `{{ sw_csrf('frontend.account.address.edit.save') }}`
- `page/account/address/create.html.twig` - `{{ sw_csrf('frontend.account.address.create') }}`
- `page/account/addressbook/edit.html.twig` - `{{ sw_csrf('frontend.account.address.edit.save') }}`
- `page/account/addressbook/create.html.twig` - `{{ sw_csrf('frontend.account.address.create') }}`

---

## Country Names (German vs English)

**Explanation:** Country names like "Türkei" (Turkey), "Philippinen" (Philippines) are NOT hardcoded. They come from Shopware's translation system via `{{ country.translated.name }}`.

Since the shop language is German (de-DE), country names display in German. This is expected behavior - the storefront shows translated names based on the active language.

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
- [x] Template overrides deployed with correct block names
- [x] Snippet file deployed as backup translation method
- [ ] Checkout text displays in German (requires logged-in customer to verify)
- [ ] Checkout flow completes successfully

---

## Rollback Plan

**If issues occur:**
1. Delete custom confirm-address.html.twig and confirm-payment.html.twig
2. Revert footer changes to restore payment icons (git revert)
3. Re-enable card payment methods in Admin if needed
