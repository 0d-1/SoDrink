<?php
declare(strict_types=1);

namespace SoDrink\Domain;

use SoDrink\Storage\JsonStore;

class Events
{
    private JsonStore $store;

    public function __construct(?string $file = null)
    {
        $file = $file ?: (realpath(__DIR__ . '/..') . '/../data/events.json');
        $this->store = new JsonStore($file);
    }

    public function all(): array { return $this->store->getAll(); }
    public function getById(int $id): ?array { return $this->store->findById($id); }

    public function create(array $e): array
    {
        $rec = [
            'date'        => $e['date'],
            'lieu'        => $e['lieu'] ?? '',
            'theme'       => $e['theme'] ?? '',
            'description' => $e['description'] ?? '',
            'created_by'  => $e['created_by'] ?? null,
            'participants'=> [], // <= new
            'created_at'  => date('c'),
        ];
        return $this->store->append($rec);
    }

    public function update(int $id, array $fields): bool
    { return $this->store->updateById($id, $fields + ['id'=>$id] + ($this->getById($id) ?? [])); }

    public function delete(int $id): bool { return $this->store->deleteById($id); }

    public function nextUpcoming(): ?array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $upcoming = array_filter($this->all(), fn($e) => ($e['date'] ?? '') >= $today);
        usort($upcoming, fn($a,$b) => strcmp($a['date'],$b['date']));
        return $upcoming[0] ?? null;
    }

    public function listUpcoming(int $limit = 6): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $upcoming = array_filter($this->all(), fn($e) => ($e['date'] ?? '') >= $today);
        usort($upcoming, fn($a,$b) => strcmp($a['date'],$b['date']));
        return array_slice($upcoming, 0, $limit);
    }

    /* ---- Participants ---- */
    public function addParticipant(int $eventId, int $userId): bool
    {
        $ev = $this->getById($eventId); if (!$ev) return false;
        $ev['participants'] = array_values(array_unique(array_map('intval', array_merge($ev['participants'] ?? [], [$userId]))));
        return $this->store->updateById($eventId, $ev);
    }
    public function removeParticipant(int $eventId, int $userId): bool
    {
        $ev = $this->getById($eventId); if (!$ev) return false;
        $ev['participants'] = array_values(array_filter($ev['participants'] ?? [], fn($id) => (int)$id !== $userId));
        return $this->store->updateById($eventId, $ev);
    }
}
