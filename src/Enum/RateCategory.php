<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Enum;

/**
 * The rate bands a member state may operate under the VAT Directive.
 *
 * Only STANDARD is reachable today: VatResolver::categoryFor() returns it for
 * every ServiceType. The rest exist so that adding a reduced-rate service later
 * is a change to one match arm rather than a change to the rate table format.
 */
enum RateCategory: string
{
    case STANDARD = 'standard';
    case REDUCED = 'reduced';
    case SUPER_REDUCED = 'super_reduced';
    case PARKING = 'parking';
    case ZERO = 'zero';
}
