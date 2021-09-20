<?php

declare(strict_types=1);

namespace Riaf\PsrExtensions\Http;

use function array_key_exists;
use function array_keys;
use function current;
use function explode;
use function fopen;
use function function_exists;
use function in_array;
use InvalidArgumentException;
use function is_array;
use function is_int;
use function is_resource;
use function is_string;
use function preg_match;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use function str_replace;
use function strtolower;
use function strtr;
use function substr;
use function trim;

// This class has been taken and adapted from nyholm/psr7-server ServerRequestCreator
// in order to support an easier interface
class ServerRequestCreator
{
    public function __construct(
        private ServerRequestFactoryInterface $serverRequestFactory,
        private UriFactoryInterface $uriFactory,
        private UploadedFileFactoryInterface $uploadedFileFactory,
        private StreamFactoryInterface $streamFactory
    ) {
    }

    /**
     * Create a new server request from the current environment variables.
     * Defaults to a GET request to minimise the risk of an \InvalidArgumentException.
     * Includes the current request headers as supplied by the server through `getallheaders()`.
     * If `getallheaders()` is unavailable on the current server it will fallback to its own `getHeadersFromServer()` method.
     * Defaults to php://input for the request body.
     *
     * @throws InvalidArgumentException if no valid URI can be determined
     */
    public function fromGlobals(): ServerRequestInterface
    {
        $server = $_SERVER;
        $method = $server['REQUEST_METHOD'] ?? $server['REQUEST_METHOD'] = 'GET';

        $headers = function_exists('getallheaders') ? getallheaders() : $this->getHeadersFromServer($_SERVER);

        $post = null;
        if ($method === 'POST') {
            foreach ($headers as $headerName => $headerValue) {
                if (is_int($headerName) || strtolower($headerName) !== 'content-type') {
                    continue;
                }
                if (in_array(
                    strtolower(trim(explode(';', $headerValue, 2)[0])),
                    ['application/x-www-form-urlencoded', 'multipart/form-data']
                )) {
                    $post = $_POST;

                    break;
                }
            }
        }

        return $this->fromArrays($server, $headers, $_COOKIE, $_GET, $post, $_FILES, fopen('php://input', 'r') ?: null);
    }

    /**
     * Implementation from Laminas\Diactoros\marshalHeadersFromSapi().
     */
    private function getHeadersFromServer(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            // Apache prefixes environment variables with REDIRECT_
            // if they are added by rewrite rules
            if (str_starts_with($key, 'REDIRECT_')) {
                $key = substr($key, 9);

                // We will not overwrite existing variables with the
                // prefixed versions, though
                if (array_key_exists($key, $server)) {
                    continue;
                }
            }

            if ($value && str_starts_with($key, 'HTTP_')) {
                $name = strtr(strtolower(substr($key, 5)), '_', '-');
                $headers[$name] = $value;

                continue;
            }

            if ($value && str_starts_with($key, 'CONTENT_')) {
                $name = 'content-' . strtolower(substr($key, 8));
                $headers[$name] = $value;

                continue;
            }
        }

        return $headers;
    }

    /**
     * Create a new server request from a set of arrays.
     *
     * @param array<string, string>                $server  typically $_SERVER or similar structure
     * @param array<string, string>                $headers typically the output of getallheaders() or similar structure
     * @param array<string, string>                $cookie  typically $_COOKIE or similar structure
     * @param array<string, string>                $get     typically $_GET or similar structure
     * @param array|null                           $post    typically $_POST or similar structure, represents parsed request body
     * @param array<string, string[]>              $files   typically $_FILES or similar structure
     * @param StreamInterface|resource|string|null $body    Typically stdIn
     *
     * @throws InvalidArgumentException if no valid method or URI can be determined
     */
    public function fromArrays(array $server, array $headers = [], array $cookie = [], array $get = [], ?array $post = null, array $files = [], $body = null): ServerRequestInterface
    {
        if (!isset($server['REQUEST_METHOD'])) {
            throw new InvalidArgumentException('Cannot determine HTTP method');
        }

        $method = $server['REQUEST_METHOD'];
        $uri = $this->createUriFromArray($server);
        if (empty($uri->getScheme())) {
            $uri = $uri->withScheme('http');
        }

        $serverRequest = $this->serverRequestFactory->createServerRequest($method, $uri, $server);

        $serverRequest = $serverRequest
            ->withProtocolVersion(isset($server['SERVER_PROTOCOL']) ? str_replace('HTTP/', '', $server['SERVER_PROTOCOL']) : '1.1');

        foreach ($headers as $name => $value) {
            // Because PHP automatically casts array keys set with numeric strings to integers, we have to make sure
            // that numeric headers will not be sent along as integers, as withAddedHeader can only accept strings.
            if (is_int($name)) {
                $name = (string) $name;
            }
            $serverRequest = $serverRequest->withAddedHeader($name, $value);
        }

        $serverRequest = $serverRequest
            ->withCookieParams($cookie)
            ->withQueryParams($get)
            ->withParsedBody($post)
            ->withUploadedFiles($this->normalizeFiles($files));

        if ($body === null) {
            return $serverRequest;
        }

        if (is_resource($body)) {
            $body = $this->streamFactory->createStreamFromResource($body);
        } elseif (is_string($body)) {
            $body = $this->streamFactory->createStream($body);
        } elseif (!$body instanceof StreamInterface) {
            throw new InvalidArgumentException('The $body parameter to ServerRequestCreator::fromArrays must be string, resource or StreamInterface');
        }

        return $serverRequest->withBody($body);
    }

    private function createUriFromArray(array $server): UriInterface
    {
        $uri = $this->uriFactory->createUri('');

        if (isset($server['HTTP_X_FORWARDED_PROTO'])) {
            $uri = $uri->withScheme($server['HTTP_X_FORWARDED_PROTO']);
        } else {
            if (isset($server['REQUEST_SCHEME'])) {
                $uri = $uri->withScheme($server['REQUEST_SCHEME']);
            } elseif (isset($server['HTTPS'])) {
                $uri = $uri->withScheme($server['HTTPS'] === 'on' ? 'https' : 'http');
            }

            if (isset($server['SERVER_PORT'])) {
                $uri = $uri->withPort($server['SERVER_PORT']);
            }
        }

        if (isset($server['HTTP_HOST'])) {
            if (preg_match('/^(.+):(\d+)$/', $server['HTTP_HOST'], $matches) === 1) {
                $uri = $uri->withHost($matches[1])->withPort($matches[2]);
            } else {
                $uri = $uri->withHost($server['HTTP_HOST']);
            }
        } elseif (isset($server['SERVER_NAME'])) {
            $uri = $uri->withHost($server['SERVER_NAME']);
        }

        if (isset($server['REQUEST_URI'])) {
            $uri = $uri->withPath(current(explode('?', $server['REQUEST_URI'])));
        }

        if (isset($server['QUERY_STRING'])) {
            $uri = $uri->withQuery($server['QUERY_STRING']);
        }

        return $uri;
    }

    /**
     * Return an UploadedFile instance array.
     *
     * @param array<string, string[]> $files A array which respect $_FILES structure
     *
     * @return UploadedFileInterface[]
     *
     * @throws InvalidArgumentException for unrecognized values
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
            } elseif (is_array($value) && isset($value['tmp_name'])) {
                $normalized[$key] = $this->createUploadedFileFromSpec($value);
            } elseif (is_array($value)) {
                $normalized[$key] = $this->normalizeFiles($value);
            } else {
                throw new InvalidArgumentException('Invalid value in files specification');
            }
        }

        return $normalized;
    }

    /**
     * Create and return an UploadedFile instance from a $_FILES specification.
     *
     * If the specification represents an array of values, this method will
     * delegate to normalizeNestedFileSpec() and return that return value.
     *
     * @param array<string, string[]> $value $_FILES struct
     *
     * @return UploadedFileInterface[]|UploadedFileInterface
     */
    private function createUploadedFileFromSpec(array $value): array|UploadedFileInterface
    {
        if (is_array($value['tmp_name'])) {
            return $this->normalizeNestedFileSpec($value);
        }

        if (UPLOAD_ERR_OK !== $value['error']) {
            $stream = $this->streamFactory->createStream();
        } else {
            try {
                $stream = $this->streamFactory->createStreamFromFile($value['tmp_name']);
            } catch (RuntimeException) {
                $stream = $this->streamFactory->createStream();
            }
        }

        return $this->uploadedFileFactory->createUploadedFile(
            $stream,
            (int) $value['size'],
            (int) $value['error'],
            $value['name'],
            $value['type']
        );
    }

    /**
     * Normalize an array of file specifications.
     *
     * Loops through all nested files and returns a normalized array of
     * UploadedFileInterface instances.
     *
     * @return UploadedFileInterface[]
     */
    private function normalizeNestedFileSpec(array $files = []): array
    {
        $normalizedFiles = [];

        foreach (array_keys($files['tmp_name']) as $key) {
            $spec = [
                'tmp_name' => $files['tmp_name'][$key],
                'size' => $files['size'][$key],
                'error' => $files['error'][$key],
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
            ];
            $normalizedFiles[$key] = $this->createUploadedFileFromSpec($spec);
        }

        return $normalizedFiles;
    }
}
