# TWINT Direct Integration Design

**Date:** 2026-02-16
**Status:** Approved
**Goal:** Add TWINT payment to ravenweapon.ch using the official TWINT Shopware 6 plugin with a direct TWINT merchant account (bypassing Payrexx).

## Background

Payrexx rejected the shop due to firearms policy. The client has a TWINT Business Portal account at portal.twint.ch. Other Swiss firearms dealers (WaffenZimmi, Gunlex, MARTI Waffen) successfully use TWINT. The official TWINT plugin is free, supports Shopware 6.6, and provides both Standard and Express Checkout.

## Approach: Official TWINT Plugin (Direct Integration)

- **Plugin:** twint-ag/twint-shopware-plugin v1.1.6 (MIT license, free)
- **Source:** GitHub / Shopware Store / Composer
- **Fees:** 1.3% per transaction, no setup or monthly fees
- **Features:** Standard Checkout + Express Checkout on product/cart pages

## Manual Steps (Client Does in Browser)

1. Log into portal.twint.ch
2. Go to Stores > create "Online Shop" (select Shopware, enter ravenweapon.ch)
3. Copy the **Store UUID** generated after creation
4. Go to Settings > "Order certificate from SwissSign"
5. Create a certificate password (save it — not recoverable)
6. Download the **.p12 certificate file** (available for 30 days)

## Technical Steps (Server — Staging First)

### 1. Verify Server Requirements
- PHP 8.3 (supported)
- Extensions: openssl, dom, intl, xsl
- Shopware 6.6 (supported)
- CHF currency (already active)

### 2. Install Plugin on Staging (shopware-dev)
```bash
docker exec -it shopware-dev bash
cd /var/www/html
composer require twint-ag/twint-shopware-plugin
bin/console plugin:refresh
bin/console plugin:install TwintPayment
bin/console plugin:activate TwintPayment
bin/console cache:clear
```

### 3. Configure Plugin (Client Does in Shopware Admin)
- Staging Admin > Settings > Extensions > TWINT
- Enter Store UUID
- Upload .p12 certificate file
- Enter certificate password
- Enable test mode for initial testing

### 4. Test on Staging
- Add product to cart, select TWINT at checkout
- Verify redirect to TWINT, payment flow, and return to shop
- Test Express Checkout if enabled

### 5. Deploy to Production (shopware-chf)
- Repeat install steps on production container
- Configure with production credentials
- Switch test mode OFF

## Codebase Changes

Minimal — most TWINT references already exist:

- **datenschutz.html.twig:** Update privacy policy from "future payment method" to "active"
- **No checkout template changes needed** — TWINT redirect handling already exists
- **No footer changes needed** — TWINT icon already displayed
- **No AGB changes needed** — TWINT already listed

## Plugin Deployment Note

The TWINT plugin is installed directly on the server via Composer, NOT deployed through GitHub Actions. It lives in the Docker container's vendor/ directory, same as Payrexx.

## Risks

| Risk | Likelihood | Mitigation |
|------|-----------|-----------|
| TWINT rejects firearms merchant | Low (others use it) | Honest registration, await response |
| Certificate upload browser bug | Medium | Try Safari/different browser |
| Plugin conflicts with Payrexx | Low | Different payment methods |
| Missing PHP extensions | Low | Verify before install |

## Server Info

- Staging: shopware-dev container (port 8082)
- Production: shopware-chf container (port 8081)
- Server: 77.42.19.154
