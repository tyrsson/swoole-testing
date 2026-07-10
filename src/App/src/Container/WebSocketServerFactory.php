<?php

declare(strict_types=1);

namespace App\Container;

use App\WebSocket\Event\WebSocketCloseEvent;
use App\WebSocket\Event\WebSocketMessageEvent;
use App\WebSocket\Event\WebSocketOpenEvent;
use ArrayAccess;
use Mezzio\Swoole\Event\EventDispatcherInterface as SwooleEventDispatcherInterface;
use Mezzio\Swoole\Exception\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Swoole\Http\Request as SwooleHttpRequest;
use Swoole\Runtime as SwooleRuntime;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as SwooleWebSocketServer;
use Webmozart\Assert\Assert;

use function assert;
use function defined;
use function fwrite;
use function get_class;
use function in_array;
use function is_array;
use function method_exists;

use const SWOOLE_BASE;
use const SWOOLE_PROCESS;
use const SWOOLE_SOCK_TCP;
use const SWOOLE_SOCK_TCP6;
use const SWOOLE_SOCK_UDP;
use const SWOOLE_SOCK_UDP6;
use const SWOOLE_SSL;
use const SWOOLE_UNIX_DGRAM;
use const SWOOLE_UNIX_STREAM;

/**
 * Builds the Swoole server used by the application.
 *
 * This replaces Mezzio\Swoole\HttpServerFactory as the factory for the
 * Swoole\Http\Server::class service: it constructs a Swoole\WebSocket\Server
 * (a drop-in subclass of Swoole\Http\Server) so a single host/port serves
 * both regular HTTP requests -- handled by the existing Mezzio pipeline via
 * `onRequest`, registered by Mezzio\Swoole\SwooleRequestHandlerRunner -- and
 * WebSocket connections, handled here via `onOpen`/`onMessage`/`onClose`.
 *
 * Native WebSocket events are not part of the fixed set of events
 * Mezzio\Swoole\SwooleRequestHandlerRunner registers (WebSocket support was
 * removed from mezzio-swoole), so this factory registers them directly. To
 * stay consistent with how every other server event in this application is
 * handled, each native callback only wraps its arguments into a typed event
 * (WebSocketOpenEvent, WebSocketMessageEvent, WebSocketCloseEvent) and
 * dispatches it through the same Mezzio\Swoole\Event\EventDispatcherInterface
 * service used elsewhere -- so listeners are plain invokable classes
 * registered via `mezzio-swoole.swoole-http-server.listeners` config, exactly
 * like Mezzio\Swoole\Event\RequestEvent's listeners.
 */
final class WebSocketServerFactory
{
    public const DEFAULT_HOST = '127.0.0.1';

    public const DEFAULT_PORT = 8080;

    /** @var int[] */
    private const MODES = [
        SWOOLE_BASE,
        SWOOLE_PROCESS,
    ];

    /** @var int[] */
    private const PROTOCOLS = [
        SWOOLE_SOCK_TCP,
        SWOOLE_SOCK_TCP6,
        SWOOLE_SOCK_UDP,
        SWOOLE_SOCK_UDP6,
        SWOOLE_UNIX_DGRAM,
        SWOOLE_UNIX_STREAM,
    ];

    public function __invoke(ContainerInterface $container): SwooleWebSocketServer
    {
        $config = $container->get('config');
        assert(is_array($config) || $config instanceof ArrayAccess);

        $swooleConfig = $config['mezzio-swoole'] ?? [];
        Assert::isMap($swooleConfig);

        $serverConfig = $swooleConfig['swoole-http-server'] ?? [];
        Assert::isMap($serverConfig);

        $host     = $serverConfig['host'] ?? self::DEFAULT_HOST;
        $port     = $serverConfig['port'] ?? self::DEFAULT_PORT;
        $mode     = $serverConfig['mode'] ?? SWOOLE_BASE;
        $protocol = $serverConfig['protocol'] ?? SWOOLE_SOCK_TCP;

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Invalid port');
        }

        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException('Invalid server mode');
        }

        $validProtocols = self::PROTOCOLS;
        if (defined('SWOOLE_SSL')) {
            $validProtocols[] = SWOOLE_SOCK_TCP | SWOOLE_SSL;
            $validProtocols[] = SWOOLE_SOCK_TCP6 | SWOOLE_SSL;
        }

        if (! in_array($protocol, $validProtocols, true)) {
            throw new InvalidArgumentException('Invalid server protocol');
        }

        $enableCoroutine = $swooleConfig['enable_coroutine'] ?? false;
        if ($enableCoroutine && method_exists(SwooleRuntime::class, 'enableCoroutine')) {
            SwooleRuntime::enableCoroutine();
        }

        $server = new SwooleWebSocketServer($host, $port, $mode, $protocol);

        $serverOptions = $serverConfig['options'] ?? [];
        Assert::isArray($serverOptions);

        // Always accept the WebSocket protocol on this server, regardless of
        // environment-specific configuration.
        $serverOptions['open_websocket_protocol'] = true;

        $server->set($serverOptions);

        $dispatcher = $container->get(SwooleEventDispatcherInterface::class);
        Assert::isInstanceOf($dispatcher, EventDispatcherInterface::class);

        // These must be registered before Mezzio\Swoole\SwooleRequestHandlerRunner::run()
        // calls $server->start(); since this factory result is resolved (and
        // cached) as a single shared service instance before the runner is
        // built, that ordering is guaranteed.
        $server->on(
            'open',
            static function (SwooleWebSocketServer $server, SwooleHttpRequest $request) use ($dispatcher): void {
                $dispatcher->dispatch(new WebSocketOpenEvent($server, $request));
            },
        );
        $server->on(
            'message',
            static function (SwooleWebSocketServer $server, Frame $frame) use ($dispatcher): void {
                $dispatcher->dispatch(new WebSocketMessageEvent($server, $frame));
            },
        );
        $server->on(
            'close',
            static function (SwooleWebSocketServer $server, int $fd, int $reactorId) use ($dispatcher): void {
                $dispatcher->dispatch(new WebSocketCloseEvent($server, $fd, $reactorId));
            },
        );

        // TEMPORARY: confirms which concrete class the running process
        // actually built for the Swoole\Http\Server::class service. Remove
        // once verified. Written directly to STDERR because this image has
        // `log_errors = Off`, which silently discards error_log() calls.
        fwrite(STDERR, 'WebSocketServerFactory built: ' . get_class($server) . PHP_EOL);

        return $server;
    }
}
