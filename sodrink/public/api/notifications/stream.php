<?php
// public/api/notifications/stream.php
// Flux SSE pour notifications en temps réel

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Notifications.php';

use SoDrink\Domain\Notifications;
use function SoDrink\Security\require_login;

require_login();
$uid = (int)($_SESSION['user_id'] ?? 0);
session_write_close();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
if (function_exists('apache_setenv')) {@apache_setenv('no-gzip', '1');}
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');

while (ob_get_level() > 0) {
    @ob_end_flush();
}

$repo = new Notifications();
$lastId = 0;
if (!empty($_GET['last_id'])) {
    $lastId = (int)$_GET['last_id'];
}
if (!empty($_SERVER['HTTP_LAST_EVENT_ID'])) {
    $lastId = max($lastId, (int)$_SERVER['HTTP_LAST_EVENT_ID']);
}

ignore_user_abort(true);
set_time_limit(0);

$start = time();
$maxDuration = 300; // 5 minutes par connexion

while (!connection_aborted() && (time() - $start) < $maxDuration) {
    $items = $repo->listForUser($uid, 30);
    $newItems = array_values(array_filter($items, static fn(array $n): bool => (int)($n['id'] ?? 0) > $lastId));

    if ($newItems) {
        $lastId = max(array_map(static fn(array $n): int => (int)($n['id'] ?? 0), $newItems));
        $payload = [
            'items'   => $items,
            'new_ids' => array_map(static fn(array $n): int => (int)($n['id'] ?? 0), $newItems),
            'unread'  => $repo->countUnread($uid),
        ];

        echo 'id: ' . $lastId . "\n";
        echo "event: notification\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
        @ob_flush();
        @flush();
    } else {
        echo ": ping\n\n";
        @ob_flush();
        @flush();
    }

    sleep(1);
}

echo ": bye\n\n";
@ob_flush();
@flush();
