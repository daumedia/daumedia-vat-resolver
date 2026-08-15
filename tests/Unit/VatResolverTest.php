<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Unit;

use Daumedia\VatResolver\Enum\CustomerType;
use Daumedia\VatResolver\Enum\ServiceType;
use Daumedia\VatResolver\Exception\UnknownCountryException;
use Daumedia\VatResolver\Exception\UnsupportedServiceTypeException;
use Daumedia\VatResolver\Rates\RateTable;
use Daumedia\VatResolver\Rates\YamlRateTableLoader;
use Daumedia\VatResolver\Tests\Double\SpyViesValidator;
use Daumedia\VatResolver\VatResolver;
use Daumedia\VatResolver\Vies\InMemoryViesValidator;
use Daumedia\VatResolver\Vies\NullViesValidator;
use Daumedia\VatResolver\Vies\ViesValidatorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VatResolverTest extends TestCase
{
    private static ?RateTable $rates = null;

    private function rates(): RateTable
    {
        return self::$rates ??= (new YamlRateTableLoader())->load();
    }

    private function resolver(?ViesValidatorInterface $vies = null, string $home = 'LU'): VatResolver
    {
        return new VatResolver($this->rates(), $vies ?? new NullViesValidator(), $home);
    }

    // --- Inland ---------------------------------------------------------

    public function testDomesticB2cIsChargedTheHomeRate(): void
    {
        $result = $this->resolver()->resolve('LU', CustomerType::B2C, null, ServiceType::DIGITAL);

        self::assertSame(17.0, $result->rate);
        self::assertFalse($result->reverseCharge);
        self::assertSame('LU', $result->countryCode);
        self::assertStringContainsString('Inlandsumsatz LU', $result->legalBasis);
    }

    public function testDomesticB2bIsAlsoChargedTheHomeRateEvenWithAValidVatNumber(): void
    {
        // Reverse charge does not exist between two parties in the same country.
        $vies = new InMemoryViesValidator(['LU12345678' => true]);

        $result = $this->resolver($vies)->resolve('LU', CustomerType::B2B, 'LU12345678', ServiceType::DIGITAL);

        self::assertSame(17.0, $result->rate);
        self::assertFalse($result->reverseCharge);
    }

    public function testTheHomeCountryIsConfigurableAndNotBakedIn(): void
    {
        $result = $this->resolver(home: 'DE')->resolve('DE', CustomerType::B2C, null, ServiceType::DIGITAL);

        self::assertSame(19.0, $result->rate);
        self::assertStringContainsString('Inlandsumsatz DE', $result->legalBasis);
    }

    // --- EU B2C ---------------------------------------------------------

    #[DataProvider('euB2cRates')]
    public function testEuB2cPaysTheirOwnCountrysRate(string $country, float $expected): void
    {
        $result = $this->resolver()->resolve($country, CustomerType::B2C, null, ServiceType::DIGITAL);

        self::assertSame($expected, $result->rate);
        self::assertFalse($result->reverseCharge);
        self::assertStringContainsString('Art. 58', $result->legalBasis);
    }

    /** @return iterable<string, array{string, float}> */
    public static function euB2cRates(): iterable
    {
        yield 'Deutschland' => ['DE', 19.0];
        yield 'Frankreich' => ['FR', 20.0];
        yield 'Ungarn (höchster Satz)' => ['HU', 27.0];
        // Finland is the reason the interface uses float and not int.
        yield 'Finnland (Dezimalsatz)' => ['FI', 25.5];
    }

    // --- EU B2B / Reverse Charge ---------------------------------------

    public function testEuB2bWithAConfirmedVatNumberIsReverseCharged(): void
    {
        $vies = new InMemoryViesValidator(['DE123456789' => true]);

        $result = $this->resolver($vies)->resolve('DE', CustomerType::B2B, 'DE123456789', ServiceType::DIGITAL);

        self::assertSame(0.0, $result->rate);
        self::assertTrue($result->reverseCharge);
        self::assertStringContainsString('Art. 196', $result->legalBasis);
    }

    public function testTheReverseChargeBasisNamesTheNumberDateAndConsultationNumber(): void
    {
        // This string ends up on the invoice and in front of an auditor.
        $vies = new InMemoryViesValidator(['DE123456789' => true]);

        $result = $this->resolver($vies)->resolve('DE', CustomerType::B2B, 'de 123.456.789', ServiceType::DIGITAL);

        self::assertStringContainsString('DE123456789', $result->legalBasis);
        self::assertStringContainsString('01.01.2026', $result->legalBasis);
        self::assertStringContainsString('Konsultationsnummer', $result->legalBasis);
    }

    public function testEuB2bWithAVatNumberViesRejectsPaysTheCustomerCountryRate(): void
    {
        $vies = new InMemoryViesValidator(['DE123456789' => false]);

        $result = $this->resolver($vies)->resolve('DE', CustomerType::B2B, 'DE123456789', ServiceType::DIGITAL);

        self::assertSame(19.0, $result->rate);
        self::assertFalse($result->reverseCharge);
        self::assertStringContainsString('nicht bestätigt', $result->legalBasis);
    }

    public function testAnUnknownVatNumberIsTreatedAsUnconfirmedRatherThanValid(): void
    {
        // The conservative direction: too much VAT is a correction, too little is our debt.
        $result = $this->resolver(new NullViesValidator())
            ->resolve('DE', CustomerType::B2B, 'DE123456789', ServiceType::DIGITAL);

        self::assertSame(19.0, $result->rate);
        self::assertFalse($result->reverseCharge);
        self::assertStringContainsString('nicht bestätigt', $result->legalBasis);
    }

    // --- VIES darf gar nicht erst gefragt werden ------------------------

    public function testViesIsNeverConsultedForAB2cCustomer(): void
    {
        $spy = new SpyViesValidator(new NullViesValidator());

        $this->resolver($spy)->resolve('DE', CustomerType::B2C, 'DE123456789', ServiceType::DIGITAL);

        self::assertSame(0, $spy->calls);
    }

    public function testViesIsNeverConsultedWithoutAVatNumber(): void
    {
        $spy = new SpyViesValidator(new NullViesValidator());

        $this->resolver($spy)->resolve('DE', CustomerType::B2B, null, ServiceType::DIGITAL);

        self::assertSame(0, $spy->calls);
    }

    public function testViesIsNeverConsultedForASyntacticallyImpossibleNumber(): void
    {
        $spy = new SpyViesValidator(new NullViesValidator());

        $this->resolver($spy)->resolve('DE', CustomerType::B2B, 'not-a-vat-number', ServiceType::DIGITAL);

        self::assertSame(0, $spy->calls);
    }

    // --- Drittland ------------------------------------------------------

    #[DataProvider('thirdCountries')]
    public function testThirdCountriesOweNoEuVat(string $country, CustomerType $type): void
    {
        $result = $this->resolver()->resolve($country, $type, null, ServiceType::DIGITAL);

        self::assertSame(0.0, $result->rate);
        self::assertFalse($result->reverseCharge, 'Drittland ist kein Reverse Charge — es ist gar nicht steuerbar.');
        self::assertStringContainsString('Drittland', $result->legalBasis);
    }

    /** @return iterable<string, array{string, CustomerType}> */
    public static function thirdCountries(): iterable
    {
        yield 'USA B2C' => ['US', CustomerType::B2C];
        yield 'Schweiz B2B' => ['CH', CustomerType::B2B];
        yield 'UK B2C' => ['GB', CustomerType::B2C];
    }

    // --- Leistungsort Veranstaltung -------------------------------------

    public function testAPhysicalEventIsTaxedWhereItHappensAndNeverReverseCharged(): void
    {
        $vies = new InMemoryViesValidator(['DE123456789' => true]);

        $result = $this->resolver($vies)->resolve('DE', CustomerType::B2B, 'DE123456789', ServiceType::PHYSICAL_EVENT);

        self::assertSame(19.0, $result->rate);
        self::assertFalse($result->reverseCharge);
        self::assertStringContainsString('Art. 53', $result->legalBasis);
    }

    public function testGoodsHaveNoRuleYetAndSaySoLoudly(): void
    {
        $this->expectException(UnsupportedServiceTypeException::class);

        $this->resolver()->resolve('DE', CustomerType::B2C, null, ServiceType::GOODS);
    }

    // --- Eingabenormalisierung ------------------------------------------

    #[DataProvider('sloppyCountryInput')]
    public function testCountryInputIsNormalised(string $input): void
    {
        $result = $this->resolver()->resolve($input, CustomerType::B2C, null, ServiceType::DIGITAL);

        self::assertSame('DE', $result->countryCode);
        self::assertSame(19.0, $result->rate);
    }

    /** @return iterable<string, array{string}> */
    public static function sloppyCountryInput(): iterable
    {
        yield 'kleingeschrieben' => ['de'];
        yield 'mit Leerzeichen' => [' DE '];
        yield 'gemischt' => [' De'];
    }

    public function testAnUnknownCountryThrowsInsteadOfBeingTreatedAsThirdCountry(): void
    {
        // Guessing here would silently zero-rate a taxable sale.
        $this->expectException(UnknownCountryException::class);

        $this->resolver()->resolve('XX', CustomerType::B2C, null, ServiceType::DIGITAL);
    }

    public function testAnEmptyCountryThrows(): void
    {
        $this->expectException(UnknownCountryException::class);

        $this->resolver()->resolve('', CustomerType::B2C, null, ServiceType::DIGITAL);
    }
}
