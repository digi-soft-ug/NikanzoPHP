<?php

declare(strict_types=1);

namespace Nikanzo\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Route
{
    /**
     */
    private string $path;
    /**
     * @var array<int, string>
     */
    private array $methods;

    /**
     * @param array<int, string> $methods
     */
    public function __construct(
        string $path,
        array $methods = ['GET']
    ) {
        $this->path = $path;
        $this->methods = $methods;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }
}
