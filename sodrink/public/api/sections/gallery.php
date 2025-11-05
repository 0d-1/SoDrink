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
    $sort = (string)($_GET['sort'] ?? 'recent');
    $searchRaw = trim((string)($_GET['q'] ?? ''));
    $meId = (int)($_SESSION['user_id'] ?? 0);

    $sort = in_array($sort, ['recent', 'popular', 'commented'], true) ? $sort : 'recent';
    $search = $searchRaw !== '' ? mb_strtolower($searchRaw) : '';

    $all = $repo->all();

    $userCache = [];
    $getUser = static function (int $id) use (&$userCache, $users): ?array {
        if ($id <= 0) {
            return null;
        }
        if (!array_key_exists($id, $userCache)) {
            $userCache[$id] = $users->getById($id) ?: null;
        }
        return $userCache[$id];
    };

    $enriched = array_map(static function (array $g) use ($getUser, $meId): array {
        $likes = array_map('intval', $g['likes'] ?? []);
        $comments = $g['comments'] ?? [];

        $preview = array_slice($comments, -2);
        foreach ($preview as &$c) {
            $u = $getUser((int)($c['user_id'] ?? 0));
            $c['pseudo'] = $u['pseudo'] ?? '—';
            $c['avatar'] = $u['avatar'] ?? null;
        }
        unset($c);

        $author = $getUser((int)($g['author_id'] ?? 0));

        $createdAt = isset($g['created_at']) ? strtotime((string)$g['created_at']) ?: 0 : 0;

        return array_merge($g, [
            'author' => $author ? ['id' => (int)$author['id'], 'pseudo' => (string)$author['pseudo'], 'avatar' => $author['avatar'] ?? null] : null,
            'likes_count' => count($likes),
            'liked_by_me' => $meId ? in_array($meId, $likes, true) : false,
            'comments_count' => count($comments),
            'comments_preview' => $preview,
            '_created_ts' => $createdAt,
        ]);
    }, $all);

    if ($authorFilter > 0) {
        $enriched = array_values(array_filter($enriched, static function ($g) use ($authorFilter): bool {
            if (!is_array($g['author'])) {
                return false;
            }

            return (int)($g['author']['id'] ?? 0) === $authorFilter;
        }));
    }

    if ($search !== '') {
        $enriched = array_values(array_filter($enriched, static function ($g) use ($search): bool {
            $fields = [
                mb_strtolower((string)($g['title'] ?? '')),
                mb_strtolower((string)($g['description'] ?? '')),
                mb_strtolower((string)(is_array($g['author']) ? ($g['author']['pseudo'] ?? '') : '')),
            ];
            foreach ($fields as $text) {
                if ($text !== '' && mb_strpos($text, $search) !== false) {
                    return true;
                }
            }
            return false;
        }));
    }

    $sortBy = static function (array $g, string $key): int {
        return (int)($g[$key] ?? 0);
    };

    usort($enriched, static function ($a, $b) use ($sort, $sortBy): int {
        $cmp = 0;
        if ($sort === 'popular') {
            $cmp = $sortBy($b, 'likes_count') <=> $sortBy($a, 'likes_count');
        } elseif ($sort === 'commented') {
            $cmp = $sortBy($b, 'comments_count') <=> $sortBy($a, 'comments_count');
        }

        if ($cmp === 0) {
            $cmp = ($b['_created_ts'] ?? 0) <=> ($a['_created_ts'] ?? 0);
        }

        return $cmp;
    });

    $total = count($enriched);
    $slice = array_slice($enriched, ($page - 1) * $per, $per);

    // Nettoyage des clés internes
    $slice = array_map(static function ($g) {
        unset($g['_created_ts']);
        return $g;
    }, $slice);

    json_success([
        'items' => $slice,
        'page' => $page,
        'per' => $per,
        'total' => $total,
    ]);
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
