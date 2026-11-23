<?php

namespace App\Billing;

use InvalidArgumentException;

/**
 * Immutable view of a plan defined in config/plans.php.
 */
final class Plan
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $description,
        public readonly int $priceMonthly,
        public readonly ?string $stripePrice,
        public readonly array $limits,
        public readonly array $providers,
        public readonly array $features,
        public readonly bool $highlighted = false,
    ) {}

    public static function find(string $key): self
    {
        $config = config("plans.plans.{$key}")
            ?? throw new InvalidArgumentException("Unknown plan [{$key}].");

        return new self(
            key: $key,
            name: $config['name'],
            description: $config['description'] ?? '',
            priceMonthly: (int) $config['price_monthly'],
            stripePrice: $config['stripe_price'] ?: null,
            limits: $config['limits'],
            providers: $config['providers'],
            features: $config['features'] ?? [],
            highlighted: (bool) ($config['highlighted'] ?? false),
        );
    }

    public static function default(): self
    {
        return self::find(config('plans.default', 'free'));
    }

    /** @return list<self> */
    public static function all(): array
    {
        return array_map(fn ($key) => self::find($key), array_keys(config('plans.plans')));
    }

    public static function exists(string $key): bool
    {
        return config("plans.plans.{$key}") !== null;
    }

    public static function forStripePrice(?string $price): ?self
    {
        if (! $price) {
            return null;
        }

        foreach (self::all() as $plan) {
            if ($plan->stripePrice === $price) {
                return $plan;
            }
        }

        return null;
    }

    /** null = unlimited */
    public function limit(string $key): ?int
    {
        return $this->limits[$key] ?? null;
    }

    public function allowsProvider(string $provider): bool
    {
        return in_array('*', $this->providers, true) || in_array($provider, $this->providers, true);
    }

    public function isFree(): bool
    {
        return $this->priceMonthly === 0;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'price_monthly' => $this->priceMonthly,
            'limits' => $this->limits,
            'providers' => $this->providers,
            'features' => $this->features,
            'highlighted' => $this->highlighted,
            'purchasable' => $this->isFree() || $this->stripePrice !== null,
        ];
    }
}
