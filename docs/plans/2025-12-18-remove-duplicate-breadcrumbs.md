# Remove Duplicate Breadcrumbs on Category Pages

**Date:** 2025-12-18
**Status:** Ready for implementation

## Problem

Category pages display TWO breadcrumbs:

1. **Top breadcrumb** (gray bar) - JS-injected via `header.html.twig`, positioned below navigation menu
2. **Content breadcrumb** - Twig-rendered inside `<main>`, positioned above category heading

The user wants to **keep only the top breadcrumb** and remove the duplicate inside the content area.

## Root Cause

The duplicate breadcrumb comes from Shopware's base template inheritance. When `navigation/index.html.twig` extends Shopware's storefront template, the base layout includes the breadcrumb via a `base_breadcrumb` or `layout_breadcrumb` block before the main content.

Current CSS hiding attempts in `navigation/index.html.twig` are insufficient because:
- The selector `main nav[aria-label="Breadcrumb"]` works but CSS `display: none` may be overridden
- The breadcrumb is rendered server-side before CSS applies

## Solution

Override the Shopware breadcrumb block to empty it. Add this to `navigation/index.html.twig`:

```twig
{% block base_breadcrumb %}{% endblock %}
```

This prevents Shopware from rendering the content-area breadcrumb while preserving the JS-injected header breadcrumb.

## Implementation Steps

### Step 1: Modify navigation/index.html.twig

**File:** `shopware-theme/RavenTheme/src/Resources/views/storefront/page/navigation/index.html.twig`

**Change:** Add empty `base_breadcrumb` block after `{% sw_extends %}`:

```twig
{% sw_extends '@Storefront/storefront/page/navigation/index.html.twig' %}

{# Disable Shopware's content-area breadcrumb - we use header JS breadcrumb only #}
{% block base_breadcrumb %}{% endblock %}

{# Hide Shopware's default CMS breadcrumb - we use the header breadcrumb only #}
{% block cms_breadcrumb %}{% endblock %}
```

### Step 2: Remove obsolete CSS

The CSS hiding selectors in `navigation/index.html.twig` are no longer needed once the block is disabled. However, keeping them as fallback is safe.

### Step 3: Deploy and verify

```bash
scp -r shopware-theme/RavenTheme root@77.42.19.154:/tmp/ && ssh root@77.42.19.154 "docker cp /tmp/RavenTheme shopware-chf:/var/www/html/custom/plugins/ && docker exec shopware-chf bash -c 'cd /var/www/html && bin/console theme:compile && bin/console cache:clear'"
```

### Step 4: Test all category pages

- `/Alle-Produkte/` - Should show only top breadcrumb
- `/Ausruestung/` - Should show only top breadcrumb
- `/Waffen/` - Should show only top breadcrumb
- Nested categories - Should show only top breadcrumb with full path

## Files Modified

| File | Change |
|------|--------|
| `views/storefront/page/navigation/index.html.twig` | Add empty `base_breadcrumb` block |

## Verification

After deployment, each category page should show:
- ONE breadcrumb in the gray bar below the navigation
- NO breadcrumb inside the white content area above the heading
