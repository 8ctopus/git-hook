<?php

declare(strict_types=1);

namespace Tests;

use Oct8pus\GiteaHook;
use Tests\TestCase;

/**
 * @internal
 *
 * @covers \Oct8pus\GiteaHook
 */
final class GiteaHookTest extends TestCase
{
    public function testNoSection() : void
    {
        $this->mockRequest('GET', '', []);

        (new GiteaHookMock($_SERVER['DOCUMENT_ROOT'] . '/../', $_SERVER['DOCUMENT_ROOT'] . '/../logs/git-hook/', 'SECRET_KEY'))
            ->run();

        static::expectOutputString('git hook -  - FAILED - no section');
    }
}

class GiteaHookMock extends GiteaHook
{
    protected function errorLog(string $error) : self
    {
        echo $error;
        return $this;
    }
}
