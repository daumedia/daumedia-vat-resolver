<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Double;

use Daumedia\VatResolver\Vies\ViesLookupQueueInterface;

/**
 * Records what the request path asked to have looked up later.
 */
final class RecordingLookupQueue implements ViesLookupQueueInterface
{
    /** @var list<array{country: string, vatNumber: string}> */
    public array $enqueued = [];

    public function enqueue(string $countryCode, string $vatNumber): void
    {
        $this->enqueued[] = ['country' => $countryCode, 'vatNumber' => $vatNumber];
    }

    public function count(): int
    {
        return \count($this->enqueued);
    }
}
