# Email Deliverability Fix - Stop Emails Going to Spam

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix all emails from ravenweapon.ch going to spam by configuring DKIM, adjusting DMARC, and fixing email content spam triggers.

**Architecture:** DNS changes for email authentication + PHP code changes for custom email headers + Shopware admin template fixes.

**Tech Stack:** Infomaniak mail server, DNS (TXT records), PHP/Symfony Mailer, Shopware 6

---

## Executive Summary

### Root Cause Analysis

**Primary Issue (90% of problem):**
- DMARC is set to `p=reject` (strictest policy)
- **DKIM is NOT configured** (no DNS records found)
- Without DKIM, DMARC authentication fails and emails are rejected/spam-marked

**Secondary Issues (10% of problem):**
- Custom PHP emails missing `Reply-To` and `List-Unsubscribe` headers
- ALL CAPS text in email templates (spam trigger)
- Inconsistent sender names between subscribers

### DNS Records Found
```
SPF:   v=spf1 include:spf.infomaniak.ch include:_spf.ewmail.com -all  ✅ Good
DMARC: v=DMARC1; p=reject;                                            ⚠️ Too strict without DKIM
DKIM:  NOT FOUND                                                       ❌ Missing!
```

---

## Task 1: Configure DKIM in Infomaniak (Manual - Domain Admin)

**This is the PRIMARY fix that will solve 90% of the spam problem.**

**What to do:**

1. Log into Infomaniak admin panel (https://manager.infomaniak.com)
2. Navigate to: Email → Domain ravenweapon.ch → Email configuration → DKIM
3. Enable DKIM signing for the domain
4. Copy the DKIM public key that Infomaniak provides
5. Add DKIM DNS record (see Task 2)

**Alternative if Infomaniak doesn't provide DKIM settings:**
Contact Infomaniak support to enable DKIM for info@ravenweapon.ch

---

## Task 2: Add DKIM DNS Record (Manual - DNS Admin)

**Files:** DNS zone for ravenweapon.ch (likely in Cloudflare or domain registrar)

**What to add:**

After getting the DKIM key from Infomaniak, add a DNS TXT record:

```
Name:  default._domainkey.ravenweapon.ch
Type:  TXT
Value: v=DKIM1; k=rsa; p=<YOUR_DKIM_PUBLIC_KEY_FROM_INFOMANIAK>
```

Note: Infomaniak may use a different selector (not "default"). Use whatever selector they provide.

---

## Task 3: Temporarily Relax DMARC Policy (Optional but Recommended)

**Files:** DNS zone for ravenweapon.ch

**Current DMARC:**
```
_dmarc.ravenweapon.ch  TXT  "v=DMARC1; p=reject;"
```

**Recommended temporary change while fixing:**
```
_dmarc.ravenweapon.ch  TXT  "v=DMARC1; p=quarantine; rua=mailto:dmarc@ravenweapon.ch;"
```

**After DKIM is verified working (2 weeks later), change back to:**
```
_dmarc.ravenweapon.ch  TXT  "v=DMARC1; p=reject; rua=mailto:dmarc@ravenweapon.ch;"
```

The `rua=` tag sends aggregate reports to help monitor email authentication.

---

## Task 4: Fix Custom Email Headers in BankTransferEmailSubscriber

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Subscriber/BankTransferEmailSubscriber.php`

**Current code (around line 100-110):**
```php
$email = (new Email())
    ->from(self::FROM_NAME . ' <' . self::FROM_EMAIL . '>')
    ->to($customerEmail)
    ->subject($subject)
    ->html($htmlContent)
    ->text($textContent);
```

**Change to:**
```php
$email = (new Email())
    ->from(self::FROM_NAME . ' <' . self::FROM_EMAIL . '>')
    ->replyTo(self::FROM_EMAIL)
    ->to($customerEmail)
    ->subject($subject)
    ->html($htmlContent)
    ->text($textContent);

// Add headers to improve deliverability
$headers = $email->getHeaders();
$headers->addTextHeader('X-Mailer', 'Raven Weapon Shop');
$headers->addTextHeader('List-Unsubscribe', '<mailto:info@ravenweapon.ch?subject=unsubscribe>');
```

**Also fix the subject line (around line 88):**
```php
// Current: "ZAHLUNGSINFORMATIONEN - Bestellung {$orderNumber}"
// Change to:
$subject = "Zahlungsinformationen - Bestellung {$orderNumber} | Raven Weapon AG";
```

---

## Task 5: Fix Custom Email Headers in OrderNotificationSubscriber

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Subscriber/OrderNotificationSubscriber.php`

**Change FROM_NAME constant (line ~15) for consistency:**
```php
// Current:
private const FROM_NAME = 'Raven Weapon Shop';

// Change to:
private const FROM_NAME = 'Raven Weapon AG';
```

**Add Reply-To and headers (around the email creation):**
```php
$email = (new Email())
    ->from(self::FROM_NAME . ' <' . self::FROM_EMAIL . '>')
    ->replyTo(self::FROM_EMAIL)
    ->to($adminEmail)
    ->subject($subject)
    ->html($htmlContent)
    ->text($textContent);

// Add headers
$headers = $email->getHeaders();
$headers->addTextHeader('X-Mailer', 'Raven Weapon Shop');
```

---

## Task 6: Fix ALL CAPS in Shopware Email Templates (Admin UI)

**Location:** Shopware Admin → Settings → E-Mail-Templates → Bestellbestätigung

**Text version - Change ALL CAPS sections:**

| Current | Change To |
|---------|-----------|
| `BESTELLTE ARTIKEL` | `Bestellte Artikel` |
| `ZUSAMMENFASSUNG` | `Zusammenfassung` |

**Do the same for other templates if they contain ALL CAPS text.**

---

## Task 7: Fix ALL CAPS in Custom PHP Emails

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Subscriber/BankTransferEmailSubscriber.php`

**Find and replace ALL CAPS text (search for uppercase words):**

| Current | Change To |
|---------|-----------|
| `WICHTIG:` | `Wichtig:` |
| Any other ALL CAPS | Proper capitalization |

---

## Verification Steps

### After DNS Changes (Tasks 1-3)

1. Wait 24-48 hours for DNS propagation
2. Check DNS records:
   ```bash
   nslookup -type=TXT _dmarc.ravenweapon.ch
   nslookup -type=TXT default._domainkey.ravenweapon.ch
   ```
3. Send test email and check headers for:
   - `dkim=pass` in Authentication-Results
   - `spf=pass` in Authentication-Results
   - `dmarc=pass` in Authentication-Results

### After Code Changes (Tasks 4-7)

1. Push changes to staging
2. Place a test order with bank transfer payment
3. Check if email lands in inbox (not spam)
4. View email headers to verify:
   - Reply-To header present
   - X-Mailer header present

### Online Verification Tools

- https://mxtoolbox.com/dkim.aspx - Check DKIM record
- https://mxtoolbox.com/dmarc.aspx - Check DMARC record
- https://mail-tester.com - Send test email and get spam score

---

## Priority Order

1. **CRITICAL - Do First:** Tasks 1-3 (DKIM/DMARC DNS fixes)
   - These are manual DNS changes, not code
   - Will fix 90% of spam issue

2. **IMPORTANT - Do After DNS:** Tasks 4-7 (Code fixes)
   - These improve deliverability further
   - Fix remaining 10% of spam triggers

---

## Files Summary

| File | Changes |
|------|---------|
| DNS Zone (ravenweapon.ch) | Add DKIM TXT record, update DMARC |
| `BankTransferEmailSubscriber.php` | Add Reply-To, headers, fix ALL CAPS |
| `OrderNotificationSubscriber.php` | Fix FROM_NAME, add Reply-To, headers |
| Shopware Admin Email Templates | Remove ALL CAPS from text versions |
