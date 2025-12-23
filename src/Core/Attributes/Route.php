<?php

declare(strict_types=1);

namespace Nikanzo\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Route
{
    /**
     * @param string[] $methods
     */
    private string $path;
    /** @var string[] */
    private array $methods;

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
