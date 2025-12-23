<?php

declare(strict_types=1);

namespace Nikanzo\Core\Database;

use PDO;
use PDOException;

final class ConnectionFactory
{
    /**
     * @param array{driver?:string,database?:string,host?:string,port?:string|int|null,username?:string|null,password?:string|null,charset?:string|null} $config
     */
    public static function make(array $config): PDO
    {
        $driver = $config['driver'] ?? 'sqlite';
        $dsn = '';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($driver === 'sqlite') {
            $database = $config['database'] ?? dirname(__DIR__, 3) . '/database/database.sqlite';
            $dir = dirname($database);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            if (!file_exists($database)) {
                touch($database);
            }
            $dsn = 'sqlite:' . $database;
        } elseif ($driver === 'mysql') {
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 3306;
            $dbname = $config['database'] ?? '';
            $charset = $config['charset'] ?? 'utf8mb4';
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbname, $charset);
        } elseif ($driver === 'pgsql') {
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 5432;
            $dbname = $config['database'] ?? '';
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbname);
        } else {
            throw new \InvalidArgumentException('Unsupported database driver: ' . $driver);
        }

        try {
            $pdo = new PDO(
                $dsn,
                $config['username'] ?? null,
                $config['password'] ?? null,
                $options
            );
        } catch (PDOException $e) {
            throw new \RuntimeException('Could not create PDO connection: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        return $pdo;
    }
}
