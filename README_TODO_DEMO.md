# NikanzoPHP Todo CRUD Demo

This Demo zeigt ein vollständiges CRUD-Beispiel (Create, Read, Update, Delete) für eine Todo-Liste mit NikanzoPHP.

## Features
- Model, Controller, Attribute-Routing
- Migration für die Datenbank
- Einfache REST-API für Todos

## Setup

1. Repository klonen:
   ```
   git clone https://github.com/digi-soft-ug/nikanzophp-todo-demo.git
   cd nikanzophp-todo-demo
   ```
2. Abhängigkeiten installieren:
   ```
   composer install
   ```
3. Migration ausführen:
   ```
   vendor/bin/phinx migrate
   ```
4. Server starten:
   ```
   php -S localhost:8000 -t public
   ```

## API-Beispiele
- Alle Todos: `GET /todos`
- Einzelnes Todo: `GET /todos/{id}`
- Neues Todo: `POST /todos`
- Todo aktualisieren: `PUT /todos/{id}`
- Todo löschen: `DELETE /todos/{id}`

## Links
- Haupt-Repo: https://github.com/digi-soft-ug/NikanzoPHP
- Demo-Repo: https://github.com/digi-soft-ug/nikanzophp-todo-demo
