<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Unit\Rates;

use Daumedia\VatResolver\Enum\RateCategory;
use Daumedia\VatResolver\Exception\InvalidRateTableException;
use Daumedia\VatResolver\Rates\YamlRateTableLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The validation cases build a valid table and break exactly one thing, rather
 * than reading fixture files. Every fixture would have to carry all 27 member
 * states, so the one broken line would be invisible in the noise — here it is
 * the only line in the test.
 */
final class YamlRateTableLoaderTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        $this->tempFiles = [];
    }

    // --- die echte Datei ------------------------------------------------

    public function testTheShippedTableLoads(): void
    {
        $table = (new YamlRateTableLoader())->load();

        self::assertCount(27, $table->euCountries());
        self::assertSame(17.0, $table->get('LU')->standard);
        self::assertSame(19.0, $table->get('DE')->standard);
        self::assertSame(25.5, $table->get('FI')->standard);
    }

    public function testTheShippedTableCarriesItsProvenance(): void
    {
        // A rate without a source and a date is a number somebody once believed.
        $table = (new YamlRateTableLoader())->load();

        self::assertStringContainsString('TAXUD', $table->source);
        self::assertSame('2026-01-01', $table->asOf->format('Y-m-d'));
    }

    public function testThirdCountriesAreListedRatherThanOmitted(): void
    {
        // Listed explicitly so a typo fails as "unknown" instead of passing as "non-EU".
        $table = (new YamlRateTableLoader())->load();

        self::assertTrue($table->has('US'));
        self::assertFalse($table->get('US')->eu);
        self::assertTrue($table->has('CH'));
        self::assertFalse($table->get('CH')->eu);
    }

    public function testLuxembourgKnowsAllFourBands(): void
    {
        $lu = (new YamlRateTableLoader())->load()->get('LU');

        self::assertSame(17.0, $lu->rate(RateCategory::STANDARD));
        self::assertSame(14.0, $lu->rate(RateCategory::REDUCED));
        self::assertSame(3.0, $lu->rate(RateCategory::SUPER_REDUCED));
        self::assertSame(14.0, $lu->rate(RateCategory::PARKING));
    }

    public function testAskingForABandACountryDoesNotHaveThrowsInsteadOfFallingBack(): void
    {
        // Falling back to the standard rate would produce a plausible wrong invoice.
        $de = (new YamlRateTableLoader())->load()->get('DE');

        $this->expectException(InvalidRateTableException::class);
        $de->rate(RateCategory::SUPER_REDUCED);
    }

    // --- Validierung ----------------------------------------------------

    public function testAMissingFileIsReported(): void
    {
        $this->expectException(InvalidRateTableException::class);
        $this->expectExceptionMessageMatches('/nicht gefunden/');

        (new YamlRateTableLoader('/nonexistent/vat_rates.yaml'))->load();
    }

    public function testUnparsableYamlIsReported(): void
    {
        $path = $this->writeRaw("countries:\n  - [unbalanced\n");

        $this->expectException(InvalidRateTableException::class);
        $this->expectExceptionMessageMatches('/nicht parsebar/');

        (new YamlRateTableLoader($path))->load();
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $break
     */
    #[DataProvider('brokenTables')]
    public function testInvalidTablesAreRejected(string $expectedMessage, callable $break): void
    {
        $path = $this->write($break(self::validTable()));

        $this->expectException(InvalidRateTableException::class);
        $this->expectExceptionMessageMatches($expectedMessage);

        (new YamlRateTableLoader($path))->load();
    }

    /** @return iterable<string, array{string, callable}> */
    public static function brokenTables(): iterable
    {
        yield 'meta.as_of fehlt' => ['/`meta.as_of` fehlt/', static function (array $t): array {
            unset($t['meta']['as_of']);

            return $t;
        }];

        yield 'meta.as_of ist kein Datum' => ['/kein Datum/', static function (array $t): array {
            $t['meta']['as_of'] = 'irgendwann';

            return $t;
        }];

        yield 'meta.source fehlt' => ['/`meta.source` fehlt/', static function (array $t): array {
            unset($t['meta']['source']);

            return $t;
        }];

        yield 'countries fehlt' => ['/`countries` fehlt/', static function (array $t): array {
            unset($t['countries']);

            return $t;
        }];

        yield 'Ländercode kleingeschrieben' => ['/alpha-2-Code/', static function (array $t): array {
            $t['countries']['de'] = $t['countries']['DE'];
            unset($t['countries']['DE']);

            return $t;
        }];

        yield 'standard fehlt' => ['/`standard` fehlt/', static function (array $t): array {
            unset($t['countries']['DE']['standard']);

            return $t;
        }];

        yield 'standard negativ' => ['/plausiblen Bereichs/', static function (array $t): array {
            $t['countries']['DE']['standard'] = -19.0;

            return $t;
        }];

        yield 'standard absurd hoch' => ['/plausiblen Bereichs/', static function (array $t): array {
            $t['countries']['DE']['standard'] = 99.0;

            return $t;
        }];

        yield 'EU-Land unter dem Mindestnormalsatz' => ['/Mindestnormalsatz/', static function (array $t): array {
            $t['countries']['DE']['standard'] = 12.0;

            return $t;
        }];

        yield 'eu ist kein Boolean' => ['/`eu` fehlt oder ist nicht/', static function (array $t): array {
            $t['countries']['DE']['eu'] = 'ja';

            return $t;
        }];

        yield 'name fehlt' => ['/`name` fehlt/', static function (array $t): array {
            unset($t['countries']['DE']['name']);

            return $t;
        }];

        yield 'reduced ist keine Liste' => ['/keine Liste/', static function (array $t): array {
            $t['countries']['DE']['reduced'] = ['low' => 7.0];

            return $t;
        }];

        yield 'ein Mitgliedstaat fehlt' => ['/26 EU-Mitgliedstaaten/', static function (array $t): array {
            unset($t['countries']['MT']);

            return $t;
        }];

        yield 'ein Drittland ist als EU markiert' => ['/28 EU-Mitgliedstaaten/', static function (array $t): array {
            $t['countries']['US']['eu'] = true;
            $t['countries']['US']['standard'] = 20.0;

            return $t;
        }];
    }

    /**
     * A structurally valid table: the 27 member states plus one third country.
     * Rates are deliberately uniform placeholders — this fixture tests the loader,
     * not the tax law.
     *
     * @return array<string, mixed>
     */
    private static function validTable(): array
    {
        $members = [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU',
            'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
        ];

        $countries = [];

        foreach ($members as $code) {
            $countries[$code] = [
                'name' => $code,
                'eu' => true,
                'standard' => 20.0,
                'reduced' => [10.0],
                'super_reduced' => null,
                'parking' => null,
            ];
        }

        $countries['US'] = [
            'name' => 'United States',
            'eu' => false,
            'standard' => 0.0,
            'reduced' => [],
            'super_reduced' => null,
            'parking' => null,
        ];

        return [
            'meta' => ['as_of' => '2026-01-01', 'source' => 'Testtabelle'],
            'countries' => $countries,
        ];
    }

    /**
     * @param array<string, mixed> $table
     */
    private function write(array $table): string
    {
        return $this->writeRaw(Yaml::dump($table, 4));
    }

    private function writeRaw(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vat_rates_') . '.yaml';
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }
}
