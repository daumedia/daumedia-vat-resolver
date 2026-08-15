<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Exception;

use Daumedia\VatResolver\Enum\ServiceType;

/**
 * A ServiceType that has no place-of-supply rule implemented yet.
 *
 * GOODS is the case today: distance selling has its own thresholds and its own
 * paperwork, and half a rule is worse than none. Throwing keeps the gap visible
 * instead of quietly charging the wrong country's rate.
 */
final class UnsupportedServiceTypeException extends \LogicException implements VatResolverExceptionInterface
{
    public function __construct(public readonly ServiceType $serviceType)
    {
        parent::__construct(sprintf(
            'Für die Leistungsart „%s" ist noch keine Leistungsortregel hinterlegt.',
            $serviceType->value,
        ));
    }
}
