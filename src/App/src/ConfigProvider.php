<?php

declare(strict_types=1);

namespace App;

use Swoole\Http\Server as SwooleHttpServer;

/**
 * The configuration provider for the App module
 *
 * @see https://docs.laminas.dev/laminas-component-installer/
 */
class ConfigProvider
{
    /**
     * Returns the container dependencies
     */
    public function getDependencies(): array
    {
        return [
            'aliases'    => [],
            'invokables' => [
                Handler\PingHandler::class                         => Handler\PingHandler::class,
                WebSocket\Listener\ConnectionOpenedListener::class => WebSocket\Listener\ConnectionOpenedListener::class,
                WebSocket\Listener\EchoMessageListener::class      => WebSocket\Listener\EchoMessageListener::class,
            ],
            'factories'  => [
                Handler\HomePageHandler::class => Handler\HomePageHandlerFactory::class,
                // Overrides Mezzio\Swoole\HttpServerFactory so the same
                // server instance handles both HTTP and WebSocket traffic.
                SwooleHttpServer::class => Container\WebSocketServerFactory::class,
            ],
        ];
    }

    /**
     * Returns mezzio-swoole configuration owned by this module.
     *
     * Registers our WebSocket event listeners the same way mezzio-swoole
     * registers its own (e.g. RequestEvent's listeners): via the
     * `swoole-http-server.listeners` map, consumed by
     * Mezzio\Swoole\Event\SwooleListenerProviderFactory. This is merged with
     * the host/port settings from config/autoload/swoole.global.php by
     * ConfigAggregator.
     */
    public function getSwooleConfig(): array
    {
        return [
            'swoole-http-server' => [
                'listeners' => [
                    WebSocket\Event\WebSocketOpenEvent::class    => [
                        WebSocket\Listener\ConnectionOpenedListener::class,
                    ],
                    WebSocket\Event\WebSocketMessageEvent::class => [
                        WebSocket\Listener\EchoMessageListener::class,
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns the templates configuration
     */
    public function getTemplates(): array
    {
        return [
            'paths' => [
                'app'    => [__DIR__ . '/../templates/app'],
                'error'  => [__DIR__ . '/../templates/error'],
                'layout' => [__DIR__ . '/../templates/layout'],
            ],
        ];
    }

    /**
     * Returns the configuration array
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     */
    public function __invoke(): array
    {
        return [
            'dependencies'  => $this->getDependencies(),
            'templates'     => $this->getTemplates(),
            'mezzio-swoole' => $this->getSwooleConfig(),
        ];
    }
}
