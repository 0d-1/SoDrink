<?php
// src/bootstrap.php — Démarrage app, autoloader, sessions, BASE URL

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// --- Autoloader minimal pour les classes SoDrink\* ---
spl_autoload_register(function (string $class): void {
    $prefix = 'SoDrink\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;

    $relative = substr($class, strlen($prefix));         // ex: "Storage\JsonStore"
    $parts = explode('\\', $relative);                   // ["Storage","JsonStore"]
    if (!$parts) return;

    // Dossiers du projet sont en minuscules (storage, domain, security, utils, ...)
    $parts[0] = strtolower($parts[0]);
    $path = __DIR__ . '/' . implode(DIRECTORY_SEPARATOR, $parts) . '.php';
    if (is_file($path)) { require_once $path; return; }

    // Fallback (si jamais vos dossiers ne sont pas en minuscules)
    $alt = __DIR__ . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($alt)) require_once $alt;
});

// Détermine les valeurs runtime (env > const)
$TZ  = defined('APP_TZ_RUNTIME') ? APP_TZ_RUNTIME : APP_TZ;
$ENV = defined('APP_ENV_RUNTIME') ? APP_ENV_RUNTIME : APP_ENV;

// Timezone & encodage
@date_default_timezone_set($TZ);
mb_internal_encoding('UTF-8');

// Erreurs
if ($ENV === 'dev') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

// Sessions sécurisées
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('SoDrinkSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --- BASE URL (supporte sous-dossier & scripts dans /public/api, /public/tools, etc.) ---
// On fixe toujours la base à ".../public" quel que soit le sous-dossier actuel.
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')); // ex: /sodrink/public/api/sections
if (preg_match('#^(.*?/public)(?:/.*)?$#', $scriptDir, $m)) {
    $base = $m[1]; // => /sodrink/public
} else {
    $base = rtrim($scriptDir, '/');
}
if ($base === '/' || $base === '.') $base = '';
if (!defined('WEB_BASE')) define('WEB_BASE', $base);

// Création des dossiers nécessaires
foreach ([DATA_PATH, UPLOADS_PATH, AVATARS_PATH, BANNERS_PATH, GALLERY_PATH, CHAT_MEDIA_PATH] as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
}

// Petite utilitaire pour vérifier méthode HTTP
function require_method(string $method): void {
    if (strcasecmp($_SERVER['REQUEST_METHOD'] ?? '', $method) !== 0) {
        http_response_code(405); // Method Not Allowed
        header('Allow: ' . strtoupper($method));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
        exit;
    }
}
