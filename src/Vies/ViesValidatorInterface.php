<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Vies;

/**
 * The request-path port: answers only from what is already known.
 *
 * Implementations of THIS interface must never perform blocking I/O. VIES is a
 * third-party service with third-party latency and third-party outages, and a
 * checkout that waits on it is a checkout that fails when Brussels does.
 *
 * The blocking side lives behind ViesLookupInterface, which no resolver ever
 * receives. Keeping the two apart in the type system — rather than in a comment —
 * is what makes "never blocks the request path" a fact instead of a promise.
 */
interface ViesValidatorInterface
{
    /**
     * @return ViesCheck|null null means "not known", never "not valid". Callers
     *                       must treat it conservatively: no reverse charge.
     */
    public function check(string $countryCode, string $vatNumber): ?ViesCheck;
}
