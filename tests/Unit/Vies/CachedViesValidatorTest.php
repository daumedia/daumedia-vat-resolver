<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Unit\Vies;

use Daumedia\VatResolver\Tests\Double\ArrayCache;
use Daumedia\VatResolver\Tests\Double\RecordingLookupQueue;
use Daumedia\VatResolver\Vies\CachedViesValidator;
use Daumedia\VatResolver\Vies\ViesCacheKey;
use Daumedia\VatResolver\Vies\ViesCheck;
use PHPUnit\Framework\TestCase;

final class CachedViesValidatorTest extends TestCase
{
    private ArrayCache $cache;
    private RecordingLookupQueue $queue;

    protected function setUp(): void
    {
        $this->cache = new ArrayCache();
        $this->queue = new RecordingLookupQueue();
    }

    private function validator(): CachedViesValidator
    {
        return new CachedViesValidator($this->cache, $this->queue);
    }

    private function seed(string $country, string $number, bool $valid): void
    {
        $this->cache->set(
            ViesCacheKey::for($country, $number),
            (new ViesCheck($valid, new \DateTimeImmutable('2026-02-01 10:00:00'), 'WAPIAAAA123'))->toArray(),
        );
    }

    public function testACachedConfirmationIsReturnedWithItsAuditTrail(): void
    {
        $this->seed('DE', 'DE123456789', true);

        $check = $this->validator()->check('DE', 'DE123456789');

        self::assertNotNull($check);
        self::assertTrue($check->valid);
        self::assertSame('WAPIAAAA123', $check->consultationNumber);
        self::assertSame('2026-02-01', $check->checkedAt->format('Y-m-d'));
    }

    public function testACachedRejectionIsReturnedAsInvalidRatherThanUnknown(): void
    {
        $this->seed('DE', 'DE123456789', false);

        $check = $this->validator()->check('DE', 'DE123456789');

        self::assertNotNull($check, 'Eine bekannte Ablehnung ist nicht dasselbe wie „unbekannt".');
        self::assertFalse($check->valid);
    }

    public function testAMissAnswersUnknownAndAsksForTheNumberToBeLookedUp(): void
    {
        $check = $this->validator()->check('DE', 'DE123456789');

        self::assertNull($check);
        self::assertSame(1, $this->queue->count());
        self::assertSame(['country' => 'DE', 'vatNumber' => 'DE123456789'], $this->queue->enqueued[0]);
    }

    public function testTheCacheIsWrittenToTheKeyTheWarmerUses(): void
    {
        // Reader and warmer live in different processes. A key mismatch would
        // never throw — it would just charge VAT forever.
        $this->seed('DE', 'DE123456789', true);

        self::assertTrue($this->cache->has(ViesCacheKey::for('DE', 'de 123.456.789')));
    }

    public function testKeysIgnoreFormattingOfTheSameNumber(): void
    {
        $this->seed('DE', 'DE123456789', true);

        self::assertNotNull($this->validator()->check('DE', 'de 123 456 789'));
        self::assertNotNull($this->validator()->check('DE', '123456789'));
        self::assertSame(0, $this->queue->count(), 'Ein Treffer darf nichts in die Queue legen.');
    }

    public function testAMalformedNumberNeverReachesTheCacheOrTheQueue(): void
    {
        // Otherwise anyone could fill the worker queue from a public form.
        $check = $this->validator()->check('DE', 'DE-nonsense');

        self::assertNull($check);
        self::assertSame(0, $this->cache->getCalls);
        self::assertSame(0, $this->queue->count());
    }

    public function testAnUnsupportedCountryIsRejectedWithoutTouchingTheCache(): void
    {
        $check = $this->validator()->check('US', '123456789');

        self::assertNull($check);
        self::assertSame(0, $this->cache->getCalls);
    }

    public function testAStalePayloadFormatIsTreatedAsAMiss(): void
    {
        // A shared cache can hold whatever an older deploy wrote.
        $this->cache->poison(ViesCacheKey::for('DE', 'DE123456789'), ['unexpected' => 'shape']);

        self::assertNull($this->validator()->check('DE', 'DE123456789'));
        self::assertSame(1, $this->queue->count(), 'Unlesbares soll neu geholt werden.');
    }

    public function testABareBooleanInTheCacheIsTreatedAsAMiss(): void
    {
        $this->cache->poison(ViesCacheKey::for('DE', 'DE123456789'), true);

        self::assertNull($this->validator()->check('DE', 'DE123456789'));
    }

    public function testItWorksWithoutAQueue(): void
    {
        // The queue is optional; a project without a worker still gets safe answers.
        $validator = new CachedViesValidator($this->cache);

        self::assertNull($validator->check('DE', 'DE123456789'));
    }
}
