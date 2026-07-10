<?php

declare(strict_types=1);

// Mezzio\Swoole\ConfigProvider is already registered directly in
// config/config.php's ConfigAggregator list, so it must not be re-invoked and
// merged here: doing so would re-include its full `dependencies` array
// (including the default Swoole\Http\Server::class factory), which -- since
// this file loads after App\ConfigProvider -- would silently override
// App\Container\WebSocketServerFactory back to the plain HTTP server.
return [
    'mezzio-swoole' => [
        'swoole-http-server' => [
            // Bind to all interfaces so the server is reachable from the host.
            'host' => '0.0.0.0',
            'port' => 8080,
        ],
    ],
];
