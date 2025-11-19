# Color Variant Images Guide

## Kailangan og Images para sa Different Colors

Para makita ang different colors sa guns sa product page, kinahanglan mo og images para sa kada color variant.

## Naming Convention

Para sa kada gun, create images with these names:

### 5.56 / .223 RAVEN
- `assets/5.56 RAVEN - Graphite Black.png`
- `assets/5.56 RAVEN - Flat Dark Earth.png`
- `assets/5.56 RAVEN - Northern Lights.png`
- `assets/5.56 RAVEN - Olive Drab Green.png`
- `assets/5.56 RAVEN - Sniper Grey.png`

### 300 AAC RAVEN
- `assets/300 AAC RAVEN - Graphite Black.png`
- `assets/300 AAC RAVEN - Flat Dark Earth.png`
- `assets/300 AAC RAVEN - Northern Lights.png`
- `assets/300 AAC RAVEN - Olive Drab Green.png`
- `assets/300 AAC RAVEN - Sniper Grey.png`

### 7.62×39 RAVEN
- `assets/7.62×39 RAVEN - Graphite Black.png`
- `assets/7.62×39 RAVEN - Flat Dark Earth.png`
- `assets/7.62×39 RAVEN - Northern Lights.png`
- `assets/7.62×39 RAVEN - Olive Drab Green.png`
- `assets/7.62×39 RAVEN - Sniper Grey.png`

### 9mm RAVEN
- `assets/9mm RAVEN - Graphite Black.png`
- `assets/9mm RAVEN - Flat Dark Earth.png`
- `assets/9mm RAVEN - Northern Lights.png`
- `assets/9mm RAVEN - Olive Drab Green.png`
- `assets/9mm RAVEN - Sniper Grey.png`

### .22 RAVEN
- `assets/.22 RAVEN - Graphite Black.png`
- `assets/.22 RAVEN - Flat Dark Earth.png`
- `assets/.22 RAVEN - Northern Lights.png`
- `assets/.22 RAVEN - Olive Drab Green.png`
- `assets/.22 RAVEN - Sniper Grey.png`

## Color Reference

**Graphite Black** - #2C2C2C (dark gray/black)
**Flat Dark Earth** - #C9B896 (tan/sand color)
**Northern Lights** - #4A7C8C (blue-green/teal)
**Olive Drab Green** - #6B7C4B (military green)
**Sniper Grey** - #7A7F84 (medium gray)

## Paano gamitin:

1. Create or edit ang gun images sa Photoshop/image editor
2. Apply ang color based sa color codes sa taas
3. Save with the exact filename shown above
4. Put sa `assets` folder
5. Pag naa na ang images, i-update ang `products.js` (guide below)

## After Adding Images

Open `products.js` and update each variant's image path. Example:

```javascript
{
  color: "Flat Dark Earth",
  colorCode: "#C9B896",
  image: "assets/5.56 RAVEN - Flat Dark Earth.png",  // ← Update this
  thumbnail: "assets/5.56 RAVEN - Flat Dark Earth.png",  // ← Update this
  priceModifier: 0
}
```

Repeat for all colors and all guns!
