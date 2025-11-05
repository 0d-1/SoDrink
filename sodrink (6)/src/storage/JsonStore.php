<?php
// src/storage/JsonStore.php
// Accès fichiers JSON (CRUD) avec verrous et écriture atomique

declare(strict_types=1);

namespace SoDrink\Storage;

class JsonStore
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
        $dir = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!file_exists($file)) {
            // Initialise fichier vide (tableau JSON)
            $this->atomicWrite($file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Retourne tout le contenu sous forme de tableau PHP.
     */
    public function getAll(): array
    {
        $fp = fopen($this->file, 'r');
        if (!$fp) return [];
        try {
            flock($fp, LOCK_SH);
            $json = stream_get_contents($fp) ?: '[]';
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Sauvegarde l'ensemble du tableau (écrasement).
     */
    public function saveAll(array $data): void
    {
        $json = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->atomicWrite($this->file, $json);
    }

    /**
     * Ajoute un enregistrement et retourne l'objet ajouté (avec id).
     */
    public function append(array $record): array
    {
        $all = $this->getAll();
        $record['id'] = $this->nextId($all);
        $all[] = $record;
        $this->saveAll($all);
        return $record;
    }

    /**
     * Met à jour un enregistrement par id. Retourne true si modifié.
     */
    public function updateById(int $id, array $newData): bool
    {
        $all = $this->getAll();
        $updated = false;
        foreach ($all as &$item) {
            if ((int)($item['id'] ?? 0) === $id) {
                $item = array_merge($item, $newData, ['id' => $id]);
                $updated = true;
                break;
            }
        }
        if ($updated) $this->saveAll($all);
        return $updated;
    }

    /**
     * Supprime un enregistrement par id. Retourne true si supprimé.
     */
    public function deleteById(int $id): bool
    {
        $all = $this->getAll();
        $countBefore = count($all);
        $all = array_values(array_filter($all, fn($i) => (int)($i['id'] ?? 0) !== $id));
        if (count($all) !== $countBefore) {
            $this->saveAll($all);
            return true;
        }
        return false;
    }

    /**
     * Trouve un enregistrement par id.
     */
    public function findById(int $id): ?array
    {
        $all = $this->getAll();
        foreach ($all as $i) {
            if ((int)($i['id'] ?? 0) === $id) return $i;
        }
        return null;
    }

    /**
     * Filtre via callback utilisateur.
     */
    public function filter(callable $predicate): array
    {
        return array_values(array_filter($this->getAll(), $predicate));
    }

    /**
     * Écriture atomique (tmp + rename), protège contre corruption.
     */
    private function atomicWrite(string $file, string $contents): void
    {
        $tmp = $file . '.tmp';
        $fp = fopen($tmp, 'c');
        if (!$fp) throw new \RuntimeException('Impossible d\'ouvrir le fichier temporaire');
        try {
            // Verrouillage exclusif pendant l'écriture
            if (!flock($fp, LOCK_EX)) throw new \RuntimeException('Lock impossible');
            // Tronque et écrit
            ftruncate($fp, 0);
            fwrite($fp, $contents);
            fflush($fp);
            // fsync sur certains systèmes (best effort)
            if (function_exists('fsync')) @fsync($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
        // Remplace le fichier cible
        if (!@rename($tmp, $file)) {
            // Windows fallback
            @unlink($file);
            if (!@rename($tmp, $file)) {
                throw new \RuntimeException('Écriture atomique échouée');
            }
        }
        @chmod($file, 0664);
    }

    private function nextId(array $all): int
    {
        $max = 0;
        foreach ($all as $i) {
            $id = (int)($i['id'] ?? 0);
            if ($id > $max) $max = $id;
        }
        return $max + 1;
    }
}