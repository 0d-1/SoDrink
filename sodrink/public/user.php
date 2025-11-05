<?php
// user.php — Profil public d'un utilisateur

declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$title = 'Profil — SoDrink';
include __DIR__ . '/../views/partials/head.php';
?>
<body class="profile-public">
<?php include __DIR__ . '/../views/partials/header.php'; ?>
<main class="container profile-container">
  <section class="card profile-hero" id="user-card">
    <div class="profile-banner-wrapper" aria-hidden="true">
      <div id="pub-banner" class="profile-banner"></div>
    </div>
    <div class="profile-hero-body">
      <div class="profile-hero-avatar">
        <img id="pub-avatar" class="avatar-xl" src="<?= WEB_BASE ?>/assets/img/ui/avatar-default.svg" alt="Avatar">
      </div>
      <div class="profile-hero-text">
        <h1 id="user-title">Profil</h1>
        <p class="muted" id="pub-fullname">—</p>
        <div class="profile-meta" id="pub-meta"></div>
        <div class="profile-actions">
          <a class="btn btn-sm btn-outline" id="btn-message" href="<?= WEB_BASE ?>/chat.php">Envoyer un message</a>
        </div>
      </div>
    </div>
  </section>

  <section class="card profile-card">
    <h2>À propos</h2>
    <p id="pub-bio" class="muted">Cet utilisateur n’a pas encore partagé d’informations.</p>
    <div class="profile-links-display">
      <h3>Liens</h3>
      <div id="pub-links" class="preview-links"></div>
    </div>
    <div class="profile-contact" id="pub-contact"></div>
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
