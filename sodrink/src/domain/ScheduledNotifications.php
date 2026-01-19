<?php
declare(strict_types=1);

namespace SoDrink\Domain;

use SoDrink\Storage\JsonStore;

class ScheduledNotifications
{
    private JsonStore $store;

    public function __construct(?string $file = null)
    {
        $file = $file ?: (realpath(__DIR__ . '/..') . '/../data/scheduled_notifications.json');
        $this->store = new JsonStore($file);
    }

    /** @return array<int,array> */
    public function listAll(): array
    {
        $all = $this->store->getAll();
        usort($all, fn($a, $b) => strcmp((string)($b['scheduled_for'] ?? ''), (string)($a['scheduled_for'] ?? '')));
        return $all;
    }

    public function create(array $data): array
    {
        return $this->store->append($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->store->updateById($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->store->deleteById($id);
    }

    public function findById(int $id): ?array
    {
        return $this->store->findById($id);
    }
}
