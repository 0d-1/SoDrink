<?php
// src/config.php
// Configuration basique + lecture .env (optionnelle)

declare(strict_types=1);

// Charge les variables depuis config/.env si présent
function sodrink_load_env(string $path): array {
    $vars = [];
    if (!is_file($path)) return $vars;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        [$k, $v] = array_map('trim', array_pad(explode('=', $line, 2), 2, ''));
        // Retire éventuelles quotes
        $v = preg_replace('/^\"|\"$/', '', $v);
        $v = preg_replace("/^'|'$/", '', $v);
        if ($k !== '') $vars[$k] = $v;
    }
    return $vars;
}

function env(string $key, mixed $default = null): mixed {
    static $ENV = null;
    if ($ENV === null) {
        $ENV = sodrink_load_env(__DIR__ . '/../config/.env');
    }
    return $_ENV[$key] ?? $_SERVER[$key] ?? $ENV[$key] ?? $default;
}

// Chemins de base
const BASE_PATH    = __DIR__ . '/..';
const PUBLIC_PATH  = BASE_PATH . '/public';
const DATA_PATH    = BASE_PATH . '/data';
const UPLOADS_PATH = PUBLIC_PATH . '/uploads';
const AVATARS_PATH = UPLOADS_PATH . '/avatars';
const BANNERS_PATH = UPLOADS_PATH . '/banners';
const GALLERY_PATH = UPLOADS_PATH . '/gallery';

// App
const APP_NAME = 'SoDrink';
const APP_ENV  = 'dev'; // par défaut; peut être surchargé via .env

// Timezone (par défaut Europe/Brussels)
const APP_TZ = 'Europe/Brussels';

// Uploads
const MAX_UPLOAD_MB = 10; // peut être ajusté via .env au besoin
const ALLOWED_IMAGE_MIME = ['image/jpeg', 'image/png', 'image/webp'];

// Permet de surcharger via .env
if (($tz = env('APP_TZ')) && $tz !== APP_TZ) {
    define('APP_TZ_RUNTIME', $tz);
}
if (($env = env('APP_ENV')) && $env !== APP_ENV) {
    define('APP_ENV_RUNTIME', $env);
}
if (($max = env('MAX_UPLOAD_MB')) && (int)$max > 0 && (int)$max !== MAX_UPLOAD_MB) {
    define('MAX_UPLOAD_MB_RUNTIME', (int)$max);
}