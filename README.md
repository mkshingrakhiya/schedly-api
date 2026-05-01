# PresenceHub API

Backend API for **PresenceHub**—schedule and publish content across your connected channels. Clients authenticate, link platforms and channels, and manage posts and media through a versioned JSON API.

## Tech stack

- **PHP 8.3** & **Laravel 13**
- **PostgreSQL** (primary datastore when run with the project’s Docker stack)

## API

REST-style routes are exposed with an `/api` prefix. Versioned resources live under `/api/v1/…` (see `routes/api.php`).

- `GET /api/ping` — quick liveness check
- `GET /up` — process health (from Laravel’s routing bootstrap)

## License

This project follows the [MIT license](https://opensource.org/licenses/MIT) used by the Laravel framework, unless a different `LICENSE` file is added in the repository root.
