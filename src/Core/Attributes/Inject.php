<?php

declare(strict_types=1);

namespace Nikanzo\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Inject
{
    public ?string $serviceId;

    public function __construct(?string $serviceId = null)
    {
        $this->serviceId = $serviceId;
    }
}
