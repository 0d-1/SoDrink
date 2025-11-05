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

require_login(); require_csrf();

$id = (int)($_GET['id'] ?? 0); if ($id<=0) json_error('id requis', 400);
$repo = new Gallery(); $users = new Users();

[$ok, $liked] = $repo->toggleLike($id, (int)$_SESSION['user_id']);
if (!$ok) json_error('Impossible', 500);

$g = $repo->getById($id);
$count = count($g['likes'] ?? []);
if ($liked && (int)$g['author_id'] !== (int)$_SESSION['user_id']) {
    $me = $users->getById((int)$_SESSION['user_id']);
    (new Notifications())->send((int)$g['author_id'], 'gallery_like', "{$me['pseudo']} aime votre photo « {$g['title']} ».", WEB_BASE.'/#gallery');
}
json_success(['liked'=>$liked,'count'=>$count]);
