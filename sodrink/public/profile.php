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
    <section class="card profile-card profile-notif-card" id="profile-notifications">
      <h2>Paramètres & notifications</h2>
      <p class="muted">Choisis les alertes que tu veux recevoir depuis SoDrink.</p>
      <form id="form-notifications" class="form notif-form">
        <div class="notif-grid">
          <label class="notif-option">
            <input type="checkbox" data-setting="messages">
            <div>
              <strong>Messages privés</strong>
              <p class="muted">Alerte quand quelqu’un t’écrit dans la messagerie.</p>
            </div>
          </label>
          <label class="notif-option">
            <input type="checkbox" data-setting="events">
            <div>
              <strong>Événements</strong>
              <p class="muted">Confirmation, modifications et nouveaux participants à tes soirées.</p>
            </div>
          </label>
          <label class="notif-option">
            <input type="checkbox" data-setting="gallery">
            <div>
              <strong>Galerie photo</strong>
              <p class="muted">Commentaires, likes et mises à jour sur tes photos.</p>
            </div>
          </label>
          <label class="notif-option">
            <input type="checkbox" data-setting="torpille">
            <div>
              <strong>Torpille</strong>
              <p class="muted">Être averti quand la torpille passe entre les mains des amis.</p>
            </div>
          </label>
          <label class="notif-option">
            <input type="checkbox" data-setting="announcements">
            <div>
              <strong>Annonces</strong>
              <p class="muted">Messages importants envoyés par les admins.</p>
            </div>
          </label>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Enregistrer mes préférences</button>
        </div>
      </form>
    </section>

    <section class="card profile-card profile-uclsport-card" id="profile-uclsport">
      <h2>Automatisation UCL Sport</h2>
      <p class="muted">Enregistre une automatisation pour réserver automatiquement tes séances sportives UCLouvain avec le script Selenium.</p>
      <div id="ucl-automation-status" class="muted" aria-live="polite"></div>
      <div class="ucl-automation-table-wrapper">
        <table class="table" id="ucl-automation-table">
          <thead>
            <tr>
              <th>Sport</th>
              <th>Date</th>
              <th>Créneau</th>
              <th>Campus</th>
              <th>Hebdo</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <p class="muted" id="ucl-automation-empty">Aucune automatisation enregistrée pour le moment.</p>
      </div>
      <form id="ucl-automation-form" class="form" autocomplete="off">
        <input type="hidden" name="id" value="">
        <div class="form-grid">
          <label>Identifiant UCLouvain
            <input type="text" name="ucl_username" maxlength="120" required>
          </label>
          <label>Mot de passe UCLouvain
            <input type="password" name="ucl_password" maxlength="120" placeholder="••••••••">
            <small class="muted">Laisse vide pour conserver le mot de passe stocké. Il est chiffré côté serveur.</small>
          </label>
        </div>
        <div class="form-grid">
          <label>Sport à réserver
            <input type="text" name="sport" maxlength="120" required placeholder="Badminton, Fitness, ...">
          </label>
          <label>Campus
            <input type="text" name="campus" maxlength="120" placeholder="Louvain-la-Neuve">
          </label>
        </div>
        <div class="form-grid">
          <label>Date de la séance (jj/mm)
            <input type="text" name="session_date" maxlength="5" required placeholder="07/01">
          </label>
          <label>Créneau (HH:MM-HH:MM)
            <input type="text" name="time_slot" maxlength="11" required placeholder="21:30-23:00">
          </label>
        </div>
        <div class="inline" style="gap:.75rem; align-items:center; flex-wrap:wrap">
          <label class="inline" style="gap:.35rem; align-items:center">
            <input type="checkbox" name="weekly">
            <span>Relancer automatiquement chaque semaine</span>
          </label>
          <label class="inline" style="gap:.35rem; align-items:center">
            <input type="checkbox" name="headless" checked>
            <span>Exécuter le navigateur en mode discret (headless)</span>
          </label>
        </div>
        <div class="form-actions" style="gap:.5rem">
          <button class="btn btn-primary" type="submit">Enregistrer l’automatisation</button>
          <button class="btn btn-outline" type="reset" id="ucl-automation-reset">Réinitialiser le formulaire</button>
        </div>
      </form>
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
