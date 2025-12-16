# Ausrüstung Category Restructuring Design

**Date:** 2025-12-16
**Status:** Approved
**Server:** ortak.ch (CHF)

## Problem Statement

The Ausrüstung category currently has 21 flat subcategories at Level 3, making navigation difficult for customers. In contrast, Raven Weapons has a well-organized hierarchical structure that groups related products logically.

## Current Structure

```
Ausrüstung (Level 2)
├── Ballistischer Schutz (21 products)
├── Beinpaneele (11 products)
├── Dienstausrüstung (6 products)
├── Gürtel (6 products)
├── Halter & Taschen (14 products)
├── K9 (6 products)
├── Medizinische Ausrüstung (27 products)
├── Multicam (7 products)
├── Patches (12 products)
├── Polizeiausrüstung (23 products)
├── Scharfschützen (9 products)
├── Source Hydration (24 products)
├── Taktische Ausrüstung (68 products)
├── Taktische Bekleidung (10 products)
├── Taschen & Rucksäcke (73 products)
├── Tragegurte & Holster (15 products)
├── Verdeckte (9 products)
├── Verschiedenes (5 products)
├── Verwaltung (12 products)
├── Warnschutz (6 products)
└── Westen & Chest Rigs (15 products)
```

**Total: 21 subcategories, 383 products**

## Proposed Structure

Group by **use-case** into 6 logical parent categories:

```
Ausrüstung (Level 2)
│
├── Taschen & Transport (Level 3) - 87 products
│   ├── Taschen & Rucksäcke (Level 4) - 73 products
│   └── Halter & Taschen (Level 4) - 14 products
│
├── Körperschutz (Level 3) - 40 products
│   ├── Ballistischer Schutz (Level 4) - 25 products
│   └── Westen & Chest Rigs (Level 4) - 15 products
│
├── Bekleidung & Tragen (Level 3) - 42 products
│   ├── Taktische Bekleidung (Level 4) - 10 products
│   ├── Gürtel (Level 4) - 6 products
│   ├── Tragegurte & Holster (Level 4) - 15 products
│   └── Beinpaneele (Level 4) - 11 products
│
├── Spezialausrüstung (Level 3) - 51 products
│   ├── Medizinische Ausrüstung (Level 4) - 27 products
│   ├── K9 (Level 4) - 6 products
│   ├── Scharfschützen (Level 4) - 9 products
│   └── Verdeckte (Level 4) - 9 products
│
├── Behörden & Dienst (Level 3) - 41 products
│   ├── Polizeiausrüstung (Level 4) - 23 products
│   ├── Verwaltung (Level 4) - 12 products
│   └── Dienstausrüstung (Level 4) - 6 products
│
└── Zubehör (Level 3) - 122 products
    ├── Taktische Ausrüstung (Level 4) - 68 products
    ├── Patches (Level 4) - 12 products
    ├── Source Hydration (Level 4) - 24 products
    ├── Verschiedenes (Level 4) - 5 products
    ├── Multicam (Level 4) - 7 products
    └── Warnschutz (Level 4) - 6 products
```

## Implementation Plan

### Phase 1: Backend - Create Parent Categories

Create 6 new Level 3 categories under Ausrüstung:

1. **Taschen & Transport** - Bags and carrying solutions
2. **Körperschutz** - Body protection/armor
3. **Bekleidung & Tragen** - Clothing and wearing gear
4. **Spezialausrüstung** - Specialized equipment
5. **Behörden & Dienst** - Law enforcement/service
6. **Zubehör** - Accessories and misc

### Phase 2: Backend - Move Subcategories

Update parent IDs for existing 21 categories to move them under new parents:

| Current Category | New Parent |
|-----------------|------------|
| Taschen & Rucksäcke | Taschen & Transport |
| Halter & Taschen | Taschen & Transport |
| Ballistischer Schutz | Körperschutz |
| Westen & Chest Rigs | Körperschutz |
| Taktische Bekleidung | Bekleidung & Tragen |
| Gürtel | Bekleidung & Tragen |
| Tragegurte & Holster | Bekleidung & Tragen |
| Beinpaneele | Bekleidung & Tragen |
| Medizinische Ausrüstung | Spezialausrüstung |
| K9 | Spezialausrüstung |
| Scharfschützen | Spezialausrüstung |
| Verdeckte | Spezialausrüstung |
| Polizeiausrüstung | Behörden & Dienst |
| Verwaltung | Behörden & Dienst |
| Dienstausrüstung | Behörden & Dienst |
| Taktische Ausrüstung | Zubehör |
| Patches | Zubehör |
| Source Hydration | Zubehör |
| Verschiedenes | Zubehör |
| Multicam | Zubehör |
| Warnschutz | Zubehör |

### Phase 3: Frontend - Update Mega Menu

Update `header.html.twig` to render hierarchical Ausrüstung menu:

- Parent categories show on first hover
- Subcategories show on second-level hover
- Match styling with Raven Weapons mega-menu

### Phase 4: SEO URLs

- Generate new SEO URLs for restructured categories
- Set up redirects from old URLs if needed

## Benefits

1. **Better Navigation** - Customers can find products faster
2. **Logical Grouping** - Related items grouped by use-case
3. **Consistent UX** - Matches Raven Weapons structure
4. **Scalable** - Easy to add new subcategories under appropriate parents

## Rollback Plan

If issues arise:
1. Keep backup of current category parent IDs
2. Script can reverse parent ID changes
3. Frontend changes are in theme (easy to revert via git)

## Scripts to Create

1. `restructure-ausrustung-categories.php` - Create parents and move subcategories
2. Update frontend mega-menu in `header.html.twig`
