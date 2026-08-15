<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Unit\Rates;

use Daumedia\VatResolver\Rates\YamlRateTableLoader;
use PHPUnit\Framework\TestCase;

/**
 * The one deliberately time-dependent test in this package.
 *
 * VAT rates drift — Finland went to 25.5 %, Estonia to 24 %, Slovakia to 23 %,
 * all within about eighteen months. Nothing in the code notices, because a stale
 * rate is a perfectly valid number; it just produces wrong invoices quietly.
 *
 * So this test goes red on its own once the table passes its shelf life. That is
 * the feature. When it fails: check the rates against the source named in
 * meta.source, correct what moved, and set meta.as_of to today.
 */
final class RateTableFreshnessTest extends TestCase
{
    private const int MAX_AGE_DAYS = 400;

    public function testTheShippedRateTableIsNotStale(): void
    {
        $table = (new YamlRateTableLoader())->load();
        $now = new \DateTimeImmutable();

        self::assertFalse(
            $table->isStale($now, self::MAX_AGE_DAYS),
            sprintf(
                'Die Satztabelle ist auf dem Stand %s und damit älter als %d Tage. '
                . 'Sätze gegen „%s" prüfen und meta.as_of aktualisieren.',
                $table->asOf->format('Y-m-d'),
                self::MAX_AGE_DAYS,
                $table->source,
            ),
        );
    }

    public function testStalenessIsMeasuredFromAsOf(): void
    {
        $table = (new YamlRateTableLoader())->load();

        $justInside = $table->asOf->modify(sprintf('+%d days', self::MAX_AGE_DAYS));
        $justOutside = $table->asOf->modify(sprintf('+%d days', self::MAX_AGE_DAYS + 1));

        self::assertFalse($table->isStale($justInside, self::MAX_AGE_DAYS));
        self::assertTrue($table->isStale($justOutside, self::MAX_AGE_DAYS));
    }
}
