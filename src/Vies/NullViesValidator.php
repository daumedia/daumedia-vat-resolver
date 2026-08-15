<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Vies;

/**
 * Knows nothing, ever.
 *
 * The safe default and the standard test double: with it, every B2B customer is
 * treated as unconfirmed and therefore charged VAT. That is the conservative
 * direction — over-charging is a correction, under-charging is our tax debt.
 */
final class NullViesValidator implements ViesValidatorInterface
{
    public function check(string $countryCode, string $vatNumber): ?ViesCheck
    {
        return null;
    }
}
