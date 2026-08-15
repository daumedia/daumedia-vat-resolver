<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Enum;

/**
 * Whether the customer acts as a business or as a private person.
 *
 * PERSISTED CONTRACT. Consuming applications map these backed values straight
 * into a database column, so renaming a case value is a schema change on their
 * side, not a refactoring here. PersistedValuesTest nails the strings down.
 */
enum CustomerType: string
{
    case B2C = 'b2c';
    case B2B = 'b2b';

    public function isBusiness(): bool
    {
        return $this === self::B2B;
    }
}
