<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Double;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that answers from a canned list. No network, ever.
 *
 * Hand-written rather than pulled from a mocking library: it needs to be obvious
 * that this test suite cannot reach the internet, and one small class says that
 * more clearly than a mock expectation.
 */
final class StaticHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $sentRequests = [];

    /**
     * @param list<ResponseInterface|ClientExceptionInterface> $responses consumed in order
     */
    public function __construct(private array $responses)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->sentRequests[] = $request;

        $next = array_shift($this->responses);

        if ($next === null) {
            throw new \LogicException('StaticHttpClient ran out of canned responses.');
        }

        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }

        return $next;
    }

    public function lastUri(): string
    {
        $last = end($this->sentRequests);

        return $last === false ? '' : (string) $last->getUri();
    }
}
