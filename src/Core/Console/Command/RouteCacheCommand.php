<?php

declare(strict_types=1);

namespace Nikanzo\Core\Console\Command;

use Nikanzo\Core\FastRouter;
use Nikanzo\Core\Router;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'route:cache', description: 'Warm route cache using FastRouter')]
final class RouteCacheCommand extends Command
{
    public function __construct(private string $basePath)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheFile = $this->basePath . '/var/cache/routes.php';
        $router = new FastRouter($cacheFile);

        $controllers = $this->discoverControllers();
        if (empty($controllers)) {
            $output->writeln('<comment>No controllers found to cache.</comment>');
            return Command::SUCCESS;
        }

        $router->warm($controllers);
        $output->writeln('<info>Route cache written:</info> ' . $cacheFile);

        return Command::SUCCESS;
    }

    /**
     * Naive controller discovery under src/Application and src/Modules (can be extended).
     *
     * @return string[]
     */
    private function discoverControllers(): array
    {
        $controllers = [];
        $controllers = array_merge(
            $controllers,
            $this->scanPath($this->basePath . '/src/Application', 'Nikanzo\\Application\\'),
            $this->scanPath($this->basePath . '/src/Modules', 'Nikanzo\\Modules\\')
        );

        return $controllers;
    }

    /**
     * @return string[]
     */
    private function scanPath(string $path, string $namespacePrefix): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if (!($file instanceof \SplFileInfo)) {
                continue;
            }
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = str_replace($path . '/', '', $file->getPathname());
            $class = $namespacePrefix . str_replace(['/', '.php'], ['\\', ''], $relative);
            if (class_exists($class)) {
                $found[] = $class;
            }
        }

        return $found;
    }
}
