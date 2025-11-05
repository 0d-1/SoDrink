<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'SoDrink\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    if (!$parts) {
        return;
    }

    $parts[0] = strtolower($parts[0]);
    $baseDir = __DIR__ . '/../src/';
    $path = $baseDir . implode(DIRECTORY_SEPARATOR, $parts) . '.php';
    if (is_file($path)) {
        require_once $path;
        return;
    }

    $alt = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($alt)) {
        require_once $alt;
    }
});
