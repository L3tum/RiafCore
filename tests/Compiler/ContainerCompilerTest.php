<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use ArrayAccess;
use ArrayIterator;
use DateTimeImmutable;
use Nyholm\Psr7\Request;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Riaf\Compiler\Analyzer\StandardAnalyzer;
use Riaf\Configuration\ParameterDefinition;
use Riaf\Configuration\ServiceDefinition;
use Riaf\TestCases\Container\ControllerInstance;
use Riaf\TestCases\Container\DefaultBoolParameter;
use Riaf\TestCases\Container\DefaultFloatParameter;
use Riaf\TestCases\Container\DefaultIntegerTestCase;
use Riaf\TestCases\Container\DefaultStringParameterTestCase;
use Riaf\TestCases\Container\EnvParameter;
use Riaf\TestCases\Container\EnvWithDefaultFallbackParameter;
use Riaf\TestCases\Container\EnvWithFallbackParameter;
use Riaf\TestCases\Container\FactoryMethodContainerParameter;
use Riaf\TestCases\Container\FactoryMethodNoParameters;
use Riaf\TestCases\Container\FactoryMethodWithParameters;
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

    public function testHandlesManuallyAddedServices(): void
    {
        self::assertInstanceOf(RequestInterface::class, self::$container->get(RequestInterface::class));
    }

    public function testHandlesAliases(): void
    {
        self::assertEquals('/some/test', self::$container->get('some_request_i_dont_want')->getUri()->getPath());
    }

    public function testHandlesFactoryMethodNoParameters(): void
    {
        self::assertEquals('Factory', self::$container->get(FactoryMethodNoParameters::class)->creator);
    }

    public function testHandlesFactoryMethodWithContainerParameter(): void
    {
        self::assertEquals('Factory', self::$container->get(FactoryMethodContainerParameter::class)->creator);
    }

    public function testHandlesFactoryMethodWithParameters(): void
    {
        self::assertEquals('Factory', self::$container->get(FactoryMethodWithParameters::class)->creator);
    }

    public function testAddsServerRequestCreatorByDefault(): void
    {
        self::assertInstanceOf(ServerRequestCreatorInterface::class, self::$container->get(ServerRequestCreatorInterface::class));
    }

    public function testHandlesMapArraysAsParameters(): void
    {
        self::assertInstanceOf(ArrayIterator::class, self::$container->get('hashmap_iterator'));
    }

    public function testHandlesPlainArraysAsParameters(): void
    {
        self::assertInstanceOf(ArrayIterator::class, self::$container->get('array_iterator'));
    }

    public function testDoesNotPullOutServiceDefinitionClassWhenReferencedMultipleTimes(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);
        self::$container->get(ArrayIterator::class);
    }

    public function testDoesNotSaveClassOrInterfaceAsServices(): void
    {
        self::$container->get('hashmap_iterator');
        $this->expectException(NotFoundExceptionInterface::class);
        self::$container->get(ArrayIterator::class);
        $this->expectException(NotFoundExceptionInterface::class);
        self::$container->get(ArrayAccess::class);
    }

    public function testSupportsSerializableObjectsAsParameters(): void
    {
        /** @var ArrayIterator $iterator */
        $iterator = self::$container->get('serializable_object');
        $iterator->rewind();
        self::assertInstanceOf(ParameterDefinition::class, $iterator->current());
    }

    /** @runInSeparateProcess */
    public function testSupportsClosureAsParameter(): void
    {
        /** @var ArrayIterator $iterator */
        $iterator = self::$container->get('serializable_closure');
        $iterator->rewind();
        self::assertIsInt($iterator->current());
    }

    /** @runInSeparateProcess */
    public function testSupportsClosureAsParameterWithParameters(): void
    {
        /** @var ArrayIterator $iterator */
        $iterator = self::$container->get('serializable_closure_parameters');
        $iterator->rewind();
        self::assertIsInt($iterator->current());
    }

    public function testSupportsContainerAwareTrait(): void
    {
        /** @var ControllerInstance $controllerInstance */
        $controllerInstance = self::$container->get(ControllerInstance::class);
        self::assertTrue($controllerInstance->hasContainer());
    }

    // TODO: Array Parsing Tests?

    /**
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function setUp(): void
    {
        $this->config = new SampleCompilerConfiguration();
        // Why? Well, setUpBeforeClasses is not counted for coverage..
        if (class_exists($this->config->getContainerNamespace() . '\\Container', false)) {
            return;
        }

        $compiler = new ContainerCompiler($this->config);
        $compiler->supportsCompilation();
        $compiler->addService(
            RequestInterface::class,
            ServiceDefinition::create(Request::class)
                ->setParameters([['name' => 'method', 'value' => 'GET'], ['name' => 'uri', 'value' => '/some/test']])
                ->setAliases('some_request_i_dont_want')
        );
        $compiler->compile();

        $stream = fopen($this->config->getProjectRoot() . $this->config->getContainerFilepath(), 'rb');
        $content = stream_get_contents($stream);
        eval('?>' . $content);

//        file_put_contents(dirname(__DIR__) . '/dev_Container.php', $content);

        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        /** @noinspection PhpUndefinedClassInspection */
        self::$container = new \Riaf\Container();
    }
}
