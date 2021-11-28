<?php

declare(strict_types=1);

namespace Riaf\ResponseEmitter;

use Psr\Http\Message\ResponseInterface;

class StandardResponseEmitter implements ResponseEmitterInterface
{
    /**
     * @param bool $flushBeforeBody Whether we should flush the output buffers before sending the body
     *                              Flushing the output buffers may improve performance as PHP will not buffer the output but send it straight through.
     *                              It may also hurt performance if there's no proxy in front of PHP or if the proxy is overloaded.
     */
    public function __construct(private bool $flushBeforeBody = false)
    {
    }

    /**
     * Emit a response with status, headers and body, and flush output buffers, and close connection.
     */
    public function emitResponse(ResponseInterface $response): void
    {
        $this->emitHeaders($response);
        $this->emitStatus($response);
        $this->emitBody($response);
        $this->closeConnection();
    }

    private function emitHeaders(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $headers = $response->getHeaders();

        /** @var string[] $keys */
        $keys = array_keys($headers);

        for ($i = 0, $count = count($keys); $i < $count; ++$i) {
            $name = $keys[$i];
            $values = $headers[$name];
            for ($j = 0, $countValues = count($values); $j < $countValues; ++$j) {
                header(
                    sprintf(
                        '%s: %s',
                        $name,
                        $values[$j]
                    ),
                    false,
                    $statusCode
                );
            }
        }
    }

    private function emitStatus(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        header(
            sprintf(
                'HTTP/%s %d %s',
                $response->getProtocolVersion(),
                $statusCode,
                $response->getReasonPhrase()
            ),
            true,
            $statusCode
        );
    }

    private function emitBody(ResponseInterface $response): void
    {
        if ($this->flushBeforeBody) {
            $this->flushOutputBuffers();
        }

        echo $response->getBody();
    }

    private function flushOutputBuffers(): void
    {
        if (!in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
            /** @var array<int, array{del: bool, flags: int}> $status */
            $status = ob_get_status(true);
            $level = count($status);
            $flags = PHP_OUTPUT_HANDLER_REMOVABLE | PHP_OUTPUT_HANDLER_FLUSHABLE;

            while ($level > 0 && isset($status[$level]) && ($status[$level]['del'] ?? !isset($status[$level]['flags']) || $flags === ($status[$level]['flags'] & $flags))) {
                ob_end_flush();
                --$level;
            }
        }
    }

    private function closeConnection(): void
    {
        $this->flushOutputBuffers();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }
}
