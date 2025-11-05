<?php
// src/domain/Users.php
declare(strict_types=1);

namespace SoDrink\Domain;

use SoDrink\Storage\JsonStore;

class Users
{
    private JsonStore $store;

    public function __construct(?string $file = null)
    {
        $file = $file ?: (realpath(__DIR__ . '/..') . '/../data/users.json');
        $this->store = new JsonStore($file);
    }

    /** Retourne tous les utilisateurs (tableau d'assoc) */
    public function getAll(): array
    {
        return $this->store->getAll();
    }

    /** Cherche par id */
    public function getById(int $id): ?array
    {
        return $this->store->findById($id) ?: null;
    }

    /** Cherche par pseudo (insensible à la casse) */
    public function findByPseudo(string $pseudo): ?array
    {
        $pseudo = mb_strtolower($pseudo);
        foreach ($this->getAll() as $u) {
            if (mb_strtolower((string)($u['pseudo'] ?? '')) === $pseudo) return $u;
        }
        return null;
    }

    /** Crée un utilisateur (retour = enregistrement complet avec id) */
    public function create(array $u): array
    {
        $rec = [
            'pseudo'          => (string)($u['pseudo'] ?? ''),
            'prenom'          => (string)($u['prenom'] ?? ''),
            'nom'             => (string)($u['nom'] ?? ''),
            'instagram'       => $u['instagram'] ?? null,
            'pass_hash'       => (string)($u['pass_hash'] ?? ''),
            'role'            => in_array(($u['role'] ?? 'user'), ['user','admin'], true) ? $u['role'] : 'user',
            'avatar'          => $u['avatar'] ?? null,
            'created_at'      => date('c'),
            'remember_tokens' => [],
        ];
        return $this->store->append($rec);
    }

    /** Met à jour (retourne true si OK) */
    public function update(int $id, array $fields): bool
    {
        $cur = $this->store->findById($id);
        if (!$cur) return false;

        foreach (['pseudo','prenom','nom','instagram','pass_hash','role','avatar','remember_tokens'] as $k) {
            if (array_key_exists($k, $fields)) $cur[$k] = $fields[$k];
        }
        return $this->store->updateById($id, $cur);
    }

    public function delete(int $id): bool
    {
        return $this->store->deleteById($id);
    }

    /** Ajoute un jeton "remember" au profil */
    public function addRememberToken(int $id, array $token): bool
    {
        $cur = $this->store->findById($id);
        if (!$cur) return false;
        $tokens = $cur['remember_tokens'] ?? [];
        $tokens[] = $token;
        $cur['remember_tokens'] = array_values($tokens);
        return $this->store->updateById($id, $cur);
    }

    /** Retire un jeton par selector */
    public function removeRememberToken(int $id, string $selector): bool
    {
        $cur = $this->store->findById($id);
        if (!$cur) return false;
        $cur['remember_tokens'] = array_values(array_filter(($cur['remember_tokens'] ?? []), function($t) use ($selector){
            return ($t['selector'] ?? '') !== $selector;
        }));
        return $this->store->updateById($id, $cur);
    }

    /** Ne renvoie que les champs “publics” (+ avatar) */
    public static function toPublic(array $u): array
    {
        return [
            'id'        => (int)($u['id'] ?? 0),
            'pseudo'    => (string)($u['pseudo'] ?? ''),
            'prenom'    => (string)($u['prenom'] ?? ''),
            'nom'       => (string)($u['nom'] ?? ''),
            'instagram' => $u['instagram'] ?? null,
            'role'      => (string)($u['role'] ?? 'user'),
            'avatar'    => $u['avatar'] ?? null,
        ];
    }
}
