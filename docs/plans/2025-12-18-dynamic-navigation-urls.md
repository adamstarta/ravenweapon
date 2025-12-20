# Dynamic Navigation URLs Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Remove all 50+ hardcoded URLs from templates and make them 100% dynamic from database.

**Architecture:** Enhance HomepageProductsSubscriber to load 3-level category tree with seoUrls association. Update all Twig templates to use `seoUrl()` function and `path()` function instead of hardcoded strings.

**Tech Stack:** Shopware 6.6, PHP 8.3, Twig, Symfony

---

## Task 1: Enhance HomepageProductsSubscriber with 3-Level Category Tree

**Files:**
- Modify: `HomepageProductsSubscriber.php` (root directory)

**Step 1: Update the loadNavigationCategories method**

Replace the current `loadNavigationCategories` method (lines 230-249) with enhanced version that loads 3 levels deep with seoUrls:

```php
/**
 * Load navigation categories for header menus on ALL pages
 * Loads 3-level deep category tree with seoUrls for dynamic navigation
 */
private function loadNavigationCategories(Page $page, SalesChannelContext $context): void
{
    $rootCategoryId = $context->getSalesChannel()->getNavigationCategoryId();

    // Get main categories with 3 levels deep + seoUrls for dynamic links
    $categoryCriteria = new Criteria();
    $categoryCriteria->addFilter(new EqualsFilter('parentId', $rootCategoryId));
    $categoryCriteria->addFilter(new EqualsFilter('active', true));
    $categoryCriteria->addFilter(new EqualsFilter('visible', true));
    $categoryCriteria->addAssociation('media');
    $categoryCriteria->addAssociation('seoUrls');                           // Level 1 SEO URLs
    $categoryCriteria->addAssociation('children');                          // Level 2
    $categoryCriteria->addAssociation('children.seoUrls');                  // Level 2 SEO URLs
    $categoryCriteria->addAssociation('children.children');                 // Level 3
    $categoryCriteria->addAssociation('children.children.seoUrls');         // Level 3 SEO URLs
    $categoryCriteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
    $categoryCriteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
    $categoryCriteria->setLimit(20);

    $categories = $this->categoryRepository->search($categoryCriteria, $context->getContext());

    // Add categories to page extensions for header navigation
    $page->addExtension('navCategories', $categories->getEntities());

    // Also set homepageCategories for backward compatibility
    if (!$page->hasExtension('homepageCategories')) {
        $page->addExtension('homepageCategories', $categories->getEntities());
    }
}
```

**Step 2: Update onHeaderPageletLoaded method**

Replace the `onHeaderPageletLoaded` method (lines 49-75) to also load 3 levels:

```php
/**
 * Add navigation categories to the header pagelet
 * This makes categories available in the header template
 */
public function onHeaderPageletLoaded(HeaderPageletLoadedEvent $event): void
{
    $pagelet = $event->getPagelet();
    $context = $event->getSalesChannelContext();

    // Skip if already loaded
    if ($pagelet->hasExtension('navCategories')) {
        return;
    }

    $rootCategoryId = $context->getSalesChannel()->getNavigationCategoryId();

    // Get main categories with 3 levels deep + seoUrls for dynamic navigation
    $categoryCriteria = new Criteria();
    $categoryCriteria->addFilter(new EqualsFilter('parentId', $rootCategoryId));
    $categoryCriteria->addFilter(new EqualsFilter('active', true));
    $categoryCriteria->addFilter(new EqualsFilter('visible', true));
    $categoryCriteria->addAssociation('media');
    $categoryCriteria->addAssociation('seoUrls');
    $categoryCriteria->addAssociation('children');
    $categoryCriteria->addAssociation('children.seoUrls');
    $categoryCriteria->addAssociation('children.children');
    $categoryCriteria->addAssociation('children.children.seoUrls');
    $categoryCriteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
    $categoryCriteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
    $categoryCriteria->setLimit(20);

    $categories = $this->categoryRepository->search($categoryCriteria, $context->getContext());

    // Add categories to header pagelet as extension
    $pagelet->addExtension('navCategories', $categories->getEntities());
}
```

**Step 3: Verify changes**

```bash
# Copy to server and test
scp HomepageProductsSubscriber.php root@77.42.19.154:/root/shopware-chf/custom/plugins/RavenTheme/src/Subscriber/
ssh root@77.42.19.154 "docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'"
```

**Step 4: Commit**

```bash
git add HomepageProductsSubscriber.php
git commit -m "feat: enhance HomepageProductsSubscriber with 3-level category tree and seoUrls"
```

---

## Task 2: Replace Hardcoded Navigation in header.html.twig

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig` (lines 1666-1772)

**Step 1: Replace the hardcoded fallback navigation**

Find the section starting with `{% else %}` at line 1666 and ending around line 1772. Replace the entire hardcoded navigation with dynamic loops:

```twig
{% else %}
    {# Dynamic Navigation - loads from database via navCategories #}
    {% set categories = page.extensions.navCategories|default(page.header.extensions.navCategories|default([])) %}

    {% for mainCat in categories %}
        {% set hasChildren = mainCat.children is defined and mainCat.children|length > 0 %}

        <div class="raven-nav-item{% if hasChildren %} dropdown-align-left{% endif %}">
            <a href="{{ seoUrl('frontend.navigation.page', { navigationId: mainCat.id }) }}"
               class="raven-nav-link inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 py-2">
                {{ mainCat.translated.name }}
                {% if hasChildren %}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 12px; height: 12px; opacity: 0.5;"><polyline points="6 9 12 15 18 9"/></svg>
                {% endif %}
            </a>

            {% if hasChildren %}
            <div class="raven-mega-menu">
                <div class="menu-header">{{ mainCat.translated.name }}</div>

                {% for subCat in mainCat.children|filter(c => c.active and c.visible) %}
                    <a href="{{ seoUrl('frontend.navigation.page', { navigationId: subCat.id }) }}">
                        <span style="color: #9ca3af;">↳</span> {{ subCat.translated.name }}
                    </a>

                    {# Level 3 children #}
                    {% if subCat.children is defined and subCat.children|length > 0 %}
                        {% for subSubCat in subCat.children|filter(c => c.active and c.visible) %}
                            <a href="{{ seoUrl('frontend.navigation.page', { navigationId: subSubCat.id }) }}" style="padding-left: 2rem; font-size: 12px;">
                                <span style="color: #d1d5db;">↳</span> {{ subSubCat.translated.name }}
                            </a>
                        {% endfor %}
                    {% endif %}
                {% endfor %}
            </div>
            {% endif %}
        </div>
    {% endfor %}
{% endif %}
```

**Step 2: Special handling for Ausrüstung mega-menu (optional enhancement)**

For the complex Ausrüstung 3-column layout, add a check:

```twig
{# Inside the loop, before the standard dropdown #}
{% if mainCat.translated.name == 'Ausrüstung' and hasChildren %}
    <div class="raven-mega-menu" style="min-width: 800px; padding: 24px;">
        <div class="menu-header" style="margin-bottom: 16px; font-size: 15px; font-weight: 600; color: #1f2937;">Taktische Ausrüstung</div>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;">
            {% for subCat in mainCat.children|filter(c => c.active and c.visible) %}
            <div>
                <a href="{{ seoUrl('frontend.navigation.page', { navigationId: subCat.id }) }}" style="display: block; font-weight: 600; font-size: 13px; color: #374151; margin-bottom: 8px; text-decoration: none;">
                    {{ subCat.translated.name }}
                </a>
                {% if subCat.children is defined %}
                    {% for subSubCat in subCat.children|filter(c => c.active and c.visible) %}
                    <a href="{{ seoUrl('frontend.navigation.page', { navigationId: subSubCat.id }) }}" style="display: block; padding: 4px 0; color: #6b7280; font-size: 12px; text-decoration: none;">
                        {{ subSubCat.translated.name }}
                    </a>
                    {% endfor %}
                {% endif %}
            </div>
            {% endfor %}
        </div>
    </div>
{% endif %}
```

**Step 3: Deploy and test**

```bash
scp shopware-theme/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig root@77.42.19.154:/root/shopware-chf/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/header/
ssh root@77.42.19.154 "docker exec shopware-chf bash -c 'cd /var/www/html && bin/console theme:compile && bin/console cache:clear'"
```

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig
git commit -m "feat: replace hardcoded navigation URLs with dynamic seoUrl() calls"
```

---

## Task 3: Fix Homepage Manufacturer Links

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/content/index.html.twig` (lines 250-283)

**Step 1: Replace hardcoded manufacturer URLs**

Find lines 250-283 with the brand links. Replace each hardcoded `/hersteller/xxx` with `path()` function:

```twig
{# Brand 1: Lockhart Tactical #}
<a href="{{ path('frontend.manufacturer.page', {slug: 'lockhart-tactical'}) }}"
   style="border: 1px solid #e5e7eb; border-radius: 0.75rem; height: 6rem; display: flex; align-items: center; justify-content: center; background: white; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); text-decoration: none;"
   onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.transform='translateY(-2px)';"
   onmouseout="this.style.boxShadow='0 1px 2px rgba(0,0,0,0.05)'; this.style.transform='translateY(0)';"
   title="Lockhart Tactical">
    <img src="{{ asset('bundles/raventheme/assets/brand-lockhart.png') }}" alt="Lockhart Tactical" style="max-height: 70%; max-width: 70%; object-fit: contain;">
</a>

{# Brand 2: Magpul #}
<a href="{{ path('frontend.manufacturer.page', {slug: 'magpul'}) }}"
   style="border: 1px solid #e5e7eb; border-radius: 0.75rem; height: 6rem; display: flex; align-items: center; justify-content: center; background: white; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); text-decoration: none;"
   onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.transform='translateY(-2px)';"
   onmouseout="this.style.boxShadow='0 1px 2px rgba(0,0,0,0.05)'; this.style.transform='translateY(0)';"
   title="Magpul">
    <img src="{{ asset('bundles/raventheme/assets/brand-magpul.png') }}" alt="Magpul" style="max-height: 70%; max-width: 70%; object-fit: contain;">
</a>

{# Brand 3: Snigel #}
<a href="{{ path('frontend.manufacturer.page', {slug: 'snigel'}) }}"
   style="border: 1px solid #e5e7eb; border-radius: 0.75rem; height: 6rem; display: flex; align-items: center; justify-content: center; background: white; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); text-decoration: none;"
   onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.transform='translateY(-2px)';"
   onmouseout="this.style.boxShadow='0 1px 2px rgba(0,0,0,0.05)'; this.style.transform='translateY(0)';"
   title="Snigel">
    <img src="{{ asset('bundles/raventheme/assets/brand-snigel.png') }}" alt="Snigel" style="max-height: 70%; max-width: 70%; object-fit: contain;">
</a>

{# Brand 4: Zerotech #}
<a href="{{ path('frontend.manufacturer.page', {slug: 'zerotech'}) }}"
   style="border: 1px solid #e5e7eb; border-radius: 0.75rem; height: 6rem; display: flex; align-items: center; justify-content: center; background: white; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); text-decoration: none;"
   onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.transform='translateY(-2px)';"
   onmouseout="this.style.boxShadow='0 1px 2px rgba(0,0,0,0.05)'; this.style.transform='translateY(0)';"
   title="Zerotech">
    <img src="{{ asset('bundles/raventheme/assets/brand-zerotech.png') }}" alt="Zerotech" style="max-height: 70%; max-width: 70%; object-fit: contain;">
</a>
```

**Step 2: Deploy and test**

```bash
scp shopware-theme/RavenTheme/src/Resources/views/storefront/page/content/index.html.twig root@77.42.19.154:/root/shopware-chf/custom/plugins/RavenTheme/src/Resources/views/storefront/page/content/
ssh root@77.42.19.154 "docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'"
```

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/content/index.html.twig
git commit -m "fix: replace hardcoded manufacturer URLs with path() function on homepage"
```

---

## Task 4: Fix Product Card Manufacturer Links

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/component/product/card/box-standard.html.twig` (line 179)

**Step 1: Replace hardcoded manufacturer URL pattern**

Find line 179:
```twig
<a href="/{{ manufacturerSlug }}" class="raven-manufacturer" ...>
```

Replace with:
```twig
<a href="{{ path('frontend.manufacturer.page', {slug: manufacturerSlug}) }}" class="raven-manufacturer" style="font-weight: 700; color: #374151; text-decoration: none; display: block;">{{ manufacturerName }}</a>
```

**Step 2: Deploy and test**

```bash
scp shopware-theme/RavenTheme/src/Resources/views/storefront/component/product/card/box-standard.html.twig root@77.42.19.154:/root/shopware-chf/custom/plugins/RavenTheme/src/Resources/views/storefront/component/product/card/
ssh root@77.42.19.154 "docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'"
```

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/component/product/card/box-standard.html.twig
git commit -m "fix: replace hardcoded manufacturer URL in product cards with path() function"
```

---

## Task 5: Fix Buy Box Manufacturer Links

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig`

**Step 1: Find and replace manufacturer URL**

Find the hardcoded pattern:
```twig
<a href="/{{ manufacturerSlug }}" class="raven-category">
```

Replace with:
```twig
<a href="{{ path('frontend.manufacturer.page', {slug: manufacturerSlug}) }}" class="raven-category">
```

**Step 2: Also fix home link if present**

Find:
```twig
<a href="/">
```

Replace with:
```twig
<a href="{{ path('frontend.home.page') }}">
```

**Step 3: Deploy and test**

```bash
scp shopware-theme/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig root@77.42.19.154:/root/shopware-chf/custom/plugins/RavenTheme/src/Resources/views/storefront/element/
ssh root@77.42.19.154 "docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'"
```

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig
git commit -m "fix: replace hardcoded URLs in buy box with path() functions"
```

---

## Task 6: Fix Coming Soon Page Manufacturer Links

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/manufacturer/coming-soon.html.twig`

**Step 1: Find and replace all hardcoded URLs**

Find patterns like:
```twig
<a href="/{{ manufacturerSlug }}/">
<a href="/alle-produkte/">
```

Replace with:
```twig
<a href="{{ path('frontend.manufacturer.page', {slug: manufacturerSlug}) }}">
<a href="{{ seoUrl('frontend.navigation.page', { navigationId: page.extensions.navCategories|first.id }) }}">
```

**Step 2: Deploy and test**

```bash
scp shopware-theme/RavenTheme/src/Resources/views/storefront/page/manufacturer/coming-soon.html.twig root@77.42.19.154:/root/shopware-chf/custom/plugins/RavenTheme/src/Resources/views/storefront/page/manufacturer/
ssh root@77.42.19.154 "docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'"
```

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/manufacturer/coming-soon.html.twig
git commit -m "fix: replace hardcoded URLs in coming-soon page with path() functions"
```

---

## Task 7: Final Verification

**Step 1: Full deployment**

```bash
# Deploy all changes
ssh root@77.42.19.154 "docker exec shopware-chf bash -c 'cd /var/www/html && bin/console theme:compile && bin/console cache:clear'"
```

**Step 2: Test navigation**

1. Open https://ortak.ch
2. Hover over each main navigation item
3. Verify dropdown menus appear with correct links
4. Click through to verify URLs work
5. Check product cards have working manufacturer links
6. Check product detail page buy box has working links

**Step 3: Verify no hardcoded URLs remain**

```bash
# Search for remaining hardcoded category URLs
grep -r 'href="/waffen' shopware-theme/
grep -r 'href="/zubehoer' shopware-theme/
grep -r 'href="/ausruestung' shopware-theme/
grep -r 'href="/hersteller/' shopware-theme/
```

Expected: No matches (all replaced with dynamic functions)

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete dynamic URL implementation - zero hardcoded URLs"
```

---

## Summary

| Task | Files | Hardcoded URLs Removed |
|------|-------|------------------------|
| Task 1 | HomepageProductsSubscriber.php | Enables dynamic data |
| Task 2 | header.html.twig | 50+ category URLs |
| Task 3 | index.html.twig | 4 manufacturer URLs |
| Task 4 | box-standard.html.twig | 1 manufacturer URL pattern |
| Task 5 | cms-element-buy-box.html.twig | 2 URLs |
| Task 6 | coming-soon.html.twig | 3 URLs |
| Task 7 | Verification | Confirm zero hardcode |

**Total: 60+ hardcoded URLs → 0**
