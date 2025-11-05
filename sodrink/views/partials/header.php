<?php
$logged = isset($_SESSION['user_id']);
$pseudo = $_SESSION['pseudo'] ?? null;
$role   = $_SESSION['role']  ?? 'user';
$avatar = $_SESSION['avatar'] ?? (WEB_BASE . '/assets/img/ui/avatar-default.svg');
?>
<header class="topbar">
  <div class="container topbar-inner">
    <a href="<?= WEB_BASE ?>/" class="logo">SoDrink</a>
    <div class="right-side">
      <div class="auth-area" id="auth-area">
        <?php if ($logged): ?>
          <div class="user-chip" title="<?= htmlspecialchars($pseudo ?: 'Moi'); ?>">
            <img class="avatar" id="nav-avatar" src="<?= htmlspecialchars($avatar); ?>" alt="avatar">
            <span id="nav-pseudo" class="user-name"><?= htmlspecialchars($pseudo ?: 'Moi'); ?></span>
            <div class="notif-wrapper">
              <button id="btn-notif" class="icon-btn bell" aria-label="Notifications">🔔
                <span id="notif-count" class="badge" hidden>0</span>
              </button>
              <div id="notif-dropdown" class="notif-dropdown" hidden>
                <div class="notif-head">
                  <strong>Notifications</strong>
                  <button id="notif-readall" class="btn btn-sm">Tout marquer lu</button>
                </div>
                <div id="notif-list" class="notif-list"></div>
              </div>
            </div>
          </div>
        <?php else: ?>
          <button id="btn-open-login" class="btn btn-primary">Se connecter</button>
        <?php endif; ?>
      </div>
      <button id="btn-burger" class="btn btn-outline burger" aria-label="Ouvrir le menu">☰</button>
    </div>
  </div>
</header>

<?php /* Drawer (inchangé) */ ?>
<aside class="drawer" id="right-drawer" aria-hidden="true">
  <div class="drawer-header">
    <strong>Menu</strong>
    <button class="btn" id="drawer-close">✕</button>
  </div>
  <div class="drawer-body">
    <div class="search-box">
      <input type="search" id="user-search" placeholder="Rechercher un utilisateur… (nom, prénom, pseudo)" autocomplete="off">
      <div id="user-suggest" class="suggestions" hidden></div>
    </div>
    <nav class="drawer-nav">
      <a class="btn btn-outline" href="<?= WEB_BASE ?>/">Accueil</a>
      <?php if ($logged): ?>
        <a class="btn btn-outline" href="<?= WEB_BASE ?>/profile.php">Profil</a>
        <?php if ($role === 'admin'): ?>
          <a class="btn btn-outline" href="<?= WEB_BASE ?>/admin.php#sections">Admin</a>
        <?php endif; ?>
      <?php endif; ?>
    </nav>
    <div class="drawer-footer">
      <?php if ($logged): ?>
        <button class="btn btn-primary" id="drawer-logout">Se déconnecter</button>
      <?php else: ?>
        <button class="btn btn-primary" id="drawer-login">Se connecter</button>
      <?php endif; ?>
    </div>
  </div>
</aside>
<div class="drawer-backdrop" id="drawer-backdrop" hidden></div>

<script type="module" src="<?= WEB_BASE ?>/assets/js/ui.js"></script>
<?php if ($logged): ?>
  <script type="module" src="<?= WEB_BASE ?>/assets/js/notifications.js"></script>
<?php endif; ?>
