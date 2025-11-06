<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Conversations.php';

use SoDrink\Domain\Conversations;
use SoDrink\Domain\Users;
use function SoDrink\Security\require_csrf;
use function SoDrink\Security\require_login;
use function SoDrink\Security\safe_text;

require_login();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$repo = new Conversations();
$userRepo = new Users();
$meId = (int)($_SESSION['user_id'] ?? 0);

$formatAttachment = static function (?array $attachment): ?array {
    if (!is_array($attachment) || empty($attachment['type'])) {
        return null;
    }
    $type = (string)$attachment['type'];
    if ($type !== 'image') {
        return null;
    }
    $path = (string)($attachment['path'] ?? '');
    if ($path === '') {
        return null;
    }
    $url = (defined('WEB_BASE') ? WEB_BASE : '') . $path;
    return [
        'type'   => 'image',
        'path'   => $path,
        'url'    => $url,
        'mime'   => $attachment['mime'] ?? null,
        'width'  => isset($attachment['width']) ? (int)$attachment['width'] : null,
        'height' => isset($attachment['height']) ? (int)$attachment['height'] : null,
        'size'   => isset($attachment['size']) ? (int)$attachment['size'] : null,
    ];
};

$hydrate = static function (array $conv) use ($userRepo, $formatAttachment): array {
    $participants = [];
    foreach ($conv['participants'] ?? [] as $participantId) {
        $user = $userRepo->getById((int)$participantId);
        if ($user) {
            $participants[] = Users::toPublic($user);
        }
    }
    $messages = $conv['messages'] ?? [];
    $lastMessage = null;
    if ($messages) {
        $last = $messages[array_key_last($messages)];
        $sender = $userRepo->getById((int)($last['sender_id'] ?? 0));
        $lastMessage = [
            'id'         => (int)($last['id'] ?? 0),
            'sender_id'  => (int)($last['sender_id'] ?? 0),
            'sender'     => $sender ? Users::toPublic($sender) : null,
            'content'    => (string)($last['content'] ?? ''),
            'created_at' => $last['created_at'] ?? null,
            'attachment' => $formatAttachment($last['attachment'] ?? null),
        ];
    }

    return [
        'id'              => (int)($conv['id'] ?? 0),
        'title'           => $conv['title'] ?? null,
        'participants'    => $participants,
        'is_group'        => count($participants) > 2,
        'created_at'      => $conv['created_at'] ?? null,
        'updated_at'      => $conv['updated_at'] ?? null,
        'last_message_at' => $conv['last_message_at'] ?? null,
        'last_message'    => $lastMessage,
    ];
};

if ($method === 'GET') {
    $convs = array_map($hydrate, $repo->listForUser($meId));
    json_success(['conversations' => $convs]);
}

if ($method === 'POST') {
    require_csrf();
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_error('Corps JSON invalide', 400);
    }

    $participantsInput = $data['participants'] ?? [];
    if (!is_array($participantsInput)) {
        json_error('Participants invalides', 422);
    }

    $ids = [];
    foreach ($participantsInput as $pid) {
        $pid = (int)$pid;
        if ($pid <= 0 || $pid === $meId) {
            continue;
        }
        $user = $userRepo->getById($pid);
        if ($user) {
            $ids[$pid] = $pid;
        }
    }

    if (count($ids) === 0) {
        json_error('Ajoute au moins une autre personne', 422);
    }

    $title = null;
    if (array_key_exists('title', $data)) {
        $candidate = safe_text((string)$data['title'], 60);
        $title = $candidate !== '' ? $candidate : null;
    }

    $conversation = $repo->create(array_values($ids), $meId, $title);
    json_success(['conversation' => $hydrate($conversation)], 201);
}

http_response_code(405);
header('Allow: GET, POST');
json_error('Méthode non autorisée', 405);
