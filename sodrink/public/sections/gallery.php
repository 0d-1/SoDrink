<?php // public/sections/gallery.php ?>
<section class="section card" id="section-gallery">
  <div class="section-head">
    <h2>Galerie des Soirées</h2>
    <form id="form-gallery-upload" enctype="multipart/form-data" class="inline">
      <input type="file" name="photo" accept="image/*" required>
      <input type="text" name="title" placeholder="Titre" maxlength="120" required>
      <input type="text" name="description" placeholder="Description" maxlength="500">
      <button class="btn btn-primary" type="submit">Publier</button>
    </form>
  </div>
  <div class="gallery-grid" id="gallery-grid"></div>
  <div class="pagination" id="gallery-pagination"></div>
</section>

<!-- Ton script existant qui charge/affiche les photos -->
<script type="module" src="<?= WEB_BASE ?>/assets/js/sections/gallery.js"></script>

<!-- Nouveau : lightbox (plein écran + flèches + swipe + téléchargement) -->
<script type="module" src="<?= WEB_BASE ?>/assets/js/sections/gallery-lightbox.js"></script>
