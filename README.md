# sane_php_frontend

This is a SANE frontend using the `scanimage` shell utility. SANE PHP Frontend is a web user interface.

**Current stack:** PHP 8.3, Symfony 6.4 LTS, Twig, Stimulus, Bootstrap 5, Imagick (Docker), Redis cache in production.

The UI lists scanners, scans at a chosen resolution, shows a preview, then lets you download JPEG, PNG, TIFF, or PDF. Cyrillic file names are supported.

## Deploy

1. Create `compose.yaml` from `compose.yaml.dist`.
   Edit ports for the _php_ service if needed (you may want to place this app behind a proxy).
2. Set a real `APP_SECRET` in `compose.yaml` (see [Environment variables](#environment-variables)).
3. Create `etc/apache2/docker-backend.conf` from `etc/apache2/docker-backend.conf.dist`.
   Edit _ServerAlias_ and _ServerAdmin_.
4. Create `etc/sane.d/net.conf` from `etc/sane.d/net.conf.dist`.
   Edit saned hosts to addresses where your SANE backend is placed.
   For further information on SANE over network see https://wiki.debian.org/SaneOverNetwork#socket
5. `docker compose up -d --build`

### Environment variables

Symfony loads committed `.env*` files only as fallbacks. **Real** variables from Compose `environment:`, systemd, or the shell always win.

| File / source | Purpose |
|---------------|---------|
| `.env` | Local CLI defaults (`composer`, `phpunit`, `php -S`). `APP_ENV=dev`. Not used as-is in the production image. |
| `.env.prod` | Production defaults. Copied over `.env` in the production Docker image. |
| `compose.yaml` `environment:` | What you set at deploy. Overrides everything in the image. |
| `.env.local` | Uncommitted local overrides. Do not copy secrets into the image. |

The production image sets `APP_ENV=prod` and `APP_DEBUG=0`, and ships a **placeholder** `APP_SECRET`. You must replace that secret at deploy.

| Variable | Required at deploy | Default | Notes |
|----------|--------------------|---------|--------|
| `APP_ENV` | No | `prod` in the prod image and Compose; `dev` in `.env` for local CLI | Do not run the public container as `dev`. |
| `APP_DEBUG` | No | `0` in prod | Keep `0` in production. |
| `APP_SECRET` | **Yes** | `ThisTokenIsNotSoSecretChangeIt` | Used for CSRF and session signing. Generate with `openssl rand -hex 32` and put it in `compose.yaml`. |
| `REDIS_URL` | If you change Redis | `redis://sane_php_frontend-redis` | Must match the Redis Compose service hostname. |
| `TZ` | No | `Europe/Moscow` in Compose | Container timezone. |

Do not commit a real `APP_SECRET`. `compose.yaml` is gitignored after you copy it from the dist file.

## Development

PHP 8.3 and Composer 2 are required on the host for CLI tests:

```bash
composer install
npm ci && npm run build
vendor/bin/phpunit
```

Set `SCAN_MOCK=1` in `.env.local` to use a fixture image instead of a physical scanner.

Local PHP server (serves Encore assets from `public/build`):

```bash
php -S 127.0.0.1:8000 -t public public/router.php
```