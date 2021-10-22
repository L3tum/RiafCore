<?php

declare(strict_types=1);

/** @var RouterCompiler $compiler */
/** @var array<string, true> $capturedParams */
/** @var bool $firstRoute */
/** @var int $indentation */
/** @var string $uri */

/** @var array<string, mixed> $route */

use Riaf\Compiler\RouterCompiler;

$index = $route['index'];
$count = $index + 1;
$check = $firstRoute ? 'if' : 'elseif';
$needClosingBraces = true;
// Parameter
if (isset($route['parameter'])) {
    $parameter = (string) $route['parameter'];
    $pattern = $route['pattern'] ?? null;
    $capture = $route['capture'];

    if ($pattern !== null) { // Parameter with Requirement
        writeLine("$check (preg_match(\"/^$pattern$/\", \$uriParts[$index], \$matches) === 1)", $indentation);
        writeLine('{', $indentation);
        if ($capture) {
            writeLine("\$capturedParams[\"$parameter\"] = \$matches[0];", $indentation + 1);
            $capturedParams[$parameter] = true;
        }
    } else { // Parameter without requirement
        if ($capture) {
            // We don't need to go down one step so reduce indentation of the rest of the generated code
            --$indentation;
            $needClosingBraces = false;
            writeLine("\$capturedParams[\"$parameter\"] = \$uriParts[$index] ?? null;", $indentation + 1);
            $capturedParams[$parameter] = true;
        }
    }
} // Normal route
else {
    writeLine("$check (\$uriParts[$index] === \"$uri\")", $indentation);
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

if ($needClosingBraces) {
    writeLine('}', $indentation);
}
