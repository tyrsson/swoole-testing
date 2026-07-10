<?php

declare(strict_types=1);

namespace App\WebSocket\Event;

use Swoole\WebSocket\Server as SwooleWebSocketServer;

/**
 * Dispatched when a WebSocket connection is closed.
 *
 * @see WebSocketOpenEvent for rationale on this event/listener pattern.
 */
final class WebSocketCloseEvent
{
    public function __construct(
        private readonly SwooleWebSocketServer $server,
        private readonly int $fd,
        private readonly int $reactorId,
    ) {}

    public function getFd(): int
    {
        return $this->fd;
    }

    public function getReactorId(): int
    {
        return $this->reactorId;
    }

    public function getServer(): SwooleWebSocketServer
    {
        return $this->server;
    }
}
