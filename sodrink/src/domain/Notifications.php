<?php
declare(strict_types=1);

namespace SoDrink\Domain;

use SoDrink\Storage\JsonStore;

class Notifications
{
    private const RETENTION_DAYS = 7;
    private const SECONDS_PER_DAY = 86400;

    private JsonStore $store;
    private Users $users;
    /** @var array<int,array>|null */
    private ?array $cache = null;

    public function __construct(?string $file = null, ?Users $users = null)
    {
        $file = $file ?: (realpath(__DIR__ . '/..') . '/../data/notifications.json');
        $this->store = new JsonStore($file);
        $this->users = $users ?? new Users();
    }

    private function invalidateCache(): void
    {
        $this->cache = null;
    }

    /**
     * Retourne toutes les notifications en appliquant la rétention automatique.
     *
     * @return array<int,array>
     */
    private function loadAll(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $all = $this->store->getAll();
        $cutoff = time() - (self::RETENTION_DAYS * self::SECONDS_PER_DAY);
        $changed = false;
        $kept = [];

        foreach ($all as $notif) {
            $createdAt = $notif['created_at'] ?? null;
            $createdTs = is_string($createdAt) ? strtotime($createdAt) : false;
            if (!empty($notif['read']) && $createdTs !== false && $createdTs < $cutoff) {
                $changed = true;
                continue;
            }
            $kept[] = $notif;
        }

        if ($changed) {
            $this->store->saveAll($kept);
        }

        $this->cache = $kept;
        return $this->cache;
    }

    private function enforceRetention(): void
    {
        $this->invalidateCache();
        $this->loadAll();
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

        $created = $this->store->append($rec);
        $this->invalidateCache();
        return $created;
    }

    /** @return array<int,array> */
    public function listForUser(int $userId, int $limit = 20): array
    {
        $all = array_values(array_filter($this->loadAll(), fn($n) => (int)($n['user_id'] ?? 0) === $userId));
        usort($all, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        return array_slice($all, 0, $limit);
    }

    public function countUnread(int $userId): int
    {
        $count = 0;
        foreach ($this->loadAll() as $n) {
            if ((int)($n['user_id'] ?? 0) === $userId && empty($n['read'])) {
                $count++;
            }
        }
        return $count;
    }

    public function markRead(int $userId, int $id): bool
    {
        $n = $this->store->findById($id);
        if (!$n || (int)($n['user_id'] ?? 0) !== $userId) {
            return false;
        }
        $n['read'] = true;
        $ok = $this->store->updateById($id, $n);
        if ($ok) {
            $this->enforceRetention();
        }
        return $ok;
    }

    public function markAllRead(int $userId): void
    {
        $all = $this->store->getAll();
        foreach ($all as &$n) {
            if ((int)($n['user_id'] ?? 0) === $userId) {
                $n['read'] = true;
            }
        }
        unset($n);
        $this->store->saveAll($all);
        $this->enforceRetention();
    }
}
