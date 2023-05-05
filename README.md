# gitea hook

A simple package to automate deployment for [Gitea](https://gitea.io/) users.

## demo

There is a demo in the repository that you can play with:

- git clone the repository
- start the local php server: `php -S localhost:80 demo.php`
- run the curl command

```bash
curl
    --request POST http://localhost/?section=site
    --header "content-type: application/json"
    --header "X-GITEA-SIGNATURE: 067ba4bc638c245de6d728095f019e8171148bdc070b98f1f7376b321ccdcd62"
    --data '{"payload":"test"}'
```

## install

    composer require 8ctopus/gitea-hook

## usage

```php
declare(strict_types=1);

use Apix\Log\Logger\File;
use Oct8pus\GiteaHook;

require_once __DIR__ . '/vendor/autoload.php';

$path = __DIR__;

$commands = [
    'site' => [
        // adjust to your flavor
        "cd {$path}",
        '/usr/bin/git pull',
        'composer install --no-interaction --no-dev',
    ],
];

// the logger is optional but provides useful information
$logger = new File(__DIR__ . '/test.log');

(new GiteaHook($commands, 'SAME_SECRET_KEY_AS_IN_GITEA_ADMIN', $logger))
    ->run();
```

- In the Gitea project `Settings`, select `Gitea` from `Add webhook`.
- Set `Target URL` to `https://www.example.com/api/git-hook/hook.php?section=site`
- Set `HTTP Method` to `POST`
- Set `Post Content Type` to `application/json`
- Set `Secret` value (use some very hard to get value)
- Set `Trigger On` to `Push Events`
- Set `Branch filter` to `master` or any branch you want to trigger the script
- Check `Active`
- Click `Add Webhook`
- Once added, click on it and scroll to the bottom and click `Test Delivery`
- If the delivery succeeds you are all set. If it fails, go to the server and check the log.

## clean code

    composer fix(-risky)

## phpstan

    composer phpstan

## phpmd

    composer phpmd
