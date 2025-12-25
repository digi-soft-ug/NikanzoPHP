<?php

declare(strict_types=1);

namespace Nikanzo\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class RequiredScope
{
    /** @var string[] */
    public array $scopes;

    public function __construct(string ...$scopes)
    {
        $this->scopes = $scopes;
    }
}
