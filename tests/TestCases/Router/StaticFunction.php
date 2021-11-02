<?php

declare(strict_types=1);

namespace Riaf\TestCases\Router;

use Nyholm\Psr7\Response;
use Riaf\Routing\Route;

class StaticFunction
{
    #[Route('/static/function')]
    public static function staticHandler(): Response
    {
        return new Response(body: 'Hey');
    }
}
