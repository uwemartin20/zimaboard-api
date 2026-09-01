# Zimaboard API Backend

A Laravel-based RESTful API backend that powers ZiMaBoard — a real-time messaging and notifications
platform for internal organization communication. This repository provides the API endpoints, event
broadcasts, push notification integration, and background queue workers used by the ZiMaBoard mobile
and web clients.

---

## Key Features

- **User authentication & API tokens** via Laravel Sanctum
- **Real-time events** and broadcasting for chats and notifications
- **Message & chat management** with attachments and statuses
- **Push notifications** via Expo (see `app/Services/ExpoPushService.php`)
- **Background processing** with Laravel queues for notifications, attachments, and broadcasts
- **Test coverage** with PHPUnit configuration included

---

## Tech Stack

- PHP 8.x
- Laravel (framework folders and conventions)
- MySQL / PostgreSQL (configurable via `config/database.php`)
- Redis (recommended for queues & broadcasting)
- Docker & Docker Compose (development-friendly setup provided)

---

## Requirements

- Docker (preferred) or PHP 8.1+, Composer, Node.js (for frontend assets if applicable)
- A database (MySQL, MariaDB, PostgreSQL)
- Optional: Redis for queue and broadcast drivers

---

## Quick Start (Docker)

The repository includes a `docker-compose.yml` and `Dockerfile` for local development. Start the
application and services with:

```bash
docker compose up -d --build
```

Then install PHP dependencies inside the container or on your host, depending on your setup:

```bash
# From host (if PHP & composer installed locally)
composer install

# Or inside the app container (example container name may vary)
docker compose exec app composer install
```

Create and configure the environment file, generate an app key, run migrations and seeders:

```bash
cp .env.example .env
# Edit .env to set DB, Redis and other variables
php artisan key:generate
php artisan migrate --seed
```

If using Docker, you may run the artisan commands inside the application container:

```bash
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan key:generate
```

---

## Environment Variables

Important environment variables (edit in `.env`):

- `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_URL`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `QUEUE_CONNECTION` (recommended: `redis` for production-like behavior)
- `BROADCAST_DRIVER` (pusher or redis), `PUSH_EXPO_TOKEN` / Expo-related config for push service
- `SANCTUM_STATEFUL_DOMAINS` and any CORS settings in `config/cors.php`

See `config/` folder for more driver-specific options.

---

## Running Tests

Run the PHPUnit test suite:

```bash
# Local
vendor/bin/phpunit

# With Docker (example)
docker compose exec app vendor/bin/phpunit
```

Unit and feature tests live under the `tests/` directory.

---

## API Authentication

This API uses Laravel Sanctum for authentication and token management. Typical flows:

- Register: `POST /api/auth/register` (creates user)
- Login: `POST /api/auth/login` (returns Sanctum token)
- Protected routes require `Authorization: Bearer <token>` header

Example cURL (login):

```bash
curl -X POST "${APP_URL:-http://localhost}/api/auth/login" \
	-H "Content-Type: application/json" \
	-d '{"email":"user@example.com","password":"secret"}'
```

---

## Core API Endpoints (Overview)

Below are representative endpoints — consult `routes/api.php` for the full specification.

- `POST /api/auth/register` — register a user
- `POST /api/auth/login` — login and receive API token
- `POST /api/auth/logout` — revoke token
- `GET /api/users` — list users (auth required)
- `GET /api/chats` — list chats
- `POST /api/chats` — create a chat
- `GET /api/chats/{id}/messages` — fetch chat messages
- `POST /api/chats/{id}/messages` — post a message (supports attachments)
- `POST /api/messages/{id}/status` — update message delivery/read status
- `GET /api/notifications` — fetch user notifications

Event broadcasting and real-time channels are defined in `routes/channels.php` and
events under `app/Events/` (e.g., `ChatCreated`, `NewMessage`, `NotificationCreated`).

---

## Real-time & Push Notifications

- Broadcasting: Uses Laravel events and configured broadcast driver (see `config/broadcasting.php`).
- Push: Expo push integration is implemented in `app/Services/ExpoPushService.php`.
- Websockets / Pusher: Configure `BROADCAST_DRIVER` and relevant credentials in `.env`.

---

## Queues & Background Jobs

For production-like behavior, set `QUEUE_CONNECTION=redis` and run queue workers:

```bash
php artisan queue:work --sleep=3 --tries=3
```

In Docker, you can run a worker service or exec into the app container and start the worker.

---

## Database Migrations & Seeders

- Migration files live in `database/migrations/` and include tables for messages, chats, activities,
	attachments, message statuses, and tokens.
- Seeders (if provided) can be run via `php artisan db:seed`.

---

## Development Notes

- Models and relationships live under `app/Models/` — examine `Message`, `ChatMessage`, `User`,
	`Notification` and their relations to understand domain rules.
- Controllers live in `app/Http/Controllers/` and use Form Requests / validation.
- Middleware and providers are in `app/Http/Middleware` and `app/Providers` respectively.

---

## Deployment

Recommended production steps:

1. Build and publish a production-ready Docker image using the provided `Dockerfile`.
2. Configure environment variables (DB, Redis, APP_KEY, queue/broadcast drivers).
3. Run migrations on deploy and ensure queue workers are running (supervisor/systemd or container tasks).
4. Use HTTPS and set correct CORS and trusted proxies.

---

## Troubleshooting

- If jobs are not processed, confirm `QUEUE_CONNECTION` and Redis availability.
- For broadcasting issues, confirm `BROADCAST_DRIVER` and credentials and that the websocket/pusher
	service is reachable.

---

## Contributing

Contributions are welcome. Please follow these steps:

1. Fork the repository and create a feature branch.
2. Add tests for new behavior and ensure existing tests pass.
3. Open a pull request describing the change and rationale.

---

## License & Maintainers

Specify project license here (e.g. MIT) and list maintainers or contact points for the API team.

---

If you want, I can also generate a short API reference from the controllers and `routes/api.php`.
