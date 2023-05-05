<?php

declare(strict_types=1);

namespace Oct8pus;

use Exception;
use Psr\Log\LoggerInterface;

class GiteaHook
{
    private array $commands;
    private string $secretKey;
    private ?LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param array            $commands
     * @param string           $secretKey
     * @param ?LoggerInterface $logger
     */
    public function __construct(array $commands, string $secretKey, ?LoggerInterface $logger = null)
    {
        $this->commands = $commands;
        $this->secretKey = $secretKey;
        $this->logger = $logger;
    }

    /**
     * Run script
     *
     * @return self
     *
     * @throws Exception
     */
    public function run() : self
    {
        try {
            // get section to pull
            $section = $_GET['section'] ?? '';

            // check section
            if (empty($section)) {
                throw new Exception('no section', 401);
            }

            if (!array_key_exists($section, $this->commands)) {
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

            if (empty($payload)) {
                throw new Exception('no payload', 401);
            }

            // get header signature
            $headerSignature = $_SERVER['HTTP_X_GITEA_SIGNATURE'] ?? null;

            if (empty($headerSignature)) {
                throw new Exception('header signature missing', 401);
            }

            // calculate payload signature
            $payloadSignature = hash_hmac('sha256', $payload, $this->secretKey, false);

            // check payload signature against header signature
            if ($headerSignature !== $payloadSignature) {
                throw new Exception('invalid payload signature', 401);
            }

            // convert json to array
            /* $decoded = */ json_decode($payload, true);

            // check for json decode errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('json decode - ' . json_last_error(), 401);
            }

            $commands = is_array($this->commands[$section]) ? $this->commands[$section] : [$this->commands[$section]];

            foreach ($commands as $command) {
                $this->logger?->debug("execute command - {$command}");

                $process = proc_open($command, [
                    0 => ['pipe', 'r'], // stdin
                    1 => ['pipe', 'w'], // stdout
                    2 => ['pipe', 'w'], // stderr
                ], $pipes);

                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);

                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);

                $status = proc_close($process);

                if (!empty($stdout)) {
                    $this->logger?->info($stdout);
                }

                if (!empty($stderr)) {
                    $this->logger?->error($stderr);
                }

                // check command return code
                if ($status !== 0) {
                    // make sure server git remote -v contains password and git branch --set-upstream-to=origin/master master
                    throw new Exception("command exit code - {$status}", 409);
                }
            }
        } catch (Exception $exception) {
            $this->logger?->error($exception->getMessage());
            throw $exception;
        }

        return $this;
    }
}
