<?php

namespace App\Checkout;

/**
 * Interface for applying pricing rules to cart items.
 */
interface PricingRuleInterface
{
    /**
     * Apply the pricing rule and return the discount adjustment.
     *
     * @param array<string, int> $items Product quantities keyed by product code
     * @param array<string, float> $prices Product prices keyed by product code
     * @return float Discount to apply (negative number)
     */
    public function apply(array $items, array $prices): float;
}
