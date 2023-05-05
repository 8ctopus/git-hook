<?php

declare(strict_types=1);

use Oct8pus\GiteaHook;

$path = __DIR__;

$commands = [
    'site' =>
        // pull and run composer
        <<<COMMAND
        cd {$path};
        /usr/bin/git pull 2>&1;
        /usr/bin/composer install --no-interaction --no-dev 2>&1;
        COMMAND,
    'store' =>
        // pull, run composer then php artisan
        // adjust to your needs
        <<<COMMAND
        cd {$path};
        /usr/bin/git pull 2>&1;
        /usr/bin/composer install --no-interaction --no-dev 2>&1;
        php artisan optimize:clear 2>&1;
        php artisan migrate --force 2>&1;
        COMMAND,
];

(new GiteaHook($commands, 'SECRET_KEY', null))
    ->run();
