<?php

declare(strict_types=1);

use Oct8pus\GiteaHook;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Oct8pus\GiteaHook
 */
final class GiteaHookTest extends TestCase
{
    public function testNoSection() : void
    {
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
