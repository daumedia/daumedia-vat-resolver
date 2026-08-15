<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Vies;

/**
 * Syntactic pre-filter for VAT identification numbers.
 *
 * This is the first of the three stages that keep VIES out of the request path.
 * A number that cannot possibly be valid is rejected here — no cache lookup, no
 * queue entry, no network. It also stops us from filling the lookup queue with
 * whatever someone typed into the field.
 *
 * Syntax only. A well-formed number still has to be confirmed by VIES before it
 * earns a reverse charge.
 */
final class VatNumberFormat
{
    /**
     * Per-country patterns for the national part, i.e. with the country prefix
     * already stripped. Source: EU Commission, "VAT identification number
     * structure".
     */
    private const array PATTERNS = [
        'AT' => '/^U[0-9]{8}$/',
        'BE' => '/^[01][0-9]{9}$/',
        'BG' => '/^[0-9]{9,10}$/',
        'CY' => '/^[0-9]{8}[A-Z]$/',
        'CZ' => '/^[0-9]{8,10}$/',
        'DE' => '/^[0-9]{9}$/',
        'DK' => '/^[0-9]{8}$/',
        'EE' => '/^[0-9]{9}$/',
        'ES' => '/^[A-Z0-9][0-9]{7}[A-Z0-9]$/',
        'FI' => '/^[0-9]{8}$/',
        'FR' => '/^[A-Z0-9]{2}[0-9]{9}$/',
        'GR' => '/^[0-9]{9}$/',
        'HR' => '/^[0-9]{11}$/',
        'HU' => '/^[0-9]{8}$/',
        'IE' => '/^([0-9]{7}[A-Z]{1,2}|[0-9][A-Z][0-9]{5}[A-Z])$/',
        'IT' => '/^[0-9]{11}$/',
        'LT' => '/^([0-9]{9}|[0-9]{12})$/',
        'LU' => '/^[0-9]{8}$/',
        'LV' => '/^[0-9]{11}$/',
        'MT' => '/^[0-9]{8}$/',
        'NL' => '/^[0-9]{9}B[0-9]{2}$/',
        'PL' => '/^[0-9]{10}$/',
        'PT' => '/^[0-9]{9}$/',
        'RO' => '/^[0-9]{2,10}$/',
        'SE' => '/^[0-9]{12}$/',
        'SI' => '/^[0-9]{8}$/',
        'SK' => '/^[0-9]{10}$/',
        // Northern Ireland keeps an EU-facing prefix under the Windsor Framework.
        'XI' => '/^([0-9]{9}|[0-9]{12}|(GD|HA)[0-9]{3})$/',
    ];

    /**
     * Strips spaces, dots and hyphens, upper-cases, and removes a leading country
     * prefix if it matches the given country.
     */
    public static function normalize(string $countryCode, string $vatNumber): string
    {
        $country = strtoupper(trim($countryCode));
        $number = strtoupper(preg_replace('/[\s.\-\/]/', '', $vatNumber) ?? '');

        if (str_starts_with($number, $country)) {
            $number = substr($number, \strlen($country));
        }

        return $number;
    }

    public static function isWellFormed(string $countryCode, string $vatNumber): bool
    {
        $country = strtoupper(trim($countryCode));
        $pattern = self::PATTERNS[$country] ?? null;

        if ($pattern === null) {
            // No pattern means no opinion: a country we do not have a rule for
            // must not be rejected here. VIES stays the authority.
            return false;
        }

        return preg_match($pattern, self::normalize($country, $vatNumber)) === 1;
    }

    /**
     * The canonical form used in cache keys and on invoices: prefix + national part.
     */
    public static function canonical(string $countryCode, string $vatNumber): string
    {
        $country = strtoupper(trim($countryCode));

        return $country . self::normalize($country, $vatNumber);
    }

    public static function hasPatternFor(string $countryCode): bool
    {
        return isset(self::PATTERNS[strtoupper(trim($countryCode))]);
    }
}
