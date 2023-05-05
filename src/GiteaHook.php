<?php

declare(strict_types=1);

namespace Oct8pus;

use Exception;

class GiteaHook
{
    private string $repoPath;
    private string $logPath;
    private string $secretKey;

    /**
     * Constructor
     *
     * @param string $repoPath
     * @param string $logPath
     * @param string $secretKey
     */
    public function __construct(string $repoPath, string $logPath, string $secretKey)
    {
        $this->repoPath = $repoPath;
        $this->logPath = $logPath;
        $this->secretKey = $secretKey;
    }

    /**
     * Run script
     *
     * @return void
     *
     * @throws Exception
     */
    public function run() : void
    {
        $logBase = 'git hook - ';

        // get section to pull
        $section = $_GET['section'] ?? '';

        // check section
        if (empty($section)) {
            throw new Exception('no section', 401);
        }

        // add section to env name
        $logBase .= $section;

        switch ($section) {
            case 'site':
                $path = $this->repoPath . 'public_html';
                break;

            case 'store':
                $path = $this->repoPath . 'store';
                break;

            default:
                throw new Exception("unknown section - {$section}", 401);
        }

        // check for POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception("not a POST request - {$_SERVER['REQUEST_METHOD']}", 401);
        }

        // get content type
        $contentType = mb_strtolower(trim($_SERVER['CONTENT_TYPE'] ?? ''));

        switch ($contentType) {
            case 'application/json':
                // get RAW post data
                $payload = trim(file_get_contents('php://input'));
                break;

            case '':
                // get payload
                $payload = $_POST['payload'] ?? '';
                break;

            default:
                throw new Exception("unknown content type - {$contentType}", 401);
        }

        // check payload exists
        if (empty($payload)) {
            throw new Exception("{$logBase} - FAILED - no payload", 401);
        }

        // get header signature
        $headerSignature = $_SERVER['HTTP_X_GITEA_SIGNATURE'] ?? null;

        if (empty($headerSignature)) {
            throw new Exception("header signature missing", 401);
        }

        // calculate payload signature
        $payloadSignature = hash_hmac('sha256', $payload, $this->secretKey, false);

        // check payload signature against header signature
        if ($headerSignature !== $payloadSignature) {
            throw new Exception("invalid payload signature", 401);
        }

        // convert json to array
        $decoded = json_decode($payload, true);

        // check for json decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("json decode - " . json_last_error(), 401);
        }

        // prepare command
        $command = "cd {$path};";

        if ($section === 'site') {
            // pull and run composer
            $command .= '/usr/bin/git pull 2>&1;';
            $command .= '/usr/bin/composer install --no-interaction --no-dev 2>&1;';
        } elseif ($section === 'store') {
            // pull, run composer then php artisan
            // adjust to your needs
            $command .= '/usr/bin/git pull 2>&1;';
            $command .= '/usr/bin/composer install --no-interaction --no-dev 2>&1;';
            $command .= 'php artisan optimize:clear 2>&1;';
            $command .= 'php artisan migrate --force 2>&1;';
        } else {
            // just pull
            $command .= '/usr/bin/git pull;';
        }

        // execute commands
        exec($command, $output, $status);

        // save log
        if (!file_exists($this->logPath)) {
            if (!mkdir($this->logPath)) {
                throw new Exception("create dir", 500);
            }
        }

        if (!file_put_contents($this->logPath . date('Ymd-His') . '.log', $command . "\n\n" . print_r($output, true))) {
            throw new Exception("save log", 500);
        }

        $outputStr = '';

        foreach ($output as $str) {
            $outputStr .= $str . "\n";
        }

        // check command return code
        if ($status !== 0) {
            throw new Exception("command return code - make sure server git remote -v contains password and git branch --set-upstream-to=origin/master master - {$outputStr}", 409);
        }

        $this->errorLog("{$logBase} - OK - {$outputStr}");
    }

    protected function errorLog(string $error) : self
    {
        error_log($error);
        return $this;
    }
}
