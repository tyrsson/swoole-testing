<?php

declare(strict_types=1);

namespace App\WebSocket\Event;

use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as SwooleWebSocketServer;

/**
 * Dispatched when a WebSocket frame is received from a connected client.
 *
 * @see WebSocketOpenEvent for rationale on this event/listener pattern.
 */
final class WebSocketMessageEvent
{
    public function __construct(
        private readonly SwooleWebSocketServer $server,
        private readonly Frame $frame,
    ) {}

    public function getFrame(): Frame
    {
        return $this->frame;
    }

    public function getServer(): SwooleWebSocketServer
    {
        return $this->server;
    }
}
