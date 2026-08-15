<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Vies;

/**
 * A fixed set of answers, for tests and for local development.
 *
 * Not for production: it has no expiry and no way to learn.
 */
final class InMemoryViesValidator implements ViesValidatorInterface
{
    /** @var array<string, ViesCheck> */
    private array $answers = [];

    /**
     * @param array<string, bool> $validity canonical VAT number => valid
     */
    public function __construct(array $validity = [], private readonly ?\DateTimeImmutable $checkedAt = null)
    {
        foreach ($validity as $vatNumber => $valid) {
            $country = substr((string) $vatNumber, 0, 2);
            $this->set($country, (string) $vatNumber, $valid);
        }
    }

    public function set(string $countryCode, string $vatNumber, bool $valid): self
    {
        $this->answers[ViesCacheKey::for($countryCode, $vatNumber)] = new ViesCheck(
            valid: $valid,
            checkedAt: $this->checkedAt ?? new \DateTimeImmutable('2026-01-01 00:00:00'),
            consultationNumber: $valid ? 'WAPIAAAA' . strtoupper(substr(md5($vatNumber), 0, 8)) : null,
        );

        return $this;
    }

    public function check(string $countryCode, string $vatNumber): ?ViesCheck
    {
        return $this->answers[ViesCacheKey::for($countryCode, $vatNumber)] ?? null;
    }
}
