<?php
namespace App\Controller;

use App\Model\Todo;
use PDO;
use Attribute;

class TodoController
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    #[Route('/todos', methods: ['GET'])]
    public function index()
    {
        return Todo::all($this->db);
    }

    #[Route('/todos/{id}', methods: ['GET'])]
    public function show($id)
    {
        return Todo::find($this->db, $id);
    }

    #[Route('/todos', methods: ['POST'])]
    public function create($data)
    {
        $todo = new Todo(null, $data['title'], $data['description'], $data['completed'] ?? false);
        $todo->save($this->db);
        return $todo;
    }

    #[Route('/todos/{id}', methods: ['PUT'])]
    public function update($id, $data)
    {
        $todo = Todo::find($this->db, $id);
        if ($todo) {
            $todo->title = $data['title'] ?? $todo->title;
            $todo->description = $data['description'] ?? $todo->description;
            $todo->completed = $data['completed'] ?? $todo->completed;
            $todo->save($this->db);
        }
        return $todo;
    }

    #[Route('/todos/{id}', methods: ['DELETE'])]
    public function delete($id)
    {
        $todo = Todo::find($this->db, $id);
        if ($todo) {
            $todo->delete($this->db);
        }
        return $todo;
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public $path;
    public $methods;
    public function __construct($path, $methods = ['GET'])
    {
        $this->path = $path;
        $this->methods = $methods;
    }
}
