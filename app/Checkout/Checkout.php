<?php

namespace App\Checkout;

use App\Checkout\Decorators\ExclusiveRuleWrapper;
use App\Checkout\PricingRuleInterface;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

class Checkout
{
    /**
     * @var array<string, array{name: string, price: float}>
     */
    private readonly array $products;

    /**
     * @var array<string, int>
     */
    private array $items = [];

    /**
     * @param PricingRuleInterface[] $pricingRules
     */
    public function __construct(
        private array $pricingRules = []
    ) {
        // from mock
        $this->products = Config::get('products', []);
    }

    public function scan(string $productCode): void
    {
        if (!isset($this->products[$productCode])) {
            throw new InvalidArgumentException("Invalid product code: {$productCode}");
        }

        $this->items[$productCode] = ($this->items[$productCode] ?? 0) + 1;
    }

    public function total(): float
    {
        $baseTotal = 0.0;

        foreach ($this->items as $code => $qty) {
            $baseTotal += $qty * $this->products[$code]['price'];
        }

        $adjustment = 0.0;
        $alreadyDiscounted = [];

        foreach ($this->pricingRules as $rule) {
            $wrappedRule = new ExclusiveRuleWrapper($rule, $alreadyDiscounted);
            $adjustment += $wrappedRule->apply($this->items, $this->getPriceMap());
        }

        return round($baseTotal + $adjustment, 2);
    }

    public function getPriceMap(): array
    {
        return array_map(fn ($p) => $p['price'], $this->products);
    }
}
