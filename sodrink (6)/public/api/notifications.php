<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Notifications.php';

use SoDrink\Domain\Notifications;
use function SoDrink\Security\require_login;
use function SoDrink\Security\require_csrf;

require_login();
$repo = new Notifications();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $uid = (int)$_SESSION['user_id'];
    $items = $repo->listForUser($uid, 30);
    $unread = $repo->countUnread($uid);
    json_success(['items'=>$items, 'unread'=>$unread]);
}

if ($method === 'POST') {
    require_csrf();
    $data = json_decode(file_get_contents('php://input'), true);
    $uid = (int)$_SESSION['user_id'];
    if (($data['action'] ?? '') === 'read_all') {
        $repo->markAllRead($uid);
        json_success(['ok'=>true]);
    } elseif (($data['action'] ?? '') === 'read' && !empty($data['id'])) {
        $ok = $repo->markRead($uid, (int)$data['id']);
        json_success(['ok'=>$ok]);
    }
    json_error('Action invalide', 400);
}

http_response_code(405);
json_error('Méthode non autorisée', 405);
