<?php

declare(strict_types=1);

namespace Nikanzo\Core\Console\Command;

use Nikanzo\Core\Database\ConnectionFactory;
use Nikanzo\Core\Database\SeederRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'db:seed', description: 'Run database seeders in database/seeds')]
final class DbSeedCommand extends Command
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

        $runner = new SeederRunner($pdo);
        $path = $this->basePath . '/database/seeds';
        $ran = $runner->seed($path);

        foreach ($ran as $seed) {
            $output->writeln('<info>Seeded:</info> ' . $seed);
        }

        $output->writeln('<info>Seeding completed.</info>');

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
