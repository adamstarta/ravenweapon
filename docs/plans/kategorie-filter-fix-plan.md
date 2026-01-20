# Kategorie Filter Fix Plan

## Problem Description

The "Kategorie" (Category) filter on manufacturer pages (e.g., `/hersteller/snigel`) shows issues:

1. **Root categories appearing**: "Raven Shop AG" (level 1) and "Alle Produkte" (level 2) appear in the filter dropdown even though they shouldn't be filter options
2. **Swapped translations**: Category names were showing in English instead of German (fixed)

## Root Cause Analysis

### Issue 1: Swapped Translations (FIXED)
- 18 level 4 categories had their German and English translations swapped in the database
- German language field contained English text
- English language field contained German text

**Fix Applied**:
```sql
-- Swapped the translations back to correct languages
-- German field now has German text, English field has English text
```

### Issue 2: Root Categories in Filter
- Products are directly assigned to root categories:
  - "Raven Shop AG" (level 1): 3 products total (0 Snigel)
  - "Alle Produkte" (level 2): 333 products (196 Snigel)
- Shopware's filter aggregation shows ALL categories that products are assigned to
- The `disableEmptyFilterOptions` setting doesn't help because products ARE assigned to these categories

## Solution

### Template Override
Create a custom `filter-multi-select.html.twig` template that filters out root categories:

**File**: `shopware-theme/RavenTheme/src/Resources/views/storefront/component/listing/filter/filter-multi-select.html.twig`

```twig
{% sw_extends '@Storefront/storefront/component/listing/filter/filter-multi-select.html.twig' %}

{% block component_filter_multi_select_list %}
    <ul class="filter-multi-select-list" aria-label="{{ displayName }}">
        {% for element in elements %}
            {% set skipElement = false %}
            {% if name == 'categoryIds' or name == 'categories' %}
                {% set categoryName = element.translated.name|default('') %}
                {% if categoryName == 'Raven Shop AG' or categoryName == 'Alle Produkte' %}
                    {% set skipElement = true %}
                {% endif %}
            {% endif %}

            {% if not skipElement %}
                {# render the filter item #}
            {% endif %}
        {% endfor %}
    </ul>
{% endblock %}
```

## Deployment Steps

1. ✅ Fix swapped translations in database
2. ✅ Clear Shopware cache
3. ✅ Create custom filter-multi-select.html.twig template
4. ⬜ Commit and push to GitHub
5. ⬜ Wait for staging deployment
6. ⬜ Verify on staging (developing.ravenweapon.ch)
7. ⬜ Approve production deployment
8. ⬜ Verify on production (ravenweapon.ch)

## Database Changes Made

### Translations Swap Query
```sql
-- Created temp table with current values
CREATE TEMPORARY TABLE swap_translations AS
SELECT
    c.id AS category_id,
    MAX(CASE WHEN ct.language_id = 0x0191C12CC15E72189D57328FB3D2D987 THEN ct.name END) AS current_in_de_field,
    MAX(CASE WHEN ct.language_id = 0x2FBB5FE2E29A4D70AA5854CE7CE3E20B THEN ct.name END) AS current_in_en_field
FROM category c
JOIN category_translation ct ON c.id = ct.category_id
WHERE c.level = 4
GROUP BY c.id
HAVING current_in_de_field != current_in_en_field;

-- Swapped German and English values
UPDATE category_translation ct
JOIN swap_translations st ON ct.category_id = st.category_id
SET ct.name = st.current_in_en_field
WHERE ct.language_id = 0x0191C12CC15E72189D57328FB3D2D987;

UPDATE category_translation ct
JOIN swap_translations st ON ct.category_id = st.category_id
SET ct.name = st.current_in_de_field
WHERE ct.language_id = 0x2FBB5FE2E29A4D70AA5854CE7CE3E20B;
```

### Config Change
```sql
-- Enabled disableEmptyFilterOptions (didn't help with root categories but good to have)
UPDATE system_config
SET configuration_value = '{"_value": true}'
WHERE configuration_key = 'core.listing.disableEmptyFilterOptions';
```

## Categories Affected

### Level 4 Categories with Fixed Translations
| Category ID | Before (DE field) | After (DE field) |
|-------------|-------------------|------------------|
| 43276FB503438ABCCF6B54B791D18365 | Bags & Backpacks | Taschen & Rucksäcke |
| 4A6C7DC219E2E3DBA3B85339D267029B | Holders & Pouches | Halter & Taschen |
| 96E5CC0D8F63DF5C8A52A64CFDDAA2ED | Sniper Gear | Scharfschützen-Ausrüstung |
| B5DFC22FD3CA35C5E3258867E6B210C2 | Vests & Chest Rigs | Westen & Chest Rigs |
| 8B5F136ED349F74F355AEB74B5FF53E8 | Tactical Clothing | Taktische Bekleidung |
| F0530DB95D0E4F26CB93EA8D9816067A | Belts | Gürtel |
| EF3D2A1ADA565FF62689EC632597CE9F | Admin Gear | Verwaltungsausrüstung |
| B4A2175DCE5C7718A80824A6F3A59448 | Leg Panels | Beinpaneele |
| 38F38BBE87620624644240A85E7FC67E | K9 Gear | K9 Ausrüstung |
| 764CB07AD5319085C21CE15D1901EA0E | Medical Gear | Medizinische Ausrüstung |
| D7937596E40F37BFA8272780CB596A06 | Slings & Holsters | Tragegurte & Holster |
| 98C2913FA110D18B3F9B5A2ED78AA918 | Miscellaneous | Verschiedenes |
| 4F583DEC67F2540065EF06394C3B2129 | Police Gear | Polizeiausrüstung |
| BED321971D62163DBC89818BE4EA69C8 | Ballistic Protection | Ballistischer Schutz |
| 6152C0C2ABB52939CEFF12C49D530D41 | Covert Gear | Verdeckte Ausrüstung |
| F628BC7799276F8286C74FB3BE2063E2 | Duty Gear | Dienstausrüstung |
| B812FB4F768AC152AC2C8E7112E9BA0C | HighVis | Warnschutz |
| 442C809DB5F4F3BFFC45FEE26C3F78A5 | Tactical Gear | Taktische Ausrüstung |

## Testing Checklist

- [ ] Visit https://ravenweapon.ch/hersteller/snigel
- [ ] Click "Kategorie" filter
- [ ] Verify "Raven Shop AG" is NOT in the list
- [ ] Verify "Alle Produkte" is NOT in the list
- [ ] Verify categories show German names (Taschen & Rucksäcke, not Bags & Backpacks)
- [ ] Select a category filter and verify it works
- [ ] Test on another manufacturer page

## Created: 2026-01-20
