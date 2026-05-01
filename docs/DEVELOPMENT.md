# Local development setup

This guide gets the API running in Docker with HTTPS in front of the Laravel app (Nginx reverse proxy → `php artisan serve` in the `api` service → PostgreSQL).

## Prerequisites

- **Docker Desktop** (or Docker Engine + Compose) with enough resources for PHP, Nginx, and PostgreSQL
- **mkcert** (recommended) so browsers trust your local certificates without constant warnings

Install mkcert (macOS with Homebrew):

```bash
brew install mkcert nss
mkcert -install
```

On Linux, follow [mkcert’s install instructions](https://github.com/FiloSottile/mkcert#installation) for your distribution.

## 1. Map local hostnames

The stack expects `presencehub.test` and `presencehub.local` (see [`docker/nginx.conf`](../docker/nginx.conf)). Add them to your hosts file:

- **macOS / Linux:** edit `/etc/hosts` and add:
  - `127.0.0.1 presencehub.test presencehub.local`
- **Windows:** edit `C:\Windows\System32\drivers\etc\hosts` the same way (may need an elevated editor).

## 2. Create TLS files (do not commit keys)

Nginx is configured to load fixed paths under `docker/ssl/`. **Generate a certificate and key on your machine** and use these exact output names so you do not have to edit Nginx:

```bash
cd /path/to/Presence-Hub-API
mkdir -p docker/ssl

mkcert -cert-file docker/ssl/presencehub.test+3.pem -key-file docker/ssl/presencehub.test+3-key.pem presencehub.test presencehub.local
```

- The `+3` in the filenames is only a label; what matters is that the paths match [`docker/nginx.conf`](../docker/nginx.conf).
- These files are **gitignored**; each developer creates their own pair.
- If you used mkcert, your system trusts the mkcert root CA, so `https://presencehub.test` should work without a browser warning.

**If the `nginx` container fails to start** with an error about missing certificate files, confirm both files exist under `docker/ssl/` and match the `ssl_certificate` and `ssl_certificate_key` lines in `docker/nginx.conf`.

## 3. Environment file

```bash
cp .env.example .env
```

Edit `.env` as needed, at minimum for HTTPS behind the proxy:

- `APP_URL=https://presencehub.test` (or `https://presencehub.local` if you prefer; keep it consistent with the URL you use in the browser)

The `api` service in [`docker-compose.yml`](../docker-compose.yml) sets `DB_*` for PostgreSQL inside the Compose network (`DB_HOST=postgres`, etc.), so the app in Docker uses Postgres regardless of the `DB_CONNECTION=sqlite` default in `.env.example`. For running Artisan **on the host** (without Compose) you would need to set `DB_CONNECTION=pgsql` and point `DB_HOST` to `127.0.0.1` and `DB_PORT` to the published port (default `5432` unless you set `POSTGRES_PORT` in `.env`).

The [`docker/entrypoint.sh`](../docker/entrypoint.sh) ensures `composer install`, a present `.env`, and `APP_KEY` are handled when the `api` container starts.

## 4. Start the stack

From the project root:

```bash
docker compose up -d --build
```

- **Nginx** listens on **80** and **443** on the host.
- The **api** service does **not** publish port `8000` to the host; traffic is meant to go through Nginx only.

If port **80** or **443** is already in use (another web server, etc.), stop that service or change the port mapping for `nginx` in `docker-compose.yml` and use matching URLs/hosts.

## 5. Database migrations

Run migrations inside the `api` container:

```bash
docker compose exec api php artisan migrate
```

## 6. Git hooks (optional)

This repo ships Git hook scripts under [`.githooks/`](../.githooks/) (version-controlled). They are **not** enabled until you point Git at that directory.

### One-time setup

From the **repository root**:

```bash
composer run setup-hooks
```

That runs `git config core.hooksPath .githooks`, so Git uses `.githooks/pre-commit` instead of `.git/hooks/`.

You can confirm:

```bash
git config --get core.hooksPath
# should print: .githooks
```

On macOS / Linux, ensure the hook script is executable (once per clone, if needed):

```bash
chmod +x .githooks/pre-commit
```

### What `pre-commit` does

The hook runs **inside the running `api` container** (same commands as a quick local gate):

1. `composer format` (Pint — may modify files)
2. `composer analyse` (PHPStan)
3. `composer test`

**Requirements:** bring the stack up first (`docker compose up -d` or equivalent) so `docker-compose exec -T api …` succeeds. The script uses `docker-compose` with a hyphen, consistent with other project docs.

**If Pint changes files**, Git will still be mid-commit with a dirty tree: stage the updates (`git add …`) and run `git commit` again (or amend) so the formatted code is included.

**To skip hooks for a single commit** (use sparingly):

```bash
git commit --no-verify
```

## 7. Quick checks

```bash
docker compose ps
docker compose exec nginx nginx -t
```

- Open `http://presencehub.test` in a browser: you should be redirected to HTTPS.
- Open `https://presencehub.test` (or `https://presencehub.local`): the Laravel app should respond.
- `curl` example (ignores cert validation for a quick test):

  ```bash
  curl -skI https://presencehub.test/
  ```

## 8. Optional: if private keys were ever committed

If keys were added to git before they were ignored, remove them from the index (keeps the files on disk if needed; adjust paths to match your repo):

```bash
git rm -r --cached docker/ssl/
```

Regenerate local certs (step 2) and commit only the updated `.gitignore` and this documentation.

## Troubleshooting

- **`nginx` container exits** — Often missing or misnamed `docker/ssl` files, or `nginx -t` errors. Check logs: `docker compose logs nginx`.
- **Database connection errors** — Confirm `postgres` is healthy: `docker compose ps`. Ensure the `api` service uses the Compose `environment` (see `docker-compose.yml`).
- **“Wrong host” or 404 from Laravel** — Set `APP_URL` to the hostname you use (`https://presencehub.test`).

## Tests (host PHP)

With dependencies installed on the host (`composer install`):

```bash
php artisan test
```

(Use a `.env` configured for the same database as your test setup if you run non-SQLite tests.)
