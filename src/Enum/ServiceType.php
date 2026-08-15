<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Enum;

/**
 * What is being sold. This decides the place of supply, which decides the rate.
 *
 * The three cases are not a wish list — they are the three place-of-supply rules
 * that behave differently:
 *
 *  - DIGITAL        Art. 58 VAT Directive: supplied where the customer is.
 *  - PHYSICAL_EVENT Art. 53/54: supplied where the event physically happens, and
 *                   notably WITHOUT reverse charge even between businesses.
 *  - GOODS          Distance selling, different thresholds and paperwork.
 *
 * PERSISTED CONTRACT — see CustomerType.
 */
enum ServiceType: string
{
    /** Telecommunications, broadcasting and electronically supplied services (TBE). */
    case DIGITAL = 'digital';

    /** Admission to, or supply at, an event at a physical location. */
    case PHYSICAL_EVENT = 'physical_event';

    /** Physical goods. */
    case GOODS = 'goods';
}
