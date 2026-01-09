<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CartLineItemSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeLineItemAddedEvent::class => ['onLineItemAdded', 100], // High priority
        ];
    }

    public function onLineItemAdded(BeforeLineItemAddedEvent $event): void
    {
        $lineItem = $event->getLineItem();

        if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        // Get existing payload or create new one
        $payload = $lineItem->getPayload();

        // Get selected color directly from request (simple field name)
        $selectedColor = $request->request->get('selectedColor');
        if ($selectedColor && !empty($selectedColor) && is_string($selectedColor)) {
            $payload['selectedColor'] = $selectedColor;
        }

        // Get selected size/variant from request
        $selectedSize = $request->request->get('selectedSize');
        if ($selectedSize && !empty($selectedSize) && is_string($selectedSize)) {
            $payload['selectedSize'] = $selectedSize;
        }

        // Get the combined varianten display from frontend
        $variantenDisplay = $request->request->get('variantenDisplay');
        if ($variantenDisplay && !empty($variantenDisplay) && is_string($variantenDisplay)) {
            $payload['variantenDisplay'] = $variantenDisplay;
        }

        // Fallback: build variantenDisplay from color/size if not provided
        if (empty($payload['variantenDisplay'])) {
            $parts = [];

            // First try request parameters
            if (!empty($payload['selectedColor'])) {
                $parts[] = $payload['selectedColor'];
            }
            if (!empty($payload['selectedSize'])) {
                $parts[] = $payload['selectedSize'];
            }

            // If still empty, try to get from Shopware's built-in product options
            if (empty($parts)) {
                $options = $lineItem->getPayloadValue('options') ?? [];
                $colorFromOptions = null;
                $sizeFromOptions = null;

                foreach ($options as $option) {
                    $groupName = $option['group'] ?? '';
                    $optionName = $option['option'] ?? '';

                    if (in_array($groupName, ['Farbe', 'Color', 'Colour'])) {
                        $colorFromOptions = $optionName;
                    } elseif (in_array($groupName, ['Größe', 'Grösse', 'Size'])) {
                        $sizeFromOptions = $optionName;
                    }
                }

                if ($colorFromOptions) {
                    $parts[] = $colorFromOptions;
                    $payload['selectedColor'] = $colorFromOptions;
                }
                if ($sizeFromOptions) {
                    $parts[] = $sizeFromOptions;
                    $payload['selectedSize'] = $sizeFromOptions;
                }
            }

            if (!empty($parts)) {
                $payload['variantenDisplay'] = implode(' / ', $parts);
            }
        }

        // Get selected variant price from request
        $selectedSizePrice = $request->request->get('selectedSizePrice');
        if ($selectedSizePrice && !empty($selectedSizePrice)) {
            $variantPrice = (float) $selectedSizePrice;
            if ($variantPrice > 0) {
                $payload['variantPrice'] = $variantPrice;

                // Create a new price definition with the variant price
                // This will override the product's default price
                $salesChannelContext = $event->getSalesChannelContext();
                $taxRules = $lineItem->getPrice()?->getTaxRules();

                if ($taxRules === null) {
                    // Use default Swiss VAT rate of 8.1%
                    $taxRules = new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection([
                        new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule(8.1)
                    ]);
                }

                // Calculate net price from gross (CHF prices are gross/including VAT)
                $netPrice = $variantPrice / 1.081;

                $priceDefinition = new QuantityPriceDefinition(
                    $variantPrice,  // gross price
                    $taxRules,
                    $lineItem->getQuantity()
                );

                $lineItem->setPriceDefinition($priceDefinition);
            }
        }

        $lineItem->setPayload($payload);
    }
}
