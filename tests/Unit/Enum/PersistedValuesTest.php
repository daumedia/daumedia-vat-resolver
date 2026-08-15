<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Unit\Enum;

use Daumedia\VatResolver\Enum\CustomerType;
use Daumedia\VatResolver\Enum\RateCategory;
use Daumedia\VatResolver\Enum\ServiceType;
use PHPUnit\Framework\TestCase;

/**
 * These strings live in consuming applications' database columns.
 *
 * Renaming a case value here is a schema change over there — a migration someone
 * else has to write, on data they cannot re-derive. This test is the tripwire:
 * if it fails, the change is not a refactoring and needs a migration note in the
 * changelog.
 */
final class PersistedValuesTest extends TestCase
{
    public function testCustomerTypeValues(): void
    {
        self::assertSame('b2c', CustomerType::B2C->value);
        self::assertSame('b2b', CustomerType::B2B->value);
        self::assertCount(2, CustomerType::cases());
    }

    public function testServiceTypeValues(): void
    {
        self::assertSame('digital', ServiceType::DIGITAL->value);
        self::assertSame('physical_event', ServiceType::PHYSICAL_EVENT->value);
        self::assertSame('goods', ServiceType::GOODS->value);
        self::assertCount(3, ServiceType::cases());
    }

    public function testRateCategoryValues(): void
    {
        self::assertSame('standard', RateCategory::STANDARD->value);
        self::assertSame('reduced', RateCategory::REDUCED->value);
        self::assertSame('super_reduced', RateCategory::SUPER_REDUCED->value);
        self::assertSame('parking', RateCategory::PARKING->value);
        self::assertSame('zero', RateCategory::ZERO->value);
        self::assertCount(5, RateCategory::cases());
    }

    public function testCustomerTypeKnowsWhichSideIsABusiness(): void
    {
        self::assertTrue(CustomerType::B2B->isBusiness());
        self::assertFalse(CustomerType::B2C->isBusiness());
    }
}
