<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Vies;

/**
 * The background port: allowed to block, allowed to be slow.
 *
 * Deliberately NOT extending ViesValidatorInterface. If it did, a blocking
 * implementation would be assignable wherever the resolver expects its
 * non-blocking port, and the one rule this design exists to enforce would be one
 * autowiring mistake away from being broken.
 *
 * Only ViesCacheWarmer consumes this, and only ever from a worker or a CLI command.
 */
interface ViesLookupInterface
{
    /**
     * @return ViesCheck|null null when the service could not answer (network
     *                       error, malformed reply, VIES-side input error).
     *                       Never throws — an unreachable VIES is a known state,
     *                       not an exceptional one.
     */
    public function lookup(string $countryCode, string $vatNumber): ?ViesCheck;
}
