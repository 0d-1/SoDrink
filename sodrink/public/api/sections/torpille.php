<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Torpille.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Notifications.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/security/auth.php';

use SoDrink\Domain\Torpille;
use SoDrink\Domain\Users;
use SoDrink\Domain\Notifications;
use function SoDrink\Security\require_login;
use function SoDrink\Security\require_admin;
use function SoDrink\Security\isAdmin;
use function SoDrink\Security\require_csrf;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$repo   = new Torpille();
$users  = new Users();

/** Stats helper (voir version précédente — inchangé) */
function build_stats(Torpille $repo): array {
    foreach (['countsByUser','statsByUser','leaderboard','stats'] as $m) {
        if (method_exists($repo, $m)) {
            $raw = $repo->$m();
            $list = [];
            if (is_array($raw)) {
                foreach ($raw as $uid => $cnt) {
                    if (is_numeric($uid) && is_numeric($cnt)) $list[] = ['user_id'=>(int)$uid,'count'=>(int)$cnt];
                }
                if (!$list && isset($raw[0]) && is_array($raw[0]) && array_key_exists('user_id',$raw[0]) && array_key_exists('count',$raw[0])) {
                    $list = array_map(fn($r)=>['user_id'=>(int)$r['user_id'],'count'=>(int)$r['count']], $raw);
                }
            }
            usort($list, fn($a,$b)=>($b['count']<=>$a['count']) ?: ($a['user_id']<=>$b['user_id']));
            return $list;
        }
    }
    $list = $repo->listPhotos(1, 10000);
    $counts = [];
    foreach (($list['items'] ?? []) as $p) {
        $uid = (int)($p['to_user_id'] ?? $p['user_id'] ?? $p['owner_id'] ?? 0);
        if ($uid > 0) $counts[$uid] = ($counts[$uid] ?? 0) + 1;
    }
    $out = [];
    foreach ($counts as $uid => $cnt) $out[] = ['user_id'=>(int)$uid, 'count'=>(int)$cnt];
    usort($out, fn($a,$b)=>($b['count']<=>$a['count']) ?: ($a['user_id']<=>$b['user_id']));
    return $out;
}

if ($method === 'GET') {
    require_login();
    $me   = (int)($_SESSION['user_id'] ?? 0);
    $page = max(1, (int)($_GET['page'] ?? 1));
    // NOUVEAU : per_page contrôlable depuis le client (borne 1..12)
    $per  = max(1, min(12, (int)($_GET['per_page'] ?? 6)));

    $state  = $repo->getState();
    $list   = $repo->listPhotos($page, $per);
    $latest = $repo->latest();

    // Anonymisation du détenteur courant pour les non-torpillés
    $current = (int)($state['current_user_id'] ?? 0);
    $is_me   = ($me > 0 && $current === $me);
    $mask    = ($current > 0 && !$is_me && !isAdmin());

    $state_for_client = [
        'current_user_id' => $mask ? null : $current,
        'sequence'        => $state['sequence'] ?? null,
        'updated_at'      => $state['updated_at'] ?? null,
        'is_me_torpille'  => $is_me,
        'mask_current'    => $mask,
    ];

    $payload = [
        'state'  => $state_for_client,
        'list'   => $list,
        'latest' => ($is_me || isAdmin()) ? $latest : null,
        'users'  => array_map([Users::class, 'toPublic'], $users->getAll()),
        'stats'  => build_stats($repo),
    ];
    json_success($payload);
}

if ($method === 'POST') {
    require_login();
    require_csrf();
    $action = (string)($_GET['action'] ?? '');
    $json = null;
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $json = json_decode((string)$raw, true);
        if (!is_array($json)) $json = null;
    }

    if ($action === 'upload_and_pass') {
        $me = (int)($_SESSION['user_id'] ?? 0);
        $next = (int)($_POST['next_user_id'] ?? 0);
        if (!isset($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            json_error('Photo manquante', 422);
        }
        $tmp = (string)$_FILES['photo']['tmp_name'];
        $name = (string)$_FILES['photo']['name'];
        try {
            $rec = $repo->passWithPhoto($me, $tmp, $name, $next);
            (new Notifications())->send($next, 'torpille', "Tu es torpillé(e) !", WEB_BASE . '/');
            json_success(['photo' => $rec]);
        } catch (\Throwable $e) {
            json_error($e->getMessage(), 400);
        }
    }

    if ($action === 'set_initial') {
        require_admin();
        $uid = (int)(($_POST['user_id'] ?? 0) ?: ($json['user_id'] ?? 0));
        if ($uid <= 0 || !$users->getById($uid)) json_error('Utilisateur invalide', 422);
        $repo->setInitial($uid);
        json_success(['ok' => true]);
    }

    if ($action === 'admin_transfer') {
        require_admin();
        $to = (int)(($_POST['to_user_id'] ?? 0) ?: ($json['to_user_id'] ?? ($json['user_id'] ?? 0)));
        if ($to <= 0 || !$users->getById($to)) json_error('Utilisateur invalide', 422);
        $repo->setInitial($to);
        json_success(['ok' => true]);
    }

    json_error('Action inconnue', 400);
}

http_response_code(405);
header('Allow: GET, POST');
json_error('Méthode non autorisée', 405);
