<?php

declare(strict_types=1);

use Nikanzo\Core\Database\SeederInterface;

return new class implements SeederInterface {
    public function run(PDO $pdo): void
    {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, created_at) VALUES (:name, :email, :created_at)');
        $stmt->execute([
            ':name' => 'Demo User',
            ':email' => 'demo@example.com',
            ':created_at' => date('c'),
        ]);
    }
};