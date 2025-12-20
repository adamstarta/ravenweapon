# Fix Breadcrumb Phantom Categories Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix broken breadcrumbs showing phantom category entries that link to 404 pages

**Architecture:**
1. Set proper main_category for all products without one
2. Fix ProductDetailSubscriber to handle edge cases
3. Regenerate SEO URLs using Shopware's built-in commands

**Tech Stack:** Shopware 6.6, PHP, Twig, MySQL

---

## Problem Analysis

### Symptoms
- Products like "223 Remington FMJ" show breadcrumb: `Home / Munition / 223 Remington Fmj 55Grs 980M / [product]`
- The middle link `/munition/223-remington-fmj-55grs-980m/` returns 404
- Product ".22 LR OFFICIAL" shows correct breadcrumb: `Home / Munition / [product]`

### Root Cause
1. **Products have NO main_category assigned** in the `main_category` table
2. Without main_category, Shopware's seoCategory derivation creates inconsistent breadcrumb data
3. The SEO URL template uses `product.seoCategory.seoBreadcrumb` which may contain stale/incorrect entries
4. No SEO URLs exist for these products in the database

### Affected Products
- 223 Remington FMJ 55grs 980m/s, 50 pcs. (ID: dead78ac80c804734cbe6f316e0a2dee)
- 300 AAC Blackout FMC, 147 grs, 580 m/s, 50 pieces (ID: c57986d687ca33d06d0ea1c644f56742)
- Potentially other products without main_category

---

## Task 1: Create Script to Identify All Products Without Main Category

**Files:**
- Create: `scripts/find-products-without-main-category.php`

**Step 1: Create the diagnostic script**

```php
<?php
/**
 * Find all products that don't have a main_category assigned
 */

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Products Without Main Category ===\n\n";

$query = "
SELECT
    LOWER(HEX(p.id)) as product_id,
    pt.name as product_name,
    LOWER(HEX(pc.category_id)) as assigned_category_id,
    ct.name as category_name
FROM product p
JOIN product_translation pt ON p.id = pt.product_id AND pt.language_id = (
    SELECT id FROM language WHERE locale_code = 'de-DE' OR locale_code = 'de-CH' LIMIT 1
)
JOIN product_category pc ON p.id = pc.product_id
LEFT JOIN category_translation ct ON pc.category_id = ct.category_id AND ct.language_id = pt.language_id
LEFT JOIN main_category mc ON p.id = mc.product_id
WHERE mc.product_id IS NULL
AND p.parent_id IS NULL
ORDER BY ct.name, pt.name
";

$stmt = $pdo->query($query);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$byCategory = [];
foreach ($products as $p) {
    $cat = $p['category_name'] ?: 'UNCATEGORIZED';
    if (!isset($byCategory[$cat])) {
        $byCategory[$cat] = [];
    }
    $byCategory[$cat][] = $p;
}

$total = 0;
foreach ($byCategory as $cat => $prods) {
    echo "Category: $cat (" . count($prods) . " products)\n";
    foreach ($prods as $p) {
        echo "  - " . $p['product_name'] . "\n";
        echo "    Product ID: " . $p['product_id'] . "\n";
        echo "    Category ID: " . $p['assigned_category_id'] . "\n";
        $total++;
    }
    echo "\n";
}

echo "Total products without main_category: $total\n";
```

**Step 2: Run locally to test**

```bash
php scripts/find-products-without-main-category.php
```

---

## Task 2: Create Script to Set Main Category for Products

**Files:**
- Create: `scripts/set-main-categories.php`

**Step 1: Create the fix script**

```php
<?php
/**
 * Set main_category for all products that don't have one
 * Uses the first assigned category from product_category
 */

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=shopware', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get the sales channel ID
$scQuery = "SELECT LOWER(HEX(id)) as id FROM sales_channel WHERE active = 1 LIMIT 1";
$scStmt = $pdo->query($scQuery);
$salesChannelId = $scStmt->fetchColumn();

echo "=== Setting Main Categories for Products ===\n";
echo "Sales Channel: $salesChannelId\n\n";

// Find products without main_category
$query = "
SELECT DISTINCT
    LOWER(HEX(p.id)) as product_id,
    pt.name as product_name,
    LOWER(HEX(pc.category_id)) as category_id
FROM product p
JOIN product_translation pt ON p.id = pt.product_id
JOIN product_category pc ON p.id = pc.product_id
JOIN category c ON pc.category_id = c.id
LEFT JOIN main_category mc ON p.id = mc.product_id AND mc.sales_channel_id = UNHEX('$salesChannelId')
WHERE mc.product_id IS NULL
AND p.parent_id IS NULL
AND c.level >= 2
ORDER BY pt.name
";

$stmt = $pdo->query($query);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($products) . " products without main_category\n\n";

if (count($products) === 0) {
    echo "All products have main_category assigned.\n";
    exit(0);
}

// Group by product (a product might be in multiple categories)
$byProduct = [];
foreach ($products as $p) {
    if (!isset($byProduct[$p['product_id']])) {
        $byProduct[$p['product_id']] = [
            'name' => $p['product_name'],
            'categories' => []
        ];
    }
    $byProduct[$p['product_id']]['categories'][] = $p['category_id'];
}

echo "Products to update:\n";
foreach ($byProduct as $pid => $data) {
    echo "  - " . $data['name'] . " -> Category: " . $data['categories'][0] . "\n";
}

echo "\nProceed with update? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'yes') {
    echo "Aborted.\n";
    exit(0);
}

$updated = 0;
foreach ($byProduct as $pid => $data) {
    $categoryId = $data['categories'][0]; // Use first category

    $insertQuery = "
    INSERT INTO main_category (id, product_id, product_version_id, category_id, category_version_id, sales_channel_id, created_at)
    SELECT
        UNHEX(REPLACE(UUID(), '-', '')),
        UNHEX('$pid'),
        p.version_id,
        UNHEX('$categoryId'),
        c.version_id,
        UNHEX('$salesChannelId'),
        NOW()
    FROM product p, category c
    WHERE LOWER(HEX(p.id)) = '$pid'
    AND LOWER(HEX(c.id)) = '$categoryId'
    LIMIT 1
    ";

    try {
        $pdo->exec($insertQuery);
        echo "  SET: " . $data['name'] . "\n";
        $updated++;
    } catch (PDOException $e) {
        echo "  ERROR: " . $data['name'] . " - " . $e->getMessage() . "\n";
    }
}

echo "\nUpdated: $updated products\n";
echo "\nIMPORTANT: Run these commands to regenerate SEO URLs:\n";
echo "  bin/console dal:refresh:index\n";
echo "  bin/console cache:clear\n";
```

**Step 2: Deploy and run on server**

```bash
scp scripts/set-main-categories.php root@77.42.19.154:/tmp/
ssh root@77.42.19.154 "docker cp /tmp/set-main-categories.php shopware-chf:/tmp/"
ssh root@77.42.19.154 "docker exec -it shopware-chf php /tmp/set-main-categories.php"
```

---

## Task 3: Regenerate SEO URLs Using Shopware Built-in Commands

**Step 1: Refresh the data abstraction layer index**

```bash
ssh root@77.42.19.154 "docker exec shopware-chf bin/console dal:refresh:index"
```

**Step 2: Clear all caches**

```bash
ssh root@77.42.19.154 "docker exec shopware-chf bin/console cache:clear"
```

**Step 3: Regenerate SEO URLs (optional, if needed)**

```bash
ssh root@77.42.19.154 "docker exec shopware-chf bin/console seo:generate-url"
```

---

## Task 4: Fix ProductDetailSubscriber to Handle Edge Cases

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Subscriber/ProductDetailSubscriber.php`

**Step 1: Update the subscriber to handle missing main_category gracefully**

Replace the `onProductPageLoaded` method to fallback to product.categories when no main_category exists:

```php
public function onProductPageLoaded(ProductPageLoadedEvent $event): void
{
    $page = $event->getPage();
    $product = $page->getProduct();
    $context = $event->getSalesChannelContext();

    // Check if seoCategory is already set with full breadcrumb data
    $existingCategory = $product->getSeoCategory();
    if ($existingCategory !== null && $existingCategory->getBreadcrumb() !== null) {
        $this->loadBreadcrumbCategories($existingCategory, $page, $context->getContext());
        return;
    }

    // Try to get the main category for this product in the current sales channel
    $criteria = new Criteria();
    $criteria->addFilter(new EqualsFilter('productId', $product->getId()));
    $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()));
    $criteria->addAssociation('category');

    $mainCategories = $this->mainCategoryRepository->search($criteria, $context->getContext());

    $category = null;

    if ($mainCategories->count() > 0) {
        $mainCategory = $mainCategories->first();
        $category = $mainCategory->get('category');
    } else {
        // FALLBACK: Use first category from product.categories (level >= 2)
        $productCategories = $product->getCategories();
        if ($productCategories !== null) {
            foreach ($productCategories as $cat) {
                if ($cat->getLevel() >= 2) {
                    $category = $cat;
                    break;
                }
            }
        }
    }

    if ($category instanceof CategoryEntity) {
        // Load the category with seoUrls for proper URL generation
        $categoryCriteria = new Criteria([$category->getId()]);
        $categoryCriteria->addAssociation('seoUrls');

        $fullCategory = $this->categoryRepository->search($categoryCriteria, $context->getContext())->first();

        if ($fullCategory instanceof CategoryEntity) {
            $product->setSeoCategory($fullCategory);
            $this->loadBreadcrumbCategories($fullCategory, $page, $context->getContext());
        }
    }
}
```

**Step 2: Deploy the updated subscriber**

```bash
scp -r shopware-theme/RavenTheme root@77.42.19.154:/tmp/
ssh root@77.42.19.154 "docker cp /tmp/RavenTheme shopware-chf:/var/www/html/custom/plugins/"
ssh root@77.42.19.154 "docker exec shopware-chf bin/console cache:clear"
```

---

## Task 5: Verify the Fix

**Step 1: Navigate to Munition category**

Go to `https://ortak.ch/munition/`

**Step 2: Click on 223 Remington product**

Verify breadcrumb shows: `Home / Munition / 223 Remington FMJ 55grs 980m/s, 50 pcs.`

**Step 3: Click on Munition breadcrumb link**

Verify it navigates to `/munition/` without 404

**Step 4: Check other products**

- 300 AAC Blackout FMC
- .22 LR OFFICIAL
- Any Waffen products

---

## Summary

| Issue | Fix |
|-------|-----|
| Products without main_category | Script to set main_category from product_category |
| Phantom breadcrumb entries | Fixed by setting correct main_category |
| Broken breadcrumb links (404) | Resolved by proper category assignment |
| ProductDetailSubscriber edge cases | Fallback to product.categories when no main_category |
| Missing SEO URLs | Regenerated via Shopware built-in commands |
