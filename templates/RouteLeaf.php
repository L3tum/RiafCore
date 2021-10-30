<?php

declare(strict_types=1);

namespace Riaf\Compiler;

/** @var RouterCompiler $compiler */
/** @var array<string, true> $capturedParams */
/** @var bool $firstRoute */
/** @var int $indentation */
/** @var string $uri */
/** @var bool $generateCall */
/** @var bool $lastRoute */
/** @var bool $hasGeneratedRoute */

/** @var array<string, mixed> $route */

$index = $route['index'];

if ($firstRoute) {
    if (!isset($route['pattern']) && !isset($route['parameter'])) {
        writeLine("match(\$uriParts[$index])");
        writeLine('{', $indentation);
        ++$indentation;
    } elseif (isset($route['pattern'])) {
        writeLine('match(true)');
        writeLine('{', $indentation);
        ++$indentation;
    }
}

// Parameter
if (isset($route['parameter'])) {
    $parameter = (string)$route['parameter'];
    $pattern = $route['pattern'] ?? null;
    $capture = $route['capture'];

    if ($pattern !== null) { // Parameter with Requirement
        write("preg_match(\"/^$pattern$/\", \$uriParts[$index], \$matches$index) === 1 => ", $indentation);
        if ($capture) {
            $capturedParams[$parameter] = "\$matches{$index}[0]";
        }
        $hasGeneratedRoute = true;
    } elseif ($capture) { // Parameter without requirement
        $capturedParams[$parameter] = "\$uriParts[$index]";
    }
} // Normal route
else {
    write("\"$uri\" => ", $indentation);
    $hasGeneratedRoute = true;
}

if (isset($route['call'])) {
    $class = $route['call']['class'];
    $method = $route['call']['method'];
    /**
     * @psalm-suppress InternalMethod
     * @phpstan-ignore-next-line
     */
    $params = implode(', ', $compiler->generateParams($class, $method, $capturedParams));

    if ($generateCall) {
        if ($method !== '') {
            writeLine("\$this->container->get(\"$class\")->$method($params),");
        } else {
            writeLine("\$this->container->get(ResponseFactoryInterface::class)->createResponse(404),");
        }
    } else {
        writeLine("\"$class::$method\",");
    }
}

$needsDefaultArm = $lastRoute;

if (isset($route['next'])) {
    $firstIncluded = true;
    $generatedLeaves = 0;
    $regexes = [];
    $lastUri = array_key_last($route['next']);
    $hasGeneratedSubRoute = false;
    foreach ($route['next'] as $newUri => $newRoute) {
        $hasGeneratedSubRouteTemp = $hasGeneratedSubRoute;
        if ($newUri === 'zzz_default_zzz') {
            $regexes = $newRoute;
            continue;
        }
        includeLeaf($newUri, $newRoute, $indentation + 1, $firstIncluded, $capturedParams, $compiler, $hasGeneratedSubRouteTemp, $generateCall, $lastUri === $newUri);
        $firstIncluded = false;
        ++$generatedLeaves;

        if ($hasGeneratedSubRouteTemp) {
            $hasGeneratedSubRoute = true;
        }
    }

    if (count($regexes) > 0) {
        if ($generatedLeaves > 0) {
            write('default => ');
            $needsDefaultArm = false;
        } else {
            --$indentation;
        }
        $firstIncluded = true;
        $lastUri = array_key_last($regexes);
        $hasGeneratedSubRoute = false;
        foreach ($regexes as $newUri => $newRoute) {
            $hasGeneratedSubRouteTemp = $hasGeneratedSubRoute;
            includeLeaf($newUri, $newRoute, $indentation + 1, $firstIncluded, $capturedParams, $compiler, $hasGeneratedSubRouteTemp, $generateCall, $lastUri === $newUri);
            $firstIncluded = false;

            if ($hasGeneratedSubRouteTemp) {
                $hasGeneratedSubRoute = true;
            }
        }
    } elseif ($generatedLeaves === 0) {
        $needsDefaultArm = false;
    }
}

if ($needsDefaultArm && $hasGeneratedRoute) {
    if ($generateCall) {
        writeLine('default => $this->container->get(ResponseFactoryInterface::class)->createResponse(404),', $indentation);
    } else {
        writeLine('default => null,', $indentation);
    }
}

if ($lastRoute && $hasGeneratedRoute) {
    --$indentation;
    writeLine('},', $indentation);
}
