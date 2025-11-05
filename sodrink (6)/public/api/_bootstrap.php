<?php
// public/api/_bootstrap.php — charge src/bootstrap.php depuis la racine du projet
declare(strict_types=1);

// Remonte l'arborescence jusqu'à trouver src/bootstrap.php
$root = __DIR__;
for ($i = 0; $i < 6; $i++) {
    if (is_file($root . '/src/bootstrap.php')) {
        require_once $root . '/src/bootstrap.php';
        $GLOBALS['SODRINK_ROOT'] = $root;
        break;
    }
    $root = dirname($root);
}
if (!isset($GLOBALS['SODRINK_ROOT'])) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Bootstrap introuvable']);
    exit;
}

// Dépendances communes API
require_once $GLOBALS['SODRINK_ROOT'] . '/src/utils/response.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/security/csrf.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/security/auth.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';
use function SoDrink\Security\auto_login_from_cookie_if_needed;
auto_login_from_cookie_if_needed();
require_once $GLOBALS['SODRINK_ROOT'] . '/src/security/sanitizer.php';
