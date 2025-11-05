<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/domain/Conversations.php';

use SoDrink\Domain\Conversations;
use SoDrink\Domain\Users;
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

$hydrateMessage = static function (array $message) use ($userRepo): array {
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
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_error('Corps JSON invalide', 400);
    }

    $conversationId = (int)($data['conversation_id'] ?? 0);
    if ($conversationId <= 0) {
        json_error('Conversation invalide', 422);
    }

    $conv = $ensureAccess($repo->getById($conversationId));

    $content = (string)($data['content'] ?? '');
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);
    $content = trim($content);
    if ($content === '') {
        json_error('Message vide', 422);
    }
    $content = mb_substr($content, 0, 1000);

    $message = $repo->addMessage($conversationId, $meId, $content);
    if (!$message) {
        json_error('Conversation introuvable', 404);
    }

    $sender = $userRepo->getById($meId);
    if ($sender) {
        $message['sender'] = Users::toPublic($sender);
    }
    json_success([
        'message'      => $hydrateMessage($message),
        'conversation' => $hydrateConversation($conv),
    ], 201);
}

http_response_code(405);
header('Allow: GET, POST');
json_error('Méthode non autorisée', 405);
