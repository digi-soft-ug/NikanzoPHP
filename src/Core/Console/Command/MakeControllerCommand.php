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
        $this->addArgument('name', InputArgument::REQUIRED, 'Controller class name (e.g. HelloController)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rawName = (string) $input->getArgument('name');
        $normalized = $this->normalizeName($rawName);

        $dir = $this->basePath . '/src/Application' . $normalized['subPath'];
        $file = $dir . '/' . $normalized['class'] . '.php';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (file_exists($file)) {
            $output->writeln('<error>Controller already exists: ' . $file . '</error>');
            return Command::FAILURE;
        }

        $template = $this->buildTemplate($normalized['namespace'], $normalized['class'], $normalized['route']);
        file_put_contents($file, $template);

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
        $class = $parts ? array_pop($parts) : 'HelloController';

        $subNamespace = $parts ? '\\' . implode('\\', $parts) : '';
        $subPath = $parts ? '/' . implode('/', $parts) : '';

        $base = preg_replace('/Controller$/', '', $class) ?: $class;
        $route = '/' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $base));
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
        $json = "json_encode(['message' => '$class'], JSON_THROW_ON_ERROR)";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Nikanzo\\Core\\Attributes\\Route;
use Nyholm\\Psr7\\Response;
use Psr\\Http\\Message\\ResponseInterface;
use Psr\\Http\\Message\\ServerRequestInterface;

final class {$class}
{
    #[Route('{$route}', methods: ['GET'])]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(200, ['Content-Type' => 'application/json'], {$json});
    }
}
PHP;
    }
}
