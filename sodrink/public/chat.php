<?php
// public/chat.php — Interface de messagerie

declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(302);
    header('Location: ' . WEB_BASE . '/login.php');
    exit;
}

$title = 'Messages — SoDrink';
include __DIR__ . '/../views/partials/head.php';
?>
<body class="chat-page">
<?php include __DIR__ . '/../views/partials/header.php'; ?>
<main class="container chat-container">
  <section class="card chat-shell">
    <aside class="chat-sidebar">
      <div class="chat-sidebar-head">
        <div>
          <h1>Messages</h1>
          <p class="muted">Retrouve toutes tes conversations privées ou de groupe.</p>
        </div>
        <button class="btn btn-primary btn-sm" id="btn-new-conversation">Nouvelle conversation</button>
      </div>
      <div class="chat-search">
        <input type="search" id="conversation-filter" placeholder="Rechercher une conversation ou un membre">
      </div>
      <div class="conversation-list" id="conversation-list"></div>
    </aside>

    <section class="chat-window">
      <div class="chat-placeholder" id="chat-placeholder">
        <h2>Bienvenue dans vos messages</h2>
        <p class="muted">Sélectionne une conversation dans la liste ou crée un nouveau groupe.</p>
      </div>
      <div class="chat-room" id="chat-room" hidden>
        <header class="chat-room-head">
          <div>
            <h2 id="chat-title">—</h2>
            <p class="muted" id="chat-participants">—</p>
          </div>
          <div class="chat-room-actions">
            <a class="btn btn-sm btn-outline" id="chat-profile-link" href="#" hidden>Voir le profil</a>
            <button class="btn btn-sm btn-outline" id="chat-refresh" aria-label="Rafraîchir la conversation">↻</button>
          </div>
        </header>
        <div class="chat-messages" id="chat-messages"></div>
        <form class="chat-composer" id="chat-form" autocomplete="off">
          <textarea name="content" id="chat-input" rows="1" placeholder="Écrire un message…" required></textarea>
          <button class="btn btn-primary" type="submit">Envoyer</button>
        </form>
      </div>
    </section>
  </section>
</main>

<div class="modal" id="modal-conversation" hidden>
  <div class="modal-card conversation-modal">
    <div class="modal-head">
      <strong>Nouvelle conversation</strong>
      <button class="btn btn-sm" type="button" id="modal-conversation-close">✕</button>
    </div>
    <div class="modal-body">
      <form id="form-conversation" class="form conversation-form">
        <label>Titre du groupe (optionnel)
          <input type="text" name="title" maxlength="60" placeholder="Brigade du samedi soir">
        </label>
        <label>Inviter des membres</label>
        <div class="participant-picker">
          <input type="search" id="participant-search" placeholder="Rechercher un membre">
          <div class="suggestions" id="participant-suggest" hidden></div>
        </div>
        <div class="selected-participants" id="selected-participants"></div>
        <p class="muted">Sélectionne au moins une personne (en plus de toi) pour démarrer la conversation.</p>
        <div class="form-actions">
          <button class="btn" type="button" id="modal-conversation-cancel">Annuler</button>
          <button class="btn btn-primary" type="submit">Créer la conversation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
<script>window.SODRINK_ME = Object.assign({}, window.SODRINK_ME || {}, {
  id: <?= (int)($_SESSION['user_id'] ?? 0); ?>,
  pseudo: <?= json_encode($_SESSION['pseudo'] ?? ''); ?>,
  avatar: <?= json_encode($_SESSION['avatar'] ?? (WEB_BASE . '/assets/img/ui/avatar-default.svg')); ?>
});</script>
<script type="module" src="<?= WEB_BASE ?>/assets/js/chat.js"></script>
</body>
</html>
