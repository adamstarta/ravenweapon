# Dienstleistungen (Services) Category Design

**Date:** 2025-12-22
**Status:** Approved
**Author:** Claude Code

## Overview

Create a new "Dienstleistungen" (Services) category in the Raven Weapon shop for training/shooting courses, with proper category hierarchy, products, SEO URLs, and header navigation.

## Category Structure

```
Dienstleistungen (Level 1)
├── SEO URL: /dienstleistungen/
│
└── Schiesskurse (Level 2)
    ├── SEO URL: /dienstleistungen/schiesskurse/
    │
    ├── Basic-Kurse (Level 3)
    │   ├── SEO URL: /dienstleistungen/schiesskurse/basic-kurse/
    │   │
    │   ├── Basic-Kurs ────────── CHF 480 (1 Person)
    │   ├── Basic-Kurs-II ─────── CHF 800 (2 Personen)
    │   ├── Basic-Kurs-III ────── CHF 1'050 (3 Personen)
    │   └── Basic-Kurs-IV ─────── CHF 1'200 (4 Personen)
    │
    └── Privatunterricht (Level 3)
        ├── SEO URL: /dienstleistungen/schiesskurse/privatunterricht/
        │
        └── Instruktor-2-H ────── CHF 300 (INACTIVE)
```

## Products

| Product Number | Name (DE) | Price (CHF) | Category | Image | Active |
|----------------|-----------|-------------|----------|-------|--------|
| Basic-Kurs | Raven Basic-Kurs – Dein Einstieg in den Schiesssport | 480 | Basic-Kurse | S3 URL | Yes |
| Basic-Kurs-II | Raven Basic-Kurs – Dein Einstieg in den Schiesssport (2 Personen) | 800 | Basic-Kurse | S3 URL | Yes |
| Basic-Kurs-III | Raven Basic-Kurs – Dein Einstieg in den Schiesssport (3 Personen) | 1050 | Basic-Kurse | S3 URL | Yes |
| Basic-Kurs-IV | Raven Basic-Kurs – Dein Einstieg in den Schiesssport (4 Personen) | 1200 | Basic-Kurse | S3 URL | Yes |
| Instruktor-2-H | Instruktor 2 Stunden | 300 | Privatunterricht | Placeholder | No |

### Image Sources

- **Basic-Kurs:** https://makaris-prod-public.s3.ewstorage.ch/45684/ChatGPT-Image-29.-Okt.-2025%2C-15_02_48.png
- **Basic-Kurs-II:** https://makaris-prod-public.s3.ewstorage.ch/45685/ChatGPT-Image-29.-Okt.-2025%2C-15_06_14.png
- **Basic-Kurs-III:** https://makaris-prod-public.s3.ewstorage.ch/45686/ChatGPT-Image-29.-Okt.-2025%2C-15_08_34.png
- **Basic-Kurs-IV:** https://makaris-prod-public.s3.ewstorage.ch/45687/ChatGPT-Image-29.-Okt.-2025%2C-15_13_15.png
- **Instruktor-2-H:** Placeholder image (to be provided by client)

### Description (German)

All Basic-Kurs products share the same description:

```html
<p><strong>Dein Start mit der Raven - sicher, präzise, professionell.</strong><br>
In diesem kompakten Einsteigerkurs lernst du den sicheren Umgang, stabile Haltung und präzises Schießen mit der Raven.</p>
```

## SEO URLs

### Categories
| Category | SEO Path |
|----------|----------|
| Dienstleistungen | `/dienstleistungen/` |
| Schiesskurse | `/dienstleistungen/schiesskurse/` |
| Basic-Kurse | `/dienstleistungen/schiesskurse/basic-kurse/` |
| Privatunterricht | `/dienstleistungen/schiesskurse/privatunterricht/` |

### Products
| Product | SEO Path |
|---------|----------|
| Basic-Kurs | `/dienstleistungen/schiesskurse/basic-kurse/basic-kurs/` |
| Basic-Kurs-II | `/dienstleistungen/schiesskurse/basic-kurse/basic-kurs-2-personen/` |
| Basic-Kurs-III | `/dienstleistungen/schiesskurse/basic-kurse/basic-kurs-3-personen/` |
| Basic-Kurs-IV | `/dienstleistungen/schiesskurse/basic-kurse/basic-kurs-4-personen/` |
| Instruktor-2-H | `/dienstleistungen/schiesskurse/privatunterricht/instruktor-2-stunden/` |

## Breadcrumbs

Example for Basic-Kurs product:
```
Home > Dienstleistungen > Schiesskurse > Basic-Kurse > Basic-Kurs
```

## Header Navigation

Add "Dienstleistungen" to the main header navigation with dropdown:

```
[Dienstleistungen ▼]
├── Schiesskurse
│   ├── Basic-Kurse
│   └── Privatunterricht
```

Style: Same as existing dropdowns (Ausrüstung, Waffen, etc.)

## Technical Implementation

1. **Create categories** via Shopware Admin API or direct SQL
2. **Create products** via PHP import script (similar to existing import scripts)
3. **Download images** from S3 URLs and upload to Shopware media
4. **Generate SEO URLs** using existing `generate-product-seo-urls-fixed.php` pattern
5. **Update header template** (`header.html.twig`) to include new category
6. **Clear cache** and test

## Notes

- Instruktor-2-H is marked as inactive (active_b2c: 0) in source data - keep hidden
- All products are services (no physical shipping required)
- Stock management not needed for training courses (is_closeout: 0)
