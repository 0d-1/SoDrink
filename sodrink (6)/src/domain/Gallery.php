<?php
declare(strict_types=1);

namespace SoDrink\Domain;

use SoDrink\Storage\JsonStore;

class Gallery
{
    private JsonStore $store;

    public function __construct(?string $file = null)
    {
        $file = $file ?: (realpath(__DIR__ . '/..') . '/../data/gallery.json');
        $this->store = new JsonStore($file);
    }

    public function all(): array { return $this->store->getAll(); }
    public function getById(int $id): ?array { return $this->store->findById($id); }

    public function add(array $g): array
    {
        $rec = [
            'path'        => $g['path'] ?? '',
            'title'       => $g['title'] ?? '',
            'description' => $g['description'] ?? '',
            'author_id'   => $g['author_id'] ?? null,
            'likes'       => [],        // <= new
            'comments'    => [],        // <= new
            'created_at'  => date('c'),
        ];
        return $this->store->append($rec);
    }

    public function update(int $id, array $fields): bool
    {
        $cur = $this->getById($id); if (!$cur) return false;
        foreach (['path','title','description','likes','comments'] as $k) if (array_key_exists($k, $fields)) $cur[$k] = $fields[$k];
        return $this->store->updateById($id, $cur);
    }

    public function delete(int $id): bool { return $this->store->deleteById($id); }

    /* ---- Likes ---- */
    public function toggleLike(int $id, int $userId): array
    {
        $g = $this->getById($id); if (!$g) return [false,false];
        $likes = array_map('intval', $g['likes'] ?? []);
        $liked = in_array($userId, $likes, true);
        if ($liked) $likes = array_values(array_filter($likes, fn($u)=>$u!==$userId));
        else $likes[] = $userId;
        $g['likes'] = $likes;
        $this->store->updateById($id, $g);
        return [true, !$liked];
    }

    /* ---- Comments ---- */
    public function addComment(int $id, array $c): ?array
    {
        $g = $this->getById($id); if (!$g) return null;
        $comments = $g['comments'] ?? [];
        $rec = [
            'id'         => ($comments ? (max(array_column($comments,'id')) + 1) : 1),
            'user_id'    => (int)$c['user_id'],
            'text'       => (string)$c['text'],
            'created_at' => date('c'),
        ];
        $comments[] = $rec;
        $g['comments'] = $comments;
        $this->store->updateById($id, $g);
        return $rec;
    }

    public function deleteComment(int $id, int $commentId): bool
    {
        $g = $this->getById($id); if (!$g) return false;
        $g['comments'] = array_values(array_filter($g['comments'] ?? [], fn($c) => (int)$c['id'] !== $commentId));
        return $this->store->updateById($id, $g);
    }
}
