<?php // public/sections/gallery.php ?>
<section class="section card" id="section-gallery">
  <div class="section-head">
    <div class="section-title">
      <h2>Galerie des Soirées</h2>
      <p class="muted">Feuilletez les meilleurs souvenirs du BDE et partagez les vôtres.</p>
    </div>
    <form id="form-gallery-upload" enctype="multipart/form-data" class="inline">
      <input type="file" name="photo" accept="image/*" required>
      <input type="text" name="title" placeholder="Titre" maxlength="120" required>
      <input type="text" name="description" placeholder="Description" maxlength="500">
      <button class="btn btn-primary" type="submit">Publier</button>
    </form>
  </div>

  <div class="gallery-toolbar" id="gallery-toolbar">
    <label class="toolbar-search">
      <span class="toolbar-icon" aria-hidden="true">🔍</span>
      <input type="search" id="gallery-search" name="q" placeholder="Rechercher par titre ou auteur…" autocomplete="off">
    </label>
    <div class="toolbar-group">
      <select id="gallery-sort" aria-label="Trier les photos">
        <option value="recent">Plus récentes</option>
        <option value="popular">Les plus aimées</option>
        <option value="commented">Les plus commentées</option>
      </select>
      <div class="toolbar-toggle">
        <input type="checkbox" id="gallery-filter-mine">
        <label for="gallery-filter-mine">Mes publications</label>
      </div>
      <div class="toolbar-views" role="group" aria-label="Affichage">
        <button type="button" class="btn btn-sm btn-ghost" data-gallery-view="grid" aria-pressed="true">Grille</button>
        <button type="button" class="btn btn-sm btn-ghost" data-gallery-view="list" aria-pressed="false">Liste</button>
      </div>
    </div>
  </div>

  <div class="gallery-status" id="gallery-status" hidden></div>
  <div class="gallery-grid" id="gallery-grid" data-view="grid"></div>
  <div class="pagination" id="gallery-pagination" hidden></div>
</section>

<!-- Ton script existant qui charge/affiche les photos -->
<script type="module" src="<?= WEB_BASE ?>/assets/js/sections/gallery.js"></script>

<!-- Nouveau : lightbox (plein écran + flèches + swipe + téléchargement) -->
<script type="module" src="<?= WEB_BASE ?>/assets/js/sections/gallery-lightbox.js"></script>
