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
    private static string $tempDir;

    public static function setUpBeforeClass(): void
    {
        static::$tempDir = sys_get_temp_dir() . '/gitea-hook-test/';
    }

    public function testNoSection() : void
    {
        $this->mockRequest('GET', '', [], []);

        (new GiteaHookMock(__DIR__, static::$tempDir, 'SECRET_KEY'))
            ->run();

        static::expectOutputString('git hook -  - FAILED - no section');
    }

    public function testUnknownSection() : void
    {
        $this->mockRequest('POST', '', [
            'section' => 'unknown',
        ]);

        (new GiteaHookMock(__DIR__, static::$tempDir, 'SECRET_KEY'))
            ->run();

        static::expectOutputString('git hook - unknown - FAILED - unknown section - unknown');
    }

    public function testNotPostRequest() : void
    {
        $this->mockRequest('GET', '', [
            'section' => 'site',
        ]);

        (new GiteaHookMock(__DIR__, static::$tempDir, 'SECRET_KEY'))
            ->run();

        static::expectOutputString('git hook - site - FAILED - not POST - GET');
    }

    public function testNoPayload() : void
    {
        $this->mockRequest('POST', '', [
            'section' => 'site',
        ]);

        (new GiteaHookMock(__DIR__, static::$tempDir, 'SECRET_KEY'))
            ->run();

        static::expectOutputString('git hook - site - FAILED - no payload');
    }

    public function testHeaderSignatureMissing() : void
    {
        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
            'payload' => 'test',
        ]);

        (new GiteaHookMock(__DIR__, static::$tempDir, 'SECRET_KEY'))
            ->run();

        static::expectOutputString('git hook - site - FAILED - header signature missing');
    }

    public function testInvalidPayloadSignature() : void
    {
        $secretKey = 'sd90sfufj';
        $payload = 'test';

        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = 'invalid signature';

        (new GiteaHookMock(__DIR__, static::$tempDir, $secretKey))
            ->run();

        static::expectOutputString('git hook - site - FAILED - payload signature');
    }

    public function testPayloadJsonDecode() : void
    {
        $secretKey = 'sd90sfufj';
        $payload = 'test';

        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = hash_hmac('sha256', $payload, $secretKey, false);

        (new GiteaHookMock(__DIR__, static::$tempDir, $secretKey))
            ->run();

        static::expectOutputString('git hook - site - FAILED - json decode - 4');
    }

    public function testSomething() : void
    {
        $secretKey = 'sd90sfufj';
        $payload = 'test';

        $payload = json_encode($payload);

        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = hash_hmac('sha256', $payload, $secretKey, false);

        (new GiteaHookMock(__DIR__, static::$tempDir, $secretKey))
            ->run();

        static::expectOutputString('git hook - site - FAILED - json decode - 4');
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
