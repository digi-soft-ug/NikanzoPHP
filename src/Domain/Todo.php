<?php
namespace App\Model;

use PDO;

class Todo
{
    public $id;
    public $title;
    public $description;
    public $completed;

    public function __construct($id = null, $title = '', $description = '', $completed = false)
    {
        $this->id = $id;
        $this->title = $title;
        $this->description = $description;
        $this->completed = $completed;
    }

    public static function all(PDO $db)
    {
        $stmt = $db->query('SELECT * FROM todos');
        return $stmt->fetchAll(PDO::FETCH_CLASS, self::class);
    }

    public static function find(PDO $db, $id)
    {
        $stmt = $db->prepare('SELECT * FROM todos WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchObject(self::class);
    }

    public function save(PDO $db)
    {
        if ($this->id) {
            $stmt = $db->prepare('UPDATE todos SET title = ?, description = ?, completed = ? WHERE id = ?');
            return $stmt->execute([$this->title, $this->description, $this->completed, $this->id]);
        } else {
            $stmt = $db->prepare('INSERT INTO todos (title, description, completed) VALUES (?, ?, ?)');
            $result = $stmt->execute([$this->title, $this->description, $this->completed]);
            if ($result) {
                $this->id = $db->lastInsertId();
            }
            return $result;
        }
    }

    public function delete(PDO $db)
    {
        if ($this->id) {
            $stmt = $db->prepare('DELETE FROM todos WHERE id = ?');
            return $stmt->execute([$this->id]);
        }
        return false;
    }
}
