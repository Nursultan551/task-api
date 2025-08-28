# Yii2 Task Manager (REST API + Minimal Frontend)

Backend-focused CRUD with filters, pagination, sorting, soft delete/restore, tags, status toggle, Bearer auth, and a tiny HTML frontend.

## Prerequisites
- PHP 8.0+ with extensions: pdo, pdo_mysql (or pdo_sqlite for tests), mbstring, json, curl
- Composer
- MySQL (or MariaDB). Default examples assume MySQL

## Project setup
1) Install dependencies
	 - composer install
2) Configure database
	 - Edit `config/db.php`
	 - Example DSN: `mysql:host=127.0.0.1;dbname=task_api;charset=utf8mb4`
	 - Set `username` and `password`
3) Run migrations
	 - php yii migrate
4) Start the dev server
	 - php yii serve

Then open the frontend: http://localhost:8080  (Click "Open Task Manager" button)

Notes
- API is stateless and requires Bearer auth on every request
- Default demo token: `MY_SUPER_TOKEN`

## API endpoints
- Base path: `/api`

Tasks
- GET `/api/tasks`
	- Filters: `status`, `priority`, `due_date_from`, `due_date_to`, `q` (title contains), `tag` (name or id)
	- Soft-delete visibility: `with_deleted=1` or `deleted=1`
	- Pagination: `page` (1-based), `limit` (max 100)
	- Sorting: `sort=created_at|due_date|priority` (prefix with `-` for desc)
- GET `/api/tasks/{id}`
- POST `/api/tasks`
- PUT `/api/tasks/{id}`
- DELETE `/api/tasks/{id}` (soft delete)
- PATCH `/api/tasks/{id}/toggle-status`
- PATCH `/api/tasks/{id}/restore`

Status codes
- 200 success, 201 created, 404 not found, 422 validation errors

### Examples (curl)

Create task
```
curl -sS -X POST http://localhost:8080/api/tasks \
	-H 'Content-Type: application/json' \
	-H 'Authorization: Bearer MY_SUPER_TOKEN' \
	-d '{
				"title": "Write API docs",
				"description": "Cover filters and examples",
				"status": "pending",
				"priority": "high",
				"due_date": "2025-09-01",
				"tags": ["docs", "feature"]
			}'
```

List with filters and sorting
```
curl 'http://localhost:8080/api/tasks?status=pending&priority=high&sort=-due_date&page=1&limit=10' \
	-H 'Authorization: Bearer MY_SUPER_TOKEN'
```

Get by ID
```
curl -sS http://localhost:8080/api/tasks/1 \
	-H 'Authorization: Bearer MY_SUPER_TOKEN'
```

Update
```
curl -sS -X PUT http://localhost:8080/api/tasks/1 \
	-H 'Content-Type: application/json' \
	-H 'Authorization: Bearer MY_SUPER_TOKEN' \
	-d '{"title":"Write better API docs","tag_ids":[1,2]}'
```

Delete (soft)
```
curl -X DELETE http://localhost:8080/api/tasks/1 \
	-H 'Authorization: Bearer MY_SUPER_TOKEN'
```

Restore
```
curl -X PATCH http://localhost:8080/api/tasks/1/restore \
	-H 'Authorization: Bearer MY_SUPER_TOKEN'
```

Toggle status
```
curl -X PATCH http://localhost:8080/api/tasks/1/toggle-status \
	-H 'Authorization: Bearer MY_SUPER_TOKEN'
```

More samples are in `curl-examples.txt` (full URLs, ready to copy).

## Frontend (how to run and test)
Location: `web/tasks/index.html` (Bootstrap + fetch API).

Run
- Start the dev server (see setup), then click "Open Task Manager" or open http://localhost:8080/tasks
- The UI sends the Authorization header automatically using the demo token

Features
- View, filter (status/priority/text/date, tag), sort, paginate
- Create/update with tags (comma-separated list)
- Toggle status, soft delete, and restore

Quick test
- Create a task in the UI and verify it appears in the list and via `GET /api/tasks`

## Authentication
Bearer token required on all API endpoints.
- Send `Authorization: Bearer MY_SUPER_TOKEN`
- Demo token is hardcoded for development (see `models/User.php`)

## Testing
Codeception is configured for unit and functional tests.

Run all tests
```
vendor/bin/codecept run -vvv
```

Run functional tests only
```
vendor/bin/codecept run functional -vvv
```

Testing DB
- Functional tests default to SQLite (see `config/test_db.php`)
- If PDO SQLite driver is not available, some CRUD tests are skipped; use MySQL for full coverage

## Assumptions and known issues
- API is stateless; sessions and login redirects are disabled for `/api`
- Priority sorting uses a CASE expression to order low < medium < high
- Soft-deleted rows are hidden by default; use `with_deleted` or `deleted` filters to see them
- Demo token is hardcoded; for production, externalize to env/params and remove extra sample tokens
- If your PHP lacks PDO drivers (e.g., sqlite), related tests will be skipped

## Postman collection or curl
- Postman collection: `postman/Task-API.postman_collection.json` (full URLs, Authorization headers included)
	- Import into Postman, then adjust host/port or token if needed
- Curl samples: `curl-examples.txt`
