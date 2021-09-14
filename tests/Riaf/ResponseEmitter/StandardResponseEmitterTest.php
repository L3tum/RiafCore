<?php

declare(strict_types=1);

namespace Riaf\ResponseEmitter;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

class StandardResponseEmitterTest extends TestCase
{
    private StandardResponseEmitter $emitter;

    /**
     * @runInSeparateProcess
     */
    public function testEmitsStatusLine(): void
    {
        $response = new Response();
        $this->emitter->emitResponse($response);
        $responseCode = http_response_code();

        self::assertEquals(200, $responseCode);
    }

    /**
     * @runInSeparateProcess
     */
    public function testEmitsHeaders(): void
    {
        $response = new Response();
        $response = $response->withHeader('Content-Type', 'application/json');
        $this->emitter->emitResponse($response);
        $headers = xdebug_get_headers();
        self::assertCount(1, $headers);
        self::assertEquals('Content-Type: application/json', $headers[0]);
    }

    /**
     * @runInSeparateProcess
     */
    public function testDoesNotOverwriteSetCookie(): void
    {
        $response = new Response(headers: ['Set-Cookie' => 'Ja=Nein']);
        setcookie('No', 'Yes');
        $this->emitter->emitResponse($response);
        $headers = xdebug_get_headers();
        self::assertCount(2, $headers);
        self::assertEquals('Set-Cookie: No=Yes', $headers[0]);
        self::assertEquals('Set-Cookie: Ja=Nein', $headers[1]);
    }

    /**
     * @runInSeparateProcess
     */
    public function testEmitsBody(): void
    {
        $response = new Response(body: 'Hello');
        $this->expectOutputString('Hello');
        $this->emitter->emitResponse($response);
    }

    /**
     * @runInSeparateProcess
     */
    public function testFlushesBeforeEmittingBody(): void
    {
        $this->emitter = new StandardResponseEmitter(true);
        $response = new Response(body: 'Hello');
        $this->expectOutputString('Hello');
        $this->emitter->emitResponse($response);
    }

    protected function setUp(): void
    {
        $this->emitter = new StandardResponseEmitter();
    }
}
