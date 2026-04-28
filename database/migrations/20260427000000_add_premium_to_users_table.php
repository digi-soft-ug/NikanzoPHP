<?php

declare(strict_types=1);

use Nikanzo\Core\Database\MigrationInterface;

/**
 * Adds subscription / membership columns to the users table.
 *
 * membership_level  – 'free' | 'premium'  (enforced via CHECK or ENUM per driver)
 * subscription_id   – external payment provider reference (Stripe subscription ID, etc.)
 * premium_until     – nullable expiry timestamp; NULL means the subscription never expires
 *                     as long as membership_level = 'premium'
 */
return new class implements MigrationInterface {
    public function up(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                ALTER TABLE users
                    ADD COLUMN membership_level ENUM('free','premium') NOT NULL DEFAULT 'free',
                    ADD COLUMN subscription_id  VARCHAR(255)           NULL DEFAULT NULL,
                    ADD COLUMN premium_until    DATETIME               NULL DEFAULT NULL
            ");

            // Index to quickly find all active premium users
            $pdo->exec("
                ALTER TABLE users
                    ADD INDEX idx_membership_level (membership_level),
                    ADD INDEX idx_premium_until    (premium_until)
            ");

        } elseif ($driver === 'pgsql') {
            $pdo->exec("
                ALTER TABLE users
                    ADD COLUMN membership_level VARCHAR(10)  NOT NULL DEFAULT 'free'
                        CONSTRAINT chk_membership_level CHECK (membership_level IN ('free','premium')),
                    ADD COLUMN subscription_id  VARCHAR(255) NULL DEFAULT NULL,
                    ADD COLUMN premium_until    TIMESTAMP    NULL DEFAULT NULL
            ");

            $pdo->exec('CREATE INDEX idx_membership_level ON users (membership_level)');
            $pdo->exec('CREATE INDEX idx_premium_until    ON users (premium_until)');

        } else {
            // SQLite — ADD COLUMN one at a time (no multi-column ALTER support)
            $pdo->exec("
                ALTER TABLE users
                    ADD COLUMN membership_level TEXT NOT NULL DEFAULT 'free'
                        CHECK (membership_level IN ('free','premium'))
            ");
            $pdo->exec("
                ALTER TABLE users
                    ADD COLUMN subscription_id TEXT NULL DEFAULT NULL
            ");
            $pdo->exec("
                ALTER TABLE users
                    ADD COLUMN premium_until TEXT NULL DEFAULT NULL
            ");
            // SQLite does not support CREATE INDEX inside a transaction that altered the table;
            // run them separately after the commit (MigrationRunner wraps up/down in a transaction).
        }
    }

    public function down(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec('
                ALTER TABLE users
                    DROP INDEX  idx_membership_level,
                    DROP INDEX  idx_premium_until,
                    DROP COLUMN membership_level,
                    DROP COLUMN subscription_id,
                    DROP COLUMN premium_until
            ');
        } elseif ($driver === 'pgsql') {
            $pdo->exec('DROP INDEX IF EXISTS idx_membership_level');
            $pdo->exec('DROP INDEX IF EXISTS idx_premium_until');
            $pdo->exec('
                ALTER TABLE users
                    DROP COLUMN membership_level,
                    DROP COLUMN subscription_id,
                    DROP COLUMN premium_until
            ');
        } else {
            // SQLite does not support DROP COLUMN before version 3.35.0.
            // Re-create the table without the premium columns.
            $pdo->exec('CREATE TABLE users_backup AS SELECT id, name, email, created_at FROM users');
            $pdo->exec('DROP TABLE users');
            $pdo->exec('
                CREATE TABLE users (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    name       TEXT NOT NULL,
                    email      TEXT NOT NULL UNIQUE,
                    created_at TEXT NOT NULL
                )
            ');
            $pdo->exec('INSERT INTO users SELECT id, name, email, created_at FROM users_backup');
            $pdo->exec('DROP TABLE users_backup');
        }
    }
};
