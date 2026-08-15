<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Exception;

/**
 * Fewer usable pieces of location evidence than the rule demands.
 *
 * For B2C digital supplies that minimum is two (Art. 24b of Implementing
 * Regulation (EU) 282/2011). One item is not "good enough" — it is the same
 * guess the regulation exists to prevent.
 */
final class InsufficientLocationEvidenceException extends \RuntimeException implements VatResolverExceptionInterface
{
    public function __construct(public readonly int $given, public readonly int $required)
    {
        parent::__construct(sprintf(
            'Nur %d Standortnachweis(e) vorhanden, benötigt werden %d.',
            $given,
            $required,
        ));
    }
}
