<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Events.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Notifications.php';

use SoDrink\Domain\Events;
use SoDrink\Domain\Users;
use SoDrink\Domain\Notifications;
use function SoDrink\Security\require_login;
use function SoDrink\Security\require_csrf;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$events = new Events();
$users  = new Users();

if ($method === 'GET') {
    $id = (int)($_GET['event_id'] ?? 0);
    if ($id <= 0) json_error('event_id requis', 400);
    $ev = $events->getById($id); if (!$ev) json_error('Évènement introuvable', 404);
    $list = [];
    foreach (($ev['participants'] ?? []) as $uid) {
        $u = $users->getById((int)$uid);
        if ($u) $list[] = ['id'=>(int)$u['id'],'pseudo'=>$u['pseudo'],'avatar'=>$u['avatar'] ?? null];
    }
    json_success(['participants'=>$list, 'count'=>count($list)]);
}

require_login();
require_csrf();
$uid = (int)$_SESSION['user_id'];

if ($method === 'POST') {
    $id = (int)($_GET['event_id'] ?? 0);
    if ($id <= 0) json_error('event_id requis', 400);
    $ok = $events->addParticipant($id, $uid);
    if (!$ok) json_error('Impossible d’ajouter', 500);

    // Notification à l'auteur de l'évènement
    $ev = $events->getById($id);
    if ($ev && !empty($ev['created_by']) && (int)$ev['created_by'] !== $uid) {
        $me = $users->getById($uid);
        (new Notifications())->send(
            (int)$ev['created_by'],
            'event_join',
            "{$me['pseudo']} participe à votre soirée du {$ev['date']}",
            WEB_BASE . '/#section-next-event'
        );
    }

    json_success(['joined'=>true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['event_id'] ?? 0);
    if ($id <= 0) json_error('event_id requis', 400);
    $ok = $events->removeParticipant($id, $uid);
    if (!$ok) json_error('Impossible de retirer', 500);
    json_success(['joined'=>false]);
}

http_response_code(405);
json_error('Méthode non autorisée', 405);
