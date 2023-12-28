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
use Apix\Log\Logger;
use Apix\Log\Logger\Stream;
use Oct8pus\GitHubHook;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/vendor/autoload.php';

$path = __DIR__;

$commands = [
    // repository name eg. https://github.com/8ctopus/site
    'site' => [
        'path' => $path,
        'commands' => [
            'git status' => checkGitStatus(...),
            'composer install --no-interaction',
            'git branch' => checkGitBranch(...),
        ],
        // it's also possible to define a global callback
        'afterExec' => genericCallback(...),
    ],

    // ngrok local test
    'git-hook' => [
        'path' => $path,
        'commands' => [
            'git status' => checkGitStatus(...),
            'composer install --no-interaction',
        ],
        'afterExec' => genericCallback(...),
    ],

    // laravel example
    'laravel' => [
        'path' => $path,
        'commands' => [
            'sudo -H -u ubuntu -- /usr/bin/php artisan down',
            'sudo -H -u ubuntu -- /usr/bin/git pull',
            'sudo -H -u ubuntu -- /usr/bin/git status' => checkGitStatus(...),
            'sudo -H -u ubuntu -- /usr/local/bin/composer install --no-interaction --no-dev',
            '/usr/bin/php artisan optimize:clear',
            'sudo -H -u ubuntu -- /usr/bin/php artisan migrate --force',
            'sudo -H -u ubuntu -- /usr/bin/php artisan up',
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

/**
 * Check git is clean
 *
 * @param ?LoggerInterface $logger
 * @param string           $command
 * @param string           $stdout
 * @param string           $stderr
 * @param string           $status
 *
 * @return bool
 */
function checkGitStatus(?LoggerInterface $logger, string $command, string $stdout, string $stderr, string $status) : bool
{
    if (str_contains($stdout, 'Your branch is up to date with') || str_contains($stdout, 'nothing to commit, working tree clean')) {
        return true;
    }

    $logger?->error('git status');
    return false;
}

function checkGitBranch(?LoggerInterface $logger, string $command, string $stdout, string $stderr, string $status) : bool
{
    if (preg_match('/^\* ([a-zA-Z]*)$/', $stdout, $matches) !== 1) {
        $logger?->error('detect branch');
        return false;
    }

    $branch = $matches[1];

    if ($branch === 'master') {
        return true;
    }

    $logger?->error("not on master branch - {$branch}");
    return false;
}

function genericCallback(?LoggerInterface $logger, string $command, string $stdout, string $stderr, string $status) : bool
{
    $logger?->debug($command);
    return true;
}
