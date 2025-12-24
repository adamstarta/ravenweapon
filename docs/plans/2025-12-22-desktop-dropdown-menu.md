# Desktop Navigation Dropdown - Thoron Style

**Date:** 2025-12-22
**Status:** Approved for Implementation
**Scope:** Desktop only (mobile menu unchanged)

## Overview

Implement a full-width cascading dropdown menu for desktop navigation, similar to thoron.ch. When hovering on a nav item with children, a large dropdown appears below the navigation bar with 2 columns.

## Design Specifications

### Layout
```
┌──────────────────────────────────────────────────────────────────────────────┐
│  LEFT COLUMN (300px)          │  RIGHT COLUMN (flex)                         │
│  ─────────────────────────    │  ─────────────────────────                   │
│  Subcategory 1            ›   │  Sub-subcategory A                           │
│  Subcategory 2            ›   │  Sub-subcategory B                           │
│  Subcategory 3                │  Sub-subcategory C                           │
│  Subcategory 4            ›   │  Sub-subcategory D                           │
│                               │  → Alle anzeigen                             │
└──────────────────────────────────────────────────────────────────────────────┘
```

### Dimensions
| Property | Value |
|----------|-------|
| Dropdown width | 100% (max-width: 1400px, centered) |
| Dropdown max-height | 500px |
| Left column width | 300px |
| Right column width | flex (remaining space) |
| Item height | 44px (touch-friendly) |
| Padding | 24px |

### Colors (Raven Theme)
| Element | Color |
|---------|-------|
| Background | #FFFFFF |
| Border | #E5E7EB |
| Shadow | 0 10px 40px rgba(0,0,0,0.15) |
| Category text | #374151 |
| Category hover | #111827 + background #F9FAFB |
| Arrow icon | #9CA3AF |
| Active/hover gold | #F2B90D |

### Typography
| Element | Style |
|---------|-------|
| Category name | Inter 500, 14px |
| Subcategory name | Inter 400, 14px |
| Header (if any) | Inter 600, 11px, uppercase |

### Behavior
1. **Hover on nav item** → Dropdown appears (0.2s fade)
2. **Hover on left category with arrow** → Right column shows subcategories
3. **Mouse leaves dropdown** → Dropdown hides (0.15s delay for UX)
4. **Click on category** → Navigate to category page

### Arrow Indicator
- Show `›` arrow only if category has children
- Arrow color: #9CA3AF, hover: #374151

## Files to Modify

| File | Changes |
|------|---------|
| `header.html.twig` | Replace current mega-menu with new dropdown structure |
| `base.scss` | Add dropdown styles (or inline in header) |

## HTML Structure

```html
<div class="raven-nav-item">
    <a href="/kategorie" class="raven-nav-link">Zubehör</a>

    <div class="raven-dropdown">
        <div class="raven-dropdown-inner">
            <!-- Left Column: Subcategories -->
            <div class="raven-dropdown-left">
                <a href="/sub1" class="raven-dropdown-item has-children" data-submenu="sub1">
                    <span>Zielfernrohre</span>
                    <svg>›</svg>
                </a>
                <a href="/sub2" class="raven-dropdown-item">
                    <span>Spektive</span>
                </a>
            </div>

            <!-- Right Column: Sub-subcategories -->
            <div class="raven-dropdown-right">
                <div class="raven-submenu" data-submenu-id="sub1">
                    <a href="/sub1/a">1-4x Zielfernrohre</a>
                    <a href="/sub1/b">1-6x Zielfernrohre</a>
                    <a href="/sub1" class="view-all">Alle Zielfernrohre →</a>
                </div>
            </div>
        </div>
    </div>
</div>
```

## JavaScript Behavior

```javascript
// Hover on left item with children → show corresponding right submenu
document.querySelectorAll('.raven-dropdown-item.has-children').forEach(item => {
    item.addEventListener('mouseenter', function() {
        // Hide all submenus
        document.querySelectorAll('.raven-submenu').forEach(s => s.classList.remove('active'));
        // Show this item's submenu
        const submenuId = this.dataset.submenu;
        document.querySelector(`[data-submenu-id="${submenuId}"]`).classList.add('active');
    });
});
```

## Protected (No Changes)
- Mobile menu (already implemented)
- Search autocomplete
- Cart offcanvas
- Account dropdown

## Implementation Notes

1. Use Shopware's category tree from `page.header.navigation.tree`
2. Check `category.children.count > 0` to show arrow
3. Desktop only: wrap in `@media (min-width: 992px)`
4. Keep existing hover delay for smooth UX

## Refined Requirements (2025-12-22 Update)

### Dropdown Type Logic

| Category | Has Grandchildren? | Dropdown Type |
|----------|-------------------|---------------|
| Waffen | Yes (RAPAX, Caracal have children) | Two-column thoron-style |
| Zielhilfen & Zubehör | No | Simple list |
| Zubehör | No | Simple list |
| Ausrüstung | Yes (all have children) | Two-column thoron-style |

### Special Case: Subcategories with Products (No Grandchildren)

For subcategories inside a two-column dropdown that have NO level 4 categories but DO have products (e.g., "Raven Weapons" inside Waffen):

**Solution:** Show a category preview card in the right column:
- Category name as title
- "Alle [X] Produkte anzeigen" button
- Styled card to maintain visual balance

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  LEFT COLUMN                     │  RIGHT COLUMN                             │
│  ─────────────────────────────   │  ─────────────────────────────            │
│  Raven Weapons                   │  ┌─────────────────────────────┐          │
│  RAPAX                       ›   │  │  Raven Weapons              │          │
│  Caracal Lynx                ›   │  │                             │          │
│                                  │  │  [Alle Produkte anzeigen →] │          │
│                                  │  └─────────────────────────────┘          │
└──────────────────────────────────────────────────────────────────────────────┘
```

When hovering on RAPAX or Caracal Lynx (which have grandchildren), the right column shows their sub-subcategories as before.

## Testing
- [x] Hover shows dropdown smoothly
- [x] Submenu appears on hover (left column items with children)
- [x] Click navigates to category
- [x] Dropdown hides when mouse leaves
- [x] No layout shift or flicker
- [x] Works on all desktop breakpoints (992px+)
- [ ] Subcategories without grandchildren show category preview card
- [ ] Waffen dropdown: Raven Weapons shows preview card, RAPAX/Caracal show grandchildren
