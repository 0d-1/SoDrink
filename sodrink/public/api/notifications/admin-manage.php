<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/security/auth.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Notifications.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';

use SoDrink\Domain\Notifications;
use SoDrink\Domain\Users;
use function SoDrink\Security\require_admin;
use function SoDrink\Security\require_csrf;

require_admin();

$repo = new Notifications();
$usersRepo = new Users();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $items = $repo->listAll();
    $users = [];
    foreach ($usersRepo->getAll() as $u) {
        $users[(int)$u['id']] = $u;
    }

    $payload = [];
    foreach ($items as $n) {
        $uid = (int)($n['user_id'] ?? 0);
        $user = $users[$uid] ?? null;
        $payload[] = $n + [
            'user' => $user ? [
                'id' => (int)$user['id'],
                'pseudo' => (string)($user['pseudo'] ?? ''),
                'role' => (string)($user['role'] ?? 'user'),
                'nom' => (string)($user['nom'] ?? ''),
                'prenom' => (string)($user['prenom'] ?? ''),
            ] : null,
        ];
    }

    json_success(['notifications' => $payload]);
}

require_csrf();

if ($method === 'PATCH') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_error('Corps JSON invalide', 400);
    }
    $id = (int)($data['id'] ?? 0);
    $read = (bool)($data['read'] ?? false);
    if ($id <= 0) {
        json_error('ID invalide', 422);
    }
    if (!$repo->setReadStatus($id, $read)) {
        json_error('Notification introuvable', 404);
    }
    json_success(['updated' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_error('ID invalide', 422);
    }
    if (!$repo->deleteById($id)) {
        json_error('Notification introuvable', 404);
    }
    json_success(['deleted' => true]);
}

json_error('Méthode non supportée', 405);
