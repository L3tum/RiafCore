<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Riaf\Compiler\Analyzer\StandardAnalyzer;
use Riaf\Metrics\Clock\SystemClock;
use Riaf\Metrics\Timing;
use Riaf\TestCases\Container\DefaultBoolParameter;
use Riaf\TestCases\Container\DefaultFloatParameter;
use Riaf\TestCases\Container\DefaultIntegerTestCase;
use Riaf\TestCases\Container\DefaultStringParameterTestCase;
use Riaf\TestCases\Container\EnvParameter;
use Riaf\TestCases\Container\EnvWithDefaultFallbackParameter;
use Riaf\TestCases\Container\EnvWithFallbackParameter;
use Riaf\TestCases\Container\InjectedBoolParameter;
use Riaf\TestCases\Container\InjectedFloatParameter;
use Riaf\TestCases\Container\InjectedIntegerParameter;
use Riaf\TestCases\Container\InjectedServiceFallbackParameter;
use Riaf\TestCases\Container\InjectedServiceParameter;
use Riaf\TestCases\Container\InjectedStringParameter;
use Riaf\TestCases\Container\NamedConstantArrayParameter;
use Riaf\TestCases\Container\NamedConstantInjectedScalarParameter;
use Riaf\TestCases\Container\NamedConstantScalarParameter;

class ContainerCompilerTest extends TestCase
{
    private static ContainerInterface $container;

    private SampleCompilerConfiguration $config;

    public function testImplementsContainerInterface(): void
    {
        self::assertInstanceOf(ContainerInterface::class, self::$container);
    }

    public function testImplementsContainerInterfaceGetMethod(): void
    {
        self::assertTrue(method_exists(self::$container, 'get'));
    }

    public function testImplementsContainerInterfaceHasMethod(): void
    {
        self::assertTrue(method_exists(self::$container, 'has'));
    }

    public function testCanGetContainerInterface(): void
    {
        self::assertSame(self::$container, self::$container->get('Riaf\\Container'));
        self::assertSame(self::$container, self::$container->get(ContainerInterface::class));
    }

    public function testCanHasContainerInterface(): void
    {
        self::assertTrue(self::$container->has('Riaf\\Container'));
        self::assertTrue(self::$container->has(ContainerInterface::class));
    }

    public function testMapsAdditionalClasses(): void
    {
        self::assertTrue(self::$container->has(StandardAnalyzer::class));
    }

    public function testHandlesDefaultStringParameter(): void
    {
        self::assertEquals('Hello', self::$container->get(DefaultStringParameterTestCase::class)->getName());
    }

    public function testHandlesInjectedStringParameter(): void
    {
        self::assertEquals('Hello', self::$container->get(InjectedStringParameter::class)->getInjectedName());
    }

    public function testHandlesDefaultIntParameter(): void
    {
        self::assertEquals(0, self::$container->get(DefaultIntegerTestCase::class)->getValue());
    }

    public function testHandlesInjectedIntParameter(): void
    {
        self::assertEquals(1, self::$container->get(InjectedIntegerParameter::class)->getInjectedValue());
    }

    public function testHandlesDefaultFloatParameter(): void
    {
        self::assertEquals(0.0, self::$container->get(DefaultFloatParameter::class)->getValue());
    }

    public function testHandlesInjectedFloatParameter(): void
    {
        self::assertEquals(1.0, self::$container->get(InjectedFloatParameter::class)->getInjectedFloat());
    }

    public function testHandlesEnvParameter(): void
    {
        $_SERVER['MY_TEST_VALUE'] = 'Test';
        self::assertEquals('Test', self::$container->get(EnvParameter::class)->getValue());
        unset($_SERVER['MY_TEST_VALUE']);
    }

    public function testHandlesEnvWithFallbackParameter(): void
    {
        unset($_SERVER['MY_TEST_VALUE']);
        self::assertEquals('Fallback', self::$container->get(EnvWithFallbackParameter::class)->getValue());
    }

    public function testHandlesEnvWithDefaultFallbackParameter(): void
    {
        unset($_SERVER['MY_TEST_VALUE']);
        $this->expectError();
        self::assertEquals('Default', self::$container->get(EnvWithDefaultFallbackParameter::class)->getValue());
    }

    public function testHandlesInjectedServiceParameter(): void
    {
        self::assertInstanceOf(DefaultFloatParameter::class, self::$container->get(InjectedServiceParameter::class)->getCompiler());
    }

    public function testHandlesInjectedServiceFallbackParameter(): void
    {
        self::assertInstanceOf(DefaultFloatParameter::class, self::$container->get(InjectedServiceFallbackParameter::class)->getCompiler());
    }

    public function testHandlesNamedConstantDefaultScalarParameter(): void
    {
        self::assertEquals(NamedConstantScalarParameter::DEFAULT, self::$container->get(NamedConstantScalarParameter::class)->getValue());
    }

    public function testHandlesNamedConstantDefaultArrayParameter(): void
    {
        self::assertEquals(NamedConstantArrayParameter::DEFAULT, self::$container->get(NamedConstantArrayParameter::class)->getValue());
    }

    public function testHandlesNamedConstantInjectedScalarParameter(): void
    {
        self::assertEquals(NamedConstantInjectedScalarParameter::DEFAULT, self::$container->get(NamedConstantInjectedScalarParameter::class)->getValue());
    }

    public function testHandlesAlias(): void
    {
        self::assertInstanceOf(DateTimeImmutable::class, self::$container->get('now'));
    }

    public function testHandlesDefaultBoolParameter(): void
    {
        self::assertTrue(self::$container->get(DefaultBoolParameter::class)->isValue());
    }

    public function testHandlesInjectedBoolParameter(): void
    {
        self::assertTrue(self::$container->get(InjectedBoolParameter::class)->isValue());
    }

    public function testHandlesAliases(): void
    {
        self::assertEquals('/some/test', self::$container->get('some_request_i_dont_want')->getUri()->getPath());
    }

    // TODO: Array Parsing Tests?

    /**
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function setUp(): void
    {
        // Why? Well, setUpBeforeClasses is not counted for coverage..
        if (class_exists('\\Riaf\\Container', false)) {
            return;
        }

        $this->config = new class() extends SampleCompilerConfiguration {
            private $stream = null;

            public function getFileHandle(BaseCompiler $compiler)
            {
                if ($this->stream === null) {
                    $this->stream = fopen('php://memory', 'wb+');
                }

                return $this->stream;
            }
        };

        $compiler = new ContainerCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $this->config);
        $compiler->supportsCompilation();
        $compiler->compile();

        $stream = $this->config->getFileHandle($compiler);
        fseek($stream, 0);
        $content = stream_get_contents($stream);
        eval('?>' . $content);

//        file_put_contents(dirname(__DIR__) . '/dev_Container.php', $content);

        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        /** @noinspection PhpUndefinedClassInspection */
        self::$container = new \Riaf\Container();
    }
}
