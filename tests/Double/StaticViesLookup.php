<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Double;

use Daumedia\VatResolver\Vies\ViesCheck;
use Daumedia\VatResolver\Vies\ViesLookupInterface;

/**
 * A blocking-port stand-in for ViesCacheWarmer tests.
 */
final class StaticViesLookup implements ViesLookupInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly ?ViesCheck $answer,
        private readonly ?\Throwable $throw = null,
    ) {
    }

    public function lookup(string $countryCode, string $vatNumber): ?ViesCheck
    {
        ++$this->calls;

        if ($this->throw !== null) {
            throw $this->throw;
        }

        return $this->answer;
    }
}
