<?php

declare(strict_types=1);

namespace Riaf\PsrExtensions\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use ReflectionException;
use ReflectionMethod;
use Riaf\TestCases\BaseController\Model;

class BaseControllerTest extends TestCase
{
    private ContainerInterface|MockObject $container;

    private BaseController $controller;

    public function testCreatesResponseFromString(): void
    {
        $this->container
            ->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive([StreamFactoryInterface::class], [ResponseFactoryInterface::class])
            ->willReturnOnConsecutiveCalls(new Psr17Factory(), new Psr17Factory());

        $response = $this->callJson('{"Hello":1}');

        self::assertEquals('{"Hello":1}', (string) $response->getBody());
        self::assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    /**
     * @throws ReflectionException
     */
    private function callJson(): ResponseInterface
    {
        $method = new ReflectionMethod(BaseController::class, 'json');
        $method->setAccessible(true);

        return $method->invoke($this->controller, ...func_get_args());
    }

    public function testCreatesResponseFromArray(): void
    {
        $this->container
            ->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive([StreamFactoryInterface::class], [ResponseFactoryInterface::class])
            ->willReturnOnConsecutiveCalls(new Psr17Factory(), new Psr17Factory());

        $response = $this->callJson(['Hello' => 1]);

        self::assertEquals('{"Hello":1}', (string) $response->getBody());
        self::assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testCreatesResponseFromObject(): void
    {
        $this->container
            ->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive([StreamFactoryInterface::class], [ResponseFactoryInterface::class])
            ->willReturnOnConsecutiveCalls(new Psr17Factory(), new Psr17Factory());

        $response = $this->callJson(new Model());

        self::assertEquals('{"name":"Hello"}', (string) $response->getBody());
        self::assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testCreatesResponseFromStream(): void
    {
        $this->container
            ->expects($this->once())
            ->method('get')
            ->with(ResponseFactoryInterface::class)
            ->willReturn(new Psr17Factory());

        $response = $this->callJson((new Psr17Factory())->createStream('{"Hello":1}'));

        self::assertEquals('{"Hello":1}', (string) $response->getBody());
        self::assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testChangesStatusCode(): void
    {
        $this->container
            ->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive([StreamFactoryInterface::class], [ResponseFactoryInterface::class])
            ->willReturnOnConsecutiveCalls(new Psr17Factory(), new Psr17Factory());

        $response = $this->callJson(new Model(), 404);

        self::assertEquals('{"name":"Hello"}', (string) $response->getBody());
        self::assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        self::assertEquals(404, $response->getStatusCode());
        self::assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function testChangesStatusText(): void
    {
        $this->container
            ->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive([StreamFactoryInterface::class], [ResponseFactoryInterface::class])
            ->willReturnOnConsecutiveCalls(new Psr17Factory(), new Psr17Factory());

        $response = $this->callJson(new Model(), 404, 'Go Away');

        self::assertEquals('{"name":"Hello"}', (string) $response->getBody());
        self::assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        self::assertEquals(404, $response->getStatusCode());
        self::assertEquals('Go Away', $response->getReasonPhrase());
    }

    public function testAddsHeader(): void
    {
        $this->container
            ->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive([StreamFactoryInterface::class], [ResponseFactoryInterface::class])
            ->willReturnOnConsecutiveCalls(new Psr17Factory(), new Psr17Factory());

        $response = $this->callJson(new Model(), 200, null, ['Cache-Control' => 'private']);

        self::assertEquals('{"name":"Hello"}', (string) $response->getBody());
        self::assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        self::assertEquals('private', $response->getHeaderLine('Cache-Control'));
    }

    public function testAddsHeaderAsArray(): void
    {
        $this->container
            ->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive([StreamFactoryInterface::class], [ResponseFactoryInterface::class])
            ->willReturnOnConsecutiveCalls(new Psr17Factory(), new Psr17Factory());

        $response = $this->callJson(new Model(), 200, null, ['Cache-Control' => ['private', 'max-age:0']]);

        self::assertEquals('{"name":"Hello"}', (string) $response->getBody());
        self::assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        self::assertEquals('private, max-age:0', $response->getHeaderLine('Cache-Control'));
    }

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->controller = new class($this->container) extends BaseController {
        };
    }
}
