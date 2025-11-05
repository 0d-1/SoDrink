<?php
// src/domain/Conversations.php
// Gestion des conversations privées et de groupe

declare(strict_types=1);

namespace SoDrink\Domain;

use SoDrink\Storage\JsonStore;

class Conversations
{
    private JsonStore $store;

    public function __construct(?string $file = null)
    {
        $file = $file ?: (realpath(__DIR__ . '/..') . '/../data/conversations.json');
        $this->store = new JsonStore($file);
    }

    public function getById(int $id): ?array
    {
        return $this->store->findById($id) ?: null;
    }

    public function listForUser(int $userId): array
    {
        $userId = (int)$userId;
        $all = $this->store->getAll();
        $filtered = array_values(array_filter($all, function (array $conv) use ($userId) {
            $participants = array_map('intval', $conv['participants'] ?? []);
            return in_array($userId, $participants, true);
        }));

        usort($filtered, function (array $a, array $b) {
            $aTime = $a['last_message_at'] ?? $a['updated_at'] ?? $a['created_at'] ?? '';
            $bTime = $b['last_message_at'] ?? $b['updated_at'] ?? $b['created_at'] ?? '';
            return strcmp($bTime, $aTime);
        });

        return $filtered;
    }

    public function create(array $participants, int $creatorId, ?string $title = null): array
    {
        $normalized = $this->normalizeParticipants($participants, $creatorId);
        $now = date('c');
        $record = [
            'participants'    => $normalized,
            'created_by'      => (int)$creatorId,
            'created_at'      => $now,
            'updated_at'      => $now,
            'last_message_at' => null,
            'title'           => $title ? trim($title) : null,
            'messages'        => [],
        ];
        return $this->store->append($record);
    }

    public function updateMeta(int $id, array $fields): bool
    {
        $current = $this->store->findById($id);
        if (!$current) {
            return false;
        }
        $current = array_merge($current, $fields);
        $current['id'] = $id;
        return $this->store->updateById($id, $current);
    }

    public function addMessage(int $conversationId, int $senderId, string $content): ?array
    {
        $conversation = $this->store->findById($conversationId);
        if (!$conversation) {
            return null;
        }
        $messages = $conversation['messages'] ?? [];
        $messageId = $this->nextMessageId($messages);
        $message = [
            'id'         => $messageId,
            'sender_id'  => (int)$senderId,
            'content'    => $content,
            'created_at' => date('c'),
        ];
        $messages[] = $message;
        $conversation['messages'] = $messages;
        $conversation['last_message_at'] = $message['created_at'];
        $conversation['updated_at'] = $message['created_at'];
        $this->store->updateById($conversationId, $conversation);
        return $message;
    }

    private function normalizeParticipants(array $participants, int $creatorId): array
    {
        $normalized = [];
        foreach ($participants as $p) {
            $id = (int)$p;
            if ($id <= 0) {
                continue;
            }
            $normalized[$id] = $id;
        }
        $normalized[(int)$creatorId] = (int)$creatorId;
        return array_values($normalized);
    }

    private function nextMessageId(array $messages): int
    {
        $max = 0;
        foreach ($messages as $msg) {
            $id = (int)($msg['id'] ?? 0);
            if ($id > $max) {
                $max = $id;
            }
        }
        return $max + 1;
    }
}
