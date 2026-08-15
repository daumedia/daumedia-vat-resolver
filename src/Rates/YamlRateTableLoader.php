<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Rates;

use Daumedia\VatResolver\Exception\InvalidRateTableException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads resources/vat_rates.yaml and validates it hard.
 *
 * Every check here answers "what would silently produce a wrong invoice?".
 * A missing country throws at load time, not at the checkout of the one customer
 * from that country three weeks later.
 *
 * Parsing is not cached: it is ~35 lines of YAML, well under a millisecond. If it
 * ever shows up in a profile, decorate this loader or compile the table into a
 * container parameter — deliberately not done in advance.
 */
final class YamlRateTableLoader implements RateTableLoaderInterface
{
    /** The EU has had 27 members since 2020-02-01. A different count means the file lost a country. */
    private const int EU_MEMBER_COUNT = 27;

    /** No member state may go below 15 % (Art. 97 VAT Directive); above 30 % nobody has ever gone. */
    private const float MIN_PLAUSIBLE_RATE = 0.0;
    private const float MAX_PLAUSIBLE_RATE = 30.0;
    private const float EU_MINIMUM_STANDARD_RATE = 15.0;

    public function __construct(
        private readonly string $path = __DIR__ . '/../../resources/vat_rates.yaml',
    ) {
    }

    public function load(): RateTable
    {
        if (!is_file($this->path) || !is_readable($this->path)) {
            throw InvalidRateTableException::at($this->path, 'Datei nicht gefunden oder nicht lesbar.');
        }

        try {
            $raw = Yaml::parseFile($this->path);
        } catch (ParseException $e) {
            throw InvalidRateTableException::at($this->path, 'YAML nicht parsebar: ' . $e->getMessage());
        }

        if (!\is_array($raw)) {
            throw InvalidRateTableException::at($this->path, 'Wurzelelement ist kein Mapping.');
        }

        return new RateTable(
            $this->parseAsOf($raw['meta'] ?? null),
            $this->parseSource($raw['meta'] ?? null),
            $this->parseCountries($raw['countries'] ?? null),
        );
    }

    private function parseAsOf(mixed $meta): \DateTimeImmutable
    {
        if (!\is_array($meta) || !isset($meta['as_of'])) {
            throw InvalidRateTableException::at($this->path, '`meta.as_of` fehlt.');
        }

        $asOf = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $meta['as_of']);

        if ($asOf === false) {
            throw InvalidRateTableException::at(
                $this->path,
                sprintf('`meta.as_of` („%s") ist kein Datum im Format YYYY-MM-DD.', (string) $meta['as_of']),
            );
        }

        return $asOf;
    }

    private function parseSource(mixed $meta): string
    {
        if (!\is_array($meta) || !isset($meta['source']) || trim((string) $meta['source']) === '') {
            throw InvalidRateTableException::at($this->path, '`meta.source` fehlt — die Herkunft der Sätze muss dokumentiert sein.');
        }

        return (string) $meta['source'];
    }

    /**
     * @return array<string, CountryRates>
     */
    private function parseCountries(mixed $countries): array
    {
        if (!\is_array($countries) || $countries === []) {
            throw InvalidRateTableException::at($this->path, '`countries` fehlt oder ist leer.');
        }

        $parsed = [];

        foreach ($countries as $code => $definition) {
            $code = (string) $code;

            if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
                throw InvalidRateTableException::forCountry(
                    $this->path,
                    $code,
                    'Schlüssel ist kein ISO-3166-1-alpha-2-Code in Grossbuchstaben.',
                );
            }

            $parsed[$code] = $this->parseCountry($code, $definition);
        }

        $this->assertEuIsComplete($parsed);

        return $parsed;
    }

    private function parseCountry(string $code, mixed $definition): CountryRates
    {
        if (!\is_array($definition)) {
            throw InvalidRateTableException::forCountry($this->path, $code, 'Eintrag ist kein Mapping.');
        }

        if (!isset($definition['eu']) || !\is_bool($definition['eu'])) {
            throw InvalidRateTableException::forCountry($this->path, $code, '`eu` fehlt oder ist nicht true/false.');
        }

        if (!isset($definition['name']) || trim((string) $definition['name']) === '') {
            throw InvalidRateTableException::forCountry($this->path, $code, '`name` fehlt.');
        }

        $standard = $this->parseRate($code, 'standard', $definition['standard'] ?? null, required: true);
        \assert($standard !== null);

        if ($definition['eu'] === true && $standard < self::EU_MINIMUM_STANDARD_RATE) {
            throw InvalidRateTableException::forCountry(
                $this->path,
                $code,
                sprintf(
                    '`standard` ist %.1f, liegt damit unter dem EU-Mindestnormalsatz von %.1f (Art. 97 MwStSystRL).',
                    $standard,
                    self::EU_MINIMUM_STANDARD_RATE,
                ),
            );
        }

        return new CountryRates(
            code: $code,
            name: (string) $definition['name'],
            eu: $definition['eu'],
            standard: $standard,
            reduced: $this->parseReduced($code, $definition['reduced'] ?? []),
            superReduced: $this->parseRate($code, 'super_reduced', $definition['super_reduced'] ?? null, required: false),
            parking: $this->parseRate($code, 'parking', $definition['parking'] ?? null, required: false),
        );
    }

    private function parseRate(string $code, string $key, mixed $value, bool $required): ?float
    {
        if ($value === null) {
            if ($required) {
                throw InvalidRateTableException::forCountry($this->path, $code, sprintf('`%s` fehlt.', $key));
            }

            return null;
        }

        if (!is_numeric($value)) {
            throw InvalidRateTableException::forCountry(
                $this->path,
                $code,
                sprintf('`%s` ist nicht numerisch.', $key),
            );
        }

        $rate = (float) $value;

        if ($rate < self::MIN_PLAUSIBLE_RATE || $rate > self::MAX_PLAUSIBLE_RATE) {
            throw InvalidRateTableException::forCountry(
                $this->path,
                $code,
                sprintf('`%s` ist %.1f und damit ausserhalb des plausiblen Bereichs 0–30.', $key, $rate),
            );
        }

        return $rate;
    }

    /**
     * @return list<float>
     */
    private function parseReduced(string $code, mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidRateTableException::forCountry($this->path, $code, '`reduced` ist keine Liste.');
        }

        $rates = [];

        foreach ($value as $rate) {
            $parsed = $this->parseRate($code, 'reduced', $rate, required: true);
            \assert($parsed !== null);
            $rates[] = $parsed;
        }

        return $rates;
    }

    /**
     * @param array<string, CountryRates> $parsed
     */
    private function assertEuIsComplete(array $parsed): void
    {
        $euCount = \count(array_filter($parsed, static fn (CountryRates $c): bool => $c->eu));

        if ($euCount !== self::EU_MEMBER_COUNT) {
            throw InvalidRateTableException::at($this->path, sprintf(
                'Die Tabelle führt %d EU-Mitgliedstaaten, erwartet werden %d. Ein Land fehlt oder ist falsch als `eu` markiert.',
                $euCount,
                self::EU_MEMBER_COUNT,
            ));
        }
    }
}
