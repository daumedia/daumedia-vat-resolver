<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Unit\Vies;

use Daumedia\VatResolver\Tests\Double\StaticHttpClient;
use Daumedia\VatResolver\Vies\Psr18ViesLookup;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;

final class Psr18ViesLookupTest extends TestCase
{
    /**
     * @param list<Response|ClientExceptionInterface> $responses
     */
    private function lookup(array $responses, ?StaticHttpClient &$client = null): Psr18ViesLookup
    {
        $client = new StaticHttpClient($responses);

        return new Psr18ViesLookup($client, new Psr17Factory());
    }

    private static function json(array $payload, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    public function testAConfirmedNumberIsReturnedWithItsConsultationNumber(): void
    {
        $lookup = $this->lookup([self::json([
            'isValid' => true,
            'requestDate' => '2026-03-05T12:00:00+01:00',
            'userError' => 'VALID',
            'requestIdentifier' => 'WAPIAAAAXYZ',
            'name' => 'Muster GmbH',
            'address' => 'Musterweg 1, 10115 Berlin',
        ])]);

        $check = $lookup->lookup('DE', 'DE123456789');

        self::assertNotNull($check);
        self::assertTrue($check->valid);
        self::assertSame('WAPIAAAAXYZ', $check->consultationNumber);
        self::assertSame('Muster GmbH', $check->name);
        self::assertSame('2026-03-05', $check->checkedAt->format('Y-m-d'));
    }

    public function testARejectedNumberIsReturnedAsInvalid(): void
    {
        $lookup = $this->lookup([self::json(['isValid' => false, 'userError' => 'VALID'])]);

        $check = $lookup->lookup('DE', 'DE123456789');

        self::assertNotNull($check);
        self::assertFalse($check->valid);
    }

    public function testTheRequestGoesToTheCommissionsRestEndpointWithoutTheCountryPrefix(): void
    {
        $lookup = $this->lookup([self::json(['isValid' => true])], $client);

        $lookup->lookup('DE', 'de 123.456.789');

        self::assertSame(
            'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/DE/vat/123456789',
            $client->lastUri(),
        );
    }

    /**
     * Every failure collapses to null, i.e. "we do not know". A caller that could
     * not tell "invalid" from "unreachable" would zero-rate a supply during an
     * outage — and that is our tax debt, not theirs.
     */
    #[DataProvider('failuresThatMeanUnknown')]
    public function testEveryFailureModeMeansUnknownRatherThanInvalid(Response|ClientExceptionInterface $response): void
    {
        self::assertNull($this->lookup([$response])->lookup('DE', 'DE123456789'));
    }

    /** @return iterable<string, array{Response|ClientExceptionInterface}> */
    public static function failuresThatMeanUnknown(): iterable
    {
        yield 'HTTP 500' => [self::json(['isValid' => false], 500)];
        yield 'HTTP 404' => [self::json([], 404)];
        yield 'kaputtes JSON' => [new Response(200, [], '{not json')];
        yield 'JSON ohne isValid' => [self::json(['requestDate' => '2026-03-05'])];
        yield 'isValid ist ein String' => [self::json(['isValid' => 'true'])];
        yield 'VIES meldet INVALID_INPUT' => [self::json(['isValid' => false, 'userError' => 'INVALID_INPUT'])];
        yield 'Mitgliedstaat nicht erreichbar' => [self::json(['isValid' => false, 'userError' => 'MS_UNAVAILABLE'])];
        yield 'Zeitüberschreitung bei VIES' => [self::json(['isValid' => false, 'userError' => 'TIMEOUT'])];
        yield 'Netzwerkfehler' => [new class extends \RuntimeException implements ClientExceptionInterface {}];
    }

    public function testAMalformedNumberIsRejectedWithoutAnyHttpCall(): void
    {
        $lookup = $this->lookup([], $client);

        self::assertNull($lookup->lookup('DE', 'völliger Unsinn'));
        self::assertSame([], $client->sentRequests);
    }

    public function testViesPlaceholderFieldsBecomeNull(): void
    {
        // VIES pads unavailable name/address with '---'.
        $lookup = $this->lookup([self::json([
            'isValid' => true,
            'name' => '---',
            'address' => '---',
        ])]);

        $check = $lookup->lookup('DE', 'DE123456789');

        self::assertNotNull($check);
        self::assertNull($check->name);
        self::assertNull($check->address);
    }

    public function testTheBaseUrlIsOverridableForTesting(): void
    {
        $client = new StaticHttpClient([self::json(['isValid' => true])]);
        $lookup = new Psr18ViesLookup($client, new Psr17Factory(), 'https://vies.test/api/');

        $lookup->lookup('LU', 'LU12345678');

        self::assertSame('https://vies.test/api/ms/LU/vat/12345678', $client->lastUri());
    }
}
