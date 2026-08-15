<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Exception;

/**
 * The country code is malformed or absent from the rate table.
 *
 * Deliberately fatal rather than "treat as third country": an unknown code is far
 * more likely a typo or a broken mapping than a genuine non-EU customer, and
 * guessing would silently zero-rate a taxable sale.
 */
final class UnknownCountryException extends \InvalidArgumentException implements VatResolverExceptionInterface
{
    public function __construct(public readonly string $countryCode)
    {
        parent::__construct(sprintf(
            'Unbekannter Ländercode „%s". Er fehlt in der Satztabelle oder ist kein ISO-3166-1-alpha-2-Code.',
            $countryCode,
        ));
    }
}
