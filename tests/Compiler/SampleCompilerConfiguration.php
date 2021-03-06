<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use ArrayIterator;
use DateTimeImmutable;
use Psr\Http\Message\RequestInterface;
use Riaf\Compiler\Analyzer\AnalyzerInterface;
use Riaf\Compiler\Analyzer\StandardAnalyzer;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Configuration\ContainerCompilerConfiguration;
use Riaf\Configuration\EventDispatcherCompilerConfiguration;
use Riaf\Configuration\MiddlewareDispatcherCompilerConfiguration;
use Riaf\Configuration\ParameterDefinition;
use Riaf\Configuration\PreloadCompilerConfiguration;
use Riaf\Configuration\RouterCompilerConfiguration;
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

class SampleCompilerConfiguration extends BaseConfiguration implements PreloadCompilerConfiguration, ContainerCompilerConfiguration, RouterCompilerConfiguration, MiddlewareDispatcherCompilerConfiguration, EventDispatcherCompilerConfiguration
{
    public function getContainerNamespace(): string
    {
        return 'Riaf';
    }

    public function getContainerFilepath(): string
    {
        return '/var/Container.php';
    }

    public function getRouterNamespace(): string
    {
        return 'Riaf';
    }

    public function getRouterFilepath(): string
    {
        return '/var/Router.php';
    }

    public function getPreloadingFilepath(): string
    {
        return '/var/preloading.php';
    }

    public function getAdditionalServices(): array
    {
        return [
            AnalyzerInterface::class => StandardAnalyzer::class,
            DefaultStringParameterTestCase::class => DefaultStringParameterTestCase::class,
            DefaultIntegerTestCase::class => DefaultIntegerTestCase::class,
            InjectedStringParameter::class => ServiceDefinition::create(InjectedStringParameter::class)
                ->setParameters([
                    ParameterDefinition::createString('injectedName', 'Hello'),
                ]),
            'someotherkey' => ServiceDefinition::create(InjectedStringParameter::class)
                ->setParameters([
                    ParameterDefinition::createString('injectedName', 'Hello'),
                ]),
            InjectedIntegerParameter::class => ServiceDefinition::create(InjectedIntegerParameter::class)
                ->setParameters([
                    ParameterDefinition::createInteger('injectedValue', 1),
                ]),
            DefaultFloatParameter::class => DefaultFloatParameter::class,
            InjectedFloatParameter::class => ServiceDefinition::create(InjectedFloatParameter::class)
                ->setParameters([
                    ParameterDefinition::createFloat('injectedFloat', 1.0),
                ]),
            EnvParameter::class => ServiceDefinition::create(EnvParameter::class)
                ->setParameters([
                    ParameterDefinition::createEnv('value', 'MY_TEST_VALUE'),
                ]),
            EnvWithFallbackParameter::class => ServiceDefinition::create(EnvWithFallbackParameter::class)
                ->setParameters([
                    ParameterDefinition::createEnv('value', 'MY_TEST_VALUE')
                        ->withFallback(ParameterDefinition::createString('value', 'Fallback')),
                ]),
            EnvWithDefaultFallbackParameter::class => ServiceDefinition::create(EnvWithDefaultFallbackParameter::class)
                ->setParameters([
                    ParameterDefinition::createEnv('value', 'MY_TEST_VALUE'),
                ]),
            InjectedServiceParameter::class => ServiceDefinition::create(InjectedServiceParameter::class)
                ->setParameters([
                    ParameterDefinition::createInjected('compiler', DefaultFloatParameter::class),
                ]),
            InjectedServiceFallbackParameter::class => ServiceDefinition::create(InjectedServiceFallbackParameter::class)
                ->setParameters([
                    ParameterDefinition::createInjected('compiler', RequestInterface::class)
                        ->withFallback(ParameterDefinition::createInjected('compiler', DefaultFloatParameter::class)),
                ]),
            NamedConstantScalarParameter::class => NamedConstantScalarParameter::class,
            NamedConstantArrayParameter::class => NamedConstantArrayParameter::class,
            NamedConstantInjectedScalarParameter::class => ServiceDefinition::create(NamedConstantInjectedScalarParameter::class)
                ->setParameters([
                    ParameterDefinition::createNamedConstant('value', '\\' . NamedConstantInjectedScalarParameter::class . '::DEFAULT'),
                ]),
            'now' => DateTimeImmutable::class,
            DefaultBoolParameter::class => DefaultBoolParameter::class,
            InjectedBoolParameter::class => ServiceDefinition::create(InjectedBoolParameter::class)
                ->setParameters([
                    ParameterDefinition::createBool('value', true),
                ]),
            FactoryMethodNoParameters::class => ServiceDefinition::create(FactoryMethodNoParameters::class)
                ->setStaticFactoryMethod(FactoryMethodNoParameters::class, 'create'),
            FactoryMethodContainerParameter::class => ServiceDefinition::create(FactoryMethodContainerParameter::class)
                ->setStaticFactoryMethod(FactoryMethodContainerParameter::class, 'create'),
            FactoryMethodWithParameters::class => ServiceDefinition::create(FactoryMethodWithParameters::class)
                ->setStaticFactoryMethod(FactoryMethodWithParameters::class, 'create'),
            'hashmap_iterator' => ServiceDefinition::create(ArrayIterator::class)
                ->setParameters([
                    ['name' => 'array', 'value' => ['Hey' => 'No']],
                ]),
            'array_iterator' => ServiceDefinition::create(ArrayIterator::class)
                ->setParameters([
                    ['name' => 'array', 'value' => [0, 1, 2]],
                ]),
            'serializable_object' => ServiceDefinition::create(ArrayIterator::class)
                ->setParameters([
                    ['name' => 'array', 'value' => [(new ParameterDefinition('test', 'test'))]],
                ]),
            'serializable_closure' => ServiceDefinition::create(ArrayIterator::class)
                ->setParameters([
                    [
                        'name' => 'array',
                        'value' => static function () {
                            return [1, 2, 3];
                        },
                    ],
                ]),
            'serializable_closure_parameters' => ServiceDefinition::create(ArrayIterator::class)
                ->setParameters([
                    [
                        'name' => 'array',
                        'value' => static function (AnalyzerInterface $analyzer) {
                            return [1, 2, 3];
                        },
                    ],
                ]),
            ControllerInstance::class => ControllerInstance::class,
        ];
    }

    public function getAdditionalPreloadedFiles(): array
    {
        return ['bin/compile', 'src/Core.php'];
    }

    public function getMiddlewareDispatcherNamespace(): string
    {
        return 'Riaf';
    }

    public function getMiddlewareDispatcherFilepath(): string
    {
        return '/var/MiddlewareDispatcher.php';
    }

    public function getEventDispatcherNamespace(): string
    {
        return 'Riaf';
    }

    public function getEventDispatcherFilepath(): string
    {
        return '/var/EventDispatcher.php';
    }

    public function getPreloadingBasePath(): ?string
    {
        return null;
    }
}
