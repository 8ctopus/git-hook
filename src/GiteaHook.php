<?php

declare(strict_types=1);

namespace Oct8pus;

use Exception;

class GiteaHook extends AbstractHook
{
    protected function headerSignature() : string
    {
        $signature = $this->request->getServerParams()['HTTP_X_GITEA_SIGNATURE'] ?? null;

        if (empty($signature)) {
            throw new Exception('header signature missing', 401);
        }

        return $signature;
    }
}
