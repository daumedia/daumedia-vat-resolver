<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Unit\Evidence;

use Daumedia\VatResolver\Evidence\LocationEvidence;
use Daumedia\VatResolver\Evidence\LocationEvidenceChecker;
use Daumedia\VatResolver\Exception\ConflictingLocationEvidenceException;
use Daumedia\VatResolver\Exception\InsufficientLocationEvidenceException;
use PHPUnit\Framework\TestCase;

final class LocationEvidenceCheckerTest extends TestCase
{
    private LocationEvidenceChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new LocationEvidenceChecker();
    }

    public function testAgreeingIndicationsGiveTheCountry(): void
    {
        $country = $this->checker->reconcile(
            LocationEvidence::billingAddress('DE'),
            LocationEvidence::ipGeolocation('DE'),
        );

        self::assertSame('DE', $country);
    }

    /**
     * The whole reason this class exists. Picking the billing address would
     * under-tax someone who lives elsewhere; picking the IP country would
     * over-tax someone on holiday. Both are wrong, so neither is chosen.
     */
    public function testContradictingIndicationsThrowInsteadOfBeingGuessed(): void
    {
        $this->expectException(ConflictingLocationEvidenceException::class);

        $this->checker->reconcile(
            LocationEvidence::billingAddress('DE'),
            LocationEvidence::ipGeolocation('AT'),
        );
    }

    public function testTheConflictCarriesBothIndicationsSoAHumanCanResolveIt(): void
    {
        try {
            $this->checker->reconcile(
                LocationEvidence::billingAddress('DE'),
                LocationEvidence::ipGeolocation('AT'),
            );
            self::fail('Es hätte eine Exception kommen müssen.');
        } catch (ConflictingLocationEvidenceException $e) {
            self::assertCount(2, $e->evidence);
            self::assertSame('billing_address', $e->evidence[0]->source);
            self::assertSame('DE', $e->evidence[0]->countryCode);
            self::assertSame('ip_geolocation', $e->evidence[1]->source);
            self::assertSame('AT', $e->evidence[1]->countryCode);
            self::assertStringContainsString('billing_address=DE', $e->getMessage());
            self::assertStringContainsString('ip_geolocation=AT', $e->getMessage());
        }
    }

    public function testASingleIndicationIsNotEnough(): void
    {
        // Art. 24b of Implementing Regulation (EU) 282/2011 wants two.
        $this->expectException(InsufficientLocationEvidenceException::class);

        $this->checker->agreedCountry([LocationEvidence::billingAddress('DE')]);
    }

    public function testAnEmptyCountryCodeDoesNotCountAsAnIndication(): void
    {
        // An unknown IP country is missing evidence, not agreement.
        $this->expectException(InsufficientLocationEvidenceException::class);

        $this->checker->reconcile(
            LocationEvidence::billingAddress('DE'),
            LocationEvidence::ipGeolocation(''),
        );
    }

    public function testTheExceptionReportsHowManyWereGivenAndHowManyAreNeeded(): void
    {
        try {
            $this->checker->agreedCountry([LocationEvidence::billingAddress('DE')]);
            self::fail('Es hätte eine Exception kommen müssen.');
        } catch (InsufficientLocationEvidenceException $e) {
            self::assertSame(1, $e->given);
            self::assertSame(2, $e->required);
        }
    }

    public function testCaseAndWhitespaceDoNotCreateAFalseConflict(): void
    {
        $country = $this->checker->reconcile(
            LocationEvidence::billingAddress(' de '),
            LocationEvidence::ipGeolocation('DE'),
        );

        self::assertSame('DE', $country);
    }

    public function testMoreThanTwoAgreeingIndicationsAreFine(): void
    {
        $country = $this->checker->agreedCountry([
            LocationEvidence::billingAddress('FR'),
            LocationEvidence::ipGeolocation('FR'),
            new LocationEvidence('bank_country', 'FR'),
        ]);

        self::assertSame('FR', $country);
    }

    public function testOneDissenterAmongManyStillBlocks(): void
    {
        $this->expectException(ConflictingLocationEvidenceException::class);

        $this->checker->agreedCountry([
            LocationEvidence::billingAddress('FR'),
            LocationEvidence::ipGeolocation('FR'),
            new LocationEvidence('bank_country', 'BE'),
        ]);
    }

    public function testTheMinimumIsConfigurableForRulesThatNeedOnlyOne(): void
    {
        $country = $this->checker->agreedCountry([LocationEvidence::billingAddress('IT')], minimum: 1);

        self::assertSame('IT', $country);
    }
}
