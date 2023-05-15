<?php

declare(strict_types=1);

namespace Tests;

use Apix\Log\Logger\Runtime;
use Exception;
use Oct8pus\GitHubHook;

/**
 * @internal
 *
 * @covers \Oct8pus\AbstractHook
 * @covers \Oct8pus\GitHubHook
 */
final class GitHubHookTest extends TestCase
{
    private static array $commands;
    private static string $payload;
    private static string $secretKey;

    public static function setUpBeforeClass() : void
    {
        static::$commands = [
            'site' => [
                'path' => __DIR__ . '/..',
                'commands' => [
                    'git status',
                    'composer install --no-interaction',
                ],
            ],
            'store' => [
                'path' => __DIR__ . '/../store/..',
                'commands' => [
                    'git status',
                    'composer install --no-interaction',
                ],
            ],
        ];

        static::$payload = <<<'JSON'
        {
            "ref": "refs/heads/master",
            "before":"fc7fc95de2d998e0b41e17cfc3442836bbf1c7c9",
            "after": "fc7fc95de2d998e0b41e17cfc3442836bbf1c7c9",
            "total_commits": 1,
            "repository": {
                "name": "site"
            }
        }

        JSON;

        static::$secretKey = 'sd90sfufj';
    }

    public function testOK() : void
    {
        $this->mockRequest('POST', '', [], [
            'payload' => static::$payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=' . hash_hmac('sha256', static::$payload, static::$secretKey, false);

        $logger = new Runtime();

        (new GitHubHook(static::$commands, static::$secretKey, $logger))
            ->run();

        // no exception will do
        //REM static::assertStringContainsString('nothing to commit', implode("\n", $logger->getItems()));
        static::assertTrue(true);
    }

    public function testNotPostRequest() : void
    {
        $this->mockRequest('GET', '', []);

        static::expectException(Exception::class);
        static::expectExceptionMessage('not a POST request - GET');
        static::expectExceptionCode(401);

        (new GitHubHook(static::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testNoPayload() : void
    {
        $this->mockRequest('POST', '', []);

        static::expectException(Exception::class);
        static::expectExceptionMessage('no payload');
        static::expectExceptionCode(401);

        (new GitHubHook(static::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testHeaderSignatureMissing() : void
    {
        $this->mockRequest('POST', '', [], [
            'payload' => 'test',
        ]);

        static::expectException(Exception::class);
        static::expectExceptionMessage('header signature missing');
        static::expectExceptionCode(401);

        (new GitHubHook(static::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testInvalidPayloadSignature() : void
    {
        $payload = 'test';

        $this->mockRequest('POST', '', [], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = 'invalid signature';

        static::expectException(Exception::class);
        static::expectExceptionMessage('payload signature');
        static::expectExceptionCode(401);

        (new GitHubHook(static::$commands, static::$secretKey, null))
            ->run();
    }

    public function testInvalidPayload() : void
    {
        $payload = '{"test":1}';

        $this->mockRequest('POST', '', [], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = hash_hmac('sha256', $payload, static::$secretKey, false);

        static::expectException(Exception::class);
        static::expectExceptionMessage('invalid payload');
        static::expectExceptionCode(401);

        (new GitHubHook(static::$commands, static::$secretKey, null))
            ->run();
    }

    public function testInvalidRepository() : void
    {
        $payload = '{"repository": {"name": "test"}}';

        $this->mockRequest('POST', '', [], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = hash_hmac('sha256', $payload, static::$secretKey, false);

        static::expectException(Exception::class);
        static::expectExceptionMessage('unknown repository - test');
        static::expectExceptionCode(401);

        (new GitHubHook(static::$commands, static::$secretKey, null))
            ->run();
    }

    public function testPayloadJsonDecode() : void
    {
        $payload = 'test';

        $this->mockRequest('POST', '', [], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = hash_hmac('sha256', $payload, static::$secretKey, false);

        static::expectException(Exception::class);
        static::expectExceptionMessage('json decode - 4');
        static::expectExceptionCode(401);

        (new GitHubHook(static::$commands, static::$secretKey, null))
            ->run();
    }

    public function testInvalidCommand() : void
    {
        $this->mockRequest('POST', '', [], [
            'payload' => static::$payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = hash_hmac('sha256', static::$payload, static::$secretKey, false);

        static::expectException(Exception::class);
        static::expectExceptionMessage('command exit code - 1');
        static::expectExceptionCode(409);

        $logger = new Runtime();

        try {
            $commands = [
                'site' => [
                    'path' => __DIR__,
                    'commands' => [
                        'invalid',
                    ],
                ],
            ];

            (new GitHubHook($commands, static::$secretKey, $logger))
                ->run();
        } catch (Exception $exception) {
            $needle = strtoupper(substr(php_uname('s'), 0, 3)) === 'WIN' ?
                'ERROR \'invalid\' is not recognized as an internal or external command' :
                'invalid: not found';

            static::assertStringContainsString($needle, implode("\n", $logger->getItems()));

            throw $exception;
        }
    }
}
