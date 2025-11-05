<?php
// public/profile.php

declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$title = 'Mon profil — SoDrink';
include __DIR__ . '/../views/partials/head.php';
?>
<body>
<?php include __DIR__ . '/../views/partials/header.php'; ?>
<main class="container">
  <section class="card">
    <h2>Mon Profil</h2>
    <div class="profile-grid">
      <form id="form-profile" class="form">
        <label>Pseudo
          <input type="text" name="pseudo" minlength="3" maxlength="20" required>
        </label>
        <label>Prénom
          <input type="text" name="prenom" maxlength="40" required>
        </label>
        <label>Nom
          <input type="text" name="nom" maxlength="40" required>
        </label>
        <label>Instagram (optionnel)
          <input type="text" name="instagram" placeholder="@pseudo">
        </label>
        <label>Nouveau mot de passe (optionnel)
          <input type="password" name="password" minlength="8" placeholder="******">
        </label>
        <button class="btn btn-primary" type="submit">Enregistrer</button>
      </form>

      <div class="avatar-panel">
        <img id="profile-avatar" class="avatar-xl"
             src="<?= htmlspecialchars($_SESSION['avatar'] ?? (WEB_BASE . '/assets/img/ui/avatar-default.svg')); ?>"
             alt="avatar">
        <form id="form-avatar" enctype="multipart/form-data">
          <input type="file" name="avatar" accept="image/*" required>
          <button class="btn" type="submit">Mettre à jour l’avatar</button>
        </form>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>

<!-- JS (profile.js importe api.js) -->
<script type="module" src="<?= WEB_BASE ?>/assets/js/profile.js"></script>
</body>
</html>
