# Shopware Homepage Redesign Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Redesign Shopware homepage using ONLY Shopware's built-in templates/components. No custom API. Keep all existing functionality (account, cart, checkout) working.

**Architecture:** Use Shopware's CMS "Shopping Experiences" to build homepage sections. Configure via Admin UI + minimal Twig overrides for styling. Reuse Shopware's existing product boxes, forms, and components.

**Tech Stack:** Shopware 6.6 CMS, Twig template inheritance, SCSS styling, Docker

**Key Principle:** We EXTEND Shopware templates, we don't REPLACE them. All Shopware features (login, cart, checkout, account) continue to work automatically.

---

### Task 1: Understand Shopware CMS Structure

**Step 1: Check current homepage CMS layout**

Navigate to: `http://localhost/admin`
Go to: Content → Shopping Experiences
Find: The layout assigned to "Storefront" sales channel

**Step 2: Identify what CMS blocks are available**

Shopware has built-in CMS blocks:
- `Image Banner` - for hero section
- `Product Slider` - for featured products
- `Image Gallery` - for brand logos
- `Text` - for any text content
- `Video` (YouTube/Vimeo) - for video section
- `Category Navigation` - for category links

**Note:** We will use these BUILT-IN blocks, configured via Admin UI.

---

### Task 2: Create Hero Section via CMS Admin

**Step 1: Open Shopping Experiences editor**

Navigate to: `http://localhost/admin#/sw/cms/index`
Click: Edit the homepage layout (or create new)

**Step 2: Add Image Banner block for Hero**

1. Add new section (full width)
2. Add block: "Image banner"
3. Configure:
   - Upload hero image (rifle background)
   - Add overlay text: "RAVEN WEAPON AG"
   - Add subtitle: "Eine Waffe für jede Mission, genau wie Du sie willst"
   - Link button to: `/search?search=raven`

**Step 3: Save and preview**

Click: Save
Click: Preview in storefront

Expected: Hero banner with image and text

---

### Task 3: Add Product Slider via CMS Admin

**Step 1: Add new section for products**

In CMS editor, add section below hero

**Step 2: Add Product Slider block**

1. Add block: "Product slider"
2. Configure:
   - Title: "Beliebte Produkte"
   - Product assignment: Manual selection OR Dynamic (bestsellers)
   - Select 6 products
   - Display: Grid, 3 columns

**Step 3: Save and preview**

Expected: Product grid with Shopware's built-in product boxes (with working Add to Cart!)

---

### Task 4: Add Category Teaser via CMS Admin

**Step 1: Add section for categories**

Add new section below products

**Step 2: Add Image/Text blocks for categories**

Option A - Use "Image" blocks:
1. Add 4 "Image" blocks side by side
2. Each links to category page
3. Add text overlay for category name

Option B - Use "Text" blocks with styling:
1. Add "Text" block
2. Use HTML for 4 category cards

**Step 3: Configure category links**

Link each to proper category navigation ID

---

### Task 5: Add Brand Logos via CMS Admin

**Step 1: Add section for brands**

Add new section below categories

**Step 2: Add Image Gallery or Image blocks**

1. Add "Image gallery" block
2. Upload brand logos: Snigel, ZeroTech, Magpul, Lockhart
3. Set display mode: Row, centered

**Step 3: Style with grayscale**

Add custom CSS class or inline style for grayscale effect

---

### Task 6: Add Video Section via CMS Admin

**Step 1: Add section for video**

Add new section below brands

**Step 2: Add Video block**

Note: Shopware's built-in video block supports YouTube/Vimeo.
For Cloudflare Stream, use "Text/HTML" block with iframe:

```html
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: center;">
    <div style="position: relative; padding-bottom: 56.25%; height: 0;">
        <iframe
            src="https://customer-zz0gro70wkcharf0.cloudflarestream.com/14b22c52b620a8e2d65ba9eb5b481bdb/iframe"
            style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; border-radius: 0.5rem;"
            allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;"
            allowfullscreen>
        </iframe>
    </div>
    <div>
        <h2>Video und Informationen</h2>
        <p>Beschreibungstext hier...</p>
    </div>
</div>
```

---

### Task 7: Upload Required Assets

**Step 1: Go to Media management**

Navigate to: `http://localhost/admin#/sw/media/index`

**Step 2: Upload hero image**

1. Create folder: "Homepage"
2. Upload: Hero background image (rifle)
3. Note the media URL

**Step 3: Upload brand logos**

Upload: Snigel, ZeroTech, Magpul, Lockhart logos

---

### Task 8: Assign CMS Layout to Storefront

**Step 1: Go to Sales Channels**

Navigate to: `http://localhost/admin#/sw/sales/channel/detail/0191c12dd4b970949e9aeec40433be3e`

**Step 2: Assign homepage layout**

1. Go to "Theme" or "Layout" section
2. Select the homepage CMS layout you created
3. Save

**Step 3: Clear cache**

Run:
```bash
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console cache:clear"
```

---

### Task 9: Style Adjustments via SCSS (If Needed)

**Files:**
- Modify: `base.scss` for any additional styling

**Step 1: Add CMS-specific styles**

Add to `temp-base.scss`:

```scss
// =============================================================================
// CMS HOMEPAGE STYLES
// =============================================================================

// Hero banner text
.cms-element-image-banner {
    .cms-element-image-banner-title {
        background: linear-gradient(135deg, #FDE047, #F59E0B, #D97706) !important;
        -webkit-background-clip: text !important;
        -webkit-text-fill-color: transparent !important;
        font-size: 3rem !important;
        font-weight: 800 !important;
    }
}

// Product slider gold buttons (already done in base.scss)

// Category cards hover effect
.cms-element-image:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

// Brand logos grayscale
.raven-brands img {
    filter: grayscale(100%);
    opacity: 0.7;
    transition: all 0.3s;

    &:hover {
        filter: grayscale(0%);
        opacity: 1;
    }
}
```

**Step 2: Copy and compile**

Run:
```bash
docker cp "C:\Users\alama\Desktop\NIKOLA WORK\ravenweapon\temp-base.scss" ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/app/storefront/src/scss/base.scss
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console theme:compile"
```

---

### Task 10: End-to-End Testing

**Step 1: Test homepage**

Navigate to: `http://localhost/`

Verify:
- [ ] Hero section displays
- [ ] Products show with working "Add to Cart" buttons
- [ ] Categories link correctly
- [ ] Brand logos display
- [ ] Video plays

**Step 2: Test existing functionality still works**

Navigate to: `http://localhost/account/login`
Verify: Login form works (Shopware component)

Navigate to: `http://localhost/checkout/cart`
Verify: Cart works (Shopware component)

Add product to cart from homepage
Verify: Product adds to cart correctly

**Step 3: Test mobile responsive**

Resize browser to mobile width
Verify: All sections responsive

---

## What We're NOT Building Custom:

| Feature | Approach |
|---------|----------|
| Login/Register | ✅ Use Shopware's built-in account pages |
| Cart | ✅ Use Shopware's built-in cart |
| Checkout | ✅ Use Shopware's built-in checkout |
| Product pages | ✅ Use Shopware's built-in product detail |
| Search | ✅ Use Shopware's built-in search |
| Add to Cart | ✅ Use Shopware's built-in AJAX cart |
| Product boxes | ✅ Use Shopware's CMS product slider |

## What We ARE Customizing:

| Feature | Approach |
|---------|----------|
| Homepage layout | Configure via CMS Admin |
| Colors/branding | SCSS overrides (already done) |
| Header | Twig template (already done) |
| Footer | Twig template (already done) |
| Hero section | CMS Image Banner block |

---

## Verification Checklist

- [ ] Homepage shows all 5 sections
- [ ] Product "Add to Cart" works (Shopware built-in)
- [ ] Login/Register works (Shopware built-in)
- [ ] Cart works (Shopware built-in)
- [ ] Checkout works (Shopware built-in)
- [ ] Search works (Shopware built-in)
- [ ] Mobile responsive
