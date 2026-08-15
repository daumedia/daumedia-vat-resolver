<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Rates;

use Daumedia\VatResolver\Exception\UnknownCountryException;

/**
 * The loaded rate table: every country we know a rate for, plus where the data
 * came from and how old it is.
 */
final readonly class RateTable
{
    /**
     * @param array<string, CountryRates> $countries keyed by upper-case ISO 3166-1 alpha-2
     */
    public function __construct(
        public \DateTimeImmutable $asOf,
        public string $source,
        private array $countries,
    ) {
    }

    public function has(string $country): bool
    {
        return isset($this->countries[strtoupper(trim($country))]);
    }

    /**
     * @throws UnknownCountryException
     */
    public function get(string $country): CountryRates
    {
        $code = strtoupper(trim($country));

        return $this->countries[$code] ?? throw new UnknownCountryException($code);
    }

    /**
     * @return list<string>
     */
    public function euCountries(): array
    {
        $eu = array_filter($this->countries, static fn (CountryRates $c): bool => $c->eu);

        return array_values(array_keys($eu));
    }

    public function countryCount(): int
    {
        return \count($this->countries);
    }

    /**
     * Rates drift. This is what turns "someone should check the table" into a
     * failing test rather than a wrong invoice — see RateTableFreshnessTest.
     */
    public function isStale(\DateTimeImmutable $now, int $maxAgeDays = 400): bool
    {
        return $this->asOf->modify(sprintf('+%d days', $maxAgeDays)) < $now;
    }
}
