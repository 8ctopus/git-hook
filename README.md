# git hook

[![packagist](http://poser.pugx.org/8ctopus/git-hook/v)](https://packagist.org/packages/8ctopus/git-hook)
[![downloads](http://poser.pugx.org/8ctopus/git-hook/downloads)](https://packagist.org/packages/8ctopus/git-hook)
[![min php version](http://poser.pugx.org/8ctopus/git-hook/require/php)](https://packagist.org/packages/8ctopus/git-hook)
[![license](http://poser.pugx.org/8ctopus/git-hook/license)](https://packagist.org/packages/8ctopus/git-hook)
[![tests](https://github.com/8ctopus/git-hook/actions/workflows/tests.yml/badge.svg)](https://github.com/8ctopus/git-hook/actions/workflows/tests.yml)
![code coverage badge](https://raw.githubusercontent.com/8ctopus/git-hook/image-data/coverage.svg)
![lines of code](https://raw.githubusercontent.com/8ctopus/git-hook/image-data/lines.svg)

Automate deployment for git pushes to [GitHub](https://github.com) and [Gitea](https://gitea.io).

## demo

- git clone the repository
- start a local php development server: `php -S localhost:80 demo.php`
- run the curl request which simulates the webhook request

```
curl \
    --request POST http://localhost/ \
    --header "content-type: application/json" \
    --header "X-HUB-SIGNATURE-256: 2d8e4a6f3114e41f09a65195b2d69b5844e7a1a9284cdb2671354568304dd7a6" \
    --data '{"ref":"refs/heads/master", "before":"fc7fc95de2d998e0b41e17cfc3442836bbf1c7c9", "after": "fc7fc95de2d998e0b41e17cfc3442836bbf1c7c9", "total_commits":1, "repository":{"name": "site"}}'
```

## install

    composer require 8ctopus/git-hook

## GitHub usage

- Create a new page such as this one in a public part of your website (in this example `https://example.com/api/myhook/index.php`)

```php
use Apix\Log\Logger\File;
use Oct8pus\GitHubHook;

// assuming script is in DOCUMENT_ROOT/public/api/myhook/index.php
$documentRoot = __DIR__ . '/../../..';

require_once $documentRoot . '/vendor/autoload.php';

$commands = [
    'site' => [
        'path' => $documentRoot,
        'commands' => [
            // adjust to your flavor
            '/usr/bin/git pull',
            '/usr/local/bin/composer install --no-interaction --no-dev',
        ],
    ],
];

// the logger is optional but provides useful information (any PSR-3 logger will do)
// to use this logger, composer require 8ctopus/apix-log
$logger = new File(sys_get_temp_dir() . '/git-hook-' . date('Y-m-d-His') . '.log');

try {
    $logger->info('Git hook...');

    (new GitHubHook($commands, 'SAME_SECRET_KEY_AS_IN_GITHUB_ADMIN', $logger))
        ->run();

    $logger->notice('Git hook - OK');
} catch (Exception $exception) {
    if ($exception->getCode() !== 0) {
        // informs the webhook that the command failed
        http_response_code($exception->getCode());

        // REMOVE ME IN PRODUCTION
        echo $exception->getMessage();
    }
}
```

- In the GitHub project, go to `Settings` > `Webhooks` > `Add webhook`.
- Set `Payload URL` to `https://example.com/api/myhook/`
- Set `Content type` to `application/json`
- Set `Secret` using a strong password (same as in the script `SAME_SECRET_KEY_AS_IN_GITHUB_ADMIN`)
- Set `Just the push event`
- Check `Active`
- Click `Add Webhook`
- Once added, click on it and scroll to the bottom to check the first delivery. If the first delivery succeeded you are all set. If it failed, review the response error and click `Redeliver` once you think you fixed it.

_Note_: Also read the important notes below.

## Gitea usage

- Create a new page such as this one in a public part of your website (in this example `https://example.com/api/myhook/index.php`)

```php
declare(strict_types=1);

use Apix\Log\Logger\File;
use Oct8pus\GiteaHook;

// assuming script is in DOCUMENT_ROOT/public/api/myhook/index.php
$documentRoot = __DIR__ . '/../../..';

require_once $documentRoot . '/vendor/autoload.php';

$commands = [
    'site' => [
        'path' => $documentRoot,
        'commands' => [
            // adjust to your flavor
            '/usr/bin/git pull',
            '/usr/local/bin/composer install --no-interaction --no-dev',
        ],
    ],
];

// the logger is optional but provides useful information (any PSR-3 logger will do)
// to use this logger, composer require 8ctopus/apix-log
$logger = new File(sys_get_temp_dir() . '/git-hook-' . date('Y-m-d-His') . '.log');

try {
    $logger->info('Git hook...');

    // use for gitea
    (new GiteaHook($commands, 'SAME_SECRET_KEY_AS_IN_GITEA_ADMIN', $logger))
        ->run();

    $logger->notice('Git hook - OK');
} catch (Exception $exception) {
    if ($exception->getCode() !== 0) {
        // informs the webhook that the command failed
        http_response_code($exception->getCode());

        // REMOVE ME IN PRODUCTION
        echo $exception->getMessage();
    }
}
```

- update your gitea configuration in order to allow to send webhooks to your domain

```ini
[webhook]
SKIP_TLS_VERIFY = false
ALLOWED_HOST_LIST = example.com
```

- In the Gitea project, go to `Settings`, select `Gitea` from `Add webhook`.
- Set `Target URL` to `https://example.com/api/myhook/`
- Set `HTTP Method` to `POST`
- Set `Post Content Type` to `application/json`
- Set `Secret` using a strong password (same as in the script `SAME_SECRET_KEY_AS_IN_GITEA_ADMIN`)
- Set `Trigger On` to `Push Events`
- Set `Branch filter` to `master` or any branch you want to trigger the script
- Check `Active`
- Click `Add Webhook`
- Once added, click on it and scroll to the bottom and click `Test Delivery`
- If the delivery succeeds you are all set. If it fails, go to the server and check the log.

## important notes for both github and gitea

_Note_: for git pulls to work using user `www-data` (the apache typically runs under that user), you probably will need to:

- make sure the upstream is set, so git knows where to pull from

```
git branch --set-upstream-to=origin/master master
```

- make sure user `www-data` is the owner of the git repository. If not, you will get the error message

```
[2023-05-05 16:23:21] ERROR fatal: detected dubious ownership in repository at '...'
```

_Note_: If you are concerned about weaker security, you can consider giving user `www-data` permissions to run git as another user such as `ubuntu`. This way, your webserver files can be owned by `ubuntu` and `www-data` can only read them. I'm not security specialist, so be warned.

```
sudo -H -u ubuntu -- /usr/bin/git pull;
```

~~_Note_: for git pulls to work using user `www-data` (the apache processes typically run under that user), you probably will need to:~~

~~- include the user and password (must be url encoded) inside the git remote url~~

```diff
- git remote set-url origin https://user:password@example.com/gitea/site.git
```

## debugging

The deployment script can be easily debugged locally using [ngrok](https://ngrok.com/).

- run ngrok `ngrok http 80`

- update the `Payload URL` for Github and `Target URL` for Gitea to the ngrok address, similar to this one `https://6daf-31-218-13-51.ngrok-free.app`

- run the php local server `php -S localhost:80 demo.php`

- start visual studio code debugging and set a breakpoint in `demo.php`

- In Github/Gitea, resend the webhook request

## clean code

    composer fix(-risky)

## phpstan

    composer phpstan

## phpmd

    composer phpmd

