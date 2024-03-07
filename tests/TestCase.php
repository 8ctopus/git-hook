<?php

declare(strict_types=1);

namespace Tests;

use HttpSoft\Message\ServerRequest;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\ServerRequestInterface;

abstract class TestCase extends BaseTestCase
{
    public function mockRequest(string $method, string $uri, array $getParams = [], array $postParams = []) : self
    {
        unset($_GET, $_POST, $_FILES, $_SERVER);

        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36 Edg/112.0.1722.58';
        $_SERVER['REQUEST_PROTOCOL'] = '1.1';

        $_GET = $getParams;
        $_POST = $postParams;

        return $this;
    }

    /**
     * Mock server request
     *
     * @param  string                 $method
     * @param  string                 $path
     * @param  array                  $params
     * @param  array                  $server
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
