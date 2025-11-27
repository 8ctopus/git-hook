<?php

declare(strict_types=1);

namespace Tests;

use HttpSoft\Message\ServerRequest;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\ServerRequestInterface;

abstract class TestCase extends BaseTestCase
{
    /**
     * Mock server request
     *
     * @param string $method
     * @param string $path
     * @param array  $params
     * @param array  $server
     *
     * @return ServerRequestInterface
     */
    public function mockServerRequest(string $method, string $path, array $params = [], array $server = []) : ServerRequestInterface
    {
        $server = array_merge([
            'REQUEST_METHOD' => $method,
            'REQUEST_SCHEME' => 'https',
            'HTTP_HOST' => 'localhost',
            'REQUEST_URI' => $path,
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36 Edg/112.0.1722.58',
            'REQUEST_PROTOCOL' => '1.1',
        ], $server);

        return new ServerRequest($server, [], [], [], $params, $method, $path, [], null, '1.1');
    }
}

