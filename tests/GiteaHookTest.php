<?php

declare(strict_types=1);

namespace Tests;

use Apix\Log\Logger\Runtime;
use Exception;
use Oct8pus\GiteaHook;
use Psr\Log\LoggerInterface;

/**
 * @internal
 *
 * @covers \Oct8pus\AbstractHook
 * @covers \Oct8pus\GiteaHook
 */
final class GiteaHookTest extends TestCase
{
    private static array $commands;
    private static string $payload;

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
    }

    public function testOK() : void
    {
        $secretKey = 'sd90sfufj';

        $this->mockRequest('POST', '', [], [
            'payload' => static::$payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = hash_hmac('sha256', static::$payload, $secretKey, false);

        $logger = new Runtime();

        (new GiteaHook(static::$commands, $secretKey, $logger))
            ->run();

        // no exception will do
        static::assertStringContainsString('nothing to commit', implode("\n", $logger->getItems()));
    }

    public function testCallbackOK() : void
    {
        $secretKey = 'sd90sfufj';

        $this->mockRequest('POST', '', [], [
            'payload' => static::$payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = hash_hmac('sha256', static::$payload, $secretKey, false);

        $logger = new Runtime();

        $callback = false;

        $commands = static::$commands;

        $commands['site']['afterExec'] = function(?LoggerInterface $logger, string $command, string $stdout, string $stderr, string $status) use (&$callback) : void {
            $callback = true;
        };

        (new GiteaHook($commands, $secretKey, $logger))
            ->run();

        static::assertTrue($callback);
    }

    public function testNotPostRequest() : void
    {
        $this->mockRequest('GET', '', []);

        static::expectException(Exception::class);
        static::expectExceptionMessage('not a POST request - GET');
        static::expectExceptionCode(401);

        (new GiteaHook(static::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testNoPayload() : void
    {
        $this->mockRequest('POST', '', []);

        static::expectException(Exception::class);
        static::expectExceptionMessage('no payload');
        static::expectExceptionCode(401);

        (new GiteaHook(static::$commands, 'SECRET_KEY'))
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

        (new GiteaHook(static::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testInvalidPayloadSignature() : void
    {
        $secretKey = 'sd90sfufj';
        $payload = 'test';

        $this->mockRequest('POST', '', [], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = 'invalid signature';

        static::expectException(Exception::class);
        static::expectExceptionMessage('payload signature');
        static::expectExceptionCode(401);

        (new GiteaHook(static::$commands, $secretKey, null))
            ->run();
    }

    public function testInvalidPayload() : void
    {
        $secretKey = 'sd90sfufj';
        $payload = '{"test":1}';

        $this->mockRequest('POST', '', [], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = hash_hmac('sha256', $payload, $secretKey, false);

        static::expectException(Exception::class);
        static::expectExceptionMessage('invalid payload');
        static::expectExceptionCode(401);

        (new GiteaHook(static::$commands, $secretKey, null))
            ->run();
    }

    public function testInvalidRepository() : void
    {
        $secretKey = 'sd90sfufj';
        $payload = '{"repository": {"name": "test"}}';

        $this->mockRequest('POST', '', [], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = hash_hmac('sha256', $payload, $secretKey, false);

        static::expectException(Exception::class);
        static::expectExceptionMessage('unknown repository - test');
        static::expectExceptionCode(401);

        (new GiteaHook(static::$commands, $secretKey, null))
            ->run();
    }

    public function testPayloadJsonDecode() : void
    {
        $secretKey = 'sd90sfufj';
        $payload = 'test';

        $this->mockRequest('POST', '', [], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = hash_hmac('sha256', $payload, $secretKey, false);

        static::expectException(Exception::class);
        static::expectExceptionMessage('json decode - 4');
        static::expectExceptionCode(401);

        (new GiteaHook(static::$commands, $secretKey, null))
            ->run();
    }

    public function testPathMissing() : void
    {
        $secretKey = 'sd90sfufj';

        $this->mockRequest('POST', '', [], [
            'payload' => static::$payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = hash_hmac('sha256', static::$payload, $secretKey, false);

        static::expectException(Exception::class);
        static::expectExceptionMessage('path missing - site');
        static::expectExceptionCode(401);

        $commands = [
            'site' => [
                'commands' => [
                    'invalid',
                ],
            ],
        ];

        (new GiteaHook($commands, $secretKey))
            ->run();
    }

    public function testCommandsMissing() : void
    {
        $secretKey = 'sd90sfufj';

        $this->mockRequest('POST', '', [], [
            'payload' => static::$payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = hash_hmac('sha256', static::$payload, $secretKey, false);

        static::expectException(Exception::class);
        static::expectExceptionMessage('commands missing - site');
        static::expectExceptionCode(401);

        $commands = [
            'site' => [
                'path' => __DIR__,
            ],
        ];

        (new GiteaHook($commands, $secretKey))
            ->run();
    }

    public function testInvalidCommand() : void
    {
        $secretKey = 'sd90sfufj';

        $this->mockRequest('POST', '', [], [
            'payload' => static::$payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = hash_hmac('sha256', static::$payload, $secretKey, false);

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

            (new GiteaHook($commands, $secretKey, $logger))
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
