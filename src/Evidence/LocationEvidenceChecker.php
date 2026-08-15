<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Evidence;

use Daumedia\VatResolver\Exception\ConflictingLocationEvidenceException;
use Daumedia\VatResolver\Exception\InsufficientLocationEvidenceException;

/**
 * Reconciles location indications into the one country we will tax in.
 *
 * The whole point of this class is what it does NOT do: when the indications
 * disagree it refuses to pick. Choosing the billing address would under-tax a
 * customer who lives elsewhere; choosing the IP country would over-tax someone
 * on holiday. Both are wrong, so the caller gets an exception carrying both
 * items and decides — usually by asking the customer.
 */
final class LocationEvidenceChecker
{
    /**
     * The two indications we actually have in a typical checkout.
     *
     * @throws ConflictingLocationEvidenceException
     * @throws InsufficientLocationEvidenceException when either code is empty
     */
    public function reconcile(LocationEvidence $billingAddress, LocationEvidence $ipCountry): string
    {
        return $this->agreedCountry([$billingAddress, $ipCountry]);
    }

    /**
     * @param list<LocationEvidence> $evidence
     *
     * @throws ConflictingLocationEvidenceException
     * @throws InsufficientLocationEvidenceException
     */
    public function agreedCountry(array $evidence, int $minimum = 2): string
    {
        $usable = array_values(array_filter(
            $evidence,
            static fn (LocationEvidence $e): bool => $e->countryCode !== '',
        ));

        if (\count($usable) < $minimum) {
            throw new InsufficientLocationEvidenceException(\count($usable), $minimum);
        }

        $countries = array_unique(array_map(
            static fn (LocationEvidence $e): string => $e->countryCode,
            $usable,
        ));

        if (\count($countries) > 1) {
            throw new ConflictingLocationEvidenceException($usable);
        }

        return reset($countries);
    }
}
