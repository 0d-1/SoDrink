<?php
// public/index.php — Accueil (robuste à une config vide)
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/security/auth.php';
require_once __DIR__ . '/../src/domain/Torpille.php';

use SoDrink\Domain\Torpille;

$title = 'SoDrink';
include __DIR__ . '/../views/partials/head.php';
include __DIR__ . '/../views/partials/header.php';

/** ---------- Charger config sections (avec fallback sûr) ---------- */
$sectionsFile = __DIR__ . '/../data/sections.json';
$defaults = [
  [ 'key' => 'next-event', 'title' => 'Prochaine Soirée', 'enabled' => true,  'order' => 1 ],
  [ 'key' => 'gallery',    'title' => 'Galerie',          'enabled' => true,  'order' => 2 ],
  [ 'key' => 'torpille',   'title' => 'Torpille',         'enabled' => false, 'order' => 3 ],
];

// Lire json (peut être vide/corrompu)
$sections = $defaults;
if (is_file($sectionsFile)) {
  $raw = @file_get_contents($sectionsFile);
  $j = json_decode((string)$raw, true);
  if (is_array($j) && count($j)) $sections = $j;
}

// S’assurer que les clés connues existent
$haveKeys = array_column($sections, 'key');
foreach ($defaults as $d) {
  if (!in_array($d['key'], $haveKeys, true)) $sections[] = $d;
}

// Trier par order, puis fallback
usort($sections, fn($a,$b)=>((int)($a['order']??999))<=>((int)($b['order']??999)));

// Si tout est désactivé (ou mal lu), fallback sur les 2 sections de base
$enabledCount = 0;
foreach ($sections as $s) if (!empty($s['enabled'])) $enabledCount++;
if ($enabledCount === 0) {
  $sections = [
    [ 'key' => 'next-event', 'title' => 'Prochaine Soirée', 'enabled' => true, 'order' => 1 ],
    [ 'key' => 'gallery',    'title' => 'Galerie',          'enabled' => true, 'order' => 2 ],
  ];
}

/** ---------- Torpille: si torpillé, afficher uniquement Torpille ---------- */
$logged = isset($_SESSION['user_id']);
$isTorpille = false;
if ($logged) {
  $tor = new Torpille();
  $isTorpille = ((int)($tor->currentUserId() ?? 0) === (int)$_SESSION['user_id']);
}
?>
<main class="container">
  <?php if ($isTorpille): ?>
    <?php include __DIR__ . '/sections/torpille.php'; ?>
  <?php else: ?>
    <?php foreach ($sections as $s): ?>
      <?php if (empty($s['enabled'])) continue; ?>
      <?php if ($s['key'] === 'next-event')  include __DIR__ . '/sections/next-event.php'; ?>
      <?php if ($s['key'] === 'gallery')     include __DIR__ . '/sections/gallery.php'; ?>
      <?php if ($s['key'] === 'torpille')    include __DIR__ . '/sections/torpille.php'; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/../views/partials/modals.php'; ?>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
<script type="module" src="<?= WEB_BASE ?>/assets/js/app.js"></script>
<script type="module" src="<?= WEB_BASE ?>/assets/js/auth.js"></script>
