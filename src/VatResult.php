<?php

declare(strict_types=1);

namespace Daumedia\VatResolver;

/**
 * What to charge, and why.
 *
 * $rate is in PERCENTAGE POINTS: 17.0 means 17 %, not 0.17. That is how it reads
 * on an invoice, and the ambiguity between the two conventions is a classic
 * factor-100 bug, so a test pins it down.
 *
 * The float must never reach a database. Persist basisPoints() as an integer
 * instead — 1700 rather than 17.0. Money arithmetic in floats is exactly the
 * mistake this library exists to prevent, and a rate column is where it sneaks in.
 *
 * $legalBasis is not decoration. A reverse-charge invoice has to state the reason
 * for the exemption, and an audit asks why a given rate was applied; this string
 * is that answer, generated at the moment the decision was made.
 */
final readonly class VatResult
{
    public function __construct(
        public float $rate,
        public bool $reverseCharge,
        public string $legalBasis,
        public string $countryCode,
    ) {
    }

    /**
     * The rate as an integer, in hundredths of a percentage point.
     * 17.0 -> 1700, 25.5 -> 2550, 0.0 -> 0. This is the storable form.
     */
    public function basisPoints(): int
    {
        return (int) round($this->rate * 100);
    }

    /**
     * Tax on a net amount, both in minor units (cents).
     *
     * Rounds half away from zero, which is PHP's round() default and the
     * convention EU tax authorities expect on the invoice total.
     */
    public function taxCentsOn(int $netCents): int
    {
        return (int) round($netCents * $this->rate / 100);
    }

    public function grossCentsOn(int $netCents): int
    {
        return $netCents + $this->taxCentsOn($netCents);
    }

    public function isTaxable(): bool
    {
        return $this->rate > 0.0;
    }
}
