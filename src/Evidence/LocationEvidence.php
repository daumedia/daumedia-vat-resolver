<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Evidence;

/**
 * One indication of where the customer is, and where it came from.
 *
 * The source string is free-form on purpose — which indications are available
 * differs per application ('billing_address', 'ip_geolocation', 'bank_country',
 * 'sim_country', …). It exists so a conflict can be reported in terms a human
 * can act on rather than as two bare country codes.
 */
final readonly class LocationEvidence
{
    public string $countryCode;

    public function __construct(public string $source, string $countryCode)
    {
        $this->countryCode = strtoupper(trim($countryCode));
    }

    public static function billingAddress(string $countryCode): self
    {
        return new self('billing_address', $countryCode);
    }

    public static function ipGeolocation(string $countryCode): self
    {
        return new self('ip_geolocation', $countryCode);
    }
}
