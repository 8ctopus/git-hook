# gitea-deploy

A simple php script for Gitea users to automatically git deploy to the server.

## howto

- Copy file `hook.php` to your project
- Edit `hook.php`, set variables `_KEY`, `_REPOPATH` and `_LOGPATH`
- Deploy to the script your server the classic way
- In the Gitea project `Settings`, select `Gitea` from `Add webhook`.
- Set `Target URL` to `http://www.example.com/api/git-hook/hook.php?section=site`
- Set `HTTP Method` to `POST`
- Set `Post Content Type` to `application/json`
- Set `Secret` to `_KEY` value
- Set `Trigger On` to `Push Events`
- Set `Branch filter` to `master` or any branch you want to pull
- Check `Active`
- Click `Add Webhook`
- Once the webhook was added, click on it and scroll to the bottom and click `Test Delivery`
- If the delivery succeeds you are all set. If it fails, go to the server and check the log.

## lint code

```sh
composer install
./vendor/friendsofphp/php-cs-fixer/php-cs-fixer fix --allow-risky=yes src/*.php
```
