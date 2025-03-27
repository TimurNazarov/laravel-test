<?php

namespace App\Checkout\Rules;

use App\Checkout\PricingRuleInterface;

class ConditionalCartDiscountRule implements PricingRuleInterface
{
    public function __construct(
        private readonly array $requiredItems, // [productCode => requiredQty]
        private readonly array $discounts      // [productCode => ['discount' => %, 'qty' => int]]
    ) {}

    public function apply(array $items, array $prices): float
    {
        // Check if all required items are present in the cart
        foreach ($this->requiredItems as $product => $requiredQty) {
            if (($items[$product] ?? 0) < $requiredQty) {
                return 0.0; // Rule not matched
            }
        }

        // Apply defined discounts
        $totalDiscount = 0.0;

        foreach ($this->discounts as $product => $discountConfig) {
            $percent = $discountConfig['discount'];
            $qtyToDiscount = $discountConfig['qty'] ?? null;

            $cartQty = $items[$product] ?? 0;

            if ($cartQty === 0 || !isset($prices[$product])) {
                continue;
            }

            // If qty is null → apply to ALL
            $discountableQty = is_null($qtyToDiscount) ? $cartQty : min($qtyToDiscount, $cartQty);

            $pricePerUnit = $prices[$product];

            $totalDiscount += $discountableQty * $pricePerUnit * ($percent / 100);
        }

        return -$totalDiscount;
    }

    public function getExclusiveProducts(): array
    {
        return array_keys(array_filter($this->discounts, fn ($d) => $d['exclusive'] ?? false));
    }
}
