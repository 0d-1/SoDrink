<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Conversations.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Notifications.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/storage/FileUpload.php';

use SoDrink\Domain\Conversations;
use SoDrink\Domain\Users;
use SoDrink\Domain\Notifications;
use SoDrink\Storage\FileUpload;
use function SoDrink\Security\require_csrf;
use function SoDrink\Security\require_login;

require_login();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$repo = new Conversations();
$userRepo = new Users();
$meId = (int)($_SESSION['user_id'] ?? 0);

$hydrateConversation = static function (array $conv) use ($userRepo): array {
    $participants = [];
    foreach ($conv['participants'] ?? [] as $participantId) {
        $user = $userRepo->getById((int)$participantId);
        if ($user) {
            $participants[] = Users::toPublic($user);
        }
    }

    return [
        'id'              => (int)($conv['id'] ?? 0),
        'title'           => $conv['title'] ?? null,
        'participants'    => $participants,
        'is_group'        => count($participants) > 2,
        'created_at'      => $conv['created_at'] ?? null,
        'updated_at'      => $conv['updated_at'] ?? null,
        'last_message_at' => $conv['last_message_at'] ?? null,
    ];
};

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

$hydrateMessage = static function (array $message) use ($userRepo, $formatAttachment): array {
    $senderData = $message['sender'] ?? null;
    if ($senderData && isset($senderData['id'])) {
        $sender = $senderData;
    } else {
        $sender = $userRepo->getById((int)($message['sender_id'] ?? 0));
        $sender = $sender ? Users::toPublic($sender) : null;
    }
    return [
        'id'         => (int)($message['id'] ?? 0),
        'sender_id'  => (int)($message['sender_id'] ?? 0),
        'sender'     => $sender,
        'content'    => (string)($message['content'] ?? ''),
        'created_at' => $message['created_at'] ?? null,
        'attachment' => $formatAttachment($message['attachment'] ?? null),
    ];
};

$ensureAccess = static function (?array $conv) use ($meId): array {
    if (!$conv) {
        json_error('Conversation introuvable', 404);
    }
    $participants = array_map('intval', $conv['participants'] ?? []);
    if (!in_array($meId, $participants, true)) {
        json_error('Accès refusé', 403);
    }
    return $conv;
};

if ($method === 'GET') {
    $id = (int)($_GET['conversation_id'] ?? $_GET['id'] ?? 0);
    $conv = $ensureAccess($repo->getById($id));
    $messages = array_map($hydrateMessage, $conv['messages'] ?? []);
    json_success([
        'conversation' => $hydrateConversation($conv),
        'messages'     => $messages,
    ]);
}

if ($method === 'POST') {
    require_csrf();
    $hasFile = !empty($_FILES);
    $conversationId = 0;
    $content = '';
    $attachment = null;

    if ($hasFile) {
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        if ($conversationId <= 0) {
            json_error('Conversation invalide', 422);
        }
        $content = (string)($_POST['content'] ?? '');
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);
        $content = trim($content);
        $content = mb_substr($content, 0, 1000);

        $photo = $_FILES['photo'] ?? null;
        if (!$photo || ($photo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            json_error('Image manquante', 422);
        }
        $maxSize = defined('MAX_UPLOAD_MB_RUNTIME') ? MAX_UPLOAD_MB_RUNTIME : MAX_UPLOAD_MB;
        try {
            $upload = FileUpload::fromImage($photo, CHAT_MEDIA_PATH, $maxSize, ALLOWED_IMAGE_MIME);
        } catch (\Throwable $e) {
            json_error($e->getMessage(), 422);
        }
        $relative = CHAT_MEDIA_WEB . '/' . $upload['filename'];
        $attachment = [
            'type' => 'image',
            'path' => $relative,
            'mime' => $upload['mime'] ?? null,
            'size' => (int)($upload['size'] ?? 0),
        ];
        $dimensions = @getimagesize($upload['path']);
        if (is_array($dimensions)) {
            $attachment['width'] = (int)($dimensions[0] ?? 0) ?: null;
            $attachment['height'] = (int)($dimensions[1] ?? 0) ?: null;
        }
        if ($content === '' && $attachment === null) {
            json_error('Message vide', 422);
        }
    } else {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            json_error('Corps JSON invalide', 400);
        }

        $conversationId = (int)($data['conversation_id'] ?? 0);
        if ($conversationId <= 0) {
            json_error('Conversation invalide', 422);
        }

        $content = (string)($data['content'] ?? '');
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);
        $content = trim($content);
        if ($content === '') {
            json_error('Message vide', 422);
        }
        $content = mb_substr($content, 0, 1000);
    }

    $conv = $ensureAccess($repo->getById($conversationId));

    if ($hasFile && !$attachment) {
        json_error('Image manquante', 422);
    }

    if ($attachment === null && $content === '') {
        json_error('Message vide', 422);
    }

    $message = $repo->addMessage($conversationId, $meId, $content, $attachment);
    if (!$message) {
        json_error('Conversation introuvable', 404);
    }

    $sender = $userRepo->getById($meId);
    if ($sender) {
        $message['sender'] = Users::toPublic($sender);
    }
    $participantIds = array_map('intval', $conv['participants'] ?? []);
    $participantIds = array_values(array_unique($participantIds));
    $senderPseudo = $sender['pseudo'] ?? "Quelqu'un";
    $link = WEB_BASE . '/chat.php?conversation=' . $conversationId;
    $notifier = new Notifications();
    foreach ($participantIds as $participantId) {
        if ($participantId === $meId) {
            continue;
        }
        $notifier->send(
            $participantId,
            'chat_message',
            sprintf("%s t'a envoyé un message.", $senderPseudo),
            $link
        );
    }
    json_success([
        'message'      => $hydrateMessage($message),
        'conversation' => $hydrateConversation($conv),
    ], 201);
}

http_response_code(405);
header('Allow: GET, POST');
json_error('Méthode non autorisée', 405);
