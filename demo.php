<?php

/**
 * Demo
 *
 * @note to use the demo
 *
 * php -S localhost:80 demo.php
 *
 * curl --request POST http://localhost/?section=site --header "content-type: application/json" --header "X-GITEA-SIGNATURE: 067ba4bc638c245de6d728095f019e8171148bdc070b98f1f7376b321ccdcd62" --data '{"payload":"test"}'
 */

declare(strict_types=1);

use Apix\Log\Logger\Stream;
use Oct8pus\GiteaHook;

require_once __DIR__ . '/vendor/autoload.php';

$path = __DIR__;

$commands = [
    'site' => [
        // pull and run composer
        "cd {$path}",
        'git status',
        'composer install --no-interaction',
    ],
    /**
    'store' => [
        // pull, run composer then php artisan
        // adjust to your needs
        "cd {$path}",
        '/usr/bin/git pull',
        '/usr/bin/composer install --no-interaction --no-dev',
        'php artisan optimize:clear',
        'php artisan migrate --force',
    ],
    */
];

$logger = (new Stream('php://stdout'));

(new GiteaHook($commands, 'SECRET_KEY', $logger))
    ->run();
