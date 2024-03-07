<?php

declare(strict_types=1);

namespace Tests;

use Oct8pus\GiteaHook;
use Psr\Http\Message\ServerRequestInterface;

class GiteaHookMock extends GiteaHook
{
    private string $payload;

    public function __construct(ServerRequestInterface $request, array $commands, string $secretKey, string $payload)
    {
        $this->payload = $payload;

        parent::__construct($request, $commands, $secretKey);
    }

    protected function input() : string
    {
        return $this->payload;
    }
}
