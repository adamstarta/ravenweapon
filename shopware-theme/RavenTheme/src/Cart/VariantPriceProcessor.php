<?php declare(strict_types=1);

namespace RavenTheme\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Processor to override line item prices for Snigel variant products.
 * This runs after the default ProductCartProcessor to apply variant-specific prices.
 */
class VariantPriceProcessor implements CartProcessorInterface
{
    private QuantityPriceCalculator $calculator;

    public function __construct(QuantityPriceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        // Iterate over all line items in the cart
        foreach ($toCalculate->getLineItems() as $lineItem) {
            // Only process product line items
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            // Check if this line item has a variant price in the payload
            $payload = $lineItem->getPayload();
            if (!isset($payload['variantPrice']) || empty($payload['variantPrice'])) {
                continue;
            }

            $variantPrice = (float) $payload['variantPrice'];
            if ($variantPrice <= 0) {
                continue;
            }

            // Get tax rules from current price or use default Swiss VAT (8.1%)
            $taxRules = $lineItem->getPrice()?->getTaxRules();
            if ($taxRules === null || $taxRules->count() === 0) {
                $taxRules = new TaxRuleCollection([
                    new TaxRule(8.1)
                ]);
            }

            // Create new price definition with the variant price
            $definition = new QuantityPriceDefinition(
                $variantPrice,
                $taxRules,
                $lineItem->getQuantity()
            );

            // Calculate the new price
            $calculatedPrice = $this->calculator->calculate($definition, $context);

            // Apply the new price to the line item
            $lineItem->setPrice($calculatedPrice);
        }
    }
}
