<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Rates;

use Daumedia\VatResolver\Exception\InvalidRateTableException;

interface RateTableLoaderInterface
{
    /**
     * @throws InvalidRateTableException
     */
    public function load(): RateTable;
}
