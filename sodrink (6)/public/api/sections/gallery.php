<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Gallery.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/storage/FileUpload.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/config.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Notifications.php';

use SoDrink\Domain\Gallery;
use SoDrink\Domain\Users;
use SoDrink\Storage\FileUpload;
use SoDrink\Domain\Notifications;
use function SoDrink\Security\require_login;
use function SoDrink\Security\isAdmin;
use function SoDrink\Security\require_csrf;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$repo = new Gallery();
$users = new Users();

if ($method === 'GET') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per  = max(1, min(36, (int)($_GET['per'] ?? 12)));
    $authorFilter = (int)($_GET['author_id'] ?? 0);
    $meId = (int)($_SESSION['user_id'] ?? 0);

    $all = array_reverse($repo->all());
    if ($authorFilter > 0) $all = array_values(array_filter($all, fn($g) => (int)($g['author_id'] ?? 0) === $authorFilter));

    foreach ($all as &$g) {
        $au = $users->getById((int)($g['author_id'] ?? 0));
        $g['author'] = $au ? ['id'=>(int)$au['id'],'pseudo'=>$au['pseudo'],'avatar'=>$au['avatar']??null] : null;
        $likes = $g['likes'] ?? [];
        $comms = $g['comments'] ?? [];
        $g['likes_count'] = count($likes);
        $g['liked_by_me'] = $meId ? in_array($meId, array_map('intval',$likes), true) : false;
        $g['comments_count'] = count($comms);
        // Aperçu 2 derniers commentaires
        $preview = array_slice($comms, -2);
        foreach ($preview as &$c) { $u = $users->getById((int)$c['user_id']); $c['pseudo'] = $u['pseudo'] ?? '—'; $c['avatar'] = $u['avatar'] ?? null; }
        $g['comments_preview'] = $preview;
    }

    $total = count($all);
    $slice = array_slice($all, ($page-1)*$per, $per);
    json_success(['items' => $slice, 'page' => $page, 'per' => $per, 'total' => $total]);
}

if ($method === 'POST') {
    require_login(); require_csrf();
    if (!isset($_FILES['photo'])) json_error('Fichier manquant (photo)', 400);
    try {
        $info = FileUpload::fromImage($_FILES['photo'], GALLERY_PATH, defined('MAX_UPLOAD_MB_RUNTIME') ? MAX_UPLOAD_MB_RUNTIME : MAX_UPLOAD_MB, ALLOWED_IMAGE_MIME);
    } catch (Throwable $e) { json_error($e->getMessage(), 400); }
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120);
    $desc  = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 500);
    $item = $repo->add([
        'path' => WEB_BASE . '/uploads/gallery/' . $info['filename'],
        'title' => $title, 'description' => $desc,
        'author_id' => (int)($_SESSION['user_id'] ?? 0),
    ]);
    // notif auteur
    (new Notifications())->send((int)$item['author_id'], 'gallery_created', "Votre photo « {$title} » est publiée.", WEB_BASE.'/#gallery');
    json_success(['item' => $item], 201);
}

if ($method === 'PUT') {
    require_login(); require_csrf();
    $id = (int)($_GET['id'] ?? 0); if ($id <= 0) json_error('id requis', 400);
    $cur = $repo->getById($id); if (!$cur) json_error('Élément introuvable', 404);
    $me = (int)$_SESSION['user_id']; $admin=isAdmin();
    if (!$admin && (int)($cur['author_id'] ?? 0) !== $me) json_error('Vous ne pouvez modifier que vos propres photos', 403);

    $data = json_decode(file_get_contents('php://input'), true); if (!is_array($data)) json_error('JSON invalide', 400);
    $fields = [];
    if (isset($data['title']))       $fields['title'] = mb_substr((string)$data['title'], 0, 120);
    if (isset($data['description'])) $fields['description'] = mb_substr((string)$data['description'], 0, 500);
    if (!$fields) json_error('Aucun champ', 400);
    $repo->update($id, $fields);
    (new Notifications())->send((int)$cur['author_id'], 'gallery_updated', "Votre photo a été modifiée.", WEB_BASE.'/#gallery');
    json_success(['updated'=>true]);
}

if ($method === 'DELETE') {
    require_login(); require_csrf();
    $id = (int)($_GET['id'] ?? 0); if ($id <= 0) json_error('id requis', 400);
    $cur = $repo->getById($id); if (!$cur) json_error('Introuvable', 404);
    $me = (int)$_SESSION['user_id']; $admin=isAdmin();
    if (!$admin && (int)($cur['author_id'] ?? 0) !== $me) json_error('Vous ne pouvez supprimer que vos propres photos', 403);
    $ok = $repo->delete($id);
    if ($ok && !empty($cur['path'])) { $abs = GALLERY_PATH . '/' . basename($cur['path']); if (is_file($abs)) @unlink($abs); }
    (new Notifications())->send((int)$cur['author_id'], 'gallery_deleted', "Votre photo a été supprimée.", WEB_BASE.'/#gallery');
    json_success(['deleted' => true]);
}

http_response_code(405);
json_error('Méthode non autorisée', 405);
