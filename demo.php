<?php

/**
 * Demo
 *
 * @note to use the demo
 *
 * php -S localhost:80 demo.php
 *
 * curl --request POST http://localhost/?section=site --header "content-type: application/json" --header "X-GITEA-SIGNATURE: 79c1e54cfbf45e322f2a789cdd14185fc18375194994265c82c122d2f139b338" --data '{"payload":{"ref":"refs/heads/master","before":"fc7fc95de2d998e0b41e17cfc3442836bbf1c7c9","after": "fc7fc95de2d998e0b41e17cfc3442836bbf1c7c9","total_commits":1,"repository":{"name":"site"}}}'
 */

declare(strict_types=1);

use Apix\Log\Format\ConsoleColors;
use Apix\Log\Logger\Stream;
use Oct8pus\GiteaHook;

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

    'store' => [
        'path' => $path,
        'commands' => [
            '/usr/bin/git pull',
            '/usr/local/bin/composer install --no-interaction --no-dev',
            'php artisan optimize:clear',
            'php artisan migrate --force',
        ],
    ],
];

$logger = (new Stream('php://stdout'));
$logger->setFormat(new ConsoleColors());

try {
    $logger->debug('Gitea hook...');

    (new GiteaHook($commands, 'SECRET_KEY', $logger))
        ->run();

    $logger->notice('Gitea hook - OK');
} catch (Exception $exception) {
    if ($exception->getCode() !== 0) {
        http_response_code($exception->getCode());
    }
}
