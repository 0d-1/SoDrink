<?php // public/sections/next-event.php ?>
<section class="section card" id="section-next-event">
  <h2>Prochaine Soirée</h2>

  <!-- Formulaire de création (affiché seulement si connecté via JS) -->
  <form id="event-form" class="form event-form" hidden>
    <div class="event-form-grid">
      <label>Date
        <input type="date" name="date" required>
      </label>
      <label>Lieu
        <input type="text" name="lieu" maxlength="120" placeholder="Ex. Appartement de Sam" required>
      </label>
      <label>Thème
        <input type="text" name="theme" maxlength="120" placeholder="Ex. Années 90">
      </label>
      <label>Places max
        <input type="number" name="max_participants" min="0" max="500" placeholder="Illimité">
      </label>
    </div>
    <label>Description
      <textarea name="description" rows="3" maxlength="800" placeholder="Infos utiles, dress code, heure…"></textarea>
    </label>
    <button class="btn btn-primary" type="submit">Publier l’évènement</button>
  </form>

  <!-- Mini calendrier -->
  <div class="calendar" id="mini-calendar"></div>

  <!-- Liste des prochains évènements (édition/suppression si auteur ou admin) -->
  <h3>Évènements à venir</h3>
  <div id="event-list" class="event-list"></div>
</section>

<script type="module" src="<?= WEB_BASE ?>/assets/js/sections/next-event.js"></script>
