<?php

declare(strict_types=1);

namespace App\Application\Contracts;

interface IMediator
{
    /** Envía un Command o Query a su Handler correspondiente. */
    public function send(object $message): mixed;
}
