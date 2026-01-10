# Email Templates Redesign Plan

> **For Claude:** These changes are made directly in Shopware Admin (Settings → E-Mail-Templates), NOT in code files.

**Goal:** Update two email templates to have consistent Raven branding with lighter, more readable styling.

**Templates to Update:**
1. **"Neues Dokument für Ihre Bestellung"** - Currently plain text, needs full Raven branding
2. **"Ihre Bestellung wurde versandt"** (Eintritt Lieferstatus: Versandt) - Has branding but dark info box needs lightening

---

## Design Specifications

### Color Palette (matching ravenweapon.ch)
| Element | Color | Hex |
|---------|-------|-----|
| Gold accent | Gold | `#F59E0B` |
| Gold dark | Gold dark | `#D97706` |
| Success green | Green | `#059669` |
| Text dark | Charcoal | `#1f2937` |
| Text gray | Gray | `#6b7280` |
| Info box bg | Light cream | `#FEF9E7` (was dark `#1f2937`) |
| Info box border | Gold | `#F59E0B` |
| Button bg | Gold gradient | `#F59E0B` |
| Footer bg | Light gray | `#f3f4f6` (was dark `#1f2937`) |

### Design Changes
**Problem:** Dark info boxes (#1f2937 background) make text hard to read
**Solution:** Use light cream background (#FEF9E7) with gold border

---

## Task 1: Update "Ihre Bestellung wurde versandt" Template

**Location:** Shopware Admin → Settings → E-Mail-Templates → "Eintritt Lieferstatus: Versandt"

**Current Issue:** The VERSANDINFORMATIONEN box has dark background (#1f2937) making text hard to read

**Changes to HTML:**

### Step 1.1: Change Info Box from Dark to Light

Find this section (around line 20-35):
```html
<div style="background: #1f2937; border-radius: 12px; padding: 20px; margin-bottom: 25px;">
    <h3 style="color: #F59E0B; ...">VERSANDINFORMATIONEN</h3>
```

Replace with:
```html
<div style="background: #FEF9E7; border: 2px solid #F59E0B; border-radius: 12px; padding: 20px; margin-bottom: 25px;">
    <h3 style="color: #D97706; font-size: 14px; font-weight: 700; margin: 0 0 15px 0; text-transform: uppercase; letter-spacing: 1px;">VERSANDINFORMATIONEN</h3>
```

### Step 1.2: Update Table Text Colors in Info Box

Find:
```html
<td style="color: #9ca3af; ...">
```

Replace with:
```html
<td style="color: #6b7280; padding: 5px 10px 5px 0; font-size: 14px;">
```

Find:
```html
<td style="color: #ffffff; ...">
```

Replace with:
```html
<td style="color: #1f2937; padding: 5px 0; font-size: 14px; font-weight: 600;">
```

### Step 1.3: Update Footer from Dark to Light

Find:
```html
<div style="background: #1f2937; ...">
    <p style="color: #9ca3af; ...">
```

Replace with:
```html
<div style="background: #f3f4f6; text-align: center; padding: 25px 20px; border-top: 3px solid #F59E0B;">
    <p style="color: #6b7280; font-size: 13px; margin: 0 0 5px 0;">
```

---

## Task 2: Update "Neues Dokument für Ihre Bestellung" Template

**Location:** Shopware Admin → Settings → E-Mail-Templates → Look for document-related templates or check Flow Builder

**Note:** This template may be under:
- "Eintritt Bestellstatus: In Bearbeitung" (Order status change)
- Or triggered via Flow Builder when documents are attached

**Current Issue:** Plain text with no branding

**New HTML Template:**

```html
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <!-- Header with Logo -->
    <div style="text-align: center; padding: 30px 20px; border-bottom: 3px solid #F59E0B;">
        <img src="https://shop.ravenweapon.ch/bundles/raventheme/assets/raven-logo.png" alt="Raven Weapon" style="max-width: 200px; height: auto;">
    </div>

    <!-- Content -->
    <div style="padding: 30px 20px;">
        <!-- Greeting -->
        <p style="font-size: 16px; color: #1f2937; margin-bottom: 20px;">
            Guten Tag {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        </p>

        <p style="font-size: 16px; color: #1f2937; margin-bottom: 25px;">
            für Ihre Bestellung <strong>#{{ order.orderNumber }}</strong> wurde ein neues Dokument erstellt.
        </p>

        <!-- Document Info Box -->
        <div style="background: #FEF9E7; border: 2px solid #F59E0B; border-radius: 12px; padding: 20px; margin-bottom: 25px;">
            <h3 style="color: #D97706; font-size: 14px; font-weight: 700; margin: 0 0 15px 0; text-transform: uppercase; letter-spacing: 1px;">DOKUMENTINFORMATIONEN</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="color: #6b7280; padding: 5px 10px 5px 0; font-size: 14px;"><strong>Bestellnummer:</strong></td>
                    <td style="color: #1f2937; padding: 5px 0; font-size: 14px; font-weight: 600;">{{ order.orderNumber }}</td>
                </tr>
                <tr>
                    <td style="color: #6b7280; padding: 5px 10px 5px 0; font-size: 14px;"><strong>Bestelldatum:</strong></td>
                    <td style="color: #1f2937; padding: 5px 0; font-size: 14px; font-weight: 600;">{{ order.orderDateTime|format_datetime('medium', 'short', locale='de') }}</td>
                </tr>
                <tr>
                    <td style="color: #6b7280; padding: 5px 10px 5px 0; font-size: 14px;"><strong>Status:</strong></td>
                    <td style="color: #1f2937; padding: 5px 0; font-size: 14px; font-weight: 600;">{{ order.stateMachineState.translated.name }}</td>
                </tr>
            </table>
        </div>

        <p style="font-size: 14px; color: #6b7280; margin-bottom: 25px;">
            Das Dokument finden Sie als PDF im Anhang dieser E-Mail. Sie können Ihre Bestellung auch jederzeit in Ihrem Kundenkonto einsehen.
        </p>

        <!-- CTA Button -->
        <div style="text-align: center; margin-bottom: 25px;">
            <a href="{{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}"
               style="display: inline-block; background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); color: #1f2937; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 700; font-size: 14px;">
                Bestellung ansehen
            </a>
        </div>

        <!-- Signature -->
        <p style="font-size: 14px; color: #6b7280; margin-bottom: 5px;">Mit freundlichen Grüssen</p>
        <p style="font-size: 14px; color: #1f2937; font-weight: 700; margin: 0;">Raven Weapon AG</p>
    </div>

    <!-- Footer -->
    <div style="background: #f3f4f6; text-align: center; padding: 25px 20px; border-top: 3px solid #F59E0B;">
        <p style="color: #6b7280; font-size: 13px; margin: 0 0 5px 0;">Raven Weapon AG | Schweiz</p>
        <p style="margin: 0;">
            <a href="https://shop.ravenweapon.ch" style="color: #F59E0B; text-decoration: none; font-size: 13px;">www.ravenweapon.ch</a>
        </p>
    </div>
</div>
```

---

## Implementation Steps

### Step 1: Access Shopware Admin
1. Go to https://ravenweapon.ch/admin
2. Login with admin credentials
3. Navigate to Settings → E-Mail-Templates

### Step 2: Update "Eintritt Lieferstatus: Versandt"
1. Find and click on "Eintritt Lieferstatus: Versandt"
2. In the HTML editor, make the color changes described in Task 1
3. Click "Vorschau anzeigen" to verify changes
4. Click "Speichern" to save

### Step 3: Find and Update Document Template
1. Search for templates related to documents:
   - "Eintritt Bestellstatus: In Bearbeitung"
   - Check Settings → Documents for email settings
   - Check Flow Builder for document-triggered emails
2. Apply the new HTML template from Task 2
3. Preview and save

### Step 4: Test
1. Use "Test-Mail senden" to send test emails
2. Verify on desktop and mobile email clients
3. Check all dynamic content renders correctly

---

## Summary

| Template | Current State | Change |
|----------|---------------|--------|
| Versandt email | Dark info box (#1f2937) | Light cream box (#FEF9E7) with gold border |
| Versandt email | Dark footer | Light gray footer (#f3f4f6) |
| Document email | Plain text | Full Raven branding with light styling |

**Key Design Principle:** Replace all dark backgrounds (#1f2937) with light alternatives (#FEF9E7 for info boxes, #f3f4f6 for footer) while keeping gold accents (#F59E0B).
