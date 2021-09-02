<?php

namespace Riaf\Events;

use Riaf\Core;

class BootEvent extends CoreEvent
{
    public function __construct(protected Core $core)
    {
    }

    public function getCore(): Core
    {
        return $this->core;
    }
}
