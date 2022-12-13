# gitea deploy

A simple php script for Gitea users to automatically git deploy to the server.

## howto

- Copy `src/hook.php` to your project
- Edit `hook.php`, set variables `_KEY`, `_REPO_PATH` and `_LOG_PATH`
- Deploy the script to your server the classic way
- In the Gitea project `Settings`, select `Gitea` from `Add webhook`.
- Set `Target URL` to `https://www.example.com/api/git-hook/hook.php?section=site`
- Set `HTTP Method` to `POST`
- Set `Post Content Type` to `application/json`
- Set `Secret` to `_KEY` value
- Set `Trigger On` to `Push Events`
- Set `Branch filter` to `master` or any branch you want to pull
- Check `Active`
- Click `Add Webhook`
- Once the webhook was added, click on it and scroll to the bottom and click `Test Delivery`
- If the delivery succeeds you are all set. If it fails, go to the server and check the log.

## clean code

```sh
vendor/bin/php-cs-fixer fix
```

## check code for problems

### phpstan

```sh
vendor/bin/phpstan analyse --level 5 src/
```

### phpmd

```sh
vendor/bin/phpmd src/ text cleancode,codesize,controversial,design,naming,unusedcode
```
