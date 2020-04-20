# gitea-deploy

A simple php script to automatically git pull the latest changes on your server.

# howto

- Copy file `hook.php` to your project
- Edit `hook.php`, set variables `_KEY`, `_REPOPATH` and `_LOGPATH`
- Deploy to the script your server the classic way
- In the Gitea project `Settings`, select `Gitea` from `Add webhook`.
- Set `Target URL` to `http://www.example.com/api/git-hook/hook.php`
- Set `HTTP Method` to `POST`
- Set `Post Content Type` to `application/json`
- Set `Secret` to `_KEY` value
- Set `Trigger On` to `Push Events`
- Set `Branch filter` to `*` or any other branch
- Check `Active`
- Click `Add Webhook`
