<?php

declare(strict_types=1);

use Oct8pus\GiteaHook;

(new GiteaHook($_SERVER['DOCUMENT_ROOT'] . '/../', $_SERVER['DOCUMENT_ROOT'] . '/../logs/git-hook/', 'SECRET_KEY'))
    ->run();
