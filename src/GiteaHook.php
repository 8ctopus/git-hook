<?php

declare(strict_types=1);

namespace Oct8pus;

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

    public function run() : void
    {
        $logBase = 'git hook - ';

        // get section to pull
        $section = $_GET['section'] ?? '';

        // check section
        if (empty($section)) {
            $this->errorLog("{$logBase} - FAILED - no section");
            http_response_code(401);
            return;
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
                $this->errorLog("{$logBase} - FAILED - unknown section - {$section}");
                http_response_code(401);
                return;
        }

        // check for POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->errorLog("{$logBase} - FAILED - not POST - {$_SERVER['REQUEST_METHOD']}");
            http_response_code(401);
            return;
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
                $this->errorLog("{$logBase} - FAILED - unknown content type - {$contentType}");
                http_response_code(401);
                return;
        }

        // check payload exists
        if (empty($payload)) {
            $this->errorLog("{$logBase} - FAILED - no payload");
            http_response_code(401);
            return;
        }

        // get header signature
        $headerSignature = $_SERVER['HTTP_X_GITEA_SIGNATURE'] ?? '';

        if (empty($headerSignature)) {
            $this->errorLog("{$logBase} - FAILED - header signature missing");
            http_response_code(401);
            return;
        }

        // calculate payload signature
        $payloadSignature = hash_hmac('sha256', $payload, $this->secretKey, false);

        // check payload signature against header signature
        if ($headerSignature !== $payloadSignature) {
            $this->errorLog("{$logBase} - FAILED - payload signature");
            http_response_code(401);
            return;
        }

        // convert json to array
        $decoded = json_decode($payload, true);

        // check for json decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errorLog("{$logBase} - FAILED - json decode - " . json_last_error());
            //file_put_contents('/var/tmp/git-debug.log', var_export($payload, true));
            http_response_code(401);
            return;
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
                $this->errorLog("{$logBase} - FAILED - create dir");
            }
        }

        if (!file_put_contents($this->logPath . date('Ymd-His') . '.log', $command . "\n\n" . print_r($output, true))) {
            $this->errorLog("{$logBase} - FAILED - save log");
        }

        $outputStr = '';

        foreach ($output as $str) {
            $outputStr .= $str . "\n";
        }

        // check command return code
        if ($status !== 0) {
            http_response_code(409);
            $this->errorLog("{$logBase} - FAILED - command return code - make sure server git remote -v contains password and git branch --set-upstream-to=origin/master master - {$outputStr}");
            return;
        }

        $this->errorLog("{$logBase} - OK - {$outputStr}");
    }

    protected function errorLog(string $error) : self
    {
        error_log($error);
        return $this;
    }
}
