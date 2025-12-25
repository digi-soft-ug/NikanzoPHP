<?php

declare(strict_types=1);

namespace Nikanzo\Core\Async;

use Fiber;

final class AsyncFileReader
{
    /**
     * @param callable(string, bool):void $onChunk
     */
    public function read(string $path, callable $onChunk, int $chunkSize = 8192): void
    {
        $fiber = new Fiber(function () use ($path, $onChunk, $chunkSize) {
            $handle = @fopen($path, 'rb');
            if ($handle === false) {
                throw new \RuntimeException('Cannot open file: ' . $path);
            }

            try {
                while (!feof($handle)) {
                    $chunk = fread($handle, $chunkSize);
                    if ($chunk === false) {
                        break;
                    }
                    $onChunk($chunk, feof($handle));
                    Fiber::suspend();
                }
            } finally {
                fclose($handle);
            }
        });

        while (!$fiber->isTerminated()) {
            $fiber->start();
        }
    }
}
