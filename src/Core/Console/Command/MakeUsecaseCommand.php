<?php

declare(strict_types=1);

namespace Nikanzo\Core\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:usecase', description: 'Generate a Use Case class skeleton')]
final class MakeUsecaseCommand extends Command
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Use Case class name (e.g. RegisterUser)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rawName = (string) $input->getArgument('name');
        $normalized = $this->normalizeName($rawName);

        $dir = $this->basePath . '/src/Domain/UseCase' . $normalized['subPath'];
        $file = $dir . '/' . $normalized['class'] . '.php';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (file_exists($file)) {
            $output->writeln('<error>Use case already exists: ' . $file . '</error>');
            return Command::FAILURE;
        }

        file_put_contents($file, $this->buildTemplate($normalized['namespace'], $normalized['class']));
        $output->writeln('<info>Created use case:</info> ' . $file);

        return Command::SUCCESS;
    }

    /**
     * @return array{namespace:string,class:string,subPath:string}
     */
    private function normalizeName(string $rawName): array
    {
        $clean = str_replace(['/', '\\'], '\\', trim($rawName, '\\/'));
        $parts = $clean === '' ? [] : explode('\\', $clean);
        $class = $parts ? array_pop($parts) : 'RegisterUser';

        $subNamespace = $parts ? '\\' . implode('\\', $parts) : '';
        $subPath = $parts ? '/' . implode('/', $parts) : '';

        return [
            'namespace' => 'Nikanzo\\Domain\\UseCase' . $subNamespace,
            'class' => $class,
            'subPath' => $subPath,
        ];
    }

    private function buildTemplate(string $namespace, string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

final class {$class}
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function __invoke(array $input = []): array
    {
        // TODO: implement use case logic
        return $input;
    }
}
PHP;
    }
}
