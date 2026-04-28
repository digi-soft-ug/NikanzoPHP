<?php
// Migration for todos table
declare(strict_types=1);

use Nikanzo\Core\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function up(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS todos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                completed TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL
            )');
        } elseif ($driver === 'pgsql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS todos (
                id SERIAL PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                completed BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP NOT NULL
            )');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS todos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT NULL,
                completed INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )');
        }
    }
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS todos');
    }
};

