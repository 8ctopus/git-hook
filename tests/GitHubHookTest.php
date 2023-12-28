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
        self::$commands = [
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

        self::$payload = <<<'JSON'
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

        self::$secretKey = 'sd90sfufj';
    }

    public function testOK() : void
    {
        $this->mockRequest('POST', '', [], [
            'payload' => self::$payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=' . hash_hmac('sha256', self::$payload, self::$secretKey, false);

        $logger = new Runtime();

        (new GitHubHook(self::$commands, self::$secretKey, $logger))
            ->run();

        // no exception will do
        //REM static::assertStringContainsString('nothing to commit', implode("\n", $logger->getItems()));
        self::assertTrue(true);
    }

    public function testNotPostRequest() : void
    {
        $this->mockRequest('GET', '', []);

        self::expectException(Exception::class);
        self::expectExceptionMessage('not a POST request - GET');
        self::expectExceptionCode(401);

        (new GitHubHook(self::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testNoPayload() : void
    {
        $this->mockRequest('POST', '', []);

        self::expectException(Exception::class);
        self::expectExceptionMessage('no payload');
        self::expectExceptionCode(401);

        (new GitHubHook(self::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testHeaderSignatureMissing() : void
    {
        $this->mockRequest('POST', '', [], [
            'payload' => 'test',
        ]);

        self::expectException(Exception::class);
        self::expectExceptionMessage('header signature missing');
        self::expectExceptionCode(401);

        (new GitHubHook(self::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testInvalidPayloadSignature() : void
    {
        $payload = 'test';

        $this->mockRequest('POST', '', [], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = 'invalid signature';

        self::expectException(Exception::class);
        self::expectExceptionMessage('payload signature');
        self::expectExceptionCode(401);

        (new GitHubHook(self::$commands, self::$secretKey, null))
            ->run();
    }

    public function testInvalidPayload() : void
    {
        $payload = '{"test":1}';

        $this->mockRequest('POST', '', [], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = hash_hmac('sha256', $payload, self::$secretKey, false);

        self::expectException(Exception::class);
        self::expectExceptionMessage('invalid payload');
        self::expectExceptionCode(401);

        (new GitHubHook(self::$commands, self::$secretKey, null))
            ->run();
    }

    public function testInvalidRepository() : void
    {
        $payload = '{"repository": {"name": "test"}}';

        $this->mockRequest('POST', '', [], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = hash_hmac('sha256', $payload, self::$secretKey, false);

        self::expectException(Exception::class);
        self::expectExceptionMessage('unknown repository - test');
        self::expectExceptionCode(401);

        (new GitHubHook(self::$commands, self::$secretKey, null))
            ->run();
    }

    public function testPayloadJsonDecode() : void
    {
        $payload = 'test';

        $this->mockRequest('POST', '', [], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = hash_hmac('sha256', $payload, self::$secretKey, false);

        self::expectException(Exception::class);
        self::expectExceptionMessage('json decode - 4');
        self::expectExceptionCode(401);

        (new GitHubHook(self::$commands, self::$secretKey, null))
            ->run();
    }

    public function testInvalidCommand() : void
    {
        $this->mockRequest('POST', '', [], [
            'payload' => self::$payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = hash_hmac('sha256', self::$payload, self::$secretKey, false);

        self::expectException(Exception::class);
        self::expectExceptionMessage('command exit code - 1');
        self::expectExceptionCode(409);

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

            (new GitHubHook($commands, self::$secretKey, $logger))
                ->run();
        } catch (Exception $exception) {
            $needle = strtoupper(substr(php_uname('s'), 0, 3)) === 'WIN' ?
                'ERROR \'invalid\' is not recognized as an internal or external command' :
                'invalid: not found';

            self::assertStringContainsString($needle, implode("\n", $logger->getItems()));

            throw $exception;
        }
    }
}
