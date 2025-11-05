<?php
// user.php — Profil public d'un utilisateur
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$title = 'Profil — SoDrink';
include __DIR__ . '/../views/partials/head.php';
?>
<body>
<?php include __DIR__ . '/../views/partials/header.php'; ?>
<main class="container">
  <section class="card" id="user-card">
    <h2 id="user-title">Profil</h2>
    <div class="profile-grid">
      <div class="avatar-panel">
        <img id="pub-avatar" class="avatar-xl" src="<?= WEB_BASE ?>/assets/img/ui/avatar-default.svg" alt="avatar">
      </div>
      <div class="form">
        <div><strong>Pseudo :</strong> <span id="pub-pseudo">—</span></div>
        <div><strong>Nom :</strong> <span id="pub-nom">—</span></div>
        <div><strong>Prénom :</strong> <span id="pub-prenom">—</span></div>
        <div><strong>Instagram :</strong> <span id="pub-ig">—</span></div>
      </div>
    </div>
  </section>

  <section class="section card">
    <h3>Photos publiées</h3>
    <div class="gallery-grid" id="user-gallery"></div>
    <div class="pagination" id="user-gallery-pagination"></div>
  </section>
</main>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
<script type="module" src="<?= WEB_BASE ?>/assets/js/user.js"></script>
</body>
</html>
