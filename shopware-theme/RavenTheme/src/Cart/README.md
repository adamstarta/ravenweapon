# RavenTheme Cart Processors

Custom cart calculation for variant pricing.

## VariantPriceProcessor

Overrides line item prices when variants have different prices.

### How It Works

1. **Runs after** Shopware's default `ProductCartProcessor`
2. Checks each line item for `variantPrice` in payload
3. Creates new `QuantityPriceDefinition` with variant price
4. Recalculates with Swiss VAT (8.1%)
5. Applies new price to line item

### Flow

```
Frontend (selectedSizePrice input)
        ↓
CartLineItemSubscriber (captures → payload['variantPrice'])
        ↓
VariantPriceProcessor (applies price override)
        ↓
Cart displays correct variant price
```

### Registration

In `config/services.xml`:
```xml
<service id="RavenTheme\Cart\VariantPriceProcessor">
    <argument type="service" id="Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator"/>
    <tag name="shopware.cart.processor" priority="-5000"/>
</service>
```

**Priority -5000** ensures it runs after ProductCartProcessor.

### Payload Fields

| Field | Set By | Used By |
|-------|--------|---------|
| `variantPrice` | CartLineItemSubscriber | VariantPriceProcessor |
| `selectedColor` | CartLineItemSubscriber | Template display |
| `selectedSize` | CartLineItemSubscriber | Template display |
| `variantenDisplay` | CartLineItemSubscriber | Cart line item label |
