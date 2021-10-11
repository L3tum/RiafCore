<?php

declare(strict_types=1);

namespace Riaf\Compiler;

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
use Riaf\TestCases\Container\InjectedServiceSkipParameter;
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
        return '/var/cache/' . ($_SERVER['APP_ENV'] ?? 'dev') . '/Container.php';
    }

    public function getRouterNamespace(): string
    {
        return 'Riaf';
    }

    public function getRouterFilepath(): string
    {
        return '/var/cache/' . ($_SERVER['APP_ENV'] ?? 'dev') . '/Router.php';
    }

    public function getPreloadingFilepath(): string
    {
        return '/var/cache/' . ($_SERVER['APP_ENV'] ?? 'dev') . '/preloading.php';
    }

    public function getAdditionalClasses(): array
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
            InjectedServiceSkipParameter::class => ServiceDefinition::create(InjectedServiceSkipParameter::class)
                ->setParameters([
                    ParameterDefinition::createInjected('compiler', RequestInterface::class)
                        ->withFallback(ParameterDefinition::createSkipIfNotFound('compiler')),
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
        return '/var/cache/' . ($_SERVER['APP_ENV'] ?? 'dev') . '/MiddlewareDispatcher.php';
    }

    public function getAdditionalMiddlewares(): array
    {
        return [];
    }

    public function getAdditionalRouterClasses(): array
    {
        return [];
    }

    public function getEventDispatcherNamespace(): string
    {
        return 'Riaf';
    }

    public function getEventDispatcherFilepath(): string
    {
        return '/var/cache/' . ($_SERVER['APP_ENV'] ?? 'dev') . '/EventDispatcher.php';
    }

    public function getAdditionalEventListeners(): array
    {
        return [];
    }
}
