<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Exception;

use Daumedia\VatResolver\Evidence\LocationEvidence;

/**
 * Two pieces of location evidence point at different countries.
 *
 * Art. 24b of Implementing Regulation (EU) 282/2011 wants two non-contradictory
 * items. When they contradict, both possible answers are wrong, so this throws
 * instead of picking one. Both items are carried on the exception so the caller
 * can log them and queue the case for a human.
 */
final class ConflictingLocationEvidenceException extends \RuntimeException implements VatResolverExceptionInterface
{
    /**
     * @param list<LocationEvidence> $evidence
     */
    public function __construct(public readonly array $evidence)
    {
        $described = array_map(
            static fn (LocationEvidence $e): string => sprintf('%s=%s', $e->source, $e->countryCode),
            $evidence,
        );

        parent::__construct(sprintf(
            'Widersprüchliche Standortnachweise: %s. Es wird kein Land geraten.',
            implode(', ', $described),
        ));
    }
}
