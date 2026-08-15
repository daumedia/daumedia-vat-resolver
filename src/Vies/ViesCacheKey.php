<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Vies;

/**
 * One place that decides what a cached VIES answer is filed under.
 *
 * Reader and warmer run in different processes; if they disagree about the key
 * the cache never hits and nothing visibly breaks — the checkout just quietly
 * charges VAT forever. Hence a single shared function and a round-trip test.
 *
 * The canonical number goes in verbatim: it is normalised to [A-Z0-9] before it
 * gets here, so none of the characters PSR-16 reserves ({}()/\@:) can occur.
 */
final class ViesCacheKey
{
    public static function for(string $countryCode, string $vatNumber): string
    {
        $canonical = VatNumberFormat::canonical($countryCode, $vatNumber);

        return 'vies.' . strtolower($canonical);
    }
}
