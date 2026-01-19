<?php
// public/admin.php — Panneau d’administration SoDrink
declare(strict_types=1);

// Bootstrap appli (constantes, session, helpers)
require_once __DIR__ . '/../src/bootstrap.php';
// ⚠️ AJOUT IMPORTANT : charge les fonctions d'auth (isAdmin, require_login, etc.)
require_once __DIR__ . '/../src/security/auth.php';

use function SoDrink\Security\isAdmin;

// Sécurisation : accès admin uniquement
if (!isset($_SESSION['user_id']) || !isAdmin()) {
    http_response_code(302);
    header('Location: ' . WEB_BASE . '/');
    exit;
}

$title = 'Administration';
include __DIR__ . '/../views/partials/head.php';
include __DIR__ . '/../views/partials/header.php';
?>
<main class="container">
  <section class="content">

    <h1 class="hero-title">Administration</h1>
    <p class="muted">Gère les utilisateurs, les sections, et envoie des notifications.</p>

    <!-- Onglets -->
    <div class="tabs inline" style="margin:.75rem 0 1rem">
      <button class="btn" data-tab="users" id="tabbtn-users">Utilisateurs</button>
      <button class="btn" data-tab="sections" id="tabbtn-sections">Sections</button>
      <button class="btn" data-tab="torpille" id="tabbtn-torpille">Torpille</button>
      <button class="btn" data-tab="notifs" id="tabbtn-notifs">Notifications</button>
    </div>

    <!-- ====== Onglet Utilisateurs ====== -->
    <section id="tab-users" class="tab">
      <div class="card" style="margin-bottom:1rem">
        <h2 style="margin-top:0">Créer un utilisateur</h2>
        <form id="form-user-create" class="form" style="display:grid; gap:.6rem">
          <div class="inline">
            <label style="flex:1">Pseudo
              <input type="text" name="pseudo" maxlength="20" required>
            </label>
            <label style="flex:1">Prénom
              <input type="text" name="prenom" maxlength="40">
            </label>
            <label style="flex:1">Nom
              <input type="text" name="nom" maxlength="40">
            </label>
          </div>
          <div class="inline">
            <label style="flex:1">Mot de passe
              <input type="password" name="password" minlength="8" required>
            </label>
            <label style="flex:1">Instagram (optionnel)
              <input type="text" name="instagram" maxlength="60" placeholder="ex. pseudo_ig">
            </label>
            <label style="flex:1">Rôle
              <select name="role">
                <option value="user" selected>user</option>
                <option value="admin">admin</option>
              </select>
            </label>
          </div>
          <div class="inline">
            <button class="btn btn-primary" type="submit">Créer</button>
          </div>
        </form>
      </div>

      <div class="card">
        <h2 style="margin-top:0">Liste des utilisateurs</h2>
        <table id="users-table" class="table">
          <thead>
            <tr><th>ID</th><th>Pseudo</th><th>Nom</th><th>Rôle</th><th>Actions</th></tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </section>

    <!-- ====== Onglet Sections ====== -->
    <section id="tab-sections" class="tab" hidden>
      <div class="card">
        <h2 style="margin-top:0">Gestion des sections</h2>
        <p class="muted">Active/désactive des sections et change l’ordre d’affichage sur la page d’accueil.</p>
        <div id="sections-list" style="display:grid; gap:.5rem"><!-- rempli par admin.js --></div>
        <p class="muted" style="margin-top:.5rem">Astuce : clique sur ↑/↓ pour réordonner, coche/décoche pour activer.</p>
      </div>
    </section>

    <!-- ====== Onglet Torpille ====== -->
    <section id="tab-torpille" class="tab" hidden>
      <div class="card">
        <h2 style="margin-top:0">Torpille – État</h2>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:.75rem">
          <div>
            <div class="muted">Détenteur actuel</div>
            <div id="torpille-current" style="display:inline-block; padding:.3rem .6rem; border:1px solid var(--border); border-radius:999px">—</div>
          </div>
          <div>
            <div class="muted">Dernière mise à jour</div>
            <div id="torpille-updated" style="display:inline-block; padding:.3rem .6rem; border:1px solid var(--border); border-radius:999px">—</div>
          </div>
        </div>
      </div>

      <div class="card">
        <h2 style="margin-top:0">Choisir le premier torpillé</h2>
        <form id="form-torpille-start" class="inline" style="gap:.5rem; align-items:end">
          <label style="flex:1">Utilisateur
            <select id="torpille-first-user" required></select>
          </label>
          <button class="btn btn-primary" type="submit">Démarrer / Réassigner</button>
          <span id="torpille-start-status" class="muted"></span>
        </form>
        <p class="muted" style="margin-top:.5rem">Astuce : tu peux relancer ici si tu veux changer la première personne.</p>
      </div>

      <div class="card">
        <h2 style="margin-top:0">Déplacer la torpille actuelle</h2>
        <form id="form-torpille-transfer" class="inline" style="gap:.5rem; align-items:end">
          <label style="flex:1">Nouveau détenteur
            <select id="torpille-transfer-to" required></select>
          </label>
          <button class="btn" type="submit">Transférer (admin)</button>
          <span id="torpille-transfer-status" class="muted"></span>
        </form>
        <p class="muted" style="margin-top:.5rem">Ne nécessite pas de photo. À utiliser pour corriger une erreur ou débloquer une chaîne.</p>
      </div>

      <div class="card">
        <h2 style="margin-top:0">Historique récent</h2>
        <div id="torpille-latest" class="muted">Aucune photo pour l’instant.</div>
      </div>
    </section>

    <!-- ====== Onglet Notifications ====== -->
    <section id="tab-notifs" class="tab" hidden>
      <h2>Notifications</h2>
      <p class="muted">Envoie une notification à tous, à un rôle, ou à une sélection d’utilisateurs.</p>

      <form id="form-broadcast" class="form" style="display:grid; gap:.6rem">
        <label>Titre
          <input type="text" name="title" maxlength="80" required>
        </label>
        <label>Message
          <textarea name="message" rows="3" maxlength="500" required></textarea>
        </label>
        <label>Lien (optionnel)
          <input type="url" name="link" placeholder="<?= WEB_BASE ?>/...">
        </label>

        <div class="card" style="margin-top:.5rem">
          <div class="inline" style="align-items:center">
            <label style="flex:1">Filtre rôle
              <select id="notif-role">
                <option value="">(tous)</option>
                <option value="user">user</option>
                <option value="admin">admin</option>
              </select>
            </label>
            <div>
              <button class="btn btn-sm" type="button" id="notif-select-all">Tout cocher</button>
              <button class="btn btn-sm" type="button" id="notif-unselect-all">Tout décocher</button>
            </div>
          </div>
          <div id="notif-users-list" style="max-height:300px; overflow:auto; margin-top:.5rem"><!-- rempli par admin.js --></div>
        </div>

        <div class="inline">
          <button class="btn btn-primary" type="submit">Envoyer</button>
        </div>
      </form>

      <div class="card" style="margin-top:1rem">
        <h3 style="margin-top:0">Historique &amp; gestion</h3>
        <p class="muted">Filtre les notifications envoyées et marque-les comme lues ou supprime-les.</p>
        <div class="inline" style="gap:.5rem; align-items:end; flex-wrap:wrap">
          <label>Recherche
            <input type="search" id="notif-search" placeholder="Message, pseudo, ID...">
          </label>
          <label>Rôle
            <select id="notif-filter-role">
              <option value="">(tous)</option>
              <option value="user">user</option>
              <option value="admin">admin</option>
            </select>
          </label>
          <label>Statut
            <select id="notif-filter-read">
              <option value="">(tous)</option>
              <option value="unread">Non lues</option>
              <option value="read">Lues</option>
            </select>
          </label>
          <button class="btn btn-sm" type="button" id="notif-refresh">Rafraîchir</button>
        </div>
        <div style="overflow:auto; margin-top:.75rem">
          <table class="table" id="notif-admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Destinataire</th>
                <th>Message</th>
                <th>Statut</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="muted" id="notif-admin-empty" style="margin-top:.5rem"></div>
      </div>
    </section>

  </section>
</main>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
<script type="module" src="<?= WEB_BASE ?>/assets/js/admin.js"></script>
</body>
</html>
