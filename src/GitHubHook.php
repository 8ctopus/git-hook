<?php

declare(strict_types=1);

namespace Oct8pus;

use Exception;

class GitHubHook extends AbstractHook
{
    protected function headerSignature() : string
    {
        // get header signature
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null;

        if (empty($signature)) {
            throw new Exception('header signature missing', 401);
        }

        return str_replace('sha256=', '', $signature);
    }
}
