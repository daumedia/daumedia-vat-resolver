<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Unit;

use Daumedia\VatResolver\VatResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VatResultTest extends TestCase
{
    /**
     * The one convention question that costs a factor of 100 if it drifts:
     * 17 % is 17.0, never 0.17.
     */
    public function testTheRateIsInPercentagePointsNotAFraction(): void
    {
        $result = new VatResult(17.0, false, 'irrelevant', 'LU');

        self::assertSame(17.0, $result->rate);
        self::assertSame(170, $result->taxCentsOn(1000), '10,00 € zu 17 % sind 1,70 €.');
    }

    #[DataProvider('basisPointConversions')]
    public function testBasisPointsIsTheStorableInteger(float $rate, int $expected): void
    {
        self::assertSame($expected, (new VatResult($rate, false, '', 'XX'))->basisPoints());
    }

    /** @return iterable<string, array{float, int}> */
    public static function basisPointConversions(): iterable
    {
        yield 'Luxemburg' => [17.0, 1700];
        yield 'Deutschland' => [19.0, 1900];
        yield 'Finnland mit Dezimalstelle' => [25.5, 2550];
        yield 'Reverse Charge' => [0.0, 0];
        yield 'ermässigt mit halber Stelle' => [9.5, 950];
    }

    #[DataProvider('taxCalculations')]
    public function testTaxOnANetAmount(int $netCents, float $rate, int $expectedTax): void
    {
        $result = new VatResult($rate, false, '', 'XX');

        self::assertSame($expectedTax, $result->taxCentsOn($netCents));
        self::assertSame($netCents + $expectedTax, $result->grossCentsOn($netCents));
    }

    /** @return iterable<string, array{int, float, int}> */
    public static function taxCalculations(): iterable
    {
        yield 'Starter monatlich, LU' => [1900, 17.0, 323];
        yield 'Starter monatlich, DE' => [1900, 19.0, 361];
        yield 'Pro monatlich, LU' => [5900, 17.0, 1003];
        yield 'Pro jährlich, FI' => [59000, 25.5, 15045];
        yield 'krummer Betrag, DE' => [1999, 19.0, 380];
        yield 'Reverse Charge rechnet nichts' => [5900, 0.0, 0];
        yield 'Nullbetrag' => [0, 19.0, 0];
    }

    /**
     * Half a cent rounds away from zero, which is PHP's round() default and what
     * tax authorities expect on the invoice total. Pinned down because a switch to
     * banker's rounding would be invisible until an audit.
     */
    #[DataProvider('halfCentCases')]
    public function testHalfCentsRoundAwayFromZero(int $netCents, float $rate, int $expected): void
    {
        self::assertSame($expected, (new VatResult($rate, false, '', 'XX'))->taxCentsOn($netCents));
    }

    /** @return iterable<string, array{int, float, int}> */
    public static function halfCentCases(): iterable
    {
        yield '0,50 € zu 17 % = 8,5 ct' => [50, 17.0, 9];
        yield '1,50 € zu 17 % = 25,5 ct' => [150, 17.0, 26];
        yield '0,50 € zu 19 % = 9,5 ct' => [50, 19.0, 10];
    }

    public function testIsTaxableSeparatesAZeroRateFromARealOne(): void
    {
        self::assertTrue((new VatResult(17.0, false, '', 'LU'))->isTaxable());
        self::assertFalse((new VatResult(0.0, true, '', 'DE'))->isTaxable());
        self::assertFalse((new VatResult(0.0, false, '', 'US'))->isTaxable());
    }
}
