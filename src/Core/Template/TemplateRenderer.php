<?php

declare(strict_types=1);

namespace Nikanzo\Core\Template;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TemplateRenderer
{
    private Environment $twig;

    public function __construct(string $templatesPath, ?string $cachePath = null)
    {
        if (!class_exists(Environment::class)) {
            throw new \RuntimeException('Twig is not installed; run composer require twig/twig');
        }

        $loader = new FilesystemLoader($templatesPath);
        $options = [];
        if ($cachePath !== null && $cachePath !== '') {
            $options['cache'] = $cachePath;
        }

        $this->twig = new Environment($loader, $options);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }
}
