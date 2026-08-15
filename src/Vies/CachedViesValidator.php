<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Vies;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as CacheInvalidArgumentException;

/**
 * The request-path validator: reads the cache, never the network.
 *
 * Stage two of three. On a hit it answers; on a miss it answers "unknown" AND
 * registers the number for a background lookup, so the miss fixes itself instead
 * of repeating forever.
 *
 * Consequence worth stating plainly: the FIRST order from a never-before-seen
 * business customer is charged VAT, because at that moment we cannot evidence
 * the reverse charge. That is the correct direction of error — an invoice with
 * too much VAT is a correction, one with too little is our tax debt.
 */
final readonly class CachedViesValidator implements ViesValidatorInterface
{
    public function __construct(
        private CacheInterface $cache,
        private ?ViesLookupQueueInterface $queue = null,
    ) {
    }

    public function check(string $countryCode, string $vatNumber): ?ViesCheck
    {
        // Malformed input never reaches the cache or the queue: it cannot become
        // valid, and letting it through would let anyone fill the worker queue.
        if (!VatNumberFormat::isWellFormed($countryCode, $vatNumber)) {
            return null;
        }

        $key = ViesCacheKey::for($countryCode, $vatNumber);

        try {
            $cached = $this->cache->get($key);
        } catch (CacheInvalidArgumentException) {
            // A cache backend that rejects our key is a bug, but not one worth
            // failing a checkout over. Behave like a miss.
            $cached = null;
        }

        $check = ViesCheck::fromArray($cached);

        if ($check !== null) {
            return $check;
        }

        // Miss, or a payload we no longer understand. Ask for it to be warmed.
        $this->queue?->enqueue($countryCode, $vatNumber);

        return null;
    }
}
