<?php

/**
 * Demo
 *
 * @note to use the demo
 *
 * php -S localhost:80 demo.php
 *
 * curl --request POST http://localhost/ --header "content-type: application/json" --header "X-HUB-SIGNATURE-256: 2d8e4a6f3114e41f09a65195b2d69b5844e7a1a9284cdb2671354568304dd7a6" --data '{"ref":"refs/heads/master", "before":"fc7fc95de2d998e0b41e17cfc3442836bbf1c7c9", "after": "fc7fc95de2d998e0b41e17cfc3442836bbf1c7c9", "total_commits":1, "repository":{"name": "site"}}'
 */

declare(strict_types=1);

use Apix\Log\Format\ConsoleColors;
use Apix\Log\Logger\Stream;
use Apix\Log\Logger;
use Oct8pus\GitHubHook;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/vendor/autoload.php';

$path = __DIR__;

$commands = [
    'site' => [
        'path' => $path,
        'commands' => [
            // pull and run composer
            'git status',
            'composer install --no-interaction',
        ],
    ],

    // ngrok local test
    'git-hook' => [
        'path' => $path,
        'commands' => [
            'git status',
            'composer install --no-interaction',
        ],
        // it's also possible to define a callback after commands were executed
        'afterExec' => function (?LoggerInterface $logger, string $command, string $stdout, string $stderr, string $status) : void {
            if ($command === 'git status') {
                if (str_contains($stdout, 'Your branch is up to date with') || str_contains($stdout, 'nothing to commit, working tree clean')) {
                    return;
                }

                $logger->error('Git status');
            }
        }
    ],

    // an example for laravel
    'laravel' => [
        'path' => $path,
        'commands' => [
            '/usr/bin/git pull',
            '/usr/local/bin/composer install --no-interaction --no-dev',
            'php artisan optimize:clear',
            'php artisan migrate --force',
        ],
    ],
];

try {
    $logger = (new Stream('php://stdout'));
    $logger->setFormat(new ConsoleColors());

    $logger->debug('Git hook...');

    (new GitHubHook($commands, 'SECRET_KEY', $logger))
        ->run();

    if ($logger->getMinLevelLogged() >= Logger::getLevelCode('notice')) {
        $logger->notice('Git hook - OK');
    } else {
        $logger->error('Git hook - FAILED');
    }
} catch (Exception $exception) {
    if ($exception->getCode() !== 0) {
        http_response_code($exception->getCode());

        // REMOVE IN PRODUCTION
        echo $exception->getMessage();
    }
}
