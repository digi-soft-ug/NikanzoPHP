<?php

declare(strict_types=1);

namespace Nikanzo\Core\Cache;

use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

final class InvalidArgumentException extends \InvalidArgumentException implements PsrInvalidArgumentException
{
}
