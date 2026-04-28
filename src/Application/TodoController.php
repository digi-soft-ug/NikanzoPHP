<?php

declare(strict_types=1);

namespace Nikanzo\Application;

use Nikanzo\Core\Attributes\Route;
use Nikanzo\Core\Controller\AbstractController;
use Nikanzo\Core\Database\QueryBuilder;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TodoController extends AbstractController
{
    public function __construct(private readonly PDO $db)
    {
    }

    #[Route('/todos', methods: ['GET'])]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $page   = max(1, (int) ($params['page'] ?? 1));
        $limit  = min(100, max(1, (int) ($params['per_page'] ?? 20)));

        $qb    = new QueryBuilder($this->db, 'todos');
        $total = $qb->count();
        $items = (new QueryBuilder($this->db, 'todos'))
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->offset(($page - 1) * $limit)
            ->get();

        return $this->json([
            'data' => $items,
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $limit],
        ]);
    }

    #[Route('/todos/{id}', methods: ['GET'])]
    public function show(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $todo = (new QueryBuilder($this->db, 'todos'))->find((int) $id);

        if ($todo === null) {
            return $this->error('not_found', 404);
        }

        return $this->json($todo);
    }

    #[Route('/todos', methods: ['POST'])]
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (!is_array($body) || !isset($body['title']) || trim((string) $body['title']) === '') {
            return $this->error('title_required', 422);
        }

        $insertId = (new QueryBuilder($this->db, 'todos'))->insert([
            'title'       => trim((string) $body['title']),
            'description' => trim((string) ($body['description'] ?? '')),
            'completed'   => 0,
        ]);

        $todo = (new QueryBuilder($this->db, 'todos'))->find((int) $insertId);

        return $this->created($todo, '/todos/' . $insertId);
    }

    #[Route('/todos/{id}', methods: ['PUT'])]
    public function update(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $qb   = new QueryBuilder($this->db, 'todos');
        $todo = $qb->find((int) $id);

        if ($todo === null) {
            return $this->error('not_found', 404);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->error('invalid_body', 422);
        }

        $data = array_filter([
            'title'       => isset($body['title'])       ? trim((string) $body['title'])       : null,
            'description' => isset($body['description']) ? trim((string) $body['description']) : null,
            'completed'   => isset($body['completed'])   ? (int) (bool) $body['completed']     : null,
        ], static fn ($v) => $v !== null);

        if ($data !== []) {
            (new QueryBuilder($this->db, 'todos'))->where('id', (int) $id)->update($data);
        }

        return $this->json((new QueryBuilder($this->db, 'todos'))->find((int) $id));
    }

    #[Route('/todos/{id}', methods: ['DELETE'])]
    public function delete(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $deleted = (new QueryBuilder($this->db, 'todos'))->where('id', (int) $id)->delete();

        if ($deleted === 0) {
            return $this->error('not_found', 404);
        }

        return $this->noContent();
    }
}
