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
require_csrf();

// Parse JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    json_error('Corps JSON invalide', 400);
}

$message = trim((string)($data['message'] ?? ''));
$link    = trim((string)($data['link'] ?? '')); // <- TOUJOURS une string
$target  = isset($data['target']) ? (string)$data['target'] : null;
$userIds = isset($data['user_ids']) && is_array($data['user_ids']) ? $data['user_ids'] : [];

if ($message === '') {
    json_error('Message requis', 422);
}

$usersRepo = new Users();
$allUsers  = $usersRepo->getAll();

// Build recipients
$ids = [];

// Priorité à la sélection manuelle si user_ids fourni et non vide
if ($userIds) {
    foreach ($userIds as $id) {
        $id = (int)$id;
        if ($id > 0 && $usersRepo->getById($id)) {
            $ids[] = $id;
        }
    }
    if (!$ids) {
        json_error('Aucun utilisateur valide sélectionné', 422);
    }
} else {
    // Sinon on utilise target
    $target = $target ?: 'all';
    if ($target === 'all') {
        foreach ($allUsers as $u) $ids[] = (int)$u['id'];
    } elseif ($target === 'admins') {
        foreach ($allUsers as $u) if (($u['role'] ?? 'user') === 'admin') $ids[] = (int)$u['id'];
    } elseif ($target === 'users') {
        foreach ($allUsers as $u) if (($u['role'] ?? 'user') !== 'admin') $ids[] = (int)$u['id'];
    } else {
        json_error('Cible invalide', 400);
    }
}

$ids = array_values(array_unique(array_filter($ids, fn($v)=>$v>0)));

// Envoi
$repo = new Notifications();
$sent = 0;
foreach ($ids as $uid) {
    // <- $link est une string (éventuellement ''), plus de null
    $repo->send($uid, 'admin_broadcast', $message, $link);
    $sent++;
}

json_success(['sent' => $sent]);
