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

        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
            'payload' => static::$payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=' . hash_hmac('sha256', static::$payload, $secretKey, false);

        $logger = new Runtime();

        (new GitHubHook(static::$commands, $secretKey, $logger))
            ->run();

        // no expection will do
        static::assertStringContainsString('nothing to commit', implode("\n", $logger->getItems()));
    }

    public function testNotPostRequest() : void
    {
        $this->mockRequest('GET', '', [
            'section' => 'site',
        ]);

        static::expectException(Exception::class);
        static::expectExceptionMessage('not a POST request - GET');
        static::expectExceptionCode(401);

        (new GitHubHook(static::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testNoPayload() : void
    {
        $this->mockRequest('POST', '', [
            'section' => 'site',
        ]);

        static::expectException(Exception::class);
        static::expectExceptionMessage('no payload');
        static::expectExceptionCode(401);

        (new GitHubHook(static::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testHeaderSignatureMissing() : void
    {
        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
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
        $secretKey = 'sd90sfufj';
        $payload = 'test';

        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = 'invalid signature';

        static::expectException(Exception::class);
        static::expectExceptionMessage('payload signature');
        static::expectExceptionCode(401);

        (new GitHubHook(static::$commands, $secretKey, null))
            ->run();
    }

    public function testInvalidPayload() : void
    {
        $secretKey = 'sd90sfufj';
        $payload = '{"test":1}';

        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = hash_hmac('sha256', $payload, $secretKey, false);

        static::expectException(Exception::class);
        static::expectExceptionMessage('invalid payload');
        static::expectExceptionCode(401);

        (new GitHubHook(static::$commands, $secretKey, null))
            ->run();
    }

    public function testInvalidRepository() : void
    {
        $secretKey = 'sd90sfufj';
        $payload = '{"repository": {"name": "test"}}';

        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = hash_hmac('sha256', $payload, $secretKey, false);

        static::expectException(Exception::class);
        static::expectExceptionMessage('unknown repository - test');
        static::expectExceptionCode(401);

        (new GitHubHook(static::$commands, $secretKey, null))
            ->run();
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

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = hash_hmac('sha256', $payload, $secretKey, false);

        static::expectException(Exception::class);
        static::expectExceptionMessage('json decode - 4');
        static::expectExceptionCode(401);

        (new GitHubHook(static::$commands, $secretKey, null))
            ->run();
    }

    public function testInvalidCommand() : void
    {
        $secretKey = 'sd90sfufj';

        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
            'payload' => static::$payload,
        ]);

        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = hash_hmac('sha256', static::$payload, $secretKey, false);

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

            (new GitHubHook($commands, $secretKey, $logger))
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
