<?php

declare(strict_types=1);

namespace App\WebSocket\Listener;

use App\WebSocket\Event\WebSocketMessageEvent;

use function sprintf;

/**
 * Example listener: echoes back any message frame received from a client.
 *
 * Registered against WebSocketMessageEvent::class via
 * `mezzio-swoole.swoole-http-server.listeners` in App\ConfigProvider.
 */
final class EchoMessageListener
{
    public function __invoke(WebSocketMessageEvent $event): void
    {
        $frame = $event->getFrame();

        $event->getServer()->push($frame->fd, sprintf('echo: %s', $frame->data));
    }
}
