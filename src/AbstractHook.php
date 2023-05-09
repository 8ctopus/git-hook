<?php

declare(strict_types=1);

namespace Oct8pus;

use Exception;
use Psr\Log\LoggerInterface;

abstract class AbstractHook
{
    protected array $commands;
    protected string $secretKey;
    protected ?LoggerInterface $logger;

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
            $this->validateRequest();

            $payload = $this->payload();

            $signature = $this->headerSignature();

            $this->validateSignature($payload, $signature);

            $json = $this->decodePayload($payload);

            // validate json
            if (!is_array($json) || !array_key_exists('repository', $json) || !array_key_exists('name', $json['repository'])) {
                throw new Exception("invalid payload", 401);
            }

            $this->runCommands($json);
        } catch (Exception $exception) {
            $this->logger?->error($exception->getMessage());
            throw $exception;
        }

        return $this;
    }

    /**
     * Validate request
     *
     * @return void
     *
     * @throws Exception
     */
    protected function validateRequest() : void
    {
        // check for POST request
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return;
        }

        throw new Exception("not a POST request - {$_SERVER['REQUEST_METHOD']}", 401);
    }

    /**
     * Get payload
     *
     * @return string
     *
     * @throws Exception
     */
    protected function payload() : string
    {
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

        return $payload;
    }

    /**
     * Decode payload
     *
     * @param  string $payload
     * @return array
     *
     * @throws Exception
     */
    protected function decodePayload(string $payload) : array
    {
        // convert json to array
        $json = json_decode($payload, true);

        // check for json decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('json decode - ' . json_last_error(), 401);
        }

        return $json;
    }

    /**
     * Get header signature
     *
     * @returns string
     *
     * @throws Exception
     */
    abstract protected function headerSignature() : string;

    /**
     * Validate signature
     *
     * @param  string $payload
     * @param  string $headerSignature
     *
     * @return void
     *
     * @throws Exception
     */
    protected function validateSignature(string $payload, string $headerSignature) : void
    {
        // calculate payload signature
        $payloadSignature = hash_hmac('sha256', $payload, $this->secretKey, false);

        // check payload signature against header signature
        if ($headerSignature !== $payloadSignature) {
            throw new Exception('invalid payload signature', 401);
        }
    }

    /**
     * Run commands
     *
     * @param array $json
     *
     * @return void
     *
     * @throws Exception
     */
    protected function runCommands(array $json) : void
    {
        $name = $json['repository']['name'];

        if (!array_key_exists($name, $this->commands)) {
            throw new Exception("unknown repository - {$name}", 401);
        }

        $commands = is_array($this->commands[$name]['commands']) ? $this->commands[$name]['commands'] : [$this->commands[$name]['commands']];

        foreach ($commands as $command) {
            $this->logger?->debug("execute command - {$command}");

            $process = proc_open($command, [
                0 => ['pipe', 'r'], // stdin
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w'], // stderr
            ], $pipes, $this->commands[$name]['path']);

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

    }
}
