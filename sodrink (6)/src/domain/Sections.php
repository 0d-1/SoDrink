<?php
// src/domain/Sections.php

declare(strict_types=1);

namespace SoDrink\Domain;

use SoDrink\Storage\JsonStore;

class Sections
{
    private JsonStore $store;
    public function __construct(?string $file = null)
    {
        $file = $file ?: (realpath(__DIR__ . '/..') . '/../data/sections.json');
        $this->store = new JsonStore($file);
        $this->ensureDefaults();
    }

    private function ensureDefaults(): void
    {
        $all = $this->store->getAll();
        if (!$all) {
            $all = [
                ['id'=>1,'key'=>'next-event','title'=>'Prochaine Soirée','enabled'=>true,'order'=>1],
                ['id'=>2,'key'=>'gallery','title'=>'Galerie','enabled'=>true,'order'=>2],
            ];
            $this->store->saveAll($all);
        }
    }

    public function all(): array
    {
        $all = $this->store->getAll();
        usort($all, fn($a,$b) => ($a['order']??0) <=> ($b['order']??0));
        return $all;
    }

    public function updateMany(array $items): void
    {
        // items: [{id,key,enabled,order,title?}]
        $current = $this->store->getAll();
        $byId = [];
        foreach ($current as $c) { $byId[(int)$c['id']] = $c; }
        foreach ($items as $it) {
            $id = (int)($it['id'] ?? 0);
            if (!$id || !isset($byId[$id])) continue;
            $record = $byId[$id];
            foreach (['key','title'] as $k) if (isset($it[$k])) $record[$k] = (string)$it[$k];
            if (isset($it['enabled'])) $record['enabled'] = (bool)$it['enabled'];
            if (isset($it['order']))   $record['order']   = (int)$it['order'];
            $this->store->updateById($id, $record);
        }
    }
}