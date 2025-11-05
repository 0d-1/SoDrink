<?php
// public/profile.php

declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(302);
    header('Location: ' . WEB_BASE . '/login.php');
    exit;
}

$title = 'Mon profil — SoDrink';
include __DIR__ . '/../views/partials/head.php';
?>
<body class="profile-page">
<?php include __DIR__ . '/../views/partials/header.php'; ?>
<main class="container profile-container">
  <section class="card profile-hero">
    <div class="profile-banner-wrapper">
      <div id="profile-banner" class="profile-banner" role="img" aria-label="Bannière de profil"></div>
      <form id="form-banner" class="banner-upload" enctype="multipart/form-data">
        <label class="btn btn-outline btn-sm banner-picker">
          <input type="file" name="banner" accept="image/*" hidden>
          Choisir une bannière
        </label>
        <button class="btn btn-sm" type="submit">Mettre à jour</button>
      </form>
    </div>
    <div class="profile-hero-body">
      <div class="profile-hero-avatar">
        <img id="profile-avatar" class="avatar-xl"
             src="<?= htmlspecialchars($_SESSION['avatar'] ?? (WEB_BASE . '/assets/img/ui/avatar-default.svg')); ?>"
             alt="Avatar du profil">
        <form id="form-avatar" class="avatar-upload" enctype="multipart/form-data">
          <label class="btn btn-outline btn-sm">
            <input type="file" name="avatar" accept="image/*" hidden required>
            Changer d’avatar
          </label>
          <button class="btn btn-sm" type="submit">Enregistrer</button>
        </form>
      </div>
      <div class="profile-hero-text">
        <h1>Mon espace</h1>
        <p class="muted">Personnalise ton profil, raconte ton histoire et partage tes liens favoris.</p>
      </div>
    </div>
  </section>

  <div class="profile-settings-grid">
    <section class="card profile-card">
      <h2>Informations principales</h2>
      <form id="form-profile" class="form profile-form">
        <div class="form-grid">
          <label>Pseudo
            <input type="text" name="pseudo" minlength="3" maxlength="20" required>
          </label>
          <label>Prénom
            <input type="text" name="prenom" maxlength="40" required>
          </label>
          <label>Nom
            <input type="text" name="nom" maxlength="40" required>
          </label>
        </div>
        <div class="form-grid">
          <label>Instagram
            <input type="text" name="instagram" placeholder="@pseudo">
          </label>
          <label>Email de contact
            <input type="email" name="email" placeholder="toi@mail.com">
          </label>
          <label>Ville / région
            <input type="text" name="location" maxlength="80" placeholder="Paris, Bruxelles…">
          </label>
        </div>
        <div class="form-grid">
          <label>Site web principal
            <input type="url" name="website" placeholder="https://monsite.fr">
          </label>
          <label>Statut relationnel
            <select name="relationship_status">
              <option value="none">(Ne pas afficher)</option>
              <option value="single">Célibataire</option>
              <option value="relationship">En couple</option>
              <option value="married">Marié·e</option>
              <option value="complicated">C’est compliqué</option>
              <option value="hidden">Préférer ne pas dire</option>
            </select>
          </label>
          <label>Nouveau mot de passe
            <input type="password" name="password" minlength="8" placeholder="••••••••">
          </label>
        </div>
        <label>À propos de toi
          <textarea name="bio" rows="4" maxlength="600" placeholder="Ajoute une courte biographie, tes passions, etc."></textarea>
        </label>
        <div class="profile-links" id="profile-links"></div>
        <div class="profile-links-actions">
          <button type="button" class="btn btn-sm btn-outline" id="add-link">Ajouter un lien</button>
          <span class="muted">Jusqu’à 5 liens personnalisés (Spotify, LinkedIn, portfolio…).</span>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Enregistrer les modifications</button>
        </div>
      </form>
    </section>

    <section class="card profile-card" id="profile-preview" aria-live="polite">
      <h2>Aperçu du profil</h2>
      <div class="preview-banner" id="preview-banner"></div>
      <div class="preview-body">
        <img id="preview-avatar" class="avatar-lg" src="<?= WEB_BASE ?>/assets/img/ui/avatar-default.svg" alt="Avatar aperçu">
        <div class="preview-names">
          <h3 id="preview-pseudo">—</h3>
          <p class="muted" id="preview-fullname">—</p>
        </div>
      </div>
      <p id="preview-bio" class="preview-bio muted">Complète ta bio pour la partager avec tes amis.</p>
      <div class="preview-meta" id="preview-meta"></div>
      <div class="preview-links" id="preview-links"></div>
    </section>
  </div>
</main>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>

<script>window.SODRINK_ME = Object.assign({}, window.SODRINK_ME || {}, {
  id: <?= (int)($_SESSION['user_id'] ?? 0); ?>,
  pseudo: <?= json_encode($_SESSION['pseudo'] ?? ''); ?>,
  avatar: <?= json_encode($_SESSION['avatar'] ?? (WEB_BASE . '/assets/img/ui/avatar-default.svg')); ?>
});</script>
<!-- JS (profile.js importe api.js) -->
<script type="module" src="<?= WEB_BASE ?>/assets/js/profile.js"></script>
</body>
</html>
