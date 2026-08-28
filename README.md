# sane_php_frontend

This is a SANE frontend using the `scanimage` shell utility. SANE PHP Frontend is a web user interface.

**Current stack:** PHP 8.3, Symfony 6.4 LTS, Twig, Stimulus, Bootstrap 5, Imagick (Docker), Redis cache in production.

Version 0.2 contains essential functionality for listing scanners, acquiring available resolutions, and executing scan tasks.

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
