<?php

namespace App\Checkout\Decorators;

use App\Checkout\PricingRuleInterface;

/**
 * Wraps a pricing rule and enforces product-level exclusivity.
 */
class ExclusiveRuleWrapper implements PricingRuleInterface
{
    public function __construct(
        private readonly PricingRuleInterface $innerRule,
        private array &$alreadyDiscounted
    ) {}

    public function apply(array $items, array $prices): float
    {
        // Filter out already discounted products from the price map
        $filteredPrices = array_filter(
            $prices,
            fn ($_, $code) => !isset($this->alreadyDiscounted[$code]),
            ARRAY_FILTER_USE_BOTH
        );

        // Let the rule apply its logic on filtered products
        $discount = $this->innerRule->apply($items, $filteredPrices);

        // If the rule has any exclusive discounts, mark them
        if (method_exists($this->innerRule, 'getExclusiveProducts')) {
            foreach ($this->innerRule->getExclusiveProducts() as $code) {
                $this->alreadyDiscounted[$code] = true;
            }
        }

        return $discount;
    }
}
