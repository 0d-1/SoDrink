<?php
declare(strict_types=1);

namespace SoDrink\Domain;

require_once __DIR__ . '/../utils/secrets.php';

use SoDrink\Storage\JsonStore;
use function SoDrink\Utils\Secrets\encrypt_secret;
use function SoDrink\Utils\Secrets\decrypt_secret;

class UclSportAutomations
{
    private JsonStore $store;

    public function __construct(?string $file = null)
    {
        $file = $file ?: (realpath(__DIR__ . '/..') . '/../data/uclsport_automations.json');
        $this->store = new JsonStore($file);
    }

    public function all(): array
    {
        return $this->store->getAll();
    }

    public function forUser(int $userId): array
    {
        $items = $this->store->filter(fn ($i) => (int)($i['user_id'] ?? 0) === $userId);
        return array_map([self::class, 'toPublic'], $items);
    }

    public function getById(int $id): ?array
    {
        return $this->store->findById($id);
    }

    public function create(array $data): array
    {
        $record = [
            'user_id'      => (int)$data['user_id'],
            'ucl_username' => $data['ucl_username'],
            'ucl_password' => isset($data['ucl_password']) && $data['ucl_password'] !== null
                ? encrypt_secret($data['ucl_password'])
                : null,
            'sport'        => $data['sport'],
            'campus'       => $data['campus'],
            'session_date' => $data['session_date'],
            'time_slot'    => $data['time_slot'],
            'weekly'       => (bool)($data['weekly'] ?? false),
            'headless'     => (bool)($data['headless'] ?? false),
            'notes'        => $data['notes'] ?? null,
            'created_at'   => date('c'),
            'updated_at'   => date('c'),
            'last_run_at'  => $data['last_run_at'] ?? null,
        ];

        $stored = $this->store->append($record);
        return self::toPublic($stored);
    }

    public function update(int $id, array $fields): ?array
    {
        $record = $this->store->findById($id);
        if (!$record) {
            return null;
        }

        $payload = [];
        if (array_key_exists('ucl_username', $fields)) {
            $payload['ucl_username'] = $fields['ucl_username'];
        }
        if (array_key_exists('sport', $fields)) {
            $payload['sport'] = $fields['sport'];
        }
        if (array_key_exists('campus', $fields)) {
            $payload['campus'] = $fields['campus'];
        }
        if (array_key_exists('session_date', $fields)) {
            $payload['session_date'] = $fields['session_date'];
        }
        if (array_key_exists('time_slot', $fields)) {
            $payload['time_slot'] = $fields['time_slot'];
        }
        if (array_key_exists('weekly', $fields)) {
            $payload['weekly'] = (bool)$fields['weekly'];
        }
        if (array_key_exists('headless', $fields)) {
            $payload['headless'] = (bool)$fields['headless'];
        }
        if (array_key_exists('notes', $fields)) {
            $payload['notes'] = $fields['notes'];
        }
        if (array_key_exists('last_run_at', $fields)) {
            $payload['last_run_at'] = $fields['last_run_at'];
        }
        if (array_key_exists('ucl_password', $fields)) {
            $payload['ucl_password'] = $fields['ucl_password'] === null
                ? null
                : encrypt_secret((string)$fields['ucl_password']);
        }

        if (!$payload) {
            return self::toPublic($record);
        }

        $payload['updated_at'] = date('c');
        $this->store->updateById($id, $payload);
        $updated = $this->store->findById($id);
        return $updated ? self::toPublic($updated) : null;
    }

    public function delete(int $id): bool
    {
        return $this->store->deleteById($id);
    }

    public static function toPublic(array $record): array
    {
        return [
            'id'           => (int)($record['id'] ?? 0),
            'user_id'      => (int)($record['user_id'] ?? 0),
            'ucl_username' => $record['ucl_username'] ?? '',
            'sport'        => $record['sport'] ?? '',
            'campus'       => $record['campus'] ?? 'Louvain-la-Neuve',
            'session_date' => $record['session_date'] ?? '',
            'time_slot'    => $record['time_slot'] ?? '',
            'weekly'       => (bool)($record['weekly'] ?? false),
            'headless'     => (bool)($record['headless'] ?? false),
            'notes'        => $record['notes'] ?? null,
            'created_at'   => $record['created_at'] ?? null,
            'updated_at'   => $record['updated_at'] ?? null,
            'last_run_at'  => $record['last_run_at'] ?? null,
            'has_password' => !empty($record['ucl_password']),
        ];
    }

    /**
     * Retourne la configuration complète (mot de passe déchiffré) — à utiliser côté worker Python.
     */
    public static function toExecutable(array $record): array
    {
        return [
            'id'           => (int)($record['id'] ?? 0),
            'user_id'      => (int)($record['user_id'] ?? 0),
            'ucl_username' => $record['ucl_username'] ?? '',
            'ucl_password' => decrypt_secret($record['ucl_password'] ?? null) ?? '',
            'sport'        => $record['sport'] ?? '',
            'campus'       => $record['campus'] ?? 'Louvain-la-Neuve',
            'session_date' => $record['session_date'] ?? '',
            'time_slot'    => $record['time_slot'] ?? '',
            'weekly'       => (bool)($record['weekly'] ?? false),
            'headless'     => (bool)($record['headless'] ?? false),
            'notes'        => $record['notes'] ?? null,
            'created_at'   => $record['created_at'] ?? null,
            'updated_at'   => $record['updated_at'] ?? null,
            'last_run_at'  => $record['last_run_at'] ?? null,
        ];
    }
}

