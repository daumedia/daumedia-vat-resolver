<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Double;

use Daumedia\VatResolver\Vies\ViesCheck;
use Daumedia\VatResolver\Vies\ViesValidatorInterface;

/**
 * Wraps a validator and counts the calls.
 *
 * Several resolver tests assert that VIES is not consulted at all — for a B2C
 * customer, for a null VAT number, for a syntactically impossible one. Those are
 * assertions about calls, not about return values.
 */
final class SpyViesValidator implements ViesValidatorInterface
{
    public int $calls = 0;

    /** @var list<array{country: string, vatNumber: string}> */
    public array $received = [];

    public function __construct(private readonly ViesValidatorInterface $inner)
    {
    }

    public function check(string $countryCode, string $vatNumber): ?ViesCheck
    {
        ++$this->calls;
        $this->received[] = ['country' => $countryCode, 'vatNumber' => $vatNumber];

        return $this->inner->check($countryCode, $vatNumber);
    }
}
