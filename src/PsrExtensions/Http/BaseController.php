<?php

declare(strict_types=1);

namespace Riaf\PsrExtensions\Http;

use JsonException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

abstract class BaseController
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    /**
     * @param string|object|mixed[]          $data
     * @param array<string, string|string[]> $headers
     *
     * @throws JsonException
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection
     */
    protected function json(string|array|object $data, int $statusCode = 200, ?string $statusText = null, array $headers = []): ResponseInterface
    {
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        return $this->respond($data, $statusCode, $statusText, $headers);
    }

    /**
     * @param string|object|mixed[]          $data
     * @param array<string, string|string[]> $headers
     *
     * @throws JsonException
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection
     */
    protected function respond(string|array|object $data, int $statusCode = 200, ?string $statusText = null, array $headers = []): ResponseInterface
    {
        if ($data instanceof StreamInterface) {
            $body = $data;
        } else {
            if (is_string($data)) {
                $body = $data;
            } else {
                $body = json_encode($data, JSON_THROW_ON_ERROR);
            }

            /** @var StreamFactoryInterface $streamFactory */
            $streamFactory = $this->container->get(StreamFactoryInterface::class);
            $body = $streamFactory->createStream($body);
        }

        /** @var ResponseFactoryInterface $factory */
        $factory = $this->container->get(ResponseFactoryInterface::class);
        if ($statusText === null) {
            $response = $factory->createResponse($statusCode);
        } else {
            $response = $factory->createResponse($statusCode, $statusText);
        }

        $keys = array_keys($headers);
        $len = count($keys);

        for ($i = 0; $i < $len; ++$i) {
            $response = $response->withHeader($keys[$i], $headers[$keys[$i]]);
        }

        return $response->withBody($body);
    }
}
