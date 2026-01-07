# Cloudflare Turnstile Spam Protection - Design Document

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add Cloudflare Turnstile captcha to Registration and Login forms to prevent spam bot account creation.

**Architecture:** Frontend widget on forms validates user is human, backend PHP subscriber verifies token with Cloudflare API before allowing registration/login.

**Tech Stack:** Cloudflare Turnstile, Twig templates, PHP Subscriber, Shopware 6 Event System

---

## Problem Statement

Spam bots are creating fake accounts in the Shopware admin. No captcha or spam protection currently exists on the registration form.

## Solution: Cloudflare Turnstile

### Why Turnstile?
- Free and privacy-friendly (GDPR compliant)
- Already using Cloudflare for CDN/SSL
- No annoying puzzles - visible checkbox mode
- Easy integration with custom forms

### Forms Protected
1. Registration form (`/account/register`)
2. Login form (`/account/login`)

### Widget Mode
Visible checkbox - users see "Verify you are human" checkbox

---

## Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   User Browser  │     │   Shopware      │     │   Cloudflare    │
│                 │     │   Server        │     │   Turnstile     │
└────────┬────────┘     └────────┬────────┘     └────────┬────────┘
         │                       │                       │
    1. Load page                 │                       │
         │──────────────────────>│                       │
         │<──────────────────────│                       │
         │   (form + turnstile)  │                       │
         │                       │                       │
    2. Solve challenge           │                       │
         │───────────────────────────────────────────────>│
         │<───────────────────────────────────────────────│
         │   (token)             │                       │
         │                       │                       │
    3. Submit form + token       │                       │
         │──────────────────────>│                       │
         │                       │  4. Verify token      │
         │                       │──────────────────────>│
         │                       │<──────────────────────│
         │                       │   (success/fail)      │
         │                       │                       │
    5. Allow/Reject              │                       │
         │<──────────────────────│                       │
```

---

## Implementation Components

### 1. Frontend - Registration Form
**File:** `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig`

- Add Turnstile script tag
- Add widget div before submit button
- Style to match Raven theme

### 2. Frontend - Login Form
**File:** Same as above (combined login/register template)

- Add widget div before login button

### 3. Backend - PHP Subscriber
**File:** `shopware-theme/RavenTheme/src/Subscriber/TurnstileValidationSubscriber.php`

- Listen to registration and login events
- Extract `cf-turnstile-response` token
- POST to Cloudflare siteverify API
- Block if validation fails

### 4. Service Registration
**File:** `shopware-theme/RavenTheme/src/Resources/config/services.xml`

- Register subscriber as tagged service

### 5. Theme Configuration
**File:** `shopware-theme/RavenTheme/src/Resources/config/config.xml`

- Add config fields for Site Key and Secret Key

---

## Configuration

Keys stored in Shopware system config:
- `RavenTheme.config.turnstileSiteKey` - Public key for frontend
- `RavenTheme.config.turnstileSecretKey` - Private key for backend

---

## Error Handling

| Scenario | Action |
|----------|--------|
| Missing token | Show error: "Bitte bestätigen Sie, dass Sie kein Roboter sind" |
| Invalid token | Show error: "Verifizierung fehlgeschlagen. Bitte versuchen Sie es erneut" |
| Cloudflare API error | Fail-open (allow) to not block real users |
| Token expired | Show error: "Verifizierung abgelaufen. Bitte erneut bestätigen" |

---

## Setup Steps (User Action)

1. Go to Cloudflare Dashboard → Turnstile
2. Add Site: `ravenweapon.ch`
3. Widget Mode: Managed, Widget Type: Visible
4. Copy Site Key and Secret Key
5. Enter keys in Shopware Admin → Extensions → RavenTheme config

---

## Sources

- [Cloudflare Turnstile Docs](https://developers.cloudflare.com/turnstile/)
- [Client-Side Rendering](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/)
- [Form Protection Tutorial](https://developers.cloudflare.com/turnstile/tutorials/login-pages/)
- [Shopware Basic Information Settings](https://docs.shopware.com/en/shopware-en/settings/basic-information)
