# Raven Weapon Shopware Twig UI/UX Conversion Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Convert the design reference UI/UX from static HTML to Shopware 6 Twig templates, creating a full shop experience with dynamic products, styled product pages, cart, checkout, and account area.

**Architecture:** Override Shopware's default Storefront Twig templates in the RavenTheme plugin using `{% sw_extends %}` pattern. Keep all Shopware functionality (cart, checkout, account) while applying custom styling and layout from design reference. Products loaded dynamically from Shopware database.

**Tech Stack:** Shopware 6.6, Twig, SCSS, Docker (dockware/dev:6.6.0.0)

**Design Reference:** `C:\Users\alama\Desktop\Raven Website testing\ravenweapon\`

**Working Directory:** `C:\Users\alama\Desktop\NIKOLA WORK\ravenweapon\`

**Shopware Admin:** http://localhost/admin (admin / shopware)

---

## Phase 1: Foundation & Header/Footer

### Task 1: Copy Design Assets to Theme

**Files:**
- Copy from: `C:\Users\alama\Desktop\Raven Website testing\ravenweapon\assets\*`
- Copy to: `shopware-theme\RavenTheme\src\Resources\public\assets\`

**Step 1: Copy all product images and logos**

```bash
# From project root
docker exec ravenweapon-shop bash -c "rm -rf /var/www/html/custom/plugins/RavenTheme/src/Resources/public/assets/*"
```

Copy these files manually or via docker cp:
- `raven-logo-bold (1)-stroke-and-fill.svg` → `logo.svg`
- `wallpaper hero.png` → `hero-background.jpg`
- `snigel logo.png` → `brand-snigel.png`
- `zero tech logo.png` → `brand-zerotech.png`
- `magpul logo.png` → `brand-magpul.png`
- `lockhart logo.png` → `brand-lockhart.png`
- All weapon images: `300 AAC RAVEN.png`, `5.56 RAVEN.png`, `7.62×39 RAVEN.png`, `9mm RAVEN.png`, `.22 RAVEN.png`
- All caliber kit images: `9mm CALIBER KIT.png`, `.223 CALIBER KIT.png`, `7.62x39 CALIBER KIT.png`, `.22LR CALIBER KIT.png`, `300 AAC CALIBER KIT.png`

**Step 2: Rebuild theme assets**

```bash
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console assets:install && bin/console cache:clear"
```

**Step 3: Verify assets accessible**

Open browser: `http://localhost/bundles/raventheme/assets/logo.svg`
Expected: Logo SVG displays

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/public/assets/
git commit -m "feat: add design reference assets to RavenTheme"
```

---

### Task 2: Update Header Template with Design Reference Styling

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig`

**Step 1: Update header.html.twig with design reference layout**

Replace entire file with:

```twig
{% sw_extends '@Storefront/storefront/layout/header/header.html.twig' %}

{% block layout_header %}
<header class="raven-header">
    {# Mobile Header #}
    <div class="raven-header-mobile md:hidden bg-white relative z-50">
        <div class="max-w-7xl mx-auto px-6 py-2.5">
            <div class="flex items-center justify-between h-[52px]">
                <a href="{{ path('frontend.home.page') }}" class="inline-flex items-center h-10 w-[182px]" aria-label="Raven Weapon home">
                    <img src="{{ asset('bundles/raventheme/assets/logo.svg') }}" alt="Raven Weapon" class="h-10 w-auto">
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ path('frontend.account.login.page') }}" class="raven-icon-btn" aria-label="Account">
                        {% sw_icon 'avatar' style { 'size': 'sm' } %}
                    </a>
                    <a href="{{ path('frontend.checkout.cart.page') }}" class="raven-icon-btn relative" aria-label="Warenkorb">
                        {% sw_icon 'bag' style { 'size': 'sm' } %}
                        {% if page.cart.lineItems.count > 0 %}
                        <span class="raven-cart-badge">{{ page.cart.lineItems.count }}</span>
                        {% endif %}
                    </a>
                    <button class="raven-icon-btn js-offcanvas-menu-toggle" data-offcanvas-menu="true" aria-label="Menu">
                        {% sw_icon 'stack' style { 'size': 'sm' } %}
                    </button>
                </div>
            </div>
        </div>
        {# Mobile Search #}
        <div class="bg-white border-b border-gray-200 px-6 py-3">
            <form action="{{ path('frontend.search.page') }}" method="get" class="relative">
                <input type="search" name="search" placeholder="Suche" class="raven-search-input w-full">
                <button type="submit" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                    {% sw_icon 'search' style { 'size': 'xs' } %}
                </button>
            </form>
        </div>
    </div>

    {# Desktop Header #}
    <div class="raven-header-desktop hidden md:block bg-white border-b border-gray-200 pt-3">
        <div class="max-w-7xl mx-auto px-6 lg:px-24">
            {# Top Row: Logo, Search, Icons #}
            <div class="flex items-center justify-between h-[52px] py-2.5">
                <a href="{{ path('frontend.home.page') }}" class="inline-flex items-center h-10 w-[222px]" aria-label="Raven Weapon home">
                    <img src="{{ asset('bundles/raventheme/assets/logo.svg') }}" alt="Raven Weapon" class="h-10 w-auto">
                </a>
                <form action="{{ path('frontend.search.page') }}" method="get" class="relative w-[300px]">
                    <input type="search" name="search" placeholder="Suche" class="raven-search-input w-full h-9">
                    <button type="submit" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                        {% sw_icon 'search' style { 'size': 'xs' } %}
                    </button>
                </form>
                <div class="flex items-center gap-2">
                    <a href="{{ path('frontend.account.login.page') }}" class="raven-icon-btn" aria-label="Account">
                        {% sw_icon 'avatar' style { 'size': 'sm' } %}
                    </a>
                    <a href="{{ path('frontend.checkout.cart.page') }}" class="raven-icon-btn relative" aria-label="Warenkorb">
                        {% sw_icon 'bag' style { 'size': 'sm' } %}
                        {% if page.cart.lineItems.count > 0 %}
                        <span class="raven-cart-badge">{{ page.cart.lineItems.count }}</span>
                        {% endif %}
                    </a>
                </div>
            </div>
            {# Navigation Row #}
            <nav class="pb-2">
                <ul class="flex items-center justify-center gap-5">
                    <li><a href="{{ path('frontend.home.page') }}" class="raven-nav-link {% if page.header.navigation.active.id == page.header.navigation.tree.first.category.id %}active{% endif %}">Startseite</a></li>
                    {% for treeItem in page.header.navigation.tree %}
                    <li><a href="{{ seoUrl('frontend.navigation.page', { navigationId: treeItem.category.id }) }}" class="raven-nav-link">{{ treeItem.category.translated.name }}</a></li>
                    {% endfor %}
                    <li><a href="{{ path('frontend.cms.page', { id: 'about-us' }) }}" class="raven-nav-link">Über uns</a></li>
                    <li><a href="{{ path('frontend.cms.page', { id: 'contact' }) }}" class="raven-nav-link">Kontakt</a></li>
                </ul>
            </nav>
        </div>
    </div>
</header>
{% endblock %}
```

**Step 2: Copy to Docker container**

```bash
docker cp shopware-theme/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/header/
```

**Step 3: Clear cache and verify**

```bash
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console cache:clear"
```

Open: http://localhost/
Expected: Header shows logo, search bar, navigation links

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/layout/header/
git commit -m "feat: update header with design reference styling"
```

---

### Task 3: Update Footer Template with Design Reference Styling

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/views/storefront/layout/footer/footer.html.twig`

**Step 1: Update footer.html.twig**

Replace entire file with:

```twig
{% sw_extends '@Storefront/storefront/layout/footer/footer.html.twig' %}

{% block layout_footer %}
<footer class="raven-footer mt-12 border-t border-gray-200 bg-white">
    {# Trust Badges #}
    <div class="max-w-7xl mx-auto px-6 lg:px-24 py-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="raven-trust-badge">
                <span class="raven-trust-icon bg-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-full h-full">
                        <rect width="32" height="32" fill="#FF0000"/>
                        <path d="M13 6h6v7h7v6h-7v7h-6v-7H6v-6h7V6z" fill="white"/>
                    </svg>
                </span>
                <div>
                    <div class="font-semibold text-gray-900">Schweizer Qualität</div>
                    <div class="text-sm text-gray-600">Produktion in Luzern</div>
                </div>
            </div>
            <div class="raven-trust-badge">
                <span class="raven-trust-icon bg-gray-800 text-white">
                    {% sw_icon 'tags' style { 'size': 'sm' } %}
                </span>
                <div>
                    <div class="font-semibold text-gray-900">Beste Preise</div>
                    <div class="text-sm text-gray-600">Direktverkauf = tiefe Preise</div>
                </div>
            </div>
            <div class="raven-trust-badge">
                <span class="raven-trust-icon bg-gray-800 text-white">
                    {% sw_icon 'package' style { 'size': 'sm' } %}
                </span>
                <div>
                    <div class="font-semibold text-gray-900">Versand heute</div>
                    <div class="text-sm text-gray-600">Mo–Fr, bis 19:20 Uhr bestellt</div>
                </div>
            </div>
            <div class="raven-trust-badge">
                <span class="raven-trust-icon bg-gray-800 text-white">
                    {% sw_icon 'phone' style { 'size': 'sm' } %}
                </span>
                <div>
                    <div class="font-semibold text-gray-900">+41 79 356 19 86</div>
                    <div class="text-sm text-gray-600">frei, freundlich, kompetent</div>
                </div>
            </div>
        </div>
    </div>

    {# Newsletter Gold Bar #}
    <div class="raven-newsletter-bar">
        <div class="max-w-7xl mx-auto px-6 lg:px-24 py-6 md:py-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-black/70 text-white">
                        {% sw_icon 'mail' style { 'size': 'sm' } %}
                    </span>
                    <div>
                        <div class="text-xl md:text-2xl font-semibold text-black" style="font-family: 'Chakra Petch', sans-serif;">Newsletter</div>
                        <div class="text-black/80 text-sm">Jetzt anmelden und profitieren</div>
                    </div>
                </div>
                <form action="{{ path('frontend.form.contact.send') }}" method="post" class="w-full md:w-auto">
                    <div class="flex rounded-md overflow-hidden shadow-sm">
                        <input type="email" name="email" placeholder="Ihre E-Mail" required class="w-full md:w-[420px] px-4 py-2 text-gray-900 placeholder-gray-600 bg-white outline-none">
                        <button type="submit" class="px-4 md:px-6 py-2 bg-black text-white font-semibold">Abonnieren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {# Footer Links #}
    <div class="max-w-7xl mx-auto px-6 lg:px-24 py-10">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 tracking-wide mb-3">DIE MARKE</h3>
                <p class="text-gray-700 text-sm">RAVEN WEAPON AG</p>
                <p class="text-gray-700 text-sm mt-2">Höchste Qualität</p>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-900 tracking-wide mb-3">DAS UNTERNEHMEN</h3>
                <ul class="text-sm text-gray-700 space-y-1">
                    <li>Raven Weapon AG</li>
                    <li>Sunnenbergstrasse 2</li>
                    <li>8633 Wolfhausen</li>
                    <li><a href="mailto:info@ravenweapon.ch" class="hover:text-gray-900">info@ravenweapon.ch</a></li>
                    <li><a href="tel:+41793561986" class="hover:text-gray-900">+41 79 356 19 86</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-900 tracking-wide mb-3">NÜTZLICHE LINKS</h3>
                <ul class="text-sm text-gray-700 space-y-2">
                    <li><a href="{{ path('frontend.cms.page', { id: 'contact' }) }}" class="hover:text-gray-900">Kontakt</a></li>
                    <li><a href="{{ path('frontend.cms.page', { id: 'versand' }) }}" class="hover:text-gray-900">Versand</a></li>
                    <li><a href="{{ path('frontend.cms.page', { id: 'faq' }) }}" class="hover:text-gray-900">FAQ</a></li>
                    <li><a href="{{ path('frontend.cms.page', { id: 'agb' }) }}" class="hover:text-gray-900">AGB</a></li>
                    <li><a href="{{ path('frontend.cms.page', { id: 'impressum' }) }}" class="hover:text-gray-900">Impressum</a></li>
                    <li><a href="{{ path('frontend.cms.page', { id: 'datenschutz' }) }}" class="hover:text-gray-900">Datenschutz</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-900 tracking-wide mb-3">BELIEBTE KATEGORIEN</h3>
                <ul class="text-sm text-gray-700 space-y-2">
                    {% for treeItem in page.header.navigation.tree|slice(0, 4) %}
                    <li><a href="{{ seoUrl('frontend.navigation.page', { navigationId: treeItem.category.id }) }}" class="hover:text-gray-900">{{ treeItem.category.translated.name }}</a></li>
                    {% endfor %}
                </ul>
            </div>
        </div>
    </div>

    {# Copyright #}
    <div class="border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-6 lg:px-24 py-6 text-sm text-gray-500 text-center">
            © {{ "now"|date("Y") }} RAVEN WEAPON AG. Alle Rechte vorbehalten.
        </div>
    </div>
</footer>
{% endblock %}
```

**Step 2: Copy to Docker and clear cache**

```bash
docker cp shopware-theme/RavenTheme/src/Resources/views/storefront/layout/footer/footer.html.twig ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/footer/
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console cache:clear"
```

**Step 3: Verify footer displays**

Open: http://localhost/
Expected: Footer shows trust badges, gold newsletter bar, footer links

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/layout/footer/
git commit -m "feat: update footer with design reference styling"
```

---

### Task 4: Update SCSS with Design Reference Styles

**Files:**
- Modify: `shopware-theme/RavenTheme/src/Resources/app/storefront/src/scss/base.scss`

**Step 1: Update base.scss with complete design reference styles**

Replace entire file with:

```scss
// Google Fonts
@import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap');

// Variables
$gold-gradient: linear-gradient(to top, #F2B90D 12%, #F6CE55 88%);
$gold-border: #C59A0F;
$price-red: #E53935;
$avail-green: #77C15A;
$ruler-gold: #E9D68A;

// Base
body {
    font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
    -webkit-font-smoothing: antialiased;
}

// Utility classes (Tailwind-like)
.hidden { display: none !important; }
@media (min-width: 768px) {
    .md\:hidden { display: none !important; }
    .md\:block { display: block !important; }
    .md\:flex { display: flex !important; }
}

// Header styles
.raven-header {
    position: relative;
    z-index: 50;
}

.raven-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 38px;
    width: 38px;
    border-radius: 0.375rem;
    border: 1px solid #d1d5db;
    color: #4b5563;
    transition: background-color 0.2s;

    &:hover {
        background-color: #f9fafb;
    }
}

.raven-cart-badge {
    position: absolute;
    top: -0.5rem;
    right: -0.5rem;
    background: $gold-gradient;
    color: black;
    font-size: 10px;
    font-weight: 700;
    width: 20px;
    height: 20px;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.raven-search-input {
    border-radius: 9999px;
    background: rgba(120, 120, 128, 0.16);
    padding: 0.5rem 1rem 0.5rem 2.25rem;
    font-size: 17px;
    line-height: 22px;
    outline: none;

    &:focus {
        ring: 2px solid #d1d5db;
    }
}

.raven-nav-link {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    padding: 0.5rem 1.25rem 0.5rem 0;
    transition: color 0.2s;

    &:hover, &.active {
        color: #111827;
    }
}

// Hero Section
.raven-hero {
    position: relative;
    width: 100%;
    overflow: hidden;
    min-height: 520px;
    padding: 4rem 0;

    @media (min-width: 1024px) {
        min-height: 640px;
        padding: 5rem 0;
    }
}

.raven-hero-title {
    font-family: 'Chakra Petch', sans-serif;
    font-size: clamp(2rem, 5vw, 4rem);
    font-weight: 700;
    background: $gold-gradient;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

// Buttons
.raven-btn-primary {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1.25rem;
    background: $gold-gradient;
    color: #1f2937;
    font-weight: 600;
    border-radius: 0.5rem;
    text-decoration: none;
    border: 1px solid $gold-border;
    box-shadow: 0 2px 6px rgba(0,0,0,0.25);
    transition: box-shadow 0.2s;

    &:hover {
        box-shadow: 0 4px 10px rgba(0,0,0,0.35);
    }
}

.raven-btn-outline {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    background: white;
    color: #414651;
    font-weight: 600;
    border-radius: 0.5rem;
    text-decoration: none;
    border: 1px solid #d1d5db;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: box-shadow 0.2s;

    &:hover {
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
}

// Product Grid
.raven-product-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;

    @media (min-width: 640px) {
        grid-template-columns: repeat(2, 1fr);
    }

    @media (min-width: 768px) {
        grid-template-columns: repeat(3, 1fr);
    }
}

// Product Card
.raven-product-card {
    transition: box-shadow 0.3s, transform 0.3s;

    &:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
}

.raven-product-img-wrap {
    height: 14rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;

    @media (min-width: 768px) { height: 16rem; }
    @media (min-width: 1024px) { height: 18rem; }

    img {
        max-height: 100%;
        max-width: 100%;
        object-fit: contain;
        padding: 0.75rem;
        transition: transform 0.3s ease-out;
    }

    &:hover img {
        transform: scale(1.05);
    }
}

.raven-product-category {
    font-size: 12px;
    line-height: 16px;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.raven-product-price {
    font-family: 'Inter', sans-serif;
    color: $price-red;
    font-weight: 600;
    font-size: 16px;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}

.raven-product-title {
    font-size: 1rem;
    color: #1f2937;
    margin-top: 0.25rem;

    .brand {
        font-weight: 700;
    }
}

.raven-avail-dot {
    width: 20px;
    height: 20px;
    border-radius: 9999px;
    background: $avail-green;
    display: inline-flex;
    align-items: center;
    justify-content: center;

    svg {
        width: 12px;
        height: 12px;
    }
}

.raven-cart-btn {
    width: 40px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    color: #1f2937;
    transition: background-color 0.2s;

    &:hover {
        background-color: #f3f4f6;
    }

    svg {
        width: 24px;
        height: 24px;
    }
}

// Gold Ruler Separators (between product columns)
.raven-product-grid-ruled {
    --ruler-color: #{$ruler-gold};
    --grid-gap: 2rem;

    @media (min-width: 640px) {
        .raven-product-card:not(:nth-child(2n))::after {
            content: '';
            position: absolute;
            right: calc(var(--grid-gap) / -2);
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--ruler-color);
        }
    }

    @media (min-width: 768px) {
        .raven-product-card:not(:nth-child(3n))::after {
            content: '';
            position: absolute;
            right: calc(var(--grid-gap) / -2);
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--ruler-color);
        }
    }
}

// Category Cards
.raven-category-card {
    display: block;
    padding: 1.25rem;
    text-align: center;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    background: white;
    text-decoration: none;
    transition: box-shadow 0.2s, transform 0.2s;

    &:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
}

.raven-category-icon {
    display: inline-flex;
    width: 3rem;
    height: 3rem;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    border-radius: 0.5rem;
    margin-bottom: 0.75rem;
    color: #374151;
}

// Footer
.raven-footer {
    margin-top: 3rem;
    border-top: 1px solid #e5e7eb;
    background: white;
}

.raven-trust-badge {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    border-radius: 0.375rem;
    border: 1px solid #e5e7eb;
    background: white;
    padding: 1rem;
}

.raven-trust-icon {
    display: inline-flex;
    height: 2.25rem;
    width: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    overflow: hidden;
}

.raven-newsletter-bar {
    background: linear-gradient(to right, #fde047, #f59e0b, #fde047);
}

// Section Headings
.raven-section-title {
    font-family: 'Chakra Petch', sans-serif;
    font-size: clamp(2rem, 4vw, 3.5rem);
    font-weight: 400;
    color: #111827;
    line-height: 1.1;
}

// Override Shopware defaults
.product-box {
    border-radius: 0.5rem;
    overflow: hidden;
}

.btn-primary {
    background: $gold-gradient !important;
    color: #1f2937 !important;
    border: 1px solid $gold-border !important;
}

.btn-outline-primary {
    border-color: $gold-border !important;
    color: #1f2937 !important;

    &:hover {
        background: $gold-gradient !important;
    }
}
```

**Step 2: Copy SCSS and compile theme**

```bash
docker cp shopware-theme/RavenTheme/src/Resources/app/storefront/src/scss/base.scss ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/app/storefront/src/scss/
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console theme:compile && bin/console cache:clear"
```

**Step 3: Verify styles applied**

Open: http://localhost/
Expected: Gold buttons, proper fonts, styled elements

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/app/storefront/src/scss/
git commit -m "feat: add comprehensive SCSS styles from design reference"
```

---

## Phase 2: Homepage with Dynamic Products

### Task 5: Create Homepage Template with Dynamic Products

**Files:**
- Create: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/content/index.html.twig`

**Step 1: Create the homepage template**

```twig
{% sw_extends '@Storefront/storefront/page/content/index.html.twig' %}

{% block base_main_inner %}
<main class="raven-homepage">

    {# HERO SECTION #}
    <section class="raven-hero relative w-full overflow-hidden" style="min-height: 520px; padding: 4rem 0;">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('bundles/raventheme/assets/hero-background.jpg') }}');"></div>
        <div class="absolute inset-0" style="background: linear-gradient(to bottom, rgba(0,0,0,0.25), rgba(0,0,0,0.55), rgba(0,0,0,0.65));"></div>

        <div class="relative z-10 max-w-7xl mx-auto px-6 lg:px-24 text-white">
            <h1 class="raven-hero-title mb-3">RAVEN WEAPON AG</h1>
            <h2 style="font-family: 'Chakra Petch', sans-serif; font-size: clamp(1.25rem, 3vw, 2.5rem); font-weight: 400; color: white; margin-bottom: 1.5rem; max-width: 42rem;">
                Eine Waffe für jede Mission,<br>genau wie Du sie willst
            </h2>
            <ul style="font-family: 'Chakra Petch', sans-serif; font-size: clamp(1rem, 2vw, 1.75rem); font-weight: 600; list-style: none; padding-left: 2rem; margin-bottom: 2.5rem;">
                <li class="relative mb-1" style="padding-left: 1.5rem;"><span class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-2 bg-white rounded-full"></span>Schweizer Familienbetrieb</li>
                <li class="relative mb-1" style="padding-left: 1.5rem;"><span class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-2 bg-white rounded-full"></span>Made in Canada</li>
                <li class="relative" style="padding-left: 1.5rem;"><span class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-2 bg-white rounded-full"></span>Höchste Qualität</li>
            </ul>
            <div class="flex flex-wrap gap-6">
                <a href="{{ path('frontend.navigation.page', { navigationId: page.header.navigation.active.id }) }}" class="raven-btn-primary">
                    {% sw_icon 'bag' style { 'size': 'sm' } %}
                    <span class="ml-2">Zum Shop</span>
                </a>
                <a href="{{ path('frontend.cms.page', { id: 'about-us' }) }}" class="raven-btn-outline">
                    Über uns
                    <svg class="w-4 h-4 ml-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"></path></svg>
                </a>
            </div>
        </div>
    </section>

    {# PRODUCTS SECTION - Dynamic from Shopware #}
    <section class="raven-products bg-white py-12 md:py-16">
        <div class="max-w-7xl mx-auto px-6 lg:px-24">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-10">
                <h2 class="raven-section-title">Beliebte Produkte</h2>
                <a href="{{ path('frontend.navigation.page', { navigationId: page.header.navigation.active.id }) }}" class="raven-btn-primary">
                    {% sw_icon 'bag' style { 'size': 'sm' } %}
                    <span class="ml-2">Alle Produkte ansehen</span>
                </a>
            </div>

            {# Fetch products from Shopware #}
            {% set products = searchResult.elements|default([]) %}
            {% if products|length > 0 %}
            <div class="raven-product-grid">
                {% for product in products|slice(0, 6) %}
                <article class="raven-product-card relative">
                    <a href="{{ seoUrl('frontend.detail.page', { productId: product.id }) }}" class="block">
                        <div class="raven-product-img-wrap">
                            {% if product.cover.media %}
                            <img src="{{ product.cover.media.url }}" alt="{{ product.translated.name }}" loading="lazy">
                            {% else %}
                            <img src="{{ asset('bundles/raventheme/assets/placeholder.png') }}" alt="{{ product.translated.name }}">
                            {% endif %}
                        </div>
                    </a>
                    <div class="px-4 py-3">
                        <p class="raven-product-category">
                            {% if product.categories|first %}{{ product.categories|first.translated.name }}{% else %}Produkt{% endif %}
                        </p>
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1">
                                <div class="raven-product-price">{{ product.calculatedPrice.unitPrice|currency }}</div>
                                <div class="raven-product-title">
                                    <span class="brand">{{ product.manufacturer.translated.name|default('Raven') }}</span> {{ product.translated.name }}
                                </div>
                            </div>
                            <div class="flex flex-col gap-1 items-center -mt-2">
                                {% if product.availableStock > 0 %}
                                <span class="raven-avail-dot" title="Verfügbar">
                                    <svg viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                {% endif %}
                                <form action="{{ path('frontend.checkout.line-item.add') }}" method="post">
                                    {{ sw_csrf('frontend.checkout.line-item.add') }}
                                    <input type="hidden" name="lineItems[{{ product.id }}][id]" value="{{ product.id }}">
                                    <input type="hidden" name="lineItems[{{ product.id }}][type]" value="product">
                                    <input type="hidden" name="lineItems[{{ product.id }}][referencedId]" value="{{ product.id }}">
                                    <input type="hidden" name="lineItems[{{ product.id }}][quantity]" value="1">
                                    <input type="hidden" name="redirectTo" value="frontend.home.page">
                                    <button type="submit" class="raven-cart-btn" aria-label="In den Warenkorb">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M0 72C0 58.7 10.7 48 24 48L69.3 48C96.4 48 119.6 67.4 124.4 94L124.8 96L524.7 96C549.8 96 568.7 118.9 564 143.6L537.6 280.6C529.6 322 493.4 352 451.2 352L171.4 352L176.5 380.3C178.6 391.7 188.5 400 200.1 400L456 400C469.3 400 480 410.7 480 424C480 437.3 469.3 448 456 448L200.1 448C165.3 448 135.5 423.1 129.3 388.9L77.2 102.6C76.5 98.8 73.2 96 69.3 96L24 96C10.7 96 0 85.3 0 72zM162.6 304L451.2 304C470.4 304 486.9 290.4 490.5 271.6L514.9 144L133.5 144L162.6 304zM208 480C234.5 480 256 501.5 256 528C256 554.5 234.5 576 208 576C181.5 576 160 554.5 160 528C160 501.5 181.5 480 208 480zM432 480C458.5 480 480 501.5 480 528C480 554.5 458.5 576 432 576C405.5 576 384 554.5 384 528C384 501.5 405.5 480 432 480z"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </article>
                {% endfor %}
            </div>
            {% else %}
            {# Fallback: Show placeholder if no products in database #}
            <div class="text-center py-12 text-gray-500">
                <p>Keine Produkte gefunden. Bitte fügen Sie Produkte im Shopware Admin hinzu.</p>
                <a href="/admin" class="raven-btn-primary mt-4">Zum Admin</a>
            </div>
            {% endif %}
        </div>
    </section>

    {# CATEGORIES SECTION #}
    <section class="raven-categories bg-white py-12 md:py-16">
        <div class="max-w-7xl mx-auto px-6 lg:px-24">
            <h2 class="raven-section-title mb-8">Kategorien</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                {% for treeItem in page.header.navigation.tree|slice(0, 4) %}
                <a href="{{ seoUrl('frontend.navigation.page', { navigationId: treeItem.category.id }) }}" class="raven-category-card">
                    <span class="raven-category-icon">
                        {% sw_icon 'products' style { 'size': 'md' } %}
                    </span>
                    <div class="font-semibold text-gray-900">{{ treeItem.category.translated.name }}</div>
                    <div class="text-sm text-gray-600 mt-1">{{ treeItem.category.translated.description|striptags|slice(0, 40)|default('Produkte ansehen') }}...</div>
                </a>
                {% else %}
                {# Static fallback categories #}
                <a href="#" class="raven-category-card">
                    <span class="raven-category-icon">{% sw_icon 'products' %}</span>
                    <div class="font-semibold text-gray-900">Waffen</div>
                    <div class="text-sm text-gray-600 mt-1">Präzisionswaffen für jeden Einsatz</div>
                </a>
                <a href="#" class="raven-category-card">
                    <span class="raven-category-icon">{% sw_icon 'products' %}</span>
                    <div class="font-semibold text-gray-900">Munition</div>
                    <div class="text-sm text-gray-600 mt-1">Kaliber 9mm, .223, 7.62x39 & mehr</div>
                </a>
                <a href="#" class="raven-category-card">
                    <span class="raven-category-icon">{% sw_icon 'products' %}</span>
                    <div class="font-semibold text-gray-900">Waffenzubehör</div>
                    <div class="text-sm text-gray-600 mt-1">Optiken, Magazine & Anbauteile</div>
                </a>
                <a href="#" class="raven-category-card">
                    <span class="raven-category-icon">{% sw_icon 'products' %}</span>
                    <div class="font-semibold text-gray-900">Ausrüstung</div>
                    <div class="text-sm text-gray-600 mt-1">Taktisches Zubehör & Schutzausrüstung</div>
                </a>
                {% endfor %}
            </div>
        </div>
    </section>

    {# BRANDS SECTION #}
    <section class="raven-brands bg-white py-12 md:py-16">
        <div class="max-w-7xl mx-auto px-6 lg:px-24">
            <h2 class="raven-section-title mb-8">Unsere Marken</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="border border-gray-200 rounded-lg h-28 md:h-36 flex items-center justify-center bg-white" style="filter: grayscale(100%); opacity: 0.7; transition: all 0.3s;" onmouseover="this.style.filter='grayscale(0%)'; this.style.opacity='1';" onmouseout="this.style.filter='grayscale(100%)'; this.style.opacity='0.7';">
                    <img src="{{ asset('bundles/raventheme/assets/brand-snigel.png') }}" alt="Snigel" class="max-h-full max-w-full object-contain p-3">
                </div>
                <div class="border border-gray-200 rounded-lg h-28 md:h-36 flex items-center justify-center bg-white" style="filter: grayscale(100%); opacity: 0.7; transition: all 0.3s;" onmouseover="this.style.filter='grayscale(0%)'; this.style.opacity='1';" onmouseout="this.style.filter='grayscale(100%)'; this.style.opacity='0.7';">
                    <img src="{{ asset('bundles/raventheme/assets/brand-zerotech.png') }}" alt="ZeroTech" class="max-h-full max-w-full object-contain p-1">
                </div>
                <div class="border border-gray-200 rounded-lg h-28 md:h-36 flex items-center justify-center bg-white" style="filter: grayscale(100%); opacity: 0.7; transition: all 0.3s;" onmouseover="this.style.filter='grayscale(0%)'; this.style.opacity='1';" onmouseout="this.style.filter='grayscale(100%)'; this.style.opacity='0.7';">
                    <img src="{{ asset('bundles/raventheme/assets/brand-magpul.png') }}" alt="Magpul" class="max-h-full max-w-full object-contain p-4">
                </div>
                <div class="border border-gray-200 rounded-lg h-28 md:h-36 flex items-center justify-center bg-white" style="filter: grayscale(100%); opacity: 0.7; transition: all 0.3s;" onmouseover="this.style.filter='grayscale(0%)'; this.style.opacity='1';" onmouseout="this.style.filter='grayscale(100%)'; this.style.opacity='0.7';">
                    <img src="{{ asset('bundles/raventheme/assets/brand-lockhart.png') }}" alt="Lockhart Tactical" class="max-h-full max-w-full object-contain p-3">
                </div>
            </div>
        </div>
    </section>

    {# VIDEO SECTION #}
    <section class="raven-video bg-white py-12 md:py-16">
        <div class="max-w-7xl mx-auto px-6 lg:px-24">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-start">
                <div class="rounded-lg overflow-hidden" style="position: relative; padding-top: 56.25%;">
                    <iframe src="https://customer-zz0gro70wkcharf0.cloudflarestream.com/14b22c52b620a8e2d65ba9eb5b481bdb/iframe?poster=https%3A%2F%2Fcustomer-zz0gro70wkcharf0.cloudflarestream.com%2F14b22c52b620a8e2d65ba9eb5b481bdb%2Fthumbnails%2Fthumbnail.jpg%3Ftime%3D%26height%3D600" loading="lazy" style="border: none; position: absolute; top: 0; left: 0; height: 100%; width: 100%;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen="true"></iframe>
                </div>
                <div>
                    <h2 class="raven-section-title mb-4" style="font-size: clamp(1.5rem, 3vw, 2.5rem);">Video und Informationen</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">Fügen Sie hier einen kurzen, klaren Beschreibungstext ein und integrieren Sie relevante Keywords, um Ihre Sichtbarkeit bei Google zu verbessern.</p>
                    <ul class="list-disc list-inside text-gray-700 space-y-2">
                        <li>Keyword 1 – Nutzenpunkt</li>
                        <li>Keyword 2 – Nutzenpunkt</li>
                        <li>Keyword 3 – Nutzenpunkt</li>
                        <li>Keyword 4 – Nutzenpunkt</li>
                        <li>Keyword 5 – Nutzenpunkt</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

</main>
{% endblock %}
```

**Step 2: Create directory and copy to Docker**

```bash
mkdir -p shopware-theme/RavenTheme/src/Resources/views/storefront/page/content/
docker cp shopware-theme/RavenTheme/src/Resources/views/storefront/page/content/index.html.twig ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/content/
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console cache:clear"
```

**Step 3: Verify homepage displays**

Open: http://localhost/
Expected: Full homepage with hero, products section (may show "no products" message), categories, brands, video

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/content/
git commit -m "feat: add homepage template with dynamic products"
```

---

### Task 6: Add Products via Shopware Admin

**Step 1: Login to Shopware Admin**

Open: http://localhost/admin
Login: admin / shopware

**Step 2: Create Products**

Navigate to: Catalogues > Products > Add product

Create these products (matching design reference):

| Name | Price | Stock | Active |
|------|-------|-------|--------|
| Lockhart Tactical 300 AAC RAVEN | 2985 CHF | 10 | Yes |
| Lockhart Tactical .223 RAVEN | 2985 CHF | 10 | Yes |
| Lockhart Tactical 7.62×39 RAVEN | 2985 CHF | 10 | Yes |
| Lockhart Tactical 9mm RAVEN | 2985 CHF | 10 | Yes |
| Lockhart Tactical .22 RAVEN | 2985 CHF | 10 | Yes |
| Lockhart Tactical 9mm CALIBER KIT | 1685 CHF | 10 | Yes |

For each product:
1. Add product name
2. Set price (gross)
3. Set stock > 0
4. Upload product image from `assets/` folder
5. Set manufacturer: "Lockhart Tactical" (create if needed)
6. Assign to category: "Waffen" or "Zubehör"
7. Save

**Step 3: Verify products show on homepage**

Open: http://localhost/
Expected: Products display in grid with images, prices, add-to-cart buttons

**Step 4: Document completion**

No git commit needed for admin configuration.

---

## Phase 3: Product Detail Page with Color Variants

### Task 7: Create Product Detail Page Template

**Files:**
- Create: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig`

**Step 1: Create product detail template**

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}

{% block page_product_detail %}
<div class="raven-product-detail max-w-7xl mx-auto px-6 lg:px-24 py-8 md:py-12">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12">
        {# Product Image Gallery #}
        <div class="raven-product-gallery">
            <div class="raven-product-main-image bg-white rounded-lg p-4 mb-4">
                {% if page.product.cover.media %}
                <img src="{{ page.product.cover.media.url }}" alt="{{ page.product.translated.name }}" class="w-full h-auto object-contain" id="mainProductImage">
                {% else %}
                <img src="{{ asset('bundles/raventheme/assets/placeholder.png') }}" alt="{{ page.product.translated.name }}" class="w-full h-auto object-contain">
                {% endif %}
            </div>

            {# Thumbnail gallery #}
            {% if page.product.media|length > 1 %}
            <div class="grid grid-cols-4 gap-2">
                {% for media in page.product.media %}
                <button class="border border-gray-200 rounded-lg p-2 hover:border-amber-500 transition" onclick="document.getElementById('mainProductImage').src='{{ media.media.url }}'">
                    <img src="{{ media.media.url }}" alt="" class="w-full h-auto object-contain">
                </button>
                {% endfor %}
            </div>
            {% endif %}
        </div>

        {# Product Info #}
        <div class="raven-product-info">
            <p class="text-sm text-gray-500 mb-2">
                {% if page.product.categories|first %}{{ page.product.categories|first.translated.name }}{% endif %}
            </p>

            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4" style="font-family: 'Chakra Petch', sans-serif;">
                {{ page.product.translated.name }}
            </h1>

            <div class="raven-product-price text-3xl font-bold mb-6" style="color: #E53935;">
                {{ page.product.calculatedPrice.unitPrice|currency }}
            </div>

            {# Availability #}
            <div class="flex items-center gap-2 mb-6">
                {% if page.product.availableStock > 0 %}
                <span class="raven-avail-dot">
                    <svg viewBox="0 0 24 24" fill="none" class="w-3 h-3"><path d="M5 13l4 4L19 7" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="text-green-600 font-medium">Verfügbar</span>
                {% else %}
                <span class="text-red-600 font-medium">Nicht verfügbar</span>
                {% endif %}
            </div>

            {# Color Variants (if product has variants) #}
            {% if page.product.options|length > 0 %}
            <div class="mb-6">
                <p class="font-semibold text-gray-900 mb-3">Farbe wählen:</p>
                <div class="flex flex-wrap gap-2">
                    {% for option in page.product.options %}
                    <button class="w-10 h-10 rounded-full border-2 border-gray-300 hover:border-amber-500 transition" style="background-color: {{ option.colorHexCode|default('#2C2C2C') }};" title="{{ option.translated.name }}"></button>
                    {% endfor %}
                </div>
            </div>
            {% endif %}

            {# Quantity & Add to Cart #}
            <form action="{{ path('frontend.checkout.line-item.add') }}" method="post" class="mb-8">
                {{ sw_csrf('frontend.checkout.line-item.add') }}
                <input type="hidden" name="lineItems[{{ page.product.id }}][id]" value="{{ page.product.id }}">
                <input type="hidden" name="lineItems[{{ page.product.id }}][type]" value="product">
                <input type="hidden" name="lineItems[{{ page.product.id }}][referencedId]" value="{{ page.product.id }}">
                <input type="hidden" name="redirectTo" value="frontend.detail.page">
                <input type="hidden" name="redirectParameters[productId]" value="{{ page.product.id }}">

                <div class="flex items-center gap-4 mb-4">
                    <label class="font-semibold text-gray-900">Menge:</label>
                    <div class="flex items-center border border-gray-300 rounded-lg">
                        <button type="button" onclick="this.nextElementSibling.stepDown(); this.nextElementSibling.dispatchEvent(new Event('change'));" class="px-3 py-2 text-gray-600 hover:bg-gray-100">−</button>
                        <input type="number" name="lineItems[{{ page.product.id }}][quantity]" value="1" min="1" max="{{ page.product.availableStock }}" class="w-16 text-center border-x border-gray-300 py-2 outline-none">
                        <button type="button" onclick="this.previousElementSibling.stepUp(); this.previousElementSibling.dispatchEvent(new Event('change'));" class="px-3 py-2 text-gray-600 hover:bg-gray-100">+</button>
                    </div>
                </div>

                <button type="submit" class="raven-btn-primary w-full justify-center py-3 text-lg">
                    {% sw_icon 'bag' style { 'size': 'sm' } %}
                    <span class="ml-2">In den Warenkorb</span>
                </button>
            </form>

            {# Product Description #}
            {% if page.product.translated.description %}
            <div class="border-t border-gray-200 pt-6">
                <h3 class="font-semibold text-gray-900 mb-3">Beschreibung</h3>
                <div class="prose text-gray-700">
                    {{ page.product.translated.description|raw }}
                </div>
            </div>
            {% endif %}
        </div>
    </div>
</div>
{% endblock %}
```

**Step 2: Create directory and copy to Docker**

```bash
mkdir -p shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/
docker cp shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/product-detail/
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console cache:clear"
```

**Step 3: Verify product detail page**

Click any product on homepage
Expected: Product detail page with image, price, quantity selector, add-to-cart button

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/
git commit -m "feat: add product detail page with design reference styling"
```

---

## Phase 4: Cart & Checkout Styling

### Task 8: Style Cart Page

**Files:**
- Create: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/cart/index.html.twig`

**Step 1: Create cart page template**

```twig
{% sw_extends '@Storefront/storefront/page/checkout/cart/index.html.twig' %}

{% block page_checkout_cart %}
<div class="raven-cart max-w-7xl mx-auto px-6 lg:px-24 py-8 md:py-12">
    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-8" style="font-family: 'Chakra Petch', sans-serif;">
        Warenkorb
    </h1>

    {% if page.cart.lineItems.count > 0 %}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {# Cart Items #}
        <div class="lg:col-span-2">
            {% for lineItem in page.cart.lineItems %}
            <div class="flex gap-4 py-6 border-b border-gray-200">
                <div class="w-24 h-24 bg-white rounded-lg flex-shrink-0">
                    {% if lineItem.cover %}
                    <img src="{{ lineItem.cover.url }}" alt="{{ lineItem.label }}" class="w-full h-full object-contain p-2">
                    {% endif %}
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900">{{ lineItem.label }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ lineItem.payload.productNumber|default('') }}</p>
                    <div class="flex items-center justify-between mt-4">
                        <form action="{{ path('frontend.checkout.line-item.change-quantity', { id: lineItem.id }) }}" method="post" class="flex items-center">
                            {{ sw_csrf('frontend.checkout.line-item.change-quantity') }}
                            <input type="hidden" name="redirectTo" value="frontend.checkout.cart.page">
                            <select name="quantity" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-1 text-sm">
                                {% for i in 1..10 %}
                                <option value="{{ i }}" {% if i == lineItem.quantity %}selected{% endif %}>{{ i }}</option>
                                {% endfor %}
                            </select>
                        </form>
                        <form action="{{ path('frontend.checkout.line-item.delete', { id: lineItem.id }) }}" method="post">
                            {{ sw_csrf('frontend.checkout.line-item.delete') }}
                            <input type="hidden" name="redirectTo" value="frontend.checkout.cart.page">
                            <button type="submit" class="text-red-600 text-sm hover:underline">Entfernen</button>
                        </form>
                    </div>
                </div>
                <div class="text-right">
                    <span class="font-bold text-gray-900">{{ lineItem.price.totalPrice|currency }}</span>
                </div>
            </div>
            {% endfor %}
        </div>

        {# Order Summary #}
        <div class="lg:col-span-1">
            <div class="bg-gray-50 rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Bestellübersicht</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Zwischensumme</span>
                        <span class="font-medium">{{ page.cart.price.netPrice|currency }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">MwSt.</span>
                        <span class="font-medium">{{ (page.cart.price.totalPrice - page.cart.price.netPrice)|currency }}</span>
                    </div>
                    <div class="border-t border-gray-200 pt-3 flex justify-between">
                        <span class="font-semibold text-gray-900">Gesamt</span>
                        <span class="font-bold text-xl" style="color: #E53935;">{{ page.cart.price.totalPrice|currency }}</span>
                    </div>
                </div>
                <a href="{{ path('frontend.checkout.confirm.page') }}" class="raven-btn-primary w-full justify-center py-3 mt-6">
                    Zur Kasse
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <a href="{{ path('frontend.home.page') }}" class="block text-center text-gray-600 text-sm mt-4 hover:underline">
                    Weiter einkaufen
                </a>
            </div>
        </div>
    </div>
    {% else %}
    <div class="text-center py-16">
        <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
        </svg>
        <h2 class="text-xl font-semibold text-gray-900 mb-2">Ihr Warenkorb ist leer</h2>
        <p class="text-gray-500 mb-6">Fügen Sie Produkte hinzu, um fortzufahren</p>
        <a href="{{ path('frontend.home.page') }}" class="raven-btn-primary">
            Zum Shop
        </a>
    </div>
    {% endif %}
</div>
{% endblock %}
```

**Step 2: Create directory and copy to Docker**

```bash
mkdir -p shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/cart/
docker cp shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/cart/index.html.twig ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/checkout/cart/
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console cache:clear"
```

**Step 3: Verify cart page**

Add product to cart, click cart icon
Expected: Styled cart page with items, summary, checkout button

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/cart/
git commit -m "feat: add styled cart page"
```

---

### Task 9: Style Checkout Confirm Page

**Files:**
- Create: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/index.html.twig`

**Step 1: Create checkout confirm template**

This task extends Shopware's checkout confirm page with custom styling while keeping all payment/shipping functionality:

```twig
{% sw_extends '@Storefront/storefront/page/checkout/confirm/index.html.twig' %}

{% block page_checkout_confirm %}
<div class="raven-checkout max-w-7xl mx-auto px-6 lg:px-24 py-8 md:py-12">
    {{ parent() }}
</div>
{% endblock %}

{% block page_checkout_confirm_header %}
<h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-8" style="font-family: 'Chakra Petch', sans-serif;">
    Bestellung abschließen
</h1>
{% endblock %}

{% block page_checkout_confirm_submit %}
<button type="submit" class="raven-btn-primary w-full justify-center py-3 text-lg" form="confirmOrderForm">
    Zahlungspflichtig bestellen
</button>
{% endblock %}
```

**Step 2: Create directory and copy to Docker**

```bash
mkdir -p shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/
docker cp shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/index.html.twig ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console cache:clear"
```

**Step 3: Verify checkout page**

Go through checkout flow
Expected: Checkout page with gold submit button, proper styling

**Step 4: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/confirm/
git commit -m "feat: add styled checkout confirm page"
```

---

## Phase 5: Account Area Styling

### Task 10: Style Account Pages

**Files:**
- Create: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/index.html.twig`

**Step 1: Create account page template**

```twig
{% sw_extends '@Storefront/storefront/page/account/index.html.twig' %}

{% block page_account %}
<div class="raven-account max-w-7xl mx-auto px-6 lg:px-24 py-8 md:py-12">
    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-8" style="font-family: 'Chakra Petch', sans-serif;">
        Mein Konto
    </h1>
    {{ parent() }}
</div>
{% endblock %}

{% block page_account_sidebar_menu %}
<nav class="raven-account-nav mb-8 lg:mb-0">
    <ul class="space-y-2">
        <li><a href="{{ path('frontend.account.home.page') }}" class="block px-4 py-2 rounded-lg {% if page.metaInformation.canonical == path('frontend.account.home.page') %}bg-amber-100 text-amber-800{% else %}hover:bg-gray-100{% endif %}">Übersicht</a></li>
        <li><a href="{{ path('frontend.account.profile.page') }}" class="block px-4 py-2 rounded-lg {% if page.metaInformation.canonical == path('frontend.account.profile.page') %}bg-amber-100 text-amber-800{% else %}hover:bg-gray-100{% endif %}">Profil</a></li>
        <li><a href="{{ path('frontend.account.address.page') }}" class="block px-4 py-2 rounded-lg {% if page.metaInformation.canonical == path('frontend.account.address.page') %}bg-amber-100 text-amber-800{% else %}hover:bg-gray-100{% endif %}">Adressen</a></li>
        <li><a href="{{ path('frontend.account.payment.page') }}" class="block px-4 py-2 rounded-lg {% if page.metaInformation.canonical == path('frontend.account.payment.page') %}bg-amber-100 text-amber-800{% else %}hover:bg-gray-100{% endif %}">Zahlungsarten</a></li>
        <li><a href="{{ path('frontend.account.order.page') }}" class="block px-4 py-2 rounded-lg {% if page.metaInformation.canonical == path('frontend.account.order.page') %}bg-amber-100 text-amber-800{% else %}hover:bg-gray-100{% endif %}">Bestellungen</a></li>
    </ul>
</nav>
{% endblock %}
```

**Step 2: Create login page template**

Create: `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/login/index.html.twig`

```twig
{% sw_extends '@Storefront/storefront/page/account/login/index.html.twig' %}

{% block page_account_login %}
<div class="raven-login max-w-md mx-auto px-6 py-12 md:py-16">
    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 text-center mb-8" style="font-family: 'Chakra Petch', sans-serif;">
        Anmelden
    </h1>
    {{ parent() }}
</div>
{% endblock %}

{% block page_account_login_form_submit %}
<button type="submit" class="raven-btn-primary w-full justify-center py-3">
    Anmelden
</button>
{% endblock %}

{% block page_account_login_register_card %}
<div class="mt-8 text-center">
    <p class="text-gray-600 mb-4">Noch kein Konto?</p>
    <a href="{{ path('frontend.account.login.page') }}#register" class="raven-btn-outline">
        Jetzt registrieren
    </a>
</div>
{% endblock %}
```

**Step 3: Create directories and copy to Docker**

```bash
mkdir -p shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/
mkdir -p shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/login/
docker cp shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/index.html.twig ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/account/
docker cp shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/login/index.html.twig ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/account/login/
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console cache:clear"
```

**Step 4: Verify account pages**

Click Account icon in header
Expected: Styled login page with gold button

**Step 5: Commit**

```bash
git add shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/
git commit -m "feat: add styled account pages"
```

---

## Phase 6: Final Verification

### Task 11: Full Flow Test with Playwright

**Step 1: Run comprehensive test**

Create test script and run via Playwright to verify:
1. Homepage loads with products
2. Product detail page shows correctly
3. Add to cart works
4. Cart page displays items
5. Checkout flow accessible
6. Login page styled

**Step 2: Take screenshots**

Capture screenshots of each page for documentation.

**Step 3: Final commit**

```bash
git add .
git commit -m "feat: complete Raven Weapon Shopware theme UI/UX conversion"
```

---

## Summary

| Phase | Tasks | Description |
|-------|-------|-------------|
| 1 | 1-4 | Foundation: Assets, Header, Footer, SCSS |
| 2 | 5-6 | Homepage with dynamic products |
| 3 | 7 | Product detail page with variants |
| 4 | 8-9 | Cart & Checkout styling |
| 5 | 10 | Account area styling |
| 6 | 11 | Final verification |

**Total Tasks:** 11
**Estimated Time:** 2-4 hours

**Key Files:**
- `shopware-theme/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig`
- `shopware-theme/RavenTheme/src/Resources/views/storefront/layout/footer/footer.html.twig`
- `shopware-theme/RavenTheme/src/Resources/views/storefront/page/content/index.html.twig`
- `shopware-theme/RavenTheme/src/Resources/views/storefront/page/product-detail/index.html.twig`
- `shopware-theme/RavenTheme/src/Resources/views/storefront/page/checkout/cart/index.html.twig`
- `shopware-theme/RavenTheme/src/Resources/views/storefront/page/account/index.html.twig`
- `shopware-theme/RavenTheme/src/Resources/app/storefront/src/scss/base.scss`
