<?php
declare(strict_types=1);

namespace SoDrink\Domain;

use SoDrink\Storage\JsonStore;

class Notifications
{
    private JsonStore $store;
    private Users $users;

    public function __construct(?string $file = null, ?Users $users = null)
    {
        $file = $file ?: (realpath(__DIR__ . '/..') . '/../data/notifications.json');
        $this->store = new JsonStore($file);
        $this->users = $users ?? new Users();
    }

    public function send(int $userId, string $type, string $message, string $link = ''): ?array
    {
        $user = $this->users->getById($userId);
        if (!$user) {
            return null;
        }
        if (!NotificationPreferences::userAllows($user, $type)) {
            return null;
        }

        $rec = [
            'user_id'    => $userId,
            'type'       => $type,
            'message'    => $message,
            'link'       => $link,
            'read'       => false,
            'created_at' => date('c'),
        ];
        return $this->store->append($rec);
    }

    /** @return array<int,array> */
    public function listForUser(int $userId, int $limit = 20): array
    {
        $all = array_values(array_filter($this->store->getAll(), fn($n) => (int)$n['user_id'] === $userId));
        usort($all, fn($a,$b) => strcmp($b['created_at'], $a['created_at']));
        return array_slice($all, 0, $limit);
    }

    public function countUnread(int $userId): int
    {
        $c = 0;
        foreach ($this->store->getAll() as $n) if ((int)$n['user_id'] === $userId && empty($n['read'])) $c++;
        return $c;
    }

    public function markRead(int $userId, int $id): bool
    {
        $n = $this->store->findById($id);
        if (!$n || (int)$n['user_id'] !== $userId) return false;
        $n['read'] = true;
        return $this->store->updateById($id, $n);
    }

    public function markAllRead(int $userId): void
    {
        $all = $this->store->getAll();
        foreach ($all as &$n) if ((int)$n['user_id'] === $userId) $n['read'] = true;
        $this->store->saveAll($all);
    }
}
