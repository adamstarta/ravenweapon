# SEO Optimization Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Optimize ravenweapon.ch for Swiss German search terms like "visier kaufen schweiz", "waffen online shop schweiz", and improve CTR from search results by showing prices.

**Architecture:** Add JSON-LD structured data for products and organization, optimize meta descriptions with German keywords, and ensure category pages target high-value search terms.

**Tech Stack:** Shopware 6, Twig templates, JSON-LD schema.org markup

---

## Task 1: Add Organization Schema to Base Layout

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/layout/meta.html.twig`

**Step 1: Add Organization JSON-LD schema**

Add after line 14 (after twitter image block):

```twig
{% block layout_head_meta_tags_schema_org %}
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "Raven Weapon AG",
    "url": "https://ravenweapon.ch",
    "logo": "https://ravenweapon.ch/bundles/raventheme/assets/raven-logo.png",
    "description": "Schweizer Waffenhändler - Waffen, Munition, Optik & Zubehör online kaufen",
    "address": {
        "@type": "PostalAddress",
        "addressCountry": "CH",
        "addressRegion": "Schweiz"
    },
    "contactPoint": {
        "@type": "ContactPoint",
        "email": "info@ravenweapon.ch",
        "contactType": "customer service"
    }
}
</script>
{% endblock %}
```

**Step 2: Verify template compiles**

Deploy to staging and check https://developing.ravenweapon.ch for no errors.

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/layout/meta.html.twig
git commit -m "feat(seo): add Organization schema.org structured data"
```

---

## Task 2: Add Product Schema to Product Detail Page

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig`

**Step 1: Add Product JSON-LD schema**

Add inside `{% block page_product_detail %}` after opening, before the content:

```twig
{% block page_product_detail_schema %}
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Product",
    "name": "{{ page.product.translated.name|e('js') }}",
    "description": "{{ page.product.translated.description|striptags|slice(0, 200)|e('js') }}",
    "image": "{% if page.product.cover.media.url %}{{ page.product.cover.media.url }}{% endif %}",
    "sku": "{{ page.product.productNumber }}",
    "brand": {
        "@type": "Brand",
        "name": "{% if page.product.manufacturer %}{{ page.product.manufacturer.translated.name|e('js') }}{% else %}Raven Weapon{% endif %}"
    },
    "offers": {
        "@type": "Offer",
        "url": "{{ seoUrl('frontend.detail.page', { productId: page.product.id }) }}",
        "priceCurrency": "CHF",
        "price": "{{ page.product.calculatedPrice.unitPrice }}",
        "availability": "{% if page.product.availableStock > 0 %}https://schema.org/InStock{% else %}https://schema.org/OutOfStock{% endif %}",
        "seller": {
            "@type": "Organization",
            "name": "Raven Weapon AG"
        }
    }
    {% if page.product.ratingAverage > 0 %},
    "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "{{ page.product.ratingAverage }}",
        "reviewCount": "{{ page.product.reviews.total|default(1) }}"
    }
    {% endif %}
}
</script>
{% endblock %}
```

**Step 2: Verify on staging**

Check a product page source for valid JSON-LD.

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig
git commit -m "feat(seo): add Product schema.org with price for rich snippets"
```

---

## Task 3: Optimize Homepage Meta Description

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/layout/meta.html.twig`

**Step 1: Add meta description block with German keywords**

Add after the title block:

```twig
{% block layout_head_meta_tags_general %}
    {{ parent() }}
    {% if page.metaInformation.metaDescription is not defined or page.metaInformation.metaDescription is empty %}
        {% if page.cmsPage is defined and page.cmsPage %}
            {# Homepage/CMS pages - German SEO optimized #}
            <meta name="description" content="Raven Weapon AG - Ihr Schweizer Waffenhändler. Waffen, Munition, Zielfernrohre, Red Dots & Zubehör online kaufen. Schnelle Lieferung in der Schweiz.">
        {% endif %}
    {% endif %}
{% endblock %}
```

**Step 2: Verify meta description appears**

View page source on homepage and check for the description.

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/layout/meta.html.twig
git commit -m "feat(seo): add German meta description for homepage"
```

---

## Task 4: Add Breadcrumb Schema

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/layout/breadcrumb.html.twig`

**Step 1: Add BreadcrumbList JSON-LD**

Add at the end of the breadcrumb template:

```twig
{% block layout_breadcrumb_schema %}
{% if breadcrumbCategories is defined and breadcrumbCategories|length > 0 %}
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {
            "@type": "ListItem",
            "position": 1,
            "name": "Home",
            "item": "https://ravenweapon.ch"
        }
        {% set position = 2 %}
        {% for category in breadcrumbCategories %}
        ,{
            "@type": "ListItem",
            "position": {{ position }},
            "name": "{{ category.translated.name|e('js') }}",
            "item": "{{ seoUrl('frontend.navigation.page', { navigationId: category.id }) }}"
        }
        {% set position = position + 1 %}
        {% endfor %}
    ]
}
</script>
{% endif %}
{% endblock %}
```

**Step 2: Test breadcrumb schema**

Navigate to a category page and validate JSON-LD in page source.

**Step 3: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/layout/breadcrumb.html.twig
git commit -m "feat(seo): add BreadcrumbList schema for navigation"
```

---

## Task 5: Create Category-Specific Meta Descriptions (Shopware Admin)

**This task is done in Shopware Admin, not code.**

**Step 1: Log into Shopware Admin**

Go to https://ravenweapon.ch/admin

**Step 2: Update category SEO settings**

For each category, go to: **Catalogues → Categories → [Category] → SEO**

Set German meta descriptions:

| Category | Meta Title | Meta Description |
|----------|------------|------------------|
| Optik/Visiere | Zielfernrohre & Red Dots kaufen Schweiz | Zielfernrohre, Red Dot Visiere & Optik online kaufen bei Raven Weapon AG. Top Marken, schnelle Lieferung in der Schweiz. |
| Munition | Munition kaufen Schweiz - 9mm, .223, 7.62 | Munition online bestellen - 9mm, .223, 7.62x39 und mehr. Schweizer Waffenhändler mit schneller Lieferung. |
| Waffen | Waffen kaufen Schweiz - Gewehre & Pistolen | Waffen online kaufen bei Raven Weapon AG. Sturmgewehre, Pistolen & mehr. Schweizer Fachhändler. |
| Zubehör | Waffen Zubehör kaufen Schweiz | Waffenzubehör online kaufen - Bipods, Griffe, Magazine & mehr. Raven Weapon AG Schweiz. |

**Step 3: Save and verify**

Check category pages for updated meta descriptions.

---

## Task 6: Validate with Google Rich Results Test

**Step 1: Test Product Schema**

Go to: https://search.google.com/test/rich-results

Enter a product URL from ravenweapon.ch and verify:
- Product detected
- Price showing
- Availability showing

**Step 2: Test Organization Schema**

Test homepage URL and verify Organization is detected.

**Step 3: Request re-indexing in Search Console**

In Google Search Console:
1. Go to URL Inspection
2. Enter homepage URL
3. Click "Request Indexing"

---

## Summary of Changes

| File | Change |
|------|--------|
| `layout/meta.html.twig` | Organization schema + meta description |
| `page/product-detail/index.html.twig` | Product schema with prices |
| `layout/breadcrumb.html.twig` | BreadcrumbList schema |
| Shopware Admin | Category meta descriptions |

## Expected Results

After implementation and Google re-indexing (1-2 weeks):
- Product prices showing in search results (rich snippets)
- Better CTR on "waffen online shop" queries
- Improved rankings for "visier kaufen schweiz" etc.
- Breadcrumbs showing in search results
