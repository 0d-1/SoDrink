<?php
declare(strict_types=1);

// CLI : envoie les notifications planifiées arrivées à échéance.
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/domain/ScheduledNotifications.php';
require_once __DIR__ . '/../src/domain/Notifications.php';
require_once __DIR__ . '/../src/domain/Users.php';

use SoDrink\Domain\ScheduledNotifications;
use SoDrink\Domain\Notifications;
use SoDrink\Domain\Users;

$repo = new ScheduledNotifications();
$usersRepo = new Users();
$notifRepo = new Notifications();

$now = new DateTimeImmutable('now', new DateTimeZone(APP_TZ));

$users = $usersRepo->getAll();
$usersById = [];
foreach ($users as $u) {
    $usersById[(int)$u['id']] = $u;
}

$schedules = $repo->listAll();

foreach ($schedules as $schedule) {
    if (($schedule['status'] ?? 'pending') !== 'pending') {
        continue;
    }
    $scheduledFor = $schedule['scheduled_for'] ?? null;
    if (!is_string($scheduledFor)) {
        continue;
    }
    $dt = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $scheduledFor);
    if (!$dt || $dt > $now) {
        continue;
    }

    $target = (string)($schedule['target'] ?? 'all');
    $ids = [];
    if ($target === 'custom') {
        foreach (($schedule['user_ids'] ?? []) as $id) {
            $id = (int)$id;
            if ($id > 0 && isset($usersById[$id])) {
                $ids[] = $id;
            }
        }
    } elseif ($target === 'admins') {
        foreach ($users as $u) {
            if (($u['role'] ?? 'user') === 'admin') {
                $ids[] = (int)$u['id'];
            }
        }
    } elseif ($target === 'users') {
        foreach ($users as $u) {
            if (($u['role'] ?? 'user') !== 'admin') {
                $ids[] = (int)$u['id'];
            }
        }
    } else {
        foreach ($users as $u) {
            $ids[] = (int)$u['id'];
        }
    }

    $ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));
    if (!$ids) {
        $repo->update((int)$schedule['id'], [
            'status' => 'canceled',
            'sent_at' => date('c'),
            'sent_count' => 0,
        ]);
        continue;
    }

    $title = trim((string)($schedule['title'] ?? ''));
    $message = trim((string)($schedule['message'] ?? ''));
    $link = trim((string)($schedule['link'] ?? ''));
    $payload = $title !== '' ? ($title . ' — ' . $message) : $message;

    $sent = 0;
    foreach ($ids as $uid) {
        if ($notifRepo->send($uid, 'admin_broadcast', $payload, $link)) {
            $sent++;
        }
    }

    $repo->update((int)$schedule['id'], [
        'status' => 'sent',
        'sent_at' => date('c'),
        'sent_count' => $sent,
    ]);
}
