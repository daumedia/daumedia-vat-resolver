<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Exception;

/**
 * The rate table file is unusable.
 *
 * Thrown at load time, loudly and with the offending key named. A wrong rate is
 * an invoice that has to be corrected later, so this fails the boot rather than
 * limping along with a partial table.
 */
final class InvalidRateTableException extends \RuntimeException implements VatResolverExceptionInterface
{
    public static function at(string $path, string $problem): self
    {
        return new self(sprintf('Satztabelle „%s" ist ungültig: %s', $path, $problem));
    }

    public static function forCountry(string $path, string $country, string $problem): self
    {
        return new self(sprintf('Satztabelle „%s", Land „%s": %s', $path, $country, $problem));
    }

    public static function missingCategory(string $country, string $category): self
    {
        return new self(sprintf(
            'Land „%s" kennt keinen Satz der Kategorie „%s". Es wird kein Ersatzsatz geraten.',
            $country,
            $category,
        ));
    }
}
