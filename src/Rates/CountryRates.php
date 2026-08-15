<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Rates;

use Daumedia\VatResolver\Enum\RateCategory;
use Daumedia\VatResolver\Exception\InvalidRateTableException;

/**
 * The rate bands one country operates.
 *
 * Rates are percentage points (17.0 means 17 %), never fractions. Finland's 25.5
 * is why this is a float and not an int.
 */
final readonly class CountryRates
{
    /**
     * @param list<float> $reduced
     */
    public function __construct(
        public string $code,
        public string $name,
        public bool $eu,
        public float $standard,
        public array $reduced,
        public ?float $superReduced,
        public ?float $parking,
    ) {
    }

    /**
     * The rate for a band, or an exception.
     *
     * No fallback to the standard rate: a country that has no reduced rate means
     * the caller asked the wrong question, and answering it with a different
     * number would produce a plausible, wrong invoice.
     *
     * @throws InvalidRateTableException
     */
    public function rate(RateCategory $category): float
    {
        return match ($category) {
            RateCategory::STANDARD => $this->standard,
            RateCategory::ZERO => 0.0,
            RateCategory::REDUCED => $this->reduced[0]
                ?? throw InvalidRateTableException::missingCategory($this->code, $category->value),
            RateCategory::SUPER_REDUCED => $this->superReduced
                ?? throw InvalidRateTableException::missingCategory($this->code, $category->value),
            RateCategory::PARKING => $this->parking
                ?? throw InvalidRateTableException::missingCategory($this->code, $category->value),
        };
    }
}
