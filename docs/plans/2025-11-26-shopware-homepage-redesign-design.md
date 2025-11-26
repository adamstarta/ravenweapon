# Shopware Homepage Redesign - Design Document

**Date:** 2025-11-26
**Status:** Approved
**Approach:** Custom Twig Templates (Code-based)

## Overview

Redesign the Shopware Twig storefront (`localhost/`) to match the custom `/raven/` design pixel-perfect. All changes made via code (Twig templates + SCSS), no CMS drag-and-drop.

## Homepage Sections (Top to Bottom)

### 1. Hero Banner
- **Background:** Rifle image from /raven/ design
- **Content:**
  - "RAVEN WEAPON AG" title (gold gradient)
  - Tagline: "Eine Waffe für jede Mission, genau wie Du sie willst"
  - Bullet points: Schweizer Familienbetrieb, Made in Canada, Höchste Qualität
  - 2 CTA buttons: "Zum Shop" (gold) + "Über uns" (outline)

### 2. Product Grid
- **Heading:** "Beliebte Produkte"
- **Products:** 6 featured products from Shopware
- **Layout:** 3 columns on desktop, 2 on tablet, 1 on mobile
- **Style:** Gold "Add to cart" buttons, price display

### 3. Category Cards
- **Heading:** "Kategorien"
- **Categories:**
  1. Waffen - Präzisionswaffen für jeden Einsatz
  2. Munition - Kaliber 9mm, .223, 7.62×39 & mehr
  3. Waffenzubehör - Optiken, Magazine & Anbauteile
  4. Ausrüstung - Taktisches Zubehör & Schutzausrüstung
- **Layout:** 4 columns on desktop, 2 on tablet/mobile

### 4. Brand Logos
- **Heading:** "Unsere Marken"
- **Brands:** Snigel, ZeroTech, Magpul, Lockhart Tactical
- **Layout:** Horizontal row, responsive

### 5. Video Section
- **Layout:** Video left, text right (or stacked on mobile)
- **Video:** Cloudflare Stream embed
  - URL: `https://customer-zz0gro70wkcharf0.cloudflarestream.com/14b22c52b620a8e2d65ba9eb5b481bdb/iframe`
- **Text:** "Video und Informationen" + description + bullet points

### 6. Footer (Already Implemented)
- Trust badges (Swiss Quality, Best Prices, Fast Shipping, Phone)
- Newsletter subscription bar (gold gradient)
- Copyright + legal links

## Technical Architecture

```
custom/plugins/RavenTheme/src/Resources/
├── views/storefront/
│   ├── page/content/
│   │   └── index.html.twig         # Homepage override
│   └── layout/
│       ├── header/header.html.twig  # Already done
│       └── footer/footer.html.twig  # Already done
└── app/storefront/src/scss/
    ├── base.scss                    # Already has gold buttons
    └── _homepage.scss               # New homepage styles
```

## Assets Required

Copy from /raven/ or existing sources:
- Hero background image (rifle)
- Category icons (SVG)
- Brand logos (Snigel, ZeroTech, Magpul, Lockhart)

## Success Criteria

- [ ] Homepage matches /raven/ design visually
- [ ] All 6 sections render correctly
- [ ] Mobile responsive
- [ ] Products load from Shopware database
- [ ] Add to cart works
- [ ] Links navigate correctly
- [ ] Video plays
