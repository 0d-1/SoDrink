<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/security/auth.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/ScheduledNotifications.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Users.php';

use SoDrink\Domain\ScheduledNotifications;
use SoDrink\Domain\Users;
use function SoDrink\Security\require_admin;
use function SoDrink\Security\require_csrf;

require_admin();

$repo = new ScheduledNotifications();
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
        $uid = (int)($n['created_by'] ?? 0);
        $creator = $users[$uid] ?? null;
        $payload[] = $n + [
            'creator' => $creator ? [
                'id' => (int)$creator['id'],
                'pseudo' => (string)($creator['pseudo'] ?? ''),
                'role' => (string)($creator['role'] ?? 'user'),
            ] : null,
        ];
    }
    json_success(['schedules' => $payload]);
}

require_csrf();

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_error('Corps JSON invalide', 400);
    }

    $title = trim((string)($data['title'] ?? ''));
    $message = trim((string)($data['message'] ?? ''));
    $link = trim((string)($data['link'] ?? ''));
    $target = (string)($data['target'] ?? 'all');
    $userIds = isset($data['user_ids']) && is_array($data['user_ids']) ? $data['user_ids'] : [];
    $scheduledRaw = trim((string)($data['scheduled_for'] ?? ''));

    if ($title === '' || $message === '') {
        json_error('Titre et message requis', 422);
    }
    if ($scheduledRaw === '') {
        json_error('Date de planification requise', 422);
    }
    if (!in_array($target, ['all', 'admins', 'users', 'custom'], true)) {
        json_error('Cible invalide', 422);
    }
    if ($target === 'custom' && !$userIds) {
        json_error('Sélectionne au moins un utilisateur', 422);
    }

    $tz = new DateTimeZone(APP_TZ);
    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $scheduledRaw, $tz);
    if (!$dt) {
        json_error('Date invalide', 422);
    }

    $record = $repo->create([
        'title' => $title,
        'message' => $message,
        'link' => $link,
        'target' => $target,
        'user_ids' => array_values(array_unique(array_map('intval', $userIds))),
        'scheduled_for' => $dt->format('c'),
        'status' => 'pending',
        'created_by' => (int)($_SESSION['user_id'] ?? 0),
        'created_at' => date('c'),
        'sent_at' => null,
        'sent_count' => 0,
    ]);

    json_success(['schedule' => $record]);
}

if ($method === 'PATCH') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_error('Corps JSON invalide', 400);
    }
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        json_error('ID invalide', 422);
    }
    $item = $repo->findById($id);
    if (!$item) {
        json_error('Planification introuvable', 404);
    }

    $fields = [];
    if (isset($data['status'])) {
        $status = (string)$data['status'];
        if (!in_array($status, ['pending', 'canceled'], true)) {
            json_error('Statut invalide', 422);
        }
        $fields['status'] = $status;
    }
    if (isset($data['scheduled_for'])) {
        $rawDate = trim((string)$data['scheduled_for']);
        $tz = new DateTimeZone(APP_TZ);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $rawDate, $tz);
        if (!$dt) {
            json_error('Date invalide', 422);
        }
        $fields['scheduled_for'] = $dt->format('c');
    }
    if (!$fields) {
        json_error('Aucune modification', 422);
    }
    $repo->update($id, $fields);
    json_success(['updated' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_error('ID invalide', 422);
    }
    if (!$repo->delete($id)) {
        json_error('Planification introuvable', 404);
    }
    json_success(['deleted' => true]);
}

json_error('Méthode non supportée', 405);
