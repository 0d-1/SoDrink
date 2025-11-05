<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Gallery.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Notifications.php';

use SoDrink\Domain\Gallery;
use SoDrink\Domain\Users;
use SoDrink\Domain\Notifications;
use function SoDrink\Security\require_login;
use function SoDrink\Security\require_csrf;
use function SoDrink\Security\isAdmin;

$repo = new Gallery();
$users = new Users();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0); if ($id<=0) json_error('id requis', 400);
    $g = $repo->getById($id); if (!$g) json_error('Introuvable', 404);
    $list = $g['comments'] ?? [];
    // enrich
    foreach ($list as &$c){ $u=$users->getById((int)$c['user_id']); $c['pseudo']=$u['pseudo']??'—'; $c['avatar']=$u['avatar']??null; }
    json_success(['items'=>$list]);
}

if ($method === 'POST') {
    require_login(); require_csrf();
    $id = (int)($_GET['id'] ?? 0); if ($id<=0) json_error('id requis', 400);
    $data = json_decode(file_get_contents('php://input'), true); if (!is_array($data)) json_error('JSON invalide', 400);
    $text = trim((string)($data['text'] ?? '')); if ($text==='') json_error('Commentaire vide', 422);
    $c = $repo->addComment($id, ['user_id'=>(int)$_SESSION['user_id'], 'text'=>mb_substr($text,0,600)]);
    if (!$c) json_error('Impossible', 500);

    $g = $repo->getById($id);
    if ((int)$g['author_id'] !== (int)$_SESSION['user_id']) {
        $me = $users->getById((int)$_SESSION['user_id']);
        (new Notifications())->send((int)$g['author_id'], 'gallery_comment', "{$me['pseudo']} a commenté votre photo « {$g['title']} ».", WEB_BASE.'/#gallery');
    }
    $u = $users->getById((int)$c['user_id']);
    $c['pseudo']=$u['pseudo']??'—'; $c['avatar']=$u['avatar']??null;
    json_success(['comment'=>$c], 201);
}

if ($method === 'DELETE') {
    require_login(); require_csrf();
    $id = (int)($_GET['id'] ?? 0);
    $cid = (int)($_GET['comment_id'] ?? 0);
    if ($id<=0 || $cid<=0) json_error('Paramètres requis', 400);
    if (!isAdmin()) json_error('Réservé admin', 403);
    $ok = $repo->deleteComment($id, $cid);
    json_success(['deleted'=>$ok]);
}

http_response_code(405);
json_error('Méthode non autorisée', 405);
