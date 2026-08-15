<?php

declare(strict_types=1);

namespace Daumedia\VatResolver;

use Daumedia\VatResolver\Enum\CustomerType;
use Daumedia\VatResolver\Enum\ServiceType;
use Daumedia\VatResolver\Exception\UnknownCountryException;
use Daumedia\VatResolver\Exception\UnsupportedServiceTypeException;

interface VatResolverInterface
{
    /**
     * @param string       $customerCountry ISO 3166-1 alpha-2; case and surrounding whitespace are forgiven
     * @param string|null  $vatNumber       with or without country prefix; only consulted for B2B
     *
     * @throws UnknownCountryException       the code is malformed or absent from the rate table
     * @throws UnsupportedServiceTypeException no place-of-supply rule for that service type yet
     */
    public function resolve(
        string $customerCountry,
        CustomerType $type,
        ?string $vatNumber,
        ServiceType $serviceType,
    ): VatResult;
}
