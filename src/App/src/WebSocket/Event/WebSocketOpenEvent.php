<?php

declare(strict_types=1);

namespace App\WebSocket\Event;

use Swoole\Http\Request as SwooleHttpRequest;
use Swoole\WebSocket\Server as SwooleWebSocketServer;

/**
 * Dispatched when a new WebSocket connection completes its handshake.
 *
 * Mirrors the shape of Mezzio\Swoole\Event\RequestEvent: a thin carrier for
 * the native Swoole arguments, dispatched through the same
 * Mezzio\Swoole\Event\EventDispatcherInterface service used for every other
 * server event, so listeners are registered the same way (via
 * `mezzio-swoole.swoole-http-server.listeners` config).
 */
final class WebSocketOpenEvent
{
    public function __construct(
        private readonly SwooleWebSocketServer $server,
        private readonly SwooleHttpRequest $request,
    ) {}

    public function getRequest(): SwooleHttpRequest
    {
        return $this->request;
    }

    public function getServer(): SwooleWebSocketServer
    {
        return $this->server;
    }
}
