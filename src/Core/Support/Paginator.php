<?php

declare(strict_types=1);

namespace Nikanzo\Core\Support;

/**
 * Simple offset-based paginator.
 *
 * Usage:
 *   $page      = (int) ($request->getQueryParams()['page'] ?? 1);
 *   $paginator = new Paginator(total: $total, page: $page, perPage: 15);
 *
 *   return $this->json([
 *       'data'       => $items,
 *       'pagination' => $paginator->toArray(),
 *   ]);
 */
final class Paginator
{
    public readonly int $currentPage;
    public readonly int $lastPage;
    public readonly int $offset;
    public readonly int $total;
    public readonly int $perPage;

    public function __construct(int $total, int $page = 1, int $perPage = 15)
    {
        $this->total       = max(0, $total);
        $this->perPage     = max(1, $perPage);
        $this->lastPage    = (int) max(1, ceil($this->total / $this->perPage));
        $this->currentPage = min(max(1, $page), $this->lastPage);
        $this->offset      = ($this->currentPage - 1) * $this->perPage;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function previousPage(): ?int
    {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : null;
    }

    public function nextPage(): ?int
    {
        return $this->hasNextPage() ? $this->currentPage + 1 : null;
    }

    /**
     * @return array{total: int, per_page: int, current_page: int, last_page: int, offset: int, has_previous: bool, has_next: bool}
     */
    public function toArray(): array
    {
        return [
            'total'        => $this->total,
            'per_page'     => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page'    => $this->lastPage,
            'offset'       => $this->offset,
            'has_previous' => $this->hasPreviousPage(),
            'has_next'     => $this->hasNextPage(),
        ];
    }

    /**
     * Generate page URLs — pass a callable that receives the page number.
     *
     * @param callable(int): string $urlResolver
     * @return array{first: string, last: string, previous: string|null, next: string|null}
     */
    public function links(callable $urlResolver): array
    {
        return [
            'first'    => $urlResolver(1),
            'last'     => $urlResolver($this->lastPage),
            'previous' => $this->previousPage() !== null ? $urlResolver($this->previousPage()) : null,
            'next'     => $this->nextPage() !== null     ? $urlResolver($this->nextPage())     : null,
        ];
    }
}
