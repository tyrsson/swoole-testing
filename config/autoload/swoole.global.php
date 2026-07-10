<?php

declare(strict_types=1);

use Mezzio\Swoole\ConfigProvider;

return array_merge((new ConfigProvider())(), [
    'mezzio-swoole' => [
        'swoole-http-server' => [
            // Bind to all interfaces so the server is reachable from the host.
            'host' => '0.0.0.0',
            'port' => 8080,
        ],
    ],
]);
