<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Unit\Vies;

use Daumedia\VatResolver\Vies\VatNumberFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VatNumberFormatTest extends TestCase
{
    #[DataProvider('wellFormedNumbers')]
    public function testWellFormedNumbersPass(string $country, string $number): void
    {
        self::assertTrue(VatNumberFormat::isWellFormed($country, $number));
    }

    /** @return iterable<string, array{string, string}> */
    public static function wellFormedNumbers(): iterable
    {
        yield 'LU mit Präfix' => ['LU', 'LU12345678'];
        yield 'LU ohne Präfix' => ['LU', '12345678'];
        yield 'DE' => ['DE', 'DE123456789'];
        yield 'DE mit Punkten' => ['DE', 'DE 123.456.789'];
        yield 'DE kleingeschrieben' => ['DE', 'de123456789'];
        yield 'NL mit B-Suffix' => ['NL', 'NL123456789B01'];
        yield 'FR alphanumerisches Präfix' => ['FR', 'FRXX123456789'];
        yield 'AT mit U' => ['AT', 'ATU12345678'];
        yield 'IE alte Form' => ['IE', 'IE1234567X'];
        yield 'IE neue Form' => ['IE', 'IE1234567XX'];
        yield 'BE' => ['BE', 'BE0123456789'];
        yield 'IT' => ['IT', 'IT12345678901'];
        yield 'ES Buchstabe + 7 Ziffern + Prüfzeichen' => ['ES', 'ESA1234567B'];
        yield 'SE zwölfstellig' => ['SE', 'SE123456789012'];
        yield 'Nordirland' => ['XI', 'XI123456789'];
    }

    #[DataProvider('malformedNumbers')]
    public function testMalformedNumbersAreRejected(string $country, string $number, string $why): void
    {
        self::assertFalse(VatNumberFormat::isWellFormed($country, $number), $why);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function malformedNumbers(): iterable
    {
        yield 'leer' => ['DE', '', 'Leere Eingabe.'];
        yield 'DE zu kurz' => ['DE', 'DE12345', 'Deutschland hat neun Ziffern.'];
        yield 'DE zu lang' => ['DE', 'DE1234567890', 'Deutschland hat neun Ziffern.'];
        yield 'DE mit Buchstaben' => ['DE', 'DEABCDEFGHI', 'Deutschland ist rein numerisch.'];
        yield 'Freitext' => ['DE', 'keine Ust-IdNr', 'Offensichtlicher Müll.'];
        yield 'NL ohne B' => ['NL', 'NL12345678901', 'Den Niederlanden fehlt das B.'];
        yield 'AT ohne U' => ['AT', 'AT12345678', 'Österreich beginnt national mit U.'];
        yield 'ES zu kurz' => ['ES', 'ESA1234567', 'Spanien hat neun Stellen, nicht acht.'];
        yield 'Drittland ohne Muster' => ['US', '123456789', 'Für Nicht-EU gibt es kein Muster.'];
        yield 'unbekanntes Land' => ['XX', '123456789', 'Unbekanntes Land.'];
    }

    #[DataProvider('normalisations')]
    public function testNormalizeStripsFormattingAndThePrefix(string $country, string $input, string $expected): void
    {
        self::assertSame($expected, VatNumberFormat::normalize($country, $input));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function normalisations(): iterable
    {
        yield 'Präfix entfällt' => ['DE', 'DE123456789', '123456789'];
        yield 'Leerzeichen' => ['DE', 'DE 123 456 789', '123456789'];
        yield 'Punkte' => ['DE', 'DE.123.456.789', '123456789'];
        yield 'Bindestriche' => ['DE', 'DE-123-456-789', '123456789'];
        yield 'Schrägstriche' => ['DE', 'DE/123/456/789', '123456789'];
        yield 'Kleinschreibung' => ['DE', 'de123456789', '123456789'];
        yield 'ohne Präfix bleibt gleich' => ['DE', '123456789', '123456789'];
        yield 'Aussenabstand' => ['DE', '  DE123456789  ', '123456789'];
    }

    public function testCanonicalAlwaysCarriesThePrefix(): void
    {
        // This is the form that goes on the invoice and into the cache key.
        self::assertSame('DE123456789', VatNumberFormat::canonical('DE', '123456789'));
        self::assertSame('DE123456789', VatNumberFormat::canonical('de', 'de 123.456.789'));
        self::assertSame('LU12345678', VatNumberFormat::canonical('LU', 'LU12345678'));
    }

    public function testAllTwentySevenMemberStatesHaveAPattern(): void
    {
        $members = [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU',
            'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
        ];

        foreach ($members as $code) {
            self::assertTrue(
                VatNumberFormat::hasPatternFor($code),
                sprintf('Für %s fehlt ein Muster — dessen USt-IdNr. würde immer abgelehnt.', $code),
            );
        }
    }
}
