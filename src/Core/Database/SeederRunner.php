<?php

declare(strict_types=1);

namespace Nikanzo\Core\Database;

use PDO;

interface SeederInterface
{
    public function run(PDO $pdo): void;
}

final class SeederRunner
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return string[] ran seed filenames
     */
    public function seed(string $seedsPath): array
    {
        if (!is_dir($seedsPath)) {
            return [];
        }

        $files = glob(rtrim($seedsPath, '\\/') . '/*.php') ?: [];
        sort($files, SORT_STRING);
        $ran = [];

        foreach ($files as $file) {
            $seeder = $this->loadSeeder($file);
            $seeder->run($this->pdo);
            $ran[] = basename($file);
        }

        return $ran;
    }

    private function loadSeeder(string $file): SeederInterface
    {
        $instance = require $file;

        if (!$instance instanceof SeederInterface) {
            throw new \RuntimeException(sprintf('Seed file %s must return an instance of SeederInterface', $file));
        }

        return $instance;
    }
}
