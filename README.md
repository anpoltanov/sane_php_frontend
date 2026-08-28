# sane_php_frontend

This is a SANE frontend using the `scanimage` shell utility. SANE PHP Frontend is a web user interface.

**Current stack:** PHP 8.3, Symfony 6.4 LTS, Twig, Stimulus, Bootstrap 5, Imagick (Docker), Redis cache in production.

The UI lists scanners, scans at a chosen resolution, shows a preview, then lets you download JPEG, PNG, TIFF, or PDF. Cyrillic file names are supported.

## Deploy

1. Create `compose.yaml` from `compose.yaml.dist`
   Edit ports for the _php_ service if needed (you may want to place this app behind a proxy).
2. Create `etc/apache2/docker-backend.conf` from `etc/apache2/docker-backend.conf.dist`
   Edit _ServerAlias_ and _ServerAdmin_.
3. Create `etc/sane.d/net.conf` from `etc/sane.d/net.conf.dist`
   Edit saned hosts to addresses where your SANE backend is placed.
   For further information on SANE over network see https://wiki.debian.org/SaneOverNetwork#socket
4. `docker compose up -d --build`

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