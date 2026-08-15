<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Unit\Vies;

use Daumedia\VatResolver\Tests\Double\ArrayCache;
use Daumedia\VatResolver\Tests\Double\StaticViesLookup;
use Daumedia\VatResolver\Vies\CachedViesValidator;
use Daumedia\VatResolver\Vies\ViesCacheWarmer;
use Daumedia\VatResolver\Vies\ViesCheck;
use PHPUnit\Framework\TestCase;

final class ViesCacheWarmerTest extends TestCase
{
    private ArrayCache $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCache();
    }

    /**
     * The test that matters most in this file: what the warmer writes, the
     * request-path reader must find. A mismatch fails silently in production —
     * every checkout keeps charging VAT and no error is ever logged.
     */
    public function testWhatTheWarmerWritesTheValidatorReads(): void
    {
        $answer = new ViesCheck(true, new \DateTimeImmutable('2026-03-05 12:00:00'), 'WAPIAAAAXYZ', 'Muster GmbH');
        $warmer = new ViesCacheWarmer(new StaticViesLookup($answer), $this->cache);

        $warmer->warm('DE', 'DE123456789');

        $readBack = (new CachedViesValidator($this->cache))->check('DE', 'DE123456789');

        self::assertNotNull($readBack, 'Warmer und Reader benutzen nicht denselben Schlüssel.');
        self::assertTrue($readBack->valid);
        self::assertSame('WAPIAAAAXYZ', $readBack->consultationNumber);
        self::assertSame('Muster GmbH', $readBack->name);
        self::assertSame('2026-03-05 12:00:00', $readBack->checkedAt->format('Y-m-d H:i:s'));
    }

    public function testARejectionIsCachedToo(): void
    {
        // Otherwise every checkout re-queues the same known-bad number.
        $answer = new ViesCheck(false, new \DateTimeImmutable('2026-03-05 12:00:00'));
        $warmer = new ViesCacheWarmer(new StaticViesLookup($answer), $this->cache);

        $warmer->warm('DE', 'DE123456789');

        $readBack = (new CachedViesValidator($this->cache))->check('DE', 'DE123456789');
        self::assertNotNull($readBack);
        self::assertFalse($readBack->valid);
    }

    public function testAnUnreachableViesCachesNothing(): void
    {
        // A five-minute outage must not freeze into thirty days of "unknown".
        $warmer = new ViesCacheWarmer(new StaticViesLookup(null), $this->cache);

        self::assertNull($warmer->warm('DE', 'DE123456789'));
        self::assertSame(0, $this->cache->setCalls);
    }

    public function testAMalformedNumberIsNotEvenLookedUp(): void
    {
        $lookup = new StaticViesLookup(new ViesCheck(true, new \DateTimeImmutable()));
        $warmer = new ViesCacheWarmer($lookup, $this->cache);

        self::assertNull($warmer->warm('DE', 'total kaputt'));
        self::assertSame(0, $lookup->calls);
        self::assertSame(0, $this->cache->setCalls);
    }

    public function testTheTtlIsConfigurableAndDefaultsToThirtyDays(): void
    {
        self::assertSame(2_592_000, ViesCacheWarmer::DEFAULT_TTL_SECONDS);
    }
}
