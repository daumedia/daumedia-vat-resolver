<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Vies;

/**
 * The outcome of a VIES consultation, with its audit trail.
 *
 * A bare bool would have been enough to decide the rate, and not enough to
 * defend it: zero-rating a supply under Art. 196 has to be evidenced, and the
 * evidence is *when* the number was confirmed and under which consultation
 * number. Carrying both here means the caller can persist them alongside the
 * invoice instead of bolting a second lookup on later.
 */
final readonly class ViesCheck
{
    public function __construct(
        public bool $valid,
        public \DateTimeImmutable $checkedAt,
        public ?string $consultationNumber = null,
        public ?string $name = null,
        public ?string $address = null,
    ) {
    }

    /**
     * @return array{valid: bool, checked_at: string, consultation_number: ?string, name: ?string, address: ?string}
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'checked_at' => $this->checkedAt->format(\DateTimeInterface::ATOM),
            'consultation_number' => $this->consultationNumber,
            'name' => $this->name,
            'address' => $this->address,
        ];
    }

    /**
     * Rebuilds a check from its cached form. Returns null for anything that does
     * not look like our own payload — a cache is shared infrastructure and may
     * hold a stale format from an older deploy.
     *
     * @param mixed $data
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!\is_array($data) || !isset($data['valid'], $data['checked_at']) || !\is_bool($data['valid'])) {
            return null;
        }

        $checkedAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, (string) $data['checked_at']);

        if ($checkedAt === false) {
            return null;
        }

        return new self(
            valid: $data['valid'],
            checkedAt: $checkedAt,
            consultationNumber: isset($data['consultation_number']) ? (string) $data['consultation_number'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            address: isset($data['address']) ? (string) $data['address'] : null,
        );
    }
}
