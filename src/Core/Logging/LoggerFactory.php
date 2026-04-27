<?php

declare(strict_types=1);

namespace Nikanzo\Core\Logging;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Creates a pre-configured Monolog instance.
 *
 * Channel  : controlled by NIKANZO_LOG_CHANNEL  (default: "app")
 * Level    : controlled by NIKANZO_LOG_LEVEL    (default: "debug")
 * Path     : controlled by NIKANZO_LOG_PATH     (default: var/log/app.log)
 * Rotation : controlled by NIKANZO_LOG_MAX_FILES (default: 7)
 */
final class LoggerFactory
{
    public static function create(
        string  $channel  = '',
        string  $path     = '',
        string  $level    = '',
        int     $maxFiles = 0,
    ): LoggerInterface {
        $channel  = $channel  ?: ((string) getenv('NIKANZO_LOG_CHANNEL')  ?: 'app');
        $path     = $path     ?: ((string) getenv('NIKANZO_LOG_PATH')     ?: dirname(__DIR__, 3) . '/var/log/app.log');
        $level    = $level    ?: ((string) getenv('NIKANZO_LOG_LEVEL')    ?: 'debug');
        $maxFiles = $maxFiles ?: (int)    (getenv('NIKANZO_LOG_MAX_FILES') ?: 7);

        $monologLevel = Level::fromName(ucfirst(strtolower($level)));

        $logger  = new Logger($channel);
        $handler = $maxFiles > 0
            ? new RotatingFileHandler($path, $maxFiles, $monologLevel)
            : new StreamHandler($path, $monologLevel);

        $logger->pushHandler($handler);

        return $logger;
    }
}
