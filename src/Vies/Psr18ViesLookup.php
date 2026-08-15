<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Vies;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * The real VIES consultation, over the Commission's REST endpoint.
 *
 * GET https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{cc}/vat/{number}
 * No credentials, no registration.
 *
 * Implements ViesLookupInterface and NOT ViesValidatorInterface — on purpose. It
 * blocks, so it must not be assignable to the port the resolver holds.
 *
 * Never throws. Every failure mode — network, non-200, unparsable body, VIES's
 * own userError field — collapses to null, i.e. "we do not know". A caller that
 * cannot tell "invalid" from "unreachable" would zero-rate a supply on an outage.
 */
final readonly class Psr18ViesLookup implements ViesLookupInterface
{
    private const string DEFAULT_BASE_URL = 'https://ec.europa.eu/taxation_customs/vies/rest-api';

    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private string $baseUrl = self::DEFAULT_BASE_URL,
    ) {
    }

    public function lookup(string $countryCode, string $vatNumber): ?ViesCheck
    {
        if (!VatNumberFormat::isWellFormed($countryCode, $vatNumber)) {
            return null;
        }

        $country = strtoupper(trim($countryCode));
        $number = VatNumberFormat::normalize($country, $vatNumber);

        $request = $this->requestFactory
            ->createRequest('GET', sprintf('%s/ms/%s/vat/%s', rtrim($this->baseUrl, '/'), $country, $number))
            ->withHeader('Accept', 'application/json');

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface) {
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        return $this->parse((string) $response->getBody());
    }

    private function parse(string $body): ?ViesCheck
    {
        try {
            $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($data) || !isset($data['isValid']) || !\is_bool($data['isValid'])) {
            return null;
        }

        // VIES reports its own problems in-band with HTTP 200. INVALID_INPUT,
        // MS_UNAVAILABLE, TIMEOUT and friends mean "no answer", which is very
        // much not the same as "the number is invalid".
        $userError = isset($data['userError']) ? strtoupper((string) $data['userError']) : 'VALID';

        if ($userError !== 'VALID' && $userError !== '') {
            return null;
        }

        return new ViesCheck(
            valid: $data['isValid'],
            checkedAt: $this->parseRequestDate($data['requestDate'] ?? null),
            consultationNumber: $this->stringOrNull($data['requestIdentifier'] ?? null),
            name: $this->stringOrNull($data['name'] ?? null),
            address: $this->stringOrNull($data['address'] ?? null),
        );
    }

    private function parseRequestDate(mixed $value): \DateTimeImmutable
    {
        if (\is_string($value) && $value !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                // fall through
            }
        }

        return new \DateTimeImmutable();
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        // VIES pads unavailable fields with '---'.
        return ($trimmed === '' || $trimmed === '---') ? null : $trimmed;
    }
}
