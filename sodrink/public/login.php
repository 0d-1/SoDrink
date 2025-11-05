<?php
// public/login.php — Page de connexion/inscription SoDrink (intégration design animé)
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/security/auth.php';
require_once __DIR__ . '/../src/security/google.php';

use function SoDrink\Security\google_oauth_is_configured;

$title = 'Connexion — SoDrink';

$googleEnabled = google_oauth_is_configured();
$googleError   = $_SESSION['google_auth_error'] ?? null;
$googleMode    = $_SESSION['google_auth_mode'] ?? 'login';
unset($_SESSION['google_auth_error'], $_SESSION['google_auth_mode']);

$openRegister = $googleMode === 'register';
$initialLoginError = ($googleError && !$openRegister) ? $googleError : null;
$initialRegisterError = ($googleError && $openRegister) ? $googleError : null;

include __DIR__ . '/../views/partials/head.php';
include __DIR__ . '/../views/partials/header.php';
?>

<main class="container login-page">
  <!-- CSS spécifique à la page -->
  <link rel="stylesheet" href="<?= WEB_BASE ?>/assets/css/login.css">
  <section class="login-wrapper">
    <div class="container-ui<?= $openRegister ? ' active' : '' ?>">
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

          <div id="login-error" class="alert alert-error animation" style="--D:3; --S:24" data-initial-message="<?= htmlspecialchars($initialLoginError ?? '', ENT_QUOTES) ?>"<?= $initialLoginError ? '' : ' hidden' ?>><?= htmlspecialchars($initialLoginError ?? '') ?></div>

          <div class="input-box animation" style="--D:4; --S:24">
            <button class="btn" id="login-submit" type="submit">
              <span class="btn-label">Se connecter</span>
              <span class="btn-spinner" hidden aria-hidden="true"></span>
            </button>
          </div>

          <?php if ($googleEnabled): ?>
          <div class="oauth-divider animation" style="--D:4; --S:24"><span>ou</span></div>
          <div class="input-box animation" style="--D:5; --S:25">
            <button class="btn btn-google" id="login-google" type="button">
              <span class="google-icon" aria-hidden="true">
                <svg viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg" role="img" focusable="false">
                  <path d="M17.64 9.2045c0-.638-.0573-1.2518-.1636-1.8409H9v3.4818h4.8418c-.2091 1.1273-.8427 2.0827-1.7955 2.7227v2.2627h2.9082c1.7018-1.5682 2.6855-3.8809 2.6855-6.6263z" fill="#4285F4"/>
                  <path d="M9 18c2.43 0 4.4636-.8055 5.9518-2.1691l-2.9082-2.2627c-.8054.54-1.8354.858-3.0436.858-2.3427 0-4.3281-1.5845-5.0359-3.7136H.956055v2.3318C2.43536 15.9836 5.48182 18 9 18z" fill="#34A853"/>
                  <path d="M3.96409 10.7136c-.18-.54-.28227-1.1154-.28227-1.7136 0-.5981.10227-1.1736.28227-1.7136V4.95455H.956364C.347727 6.16273 0 7.54545 0 9s.347727 2.8373.956364 4.0455l3.007726-2.3318z" fill="#FBBC05"/>
                  <path d="M9 3.54545c1.3209 0 2.5077.45455 3.4418 1.34727l2.5827-2.58273C13.4609.93 11.4273 0 9 0 5.48182 0 2.43536 2.01636.956055 4.95455l3.007995 2.33182C4.67182 5.12909 6.65727 3.54545 9 3.54545z" fill="#EA4335"/>
                </svg>
              </span>
              <span class="google-label">Continuer avec Google</span>
            </button>
          </div>
          <?php endif; ?>

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

          <div id="register-error" class="alert alert-error animation" style="--li:20; --S:5" data-initial-message="<?= htmlspecialchars($initialRegisterError ?? '', ENT_QUOTES) ?>"<?= $initialRegisterError ? '' : ' hidden' ?>><?= htmlspecialchars($initialRegisterError ?? '') ?></div>
          <div class="alert alert-ok animation" id="register-ok" style="--li:20; --S:5" hidden>Compte créé ! Redirection…</div>

          <div class="input-box animation" style="--li:20; --S:6">
            <button class="btn" id="register-submit" type="submit">
              <span class="btn-label">Créer mon compte</span>
              <span class="btn-spinner" hidden aria-hidden="true"></span>
            </button>
          </div>

          <?php if ($googleEnabled): ?>
          <div class="oauth-divider animation" style="--li:20; --S:6"><span>ou</span></div>
          <div class="input-box animation" style="--li:21; --S:7">
            <button class="btn btn-google" id="register-google" type="button">
              <span class="google-icon" aria-hidden="true">
                <svg viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg" role="img" focusable="false">
                  <path d="M17.64 9.2045c0-.638-.0573-1.2518-.1636-1.8409H9v3.4818h4.8418c-.2091 1.1273-.8427 2.0827-1.7955 2.7227v2.2627h2.9082c1.7018-1.5682 2.6855-3.8809 2.6855-6.6263z" fill="#4285F4"/>
                  <path d="M9 18c2.43 0 4.4636-.8055 5.9518-2.1691l-2.9082-2.2627c-.8054.54-1.8354.858-3.0436.858-2.3427 0-4.3281-1.5845-5.0359-3.7136H.956055v2.3318C2.43536 15.9836 5.48182 18 9 18z" fill="#34A853"/>
                  <path d="M3.96409 10.7136c-.18-.54-.28227-1.1154-.28227-1.7136 0-.5981.10227-1.1736.28227-1.7136V4.95455H.956364C.347727 6.16273 0 7.54545 0 9s.347727 2.8373.956364 4.0455l3.007726-2.3318z" fill="#FBBC05"/>
                  <path d="M9 3.54545c1.3209 0 2.5077.45455 3.4418 1.34727l2.5827-2.58273C13.4609.93 11.4273 0 9 0 5.48182 0 2.43536 2.01636.956055 4.95455l3.007995 2.33182C4.67182 5.12909 6.65727 3.54545 9 3.54545z" fill="#EA4335"/>
                </svg>
              </span>
              <span class="google-label">Créer avec Google</span>
            </button>
          </div>
          <?php endif; ?>

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
