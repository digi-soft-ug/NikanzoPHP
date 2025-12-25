<?php

declare(strict_types=1);

namespace Nikanzo\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Service
{
    public function __construct(
        public ?bool $lazy = null,
        public ?bool $public = null,
        public ?bool $shared = null
    ) {
    }
}
