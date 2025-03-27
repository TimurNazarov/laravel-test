<?php

namespace Tests\Feature;

use App\Checkout\Rules\ConditionalCartDiscountRule;
use InvalidArgumentException;
use Tests\TestCase;
use App\Checkout\Checkout;

class CheckoutTest extends TestCase
{
    public function test_basket_1(): void
    {
        $checkout = $this->createCheckout();

        foreach (['FR1', 'SR1', 'FR1', 'FR1', 'CF1'] as $item) {
            $checkout->scan($item);
        }

        $this->assertEquals(22.45, $checkout->total());
    }

    public function test_basket_2(): void
    {
        $checkout = $this->createCheckout();

        foreach (['FR1', 'FR1'] as $item) {
            $checkout->scan($item);
        }

        $this->assertEquals(3.11, $checkout->total());
    }

    public function test_basket_3(): void
    {
        $checkout = $this->createCheckout();

        foreach (['SR1', 'SR1', 'FR1', 'SR1'] as $item) {
            $checkout->scan($item);
        }

        $this->assertEquals(16.61, $checkout->total());
    }

    public function test_invalid_produt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $checkout = $this->createCheckout();
        $checkout->scan('INVALID');
    }

    private function createCheckout(): Checkout
    {
        return new Checkout([
            // BOGOF on FR1
            new ConditionalCartDiscountRule(
                requiredItems: ['FR1' => 2],
                discounts: ['FR1' => ['discount' => 100.0, 'qty' => 1]]
            ),
            // Bulk discount on SR1 if qty >= 3
            new ConditionalCartDiscountRule(
                requiredItems: ['SR1' => 3],
                discounts: ['SR1' => ['discount' => 10.0, 'qty' => 3]] // 10% of £5.00 is £0.50 → £4.50
            )
        ]);
    }

    public function test_exclusive_discount_prevents_stacking(): void
    {
        $checkout = new Checkout([
            new ConditionalCartDiscountRule(
                requiredItems: ['FR1' => 2],
                discounts: ['SR1' => ['discount' => 50.0, 'qty' => 1, 'exclusive' => true]]
            ),
            new ConditionalCartDiscountRule(
                requiredItems: ['CF1' => 1],
                discounts: ['SR1' => ['discount' => 50.0, 'qty' => 1]]
            )
        ]);

        // Trigger both rules, but only the first should discount SR1 due to exclusivity
        foreach (['FR1', 'FR1', 'CF1', 'SR1'] as $item) {
            $checkout->scan($item);
        }

        // Expected price:
        // FR1 x2 = £6.22
        // CF1 = £11.23
        // SR1 = £5.00 - 50% = £2.50
        // Total = £6.22 + £11.23 + £2.50 = £19.95
        $this->assertEquals(19.95, $checkout->total());
    }

    public function test_non_exclusive_discount_allows_stacking(): void
    {
        $checkout = new Checkout([
            new ConditionalCartDiscountRule(
                requiredItems: ['FR1' => 2],
                discounts: ['SR1' => ['discount' => 20.0, 'qty' => 1, 'exclusive' => false]]
            ),
            new ConditionalCartDiscountRule(
                requiredItems: ['CF1' => 1],
                discounts: ['SR1' => ['discount' => 10.0, 'qty' => 1, 'exclusive' => false]]
            )
        ]);

        // Both rules apply to SR1, discounting it twice (stacked)
        foreach (['FR1', 'FR1', 'CF1', 'SR1'] as $item) {
            $checkout->scan($item);
        }

        // SR1 gets 20% + 10% off = 30% total on one unit
        // FR1 x2 = £6.22, CF1 = £11.23, SR1 = £5.00 - 30% = £3.50
        $this->assertEquals(6.22 + 11.23 + 3.50, $checkout->total());
    }
}
