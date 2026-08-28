<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$filename = $_SERVER['DOCUMENT_ROOT'].$path;
if ($path !== '/' && is_file($filename)) {
    return false;
}

require __DIR__.'/index.php';
