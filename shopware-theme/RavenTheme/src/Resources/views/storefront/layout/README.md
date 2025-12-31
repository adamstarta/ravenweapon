# RavenTheme Layout Templates

Custom header, navigation, breadcrumb, and footer templates.

## Header (`header/header.html.twig`)

Full-width fixed header with custom navigation.

### Features
- **Fixed positioning** with body padding compensation (70px mobile, 140px desktop)
- **Full-width override** - removes Shopware's container constraints
- **Custom cart button** with gold gradient badge showing item count
- **Navigation link animations** - gold underline on hover
- **Mega menu dropdowns** via `nav-dropdown.html.twig`

### Key Blocks
| Block | Purpose |
|-------|---------|
| `layout_header_actions_cart` | Custom cart button with badge |
| `layout_header` | Full header with CSS and structure |

### CSS Classes
- `.raven-header` - Main header container
- `.raven-nav-link` - Navigation links with hover animation
- `.raven-cart-badge` - Gold gradient item count badge

---

## Navigation Dropdown (`header/nav-dropdown.html.twig`)

Mega menu dropdown with two-column layout.

### Structure
```
┌─────────────────────────────────────────┐
│  LEFT COLUMN      │   RIGHT COLUMN      │
│  Subcategories    │   Grandchildren OR  │
│  (clickable)      │   Products          │
└─────────────────────────────────────────┘
```

### Logic
- **Has grandchildren:** Shows grid of grandchild category links
- **No grandchildren:** Shows products from `header.extensions.navigationProducts`

### Data Source
Products loaded by `NavigationProductsSubscriber` (max 8 per category).

---

## Breadcrumb (`breadcrumb.html.twig`)

Category/product breadcrumb navigation.

### Features
- Uses Shopware's `sw_breadcrumb_full()` function
- Filters SEO URLs from category's `seoUrls` association
- Fallback URL building when SEO URLs unavailable
- German umlaut handling (ä→ae, ö→oe, ü→ue)

### URL Resolution Order
1. Check category's `seoUrls` association for canonical URL
2. Fallback to `seoUrl()` function
3. If still `/navigation/` URL, build from slugified path

### Styling
- Font: Inter, 0.875rem
- Links: #666, hover #333
- Current item: #333, font-weight 500
- Separator: `/` in #999

---

## Footer (`footer/footer.html.twig`)

Custom footer with newsletter, contact info, and payment icons.

---

## Meta (`meta.html.twig`)

Custom meta tags and SEO configuration.

---

## How to Modify

1. Always use `{% sw_extends %}` to extend base templates
2. After changes: `bin/console theme:compile && cache:clear`
3. Test on staging before production
4. Check mobile responsiveness (breakpoint: 768px)

### Common Gotchas
- Body `padding-top` must match header height
- Navigation depth is set to 5 levels for RAPAX categories
- Mega menu JS is in `header.html.twig` inline `<script>`
