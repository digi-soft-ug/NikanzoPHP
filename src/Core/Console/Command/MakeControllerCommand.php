<?php

declare(strict_types=1);

namespace Nikanzo\Core\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:controller', description: 'Generate a controller skeleton with a Route attribute')]
final class MakeControllerCommand extends Command
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Controller class name (e.g. HelloController or Admin/DashboardController)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nameArg = $input->getArgument('name');
        if (!is_string($nameArg)) {
            $output->writeln('<error>Argument "name" must be a string.</error>');
            return Command::FAILURE;
        }

        $normalized = $this->normalizeName($nameArg);

        // Path resolution: logic ensures /src/Application is the root for controllers
        $dir = $this->basePath . '/src/Application' . $normalized['subPath'];
        $file = $dir . '/' . $normalized['class'] . '.php';

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            $output->writeln('<error>Directory could not be created: ' . $dir . '</error>');
            return Command::FAILURE;
        }

        if (file_exists($file)) {
            $output->writeln('<error>Controller already exists: ' . $file . '</error>');
            return Command::FAILURE;
        }

        $template = $this->buildTemplate($normalized['namespace'], $normalized['class'], $normalized['route']);

        if (file_put_contents($file, $template) === false) {
            $output->writeln('<error>Failed to write file: ' . $file . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Created controller:</info> ' . $file);

        return Command::SUCCESS;
    }

    /**
     * @return array{namespace:string,class:string,subPath:string,route:string}
     */
    private function normalizeName(string $rawName): array
    {
        $clean = str_replace(['/', '\\'], '\\', trim($rawName, '\\/'));
        $parts = $clean === '' ? [] : explode('\\', $clean);
        $class = !empty($parts) ? (string) array_pop($parts) : 'HelloController';

        $subNamespace = $parts ? '\\' . implode('\\', $parts) : '';
        $subPath = $parts ? '/' . implode('/', $parts) : '';

        // Generate kebab-case route from ClassName
        $base = preg_replace('/Controller$/', '', $class) ?: $class;
        $route = '/' . strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', $base));
        $route = str_replace('_', '-', $route);

        return [
            'namespace' => 'Nikanzo\\Application' . $subNamespace,
            'class' => $class,
            'subPath' => $subPath,
            'route' => $route,
        ];
    }

    private function buildTemplate(string $namespace, string $class, string $route): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace};

use Nikanzo\Core\Attributes\Route;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class {$class}
{
    #[Route('{$route}', methods: ['GET'])]
    public function __invoke(ServerRequestInterface \$request): ResponseInterface
    {
        \$message = ['message' => 'Hello from {$class}'];
        return new Response(
            200, 
            ['Content-Type' => 'application/json'], 
            (string) json_encode(\$message, JSON_THROW_ON_ERROR)
        );
    }
}
PHP;
    }
}
