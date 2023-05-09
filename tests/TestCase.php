<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

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
}
