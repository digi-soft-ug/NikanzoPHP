<?php

declare(strict_types=1);

namespace Nikanzo\Core\Database;

use PDO;
use Throwable;

interface MigrationInterface
{
    public function up(PDO $pdo): void;
    public function down(PDO $pdo): void;
}

final class MigrationRunner
{
    private string $driver;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * @return array{ran: string[], skipped: string[]}
     */
    public function migrate(string $migrationsPath): array
    {
        $this->ensureMigrationsTable();

        if (!is_dir($migrationsPath)) {
            return ['ran' => [], 'skipped' => []];
        }

        $files = glob(rtrim($migrationsPath, '\\/') . '/*.php') ?: [];
        sort($files, SORT_STRING);

        $applied = $this->appliedMigrations();
        $ran = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $migration = $this->loadMigration($file);

            $this->pdo->beginTransaction();
            try {
                $migration->up($this->pdo);
                $this->recordMigration($name);
                $this->pdo->commit();
                $ran[] = $name;
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw new \RuntimeException(sprintf('Migration %s failed: %s', $name, $e->getMessage()), (int) $e->getCode(), $e);
            }
        }

        return ['ran' => $ran, 'skipped' => []];
    }

    /**
     * @return string[]
     */
    private function appliedMigrations(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT migration FROM migrations ORDER BY migration');
            $result = $stmt ? array_column($stmt->fetchAll(), 'migration') : [];
            /** @var string[] $result */
            return $result;
        } catch (\Throwable $e) {
            // Table may have been dropped; ensure and retry once.
            $this->ensureMigrationsTable();
            $stmt = $this->pdo->query('SELECT migration FROM migrations ORDER BY migration');
            $result = $stmt ? array_column($stmt->fetchAll(), 'migration') : [];
            /** @var string[] $result */
            return $result;
        }
    }

    private function recordMigration(string $name): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO migrations (migration, ran_at) VALUES (:migration, :ran_at)');
        $stmt->execute([
            ':migration' => $name,
            ':ran_at' => date('c'),
        ]);
    }

    private function loadMigration(string $file): MigrationInterface
    {
        $instance = require $file;

        if (!$instance instanceof MigrationInterface) {
            throw new \RuntimeException(sprintf('Migration file %s must return an instance of MigrationInterface', $file));
        }

        return $instance;
    }

    private function ensureMigrationsTable(): void
    {
        if ($this->driver === 'mysql') {
            $sql = 'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                ran_at DATETIME NOT NULL
            )';
        } elseif ($this->driver === 'pgsql') {
            $sql = 'CREATE TABLE IF NOT EXISTS migrations (
                id SERIAL PRIMARY KEY,
                migration VARCHAR(255) UNIQUE NOT NULL,
                ran_at TIMESTAMP NOT NULL
            )';
        } else {
            // SQLite default
            $sql = 'CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                ran_at TEXT NOT NULL
            )';
        }

        $this->pdo->exec($sql);
    }
}
