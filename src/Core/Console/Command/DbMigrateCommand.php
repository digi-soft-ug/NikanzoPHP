<?php

declare(strict_types=1);

namespace Nikanzo\Core\Console\Command;

use Nikanzo\Core\Database\ConnectionFactory;
use Nikanzo\Core\Database\MigrationRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'db:migrate', description: 'Run database migrations in database/migrations')]
final class DbMigrateCommand extends Command
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->loadDatabaseConfig();
        /** @var array{driver?:string,database?:string,host?:string,port?:string|int|null,username?:string|null,password?:string|null,charset?:string|null} $config */
        $pdo = ConnectionFactory::make($config);

        $runner = new MigrationRunner($pdo);
        $path = $this->basePath . '/database/migrations';
        $result = $runner->migrate($path);

        foreach ($result['ran'] as $migration) {
            $output->writeln('<info>Ran:</info> ' . $migration);
        }
        foreach ($result['skipped'] as $migration) {
            $output->writeln('<comment>Skipped:</comment> ' . $migration);
        }

        $output->writeln('<info>Migrations completed.</info>');

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDatabaseConfig(): array
    {
        $path = $this->basePath . '/config/database.php';

        if (is_file($path)) {
            /** @var array<string, mixed> $config */
            $config = require $path;

            return $config;
        }

        return [];
    }
}
