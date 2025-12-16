# Snigel Color Variants Design

## Overview

Add real color variant selection to Snigel products with:
- Left-side thumbnail gallery (all images, scrollable)
- Right-side color selector buttons (dynamic from data)
- Cart integration (selected color persists to checkout)

## User Requirements

1. **Thumbnail Gallery (Left)**
   - Show ALL images: gallery + color variant images combined
   - Vertical layout, scrollable if more than 5-6 images
   - Clicking thumbnail changes main product image only

2. **Color Selector (Right)**
   - Show color buttons based on product's actual colors
   - Clicking color button changes main image + sets cart color
   - Single-color products also show 1 button for consistency

3. **Cart Integration**
   - Selected color stored in cart line item
   - Displayed throughout cart → checkout flow
   - Format: "Farbe: Black"

## Technical Design

### Data Storage

Custom fields on Snigel products:

```json
snigel_color_options: [
  { "name": "Black", "imageFilename": "52-00506-01-000.jpg" },
  { "name": "Grey", "imageFilename": "52-00506-01-000_DEFAULT.jpg" }
]

snigel_has_colors: true/false
```

### Data Source

Color data from scraper JSON (`products-with-variants.json`):
- `colorOptions` array with name + imageUrl
- `hasColorVariants` boolean flag

Filename pattern: `XX-XXXXX-CC-XXX` where CC = color code (01=Black, 09=Grey)

### Template Changes

**File:** `cms-element-buy-box.html.twig`

```twig
{% if manufacturerName == 'Snigel' %}
  {# Left: Thumbnail Gallery #}
  <div class="snigel-thumbnail-gallery">
    {% for media in allMedia %}
      <button class="snigel-thumb" data-image="{{ media.media.url }}">
        <img src="{{ media.media.url }}" alt="thumbnail">
      </button>
    {% endfor %}
  </div>

  {# Right: Color Selector #}
  {% set colorOptions = product.customFields.snigel_color_options|default([]) %}
  {% if colorOptions|length > 0 %}
    <div class="snigel-color-section">
      <p>Farbe: <span id="snigel-selected-color">{{ colorOptions[0].name }}</span></p>
      <div class="snigel-color-buttons">
        {% for color in colorOptions %}
          <button class="snigel-color-btn"
                  data-color="{{ color.name }}"
                  data-image="{{ color.imageFilename }}">
            {{ color.name }}
          </button>
        {% endfor %}
      </div>
    </div>
  {% endif %}
{% endif %}
```

### Cart Integration

**Add to cart form:**
```twig
<input type="hidden" id="snigel-color-input"
       name="lineItems[{{ product.id }}][payload][snigelColor]"
       value="{{ colorOptions[0].name|default('') }}">
```

**Cart line item display:**
```twig
{% if item.payload.snigelColor %}
  <span class="cart-item-color">Farbe: {{ item.payload.snigelColor }}</span>
{% endif %}
```

### JavaScript

```javascript
// Thumbnail click - change main image only
document.querySelectorAll('.snigel-thumb').forEach(thumb => {
  thumb.addEventListener('click', () => {
    document.getElementById('raven-main-product-image').src = thumb.dataset.image;
  });
});

// Color button click - change image + update color selection
document.querySelectorAll('.snigel-color-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    // Update main image
    const imageUrl = findImageByFilename(btn.dataset.image);
    document.getElementById('raven-main-product-image').src = imageUrl;

    // Update color label
    document.getElementById('snigel-selected-color').textContent = btn.dataset.color;

    // Update hidden input for cart
    document.getElementById('snigel-color-input').value = btn.dataset.color;

    // Highlight selected button
    document.querySelectorAll('.snigel-color-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
  });
});
```

## Implementation Phases

### Phase 1: Data Setup
1. Create Shopware custom fields
2. Write import script from JSON
3. Run import on all Snigel products

### Phase 2: Thumbnail Gallery
4. Add left-side thumbnail gallery HTML
5. CSS styling (vertical, scrollable)
6. JavaScript for thumbnail click

### Phase 3: Color Selector
7. Add color buttons section
8. JavaScript for color selection
9. Hidden input for cart

### Phase 4: Cart Integration
10. Modify add-to-cart form
11. Update cart template
12. Update checkout template

### Phase 5: Testing (Playwright)
13. Test multi-color product
14. Test single-color product
15. Test cart → checkout flow

## Files to Modify

1. `RavenTheme/.../cms-element-buy-box.html.twig` - Main template
2. `RavenTheme/.../cart/line-item.html.twig` - Cart display
3. `RavenTheme/.../checkout/.../line-item.html.twig` - Checkout display
4. New: `scripts/import-snigel-colors.php` - Import script

## Success Criteria

- [ ] Snigel products show thumbnail gallery on left
- [ ] Color buttons appear for products with colors
- [ ] Clicking color changes main image
- [ ] Selected color shows in cart
- [ ] Selected color shows in checkout
- [ ] Single-color products show 1 button
