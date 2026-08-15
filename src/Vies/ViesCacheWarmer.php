<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Vies;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as CacheInvalidArgumentException;

/**
 * Stage three: the only thing in this library that is allowed to wait on VIES.
 *
 * Runs in a worker or a CLI command, never in a web request. Writes under exactly
 * the key CachedViesValidator reads — that round trip is covered by a test,
 * because a key mismatch fails silently and expensively (every checkout keeps
 * charging VAT and nobody sees an error).
 */
final readonly class ViesCacheWarmer
{
    public const int DEFAULT_TTL_SECONDS = 2_592_000; // 30 days

    public function __construct(
        private ViesLookupInterface $upstream,
        private CacheInterface $cache,
        private int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ) {
    }

    /**
     * @return ViesCheck|null null when VIES could not answer. Nothing is cached in
     *                       that case: a temporary outage must not freeze into a
     *                       month of "unknown".
     */
    public function warm(string $countryCode, string $vatNumber): ?ViesCheck
    {
        if (!VatNumberFormat::isWellFormed($countryCode, $vatNumber)) {
            return null;
        }

        $check = $this->upstream->lookup($countryCode, $vatNumber);

        if ($check === null) {
            return null;
        }

        try {
            $this->cache->set(
                ViesCacheKey::for($countryCode, $vatNumber),
                $check->toArray(),
                $this->ttlSeconds,
            );
        } catch (CacheInvalidArgumentException) {
            // The answer is still good even if we failed to file it.
        }

        return $check;
    }
}
