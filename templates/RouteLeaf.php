<?php

declare(strict_types=1);

/** @var RouterCompiler $compiler */
/** @var array<string, true> $capturedParams */
/** @var bool $firstRoute */
/** @var int $indentation */
/** @var string $uri */

/** @var array<string, mixed> $route */

use Riaf\Compiler\RouterCompiler;

if (!function_exists('includeLeaf')) {
    function includeLeaf(string $uri, array $route, int $indentation, bool $firstRoute, array $capturedParams, RouterCompiler $compiler): void
    {
        include __DIR__ . '/RouteLeaf.php';
    }
}

if (!function_exists('writeLine')) {
    function writeLine(string $line, ?int $indentation = null): void
    {
        $indentation = $indentation ?? 0;
        echo sprintf('%s%s%s', implode('', array_fill(0, $indentation, "\t")), $line, PHP_EOL);
    }
}

$index = $route['index'];
$count = $index + 1;
// Parameter
if (isset($route['parameter'])) {
    $parameter = (string) $route['parameter'];
    $pattern = $route['pattern'] ?? null;

    if ($pattern !== null) { // Parameter with Requirement
        writeLine("if(preg_match(\"/^$pattern$/\", \$uriParts[$index], \$matches) === 1)", $indentation);
        writeLine('{', $indentation);
        writeLine("\$capturedParams[\"$parameter\"] = \$matches[0];", $indentation + 1);
    } else { // Parameter without requirement
        writeLine("if(\$countParts >= $count)", $indentation);
        writeLine('{', $indentation);
        writeLine("\$capturedParams[\"$parameter\"] = \$uriParts[$index];", $indentation + 1);
    }

    $capturedParams[$parameter] = true;
} // Normal route
else {
    writeLine("if(\$uriParts[$index] === \"$uri\")", $indentation);
    writeLine('{', $indentation);
}

if (isset($route['call'])) {
    $class = $route['call']['class'];
    $method = $route['call']['method'];

    writeLine("if(\$countParts === $count)", $indentation + 1);
    writeLine('{', $indentation + 1);
    /**
     * @psalm-suppress InternalMethod
     */
    writeLine(
        sprintf(
            'return $this->container->get("%s")->%s(%s);',
            $class,
            $method,
            /**
             * @phpstan-ignore-next-line
             */
            implode(', ', $compiler->generateParams($class, $method, $capturedParams))
        ),
        $indentation + 2
    );
    writeLine('}', $indentation + 1);
}

if (isset($route['next'])) {
    $firstIncluded = true;
    foreach ($route['next'] as $newUri => $newRoute) {
        includeLeaf($newUri, $newRoute, $indentation + 1, $firstIncluded, $capturedParams, $compiler);
        $firstIncluded = false;
    }
}

writeLine('}', $indentation);
