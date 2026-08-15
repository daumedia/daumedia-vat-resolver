<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Vies;

/**
 * How a cache miss asks to be resolved later.
 *
 * Without this, an unknown VAT number stays unknown forever: the request path
 * may not call VIES, and nothing else knows the number was ever asked about.
 * The queue closes that loop — the miss registers itself, a worker warms the
 * cache, and the next checkout finds a hit.
 *
 * Implementations must return immediately. In a Symfony application this is one
 * MessageBusInterface::dispatch().
 */
interface ViesLookupQueueInterface
{
    public function enqueue(string $countryCode, string $vatNumber): void;
}
