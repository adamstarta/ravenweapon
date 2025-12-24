# Mobile Menu Responsive Design

**Date:** 2025-12-21
**Status:** Approved for Implementation

## Overview

Implement a full-screen mobile menu with accordion submenus for RavenWeapon e-commerce site. The menu will be fully responsive and compatible with Safari/iOS.

## Design Decisions

| Decision | Choice |
|----------|--------|
| Menu Type | Full-screen overlay |
| Slide Direction | From RIGHT (where hamburger button is) |
| Submenu Behavior | Accordion (expand/collapse inline) |
| Visual Style | Light (white background) |

## Structure

```
┌─────────────────────────────────┐
│ [X Close]              MENU     │
├─────────────────────────────────┤
│  Startseite                     │
│  Waffen                    [▼]  │
│    ├─ Sturmgewehre              │
│    ├─ Raven Caliber Kit         │
│    └─ ...                       │
│  Ausrüstung                [▼]  │
│  Munition                  [▼]  │
│  Zubehör                   [▼]  │
├─────────────────────────────────┤
│  Mein Konto                     │
│  +41 79 356 19 86               │
│  info@ravenweapon.ch            │
└─────────────────────────────────┘
```

## Visual Styling

### Colors
- Background: #FFFFFF
- Backdrop: rgba(0, 0, 0, 0.5)
- Category text: #111827 (Inter 500, 16px)
- Subcategory text: #6B7280 (Inter 400, 14px)
- Active/Hover: #F2B90D (gold)
- Arrow icons: #9CA3AF

### Spacing
- Menu item height: 56px (touch-friendly)
- Subcategory height: 48px
- Padding: 24px horizontal
- Accordion indent: 16px

### Animations
- Menu slide: 0.3s ease
- Accordion expand: 0.25s ease
- Arrow rotation: 180deg
- Backdrop fade: 0.2s ease

## Safari/iOS Compatibility

1. Touch events with passive listeners
2. Safe area insets for notch/home bar
3. Body scroll lock when menu open
4. -webkit-overflow-scrolling: touch
5. Backdrop blur with fallback
6. Position sticky prefix

## Files to Modify

| File | Changes |
|------|---------|
| `header.html.twig` | Add mobile menu HTML + JS (~150 lines) |
| `base.scss` | Add mobile menu styles (~200 lines) |

## Protected (No Changes)

- raven-offcanvas-cart.plugin.js
- raven-toast.plugin.js
- Product detail page (zoom, variants)
- Desktop mega menu
- Live search autocomplete

## Testing Requirements

- [ ] iPhone Safari
- [ ] iPad Safari
- [ ] Android Chrome
- [ ] Desktop (menu hidden on md+)
- [ ] Cart functionality
- [ ] Search functionality
- [ ] Account dropdown
