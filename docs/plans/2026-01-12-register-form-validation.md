# Register Form Real-Time Validation Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add real-time client-side validation to the register form with visual feedback for password requirements and matching.

**Architecture:** JavaScript event listeners on password fields that validate on input/blur events, showing inline error messages and changing border colors. Prevents form submission if validation fails. Uses existing toast system for error notifications.

**Tech Stack:** Vanilla JavaScript, CSS, existing ravenToast notification system

---

## Current State

The register form at `/account/register` currently:
- Has two password fields: `personalPassword` and `personalPasswordConfirmation`
- Uses HTML5 `required` and `minlength="8"` attributes
- On validation failure, page refreshes with no user feedback
- Has existing `ravenToast()` function for notifications

## What We'll Add

1. **Real-time password strength indicator** - Shows if password meets 8 character minimum
2. **Real-time password match validation** - Shows if passwords match as user types
3. **Visual feedback** - Border colors (red/green) and inline error messages
4. **Form submission prevention** - Block submit if validation fails, show toast error
5. **Email validation** - Basic email format check

---

## Task 1: Add CSS for Validation States

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig` (CSS section around line 830)

**Step 1: Add validation CSS styles**

Add these styles inside the `<style>` tag, after the `.password-toggle svg` styles (around line 862):

```css
/* Form Validation States */
.form-group.has-error input,
.form-group.has-error .password-wrapper input {
    border-color: #dc2626 !important;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
}

.form-group.has-success input,
.form-group.has-success .password-wrapper input {
    border-color: #16a34a !important;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1) !important;
}

.validation-message {
    font-size: 0.75rem;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.validation-message.error {
    color: #dc2626;
}

.validation-message.success {
    color: #16a34a;
}

.validation-message svg {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
}

.password-requirements {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.5rem;
}

.password-requirements.met {
    color: #16a34a;
}

.password-requirements.unmet {
    color: #dc2626;
}
```

**Step 2: Verify CSS is added correctly**

Check the file around line 862-900 to confirm styles are in place.

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig
git commit -m "style(register): add CSS for form validation states"
```

---

## Task 2: Add Password Validation Helper Elements

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig` (HTML around lines 110-143)

**Step 1: Add validation message containers after each password field**

Update the password field (around line 125) to add a requirements message:

```twig
{# Password #}
<div class="form-group" id="passwordGroup">
    <label for="personalPassword">Passwort</label>
    <div class="password-wrapper">
        <input type="password"
               name="password"
               id="personalPassword"
               placeholder="Mindestens 8 Zeichen"
               autocomplete="new-password"
               minlength="8"
               required>
        <button type="button" class="password-toggle" onclick="togglePassword('personalPassword', this)" aria-label="Passwort anzeigen">
            <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            <svg class="eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
        </button>
    </div>
    <div id="passwordRequirements" class="password-requirements">Mindestens 8 Zeichen erforderlich</div>
</div>
```

Update the confirm password field (around line 143) to add match message:

```twig
{# Confirm Password #}
<div class="form-group" id="passwordConfirmGroup">
    <label for="personalPasswordConfirmation">Passwort bestätigen</label>
    <div class="password-wrapper">
        <input type="password"
               name="passwordConfirmation"
               id="personalPasswordConfirmation"
               placeholder="Passwort wiederholen"
               autocomplete="new-password"
               minlength="8"
               required>
        <button type="button" class="password-toggle" onclick="togglePassword('personalPasswordConfirmation', this)" aria-label="Passwort anzeigen">
            <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            <svg class="eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
        </button>
    </div>
    <div id="passwordMatchMessage" class="validation-message" style="display:none;"></div>
</div>
```

**Step 2: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig
git commit -m "feat(register): add validation message containers to password fields"
```

---

## Task 3: Add JavaScript Validation Functions

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig` (Script section after togglePassword function, around line 1103)

**Step 1: Add validation JavaScript**

Add this code after the `togglePassword` function (around line 1103):

```javascript
// ========== REAL-TIME FORM VALIDATION ==========
document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.querySelector('.register-form');
    if (!registerForm) return;

    const passwordInput = document.getElementById('personalPassword');
    const confirmInput = document.getElementById('personalPasswordConfirmation');
    const passwordGroup = document.getElementById('passwordGroup');
    const confirmGroup = document.getElementById('passwordConfirmGroup');
    const passwordRequirements = document.getElementById('passwordRequirements');
    const passwordMatchMessage = document.getElementById('passwordMatchMessage');

    // SVG icons for validation messages
    const checkIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>';
    const errorIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';

    // Validate password length
    function validatePassword() {
        const value = passwordInput.value;
        const isValid = value.length >= 8;

        if (value.length === 0) {
            // Empty - reset to default
            passwordGroup.classList.remove('has-error', 'has-success');
            passwordRequirements.className = 'password-requirements';
            passwordRequirements.textContent = 'Mindestens 8 Zeichen erforderlich';
        } else if (isValid) {
            passwordGroup.classList.remove('has-error');
            passwordGroup.classList.add('has-success');
            passwordRequirements.className = 'password-requirements met';
            passwordRequirements.innerHTML = checkIcon + ' Passwort erfüllt die Anforderungen';
        } else {
            passwordGroup.classList.remove('has-success');
            passwordGroup.classList.add('has-error');
            passwordRequirements.className = 'password-requirements unmet';
            passwordRequirements.innerHTML = errorIcon + ' Noch ' + (8 - value.length) + ' Zeichen erforderlich';
        }

        // Also check match if confirm has value
        if (confirmInput.value.length > 0) {
            validatePasswordMatch();
        }

        return isValid;
    }

    // Validate password match
    function validatePasswordMatch() {
        const password = passwordInput.value;
        const confirm = confirmInput.value;

        if (confirm.length === 0) {
            // Empty - hide message
            confirmGroup.classList.remove('has-error', 'has-success');
            passwordMatchMessage.style.display = 'none';
            return false;
        }

        const isMatch = password === confirm && password.length >= 8;

        if (password === confirm) {
            if (password.length >= 8) {
                confirmGroup.classList.remove('has-error');
                confirmGroup.classList.add('has-success');
                passwordMatchMessage.className = 'validation-message success';
                passwordMatchMessage.innerHTML = checkIcon + ' Passwörter stimmen überein';
            } else {
                confirmGroup.classList.remove('has-error', 'has-success');
                passwordMatchMessage.className = 'validation-message';
                passwordMatchMessage.innerHTML = '';
            }
        } else {
            confirmGroup.classList.remove('has-success');
            confirmGroup.classList.add('has-error');
            passwordMatchMessage.className = 'validation-message error';
            passwordMatchMessage.innerHTML = errorIcon + ' Passwörter stimmen nicht überein';
        }

        passwordMatchMessage.style.display = 'flex';
        return isMatch;
    }

    // Add event listeners
    passwordInput.addEventListener('input', validatePassword);
    passwordInput.addEventListener('blur', validatePassword);
    confirmInput.addEventListener('input', validatePasswordMatch);
    confirmInput.addEventListener('blur', validatePasswordMatch);

    // Form submission validation
    registerForm.addEventListener('submit', function(e) {
        const isPasswordValid = passwordInput.value.length >= 8;
        const isMatchValid = passwordInput.value === confirmInput.value;

        if (!isPasswordValid) {
            e.preventDefault();
            validatePassword();
            window.ravenToast('error', 'Das Passwort muss mindestens 8 Zeichen lang sein.');
            passwordInput.focus();
            return false;
        }

        if (!isMatchValid) {
            e.preventDefault();
            validatePasswordMatch();
            window.ravenToast('error', 'Die Passwörter stimmen nicht überein.');
            confirmInput.focus();
            return false;
        }
    });
});
```

**Step 2: Verify JavaScript is added correctly**

Check that the code is placed after the `togglePassword` function and before the IIFE that starts with `(function() {`.

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig
git commit -m "feat(register): add real-time password validation with visual feedback"
```

---

## Task 4: Test and Deploy

**Step 1: Push changes to staging**

```bash
git push origin main
```

**Step 2: Wait for deployment (~2 minutes)**

**Step 3: Test on staging**

1. Go to https://developing.ravenweapon.ch/account/register
2. Test password field:
   - Type less than 8 characters → Red border, "Noch X Zeichen erforderlich"
   - Type 8+ characters → Green border, checkmark "Passwort erfüllt die Anforderungen"
3. Test confirm password field:
   - Type different password → Red border, "Passwörter stimmen nicht überein"
   - Type matching password → Green border, checkmark "Passwörter stimmen überein"
4. Test form submission:
   - Try to submit with short password → Toast error, form blocked
   - Try to submit with mismatched passwords → Toast error, form blocked
   - Submit valid form → Should submit successfully

**Step 4: Final commit if any fixes needed**

---

## Summary

| Task | Description | Estimated Time |
|------|-------------|----------------|
| Task 1 | Add CSS validation styles | 2-3 min |
| Task 2 | Add HTML validation containers | 2-3 min |
| Task 3 | Add JavaScript validation | 5-7 min |
| Task 4 | Test and deploy | 5 min |

**Total: ~15 minutes**

## Visual Preview

**Before validation:**
```
[Passwort          ] ← Gray border
Mindestens 8 Zeichen erforderlich
```

**Password too short:**
```
[Pass              ] ← Red border + red shadow
✗ Noch 4 Zeichen erforderlich
```

**Password valid:**
```
[Password123       ] ← Green border + green shadow
✓ Passwort erfüllt die Anforderungen
```

**Passwords don't match:**
```
[Password123       ] ← Green border
[Password456       ] ← Red border
✗ Passwörter stimmen nicht überein
```

**Passwords match:**
```
[Password123       ] ← Green border
[Password123       ] ← Green border
✓ Passwörter stimmen überein
```
