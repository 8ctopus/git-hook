# gitea hook

A simple package to automate deployment for [Gitea](https://gitea.io/) users.

## demo

There is a demo in the repository that you can play with:

- git clone the repository
- start a local php development server: `php -S localhost:80 demo.php`
- run the curl request which simulates the gitea webhook

```
curl
    --request POST http://localhost/?section=site
    --header "content-type: application/json"
    --header "X-GITEA-SIGNATURE: 067ba4bc638c245de6d728095f019e8171148bdc070b98f1f7376b321ccdcd62"
    --data '{"payload":"test"}'
```

## install

    composer require 8ctopus/gitea-hook

## usage

- Create a new page such as this one in a public part of your website (in this example `https://example.com/api/gitea-hook/index.php`)

```php
declare(strict_types=1);

use Apix\Log\Logger\File;
use Oct8pus\GiteaHook;

// assuming script is in document_root/public/api/gitea-hook/index.php
$documentRoot = __DIR__ . '/../../..';

require_once $documentRoot . '/../../../vendor/autoload.php';

$commands = [
    'site' => [
        // adjust to your flavor
        "cd {$documentRoot}",
        '/usr/bin/git pull',
        'composer install --no-interaction --no-dev',
    ],
];

// the logger is optional but provides useful information (any PSR-3 logger will do)
// to use this logger, composer require 8ctopus/apix-log
$logger = new File(sys_get_temp_dir() . '/gitea-hook-' . date('Y-m-d-his') . '.log');

try {
    $logger->info('Gitea hook...');

    (new GiteaHook($commands, 'SAME_SECRET_KEY_AS_IN_GITEA_ADMIN', $logger))
        ->run();

    $logger->notice('Gitea hook - OK');
} catch (Exception $exception) {
    if ($exception->getCode() !== 0) {
        // informs the webhook that the command failed
        http_response_code($exception->getCode());
    }
}
```

- In the Gitea project, go to `Settings`, select `Gitea` from `Add webhook`.
- Set `Target URL` to `https://example.com/api/gitea-hook/index.php?section=site`
- Set `HTTP Method` to `POST`
- Set `Post Content Type` to `application/json`
- Set `Secret` using a strong password (same as in the script `SAME_SECRET_KEY_AS_IN_GITEA_ADMIN`)
- Set `Trigger On` to `Push Events`
- Set `Branch filter` to `master` or any branch you want to trigger the script
- Check `Active`
- Click `Add Webhook`
- Once added, click on it and scroll to the bottom and click `Test Delivery`
- If the delivery succeeds you are all set. If it fails, go to the server and check the log.

_Note_: for git pulls to work using user `www-data` (the apache processes typically run under that user), you probably will need to:

- include the user and password (must be url encoded) inside the git remote url

```
git remote set-url origin https://user:password@example.com/gitea/site.git
```

- set the upstream (so git knows where to pull from)

```
git branch --set-upstream-to=origin/master master
```

- make sure user `www-data` has the write permissions for the repository.

_Note_: If you are concerned about weaker security, you can consider giving user `www-data` permissions to run git as another user such as `ubuntu`. This way, your webserver files can be owned by `ubuntu` and `www-data` can only read them. I'm not security specialist, so be warned.

```
sudo -H -u ubuntu -- /usr/bin/git pull;
```

## clean code

    composer fix(-risky)

## phpstan

    composer phpstan

## phpmd

    composer phpmd
