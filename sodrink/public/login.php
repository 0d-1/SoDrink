<?php
// public/login.php — Page de connexion/inscription SoDrink (intégration design animé)
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/security/auth.php';

$title = 'Connexion — SoDrink';

include __DIR__ . '/../views/partials/head.php';
include __DIR__ . '/../views/partials/header.php';
?>

<main class="container login-page">
  <!-- CSS spécifique à la page -->
  <link rel="stylesheet" href="<?= WEB_BASE ?>/assets/css/login.css">
  <section class="login-wrapper">
    <div class="container-ui">
      <div class="curved-shape"></div>
      <div class="curved-shape2"></div>

      <!-- Panneau Connexion -->
      <div class="form-box Login" aria-labelledby="login-title">
        <h2 class="animation" id="login-title" style="--D:0; --S:21">Connexion</h2>
        <form id="form-login" action="#" novalidate autocomplete="off">
          <div class="input-box animation" style="--D:1; --S:22">
            <input id="login-pseudo" type="text" name="pseudo" required minlength="3" maxlength="20" autocomplete="username">
            <label for="login-pseudo">Pseudo</label>
            <box-icon type="solid" name="user"></box-icon>
          </div>

          <div class="input-box animation" style="--D:2; --S:23">
            <input id="login-password" type="password" name="password" required minlength="8" autocomplete="current-password">
            <label for="login-password">Mot de passe</label>
            <box-icon name="lock-alt" type="solid"></box-icon>
          </div>

          <label class="checkbox-line animation" style="--D:3; --S:23">
            <input id="login-remember" type="checkbox" name="remember" value="1"> Se souvenir de moi (2 semaines)
          </label>

          <div id="login-error" class="alert alert-error animation" style="--D:3; --S:24" hidden></div>

          <div class="input-box animation" style="--D:4; --S:24">
            <button class="btn" id="login-submit" type="submit">
              <span class="btn-label">Se connecter</span>
              <span class="btn-spinner" hidden aria-hidden="true"></span>
            </button>
          </div>

          <div class="regi-link animation" style="--D:5; --S:25">
            <p>Pas encore de compte ? <br> <a href="#" class="SignUpLink">Créer un compte</a></p>
          </div>
        </form>
      </div>

      <div class="info-content Login">
        <h2 class="animation" style="--D:0; --S:20">CONTENT DE TE REVOIR !</h2>
        <p class="animation" style="--D:1; --S:21">Connecte-toi pour rejoindre la soirée 🍹</p>
      </div>

      <!-- Panneau Inscription -->
      <div class="form-box Register" aria-labelledby="register-title">
        <h2 class="animation" id="register-title" style="--li:17; --S:0">Inscription</h2>
        <form id="form-register" action="#" novalidate autocomplete="off">
          <div class="input-box animation" style="--li:18; --S:1">
            <input id="reg-pseudo" type="text" name="pseudo" required minlength="3" maxlength="20" autocomplete="username">
            <label for="reg-pseudo">Pseudo</label>
            <box-icon type="solid" name="user"></box-icon>
          </div>

          <div class="input-box animation" style="--li:19; --S:2">
            <input id="reg-prenom" type="text" name="prenom" required minlength="2" maxlength="40" autocomplete="given-name">
            <label for="reg-prenom">Prénom</label>
            <box-icon name="id-card" type="solid"></box-icon>
          </div>

          <div class="input-box animation" style="--li:19; --S:3">
            <input id="reg-nom" type="text" name="nom" required minlength="2" maxlength="40" autocomplete="family-name">
            <label for="reg-nom">Nom</label>
            <box-icon name="id-card" type="solid"></box-icon>
          </div>

          <div class="input-box animation" style="--li:19; --S:4">
            <input id="reg-password" type="password" name="password" required minlength="8" autocomplete="new-password">
            <label for="reg-password">Mot de passe</label>
            <box-icon name="lock-alt" type="solid"></box-icon>
          </div>

          <div class="input-box animation" style="--li:19; --S:5">
            <input id="reg-instagram" type="text" name="instagram" inputmode="text" placeholder="@pseudo (facultatif)">
            <label for="reg-instagram">Instagram (facultatif)</label>
            <box-icon name="instagram" type="logo"></box-icon>
          </div>

          <div id="register-error" class="alert alert-error animation" style="--li:20; --S:5" hidden></div>
          <div class="alert alert-ok animation" id="register-ok" style="--li:20; --S:5" hidden>Compte créé ! Redirection…</div>

          <div class="input-box animation" style="--li:20; --S:6">
            <button class="btn" id="register-submit" type="submit">
              <span class="btn-label">Créer mon compte</span>
              <span class="btn-spinner" hidden aria-hidden="true"></span>
            </button>
          </div>

          <div class="regi-link animation" style="--li:21; --S:7">
            <p>Déjà membre ? <br> <a href="#" class="SignInLink">Se connecter</a></p>
          </div>
        </form>
      </div>

      <div class="info-content Register">
        <h2 class="animation" style="--li:17; --S:0">BIENVENUE !</h2>
        <p class="animation" style="--li:18; --S:1">Crée ton compte en 10 secondes, et c’est parti ✨</p>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>

<!-- JS spécifique à la page -->
<script type="module" src="<?= WEB_BASE ?>/assets/js/login-page.js"></script>
<!-- Icônes -->
<script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js" defer></script>
</body>
</html>
