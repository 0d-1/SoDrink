<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once $GLOBALS['SODRINK_ROOT'] . '/src/security/auth.php';

use function SoDrink\Security\require_admin;
use function SoDrink\Security\require_csrf;

/**
 * /data/sections.json
 * [
 *   { "id":1, "key":"next-event", "title":"Prochaine Soirée", "enabled":true,  "order":1 },
 *   { "id":2, "key":"gallery",    "title":"Galerie",          "enabled":true,  "order":2 },
 *   { "id":3, "key":"torpille",   "title":"Torpille",         "enabled":true,  "order":3 }
 * ]
 */

$file = $GLOBALS['SODRINK_ROOT'] . '/data/sections.json';
$dataDir= dirname($file);

$defaults = [
  [ 'key' => 'next-event', 'title' => 'Prochaine Soirée', 'enabled' => true,  'order' => 1 ],
  [ 'key' => 'gallery',    'title' => 'Galerie',          'enabled' => true,  'order' => 2 ],
  [ 'key' => 'torpille',   'title' => 'Torpille',         'enabled' => true,  'order' => 3 ],
];

function normalize_with_injection(string $file, array $defaults): array {
    // Lire existant (peut être vide/invalide)
    $items = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $j = json_decode((string)$raw, true);
        if (is_array($j)) $items = $j;
    }

    // Indexer par key et récupérer id/order/title/enabled
    $byKey = [];
    foreach ($items as $it) {
        if (!is_array($it) || empty($it['key'])) continue;
        $id    = (int)($it['id'] ?? 0);
        $title = (string)($it['title'] ?? $it['key']);
        $en    = (bool)($it['enabled'] ?? false);
        $ord   = (int)($it['order'] ?? 0);
        $byKey[(string)$it['key']] = [
            'id'      => $id,       // on renumérote plus bas si <=0 ou dupliqué
            'key'     => (string)$it['key'],
            'title'   => $title,
            'enabled' => $en,
            'order'   => $ord,
        ];
    }

    // Injecter les defaults manquants (y compris torpille)
    foreach ($defaults as $d) {
        $k = (string)$d['key'];
        if (!isset($byKey[$k])) {
            $byKey[$k] = [
                'id'      => 0, // renuméroté plus bas
                'key'     => $k,
                'title'   => (string)$d['title'],
                'enabled' => (bool)$d['enabled'],
                'order'   => (int)($d['order'] ?? 0),
            ];
        } else {
            if (empty($byKey[$k]['title'])) $byKey[$k]['title'] = (string)$d['title'];
        }
    }

    // Trier par order, puis renuméroter proprement **id** ET **order** à partir de 1
    $items = array_values($byKey);
    usort($items, fn($a,$b)=>($a['order']<=>$b['order']) ?: strcmp($a['key'],$b['key']));
    $i = 1;
    foreach ($items as &$it) {
        $it['id']    = $i;   // <= 1..N garanti (corrige les id à 0)
        $it['order'] = $i;
        $i++;
    }

    return $items;
}

function persist_sections_file(string $file, array $items): void {
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $ok = @file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    if ($ok === false) {
        http_response_code(500);
        json_error("Impossible d'écrire $file");
    }
}

require_admin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $sections = normalize_with_injection($file, $defaults);
    // Persister afin de corriger/renuméroter au passage
    if (is_dir($dataDir)) @file_put_contents($file, json_encode($sections, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    json_success(['sections' => $sections]);
}

if ($method === 'PUT') {
    require_csrf();
    $data = json_decode(file_get_contents('php://input'), true);

    $incoming = $data['items'] ?? ($data['sections'] ?? null);
    if (!is_array($incoming)) json_error('Payload invalide (items attendu)', 400);

    $current = normalize_with_injection($file, $defaults);
    $byId = [];
    $byKey = [];
    foreach ($current as $c) { $byId[(int)$c['id']] = $c; $byKey[(string)$c['key']] = $c; }

    // Appliquer les modifs sur ids connus (ou par key si id invalide)
    foreach ($incoming as $it) {
        if (!is_array($it)) continue;
        $id  = (int)($it['id'] ?? 0);
        $key = isset($it['key']) ? (string)$it['key'] : '';
        if (!$id || !isset($byId[$id])) {
            if ($key !== '' && isset($byKey[$key])) {
                $id = (int)$byKey[$key]['id'];
            } else {
                continue;
            }
        }

        $rec = $byId[$id];
        if (array_key_exists('key', $it))     $rec['key']     = (string)$it['key'];
        if (array_key_exists('title', $it))   $rec['title']   = (string)$it['title'];
        if (array_key_exists('enabled', $it)) $rec['enabled'] = (bool)$it['enabled'];
        if (array_key_exists('order', $it))   $rec['order']   = (int)$it['order'];
        $byId[$id] = $rec;
    }

    // Renuméroter proprement puis persister
    $items = array_values($byId);
    usort($items, fn($a,$b)=>$a['order']<=>$b['order']);
    $i=1; foreach ($items as &$it) { $it['order']=$i++; $it['id']=$it['order']; }

    persist_sections_file($file, $items);
    json_success(['sections' => $items, 'updated' => true]);
}

http_response_code(405);
header('Allow: GET, PUT');
json_error('Méthode non autorisée', 405);
