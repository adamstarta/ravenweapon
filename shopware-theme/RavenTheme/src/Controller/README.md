# RavenTheme Custom Controllers

Shopware 6 storefront controllers for custom routes.

## ManufacturerPageController

Brand/manufacturer product listing pages.

### Routes

| Path | Name | Description |
|------|------|-------------|
| `/hersteller/{slug}` | `frontend.manufacturer.page` | Brand page with products |
| `/hersteller/{slug}/{subcategory}` | `frontend.manufacturer.subcategory` | Coming soon page |

### Features
- Loads products via `SalesChannelRepository` (respects CHF currency)
- Slugify/unslugify helpers for manufacturer name handling
- HTTP caching enabled (`_httpCache = true`)
- "Coming Soon" fallback for unknown manufacturers

### Template
`@RavenTheme/storefront/page/manufacturer/index.html.twig`

---

## LegalPagesController

Static legal pages with German routing.

### Routes

| Path | Name | Template |
|------|------|----------|
| `/agb` | `frontend.legal.agb` | Allgemeine Geschäftsbedingungen |
| `/impressum` | `frontend.legal.impressum` | Impressum |
| `/datenschutz` | `frontend.legal.datenschutz` | Datenschutzerklärung |
| `/kontakt` | `frontend.legal.kontakt` | Kontakt |

### Features
- All routes have `_httpCache = true`
- SEO disabled (`seo => false`) - static pages
- Templates in `@RavenTheme/storefront/page/legal/`

---

## Registration

Controllers are registered in `config/routes.xml` and `services.xml`.
