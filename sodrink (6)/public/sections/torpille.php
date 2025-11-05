<?php // public/sections/torpille.php ?>
<section class="section card" id="section-torpille">
  <div class="torpille-head">
    <h2>Torpille</h2>

    <!-- Top 3 -->
    <button id="torpille-top3" class="btn btn-sm" type="button" title="Voir le classement">Top 3</button>
    <div id="torpille-stats-panel" class="stats-panel" hidden>
      <div class="stats-inner">
        <div class="stats-title">Classement complet</div>
        <div id="torpille-stats-list" class="stats-list"></div>
      </div>
    </div>
  </div>

  <div id="torpille-state" class="muted" style="margin-bottom:.5rem"></div>

  <form id="torpille-form" class="form" hidden enctype="multipart/form-data" autocomplete="off" novalidate>
    <p><strong>Tu es torpillé(e) !</strong> Prends-toi en photo, puis choisis la prochaine personne torpillée.</p>

    <div class="upload-area">
      <div class="preview-box">
        <img id="torpille-preview" alt="Aperçu" style="display:none">
        <div id="torpille-placeholder" class="muted">Aucune photo sélectionnée</div>
      </div>
      <div class="upload-actions">
        <div class="inline" style="gap:.5rem; flex-wrap:wrap">
          <button type="button" class="btn btn-primary" id="torpille-btn-camera">Prendre une photo</button>
          <button type="button" class="btn" id="torpille-btn-choose">Choisir depuis l’appareil</button>
          <input id="torpille-file" name="photo" type="file" accept="image/*" required style="display:none">
        </div>
        <p class="muted" style="margin:.5rem 0 0">Formats: JPG/PNG/HEIC. Taille raisonnable recommandée. Recadrage auto en 3:4.</p>
      </div>
    </div>

    <label>Prochain(e) torpillé(e)
      <select name="next_user_id" id="torpille-next" required></select>
    </label>
    <button class="btn btn-primary" type="submit">Envoyer &amp; torpiller</button>
  </form>

  <!-- Galerie : une seule ligne desktop, 2 items en mobile -->
  <div id="torpille-gallery" class="torpille-gallery"></div>
  <div class="pagination" id="torpille-pagination" hidden></div>
</section>

<!-- Overlay (identique) -->
<div id="torpille-overlay" class="torpille-overlay" hidden>
  <img id="torpille-overlay-img" alt="Dernière torpille">
  <button class="torpille-close" id="torpille-close">✕</button>
  <div class="torpille-confirm" id="torpille-confirm" hidden>
    <div class="card confirm-card">
      <p style="margin:.25rem 0 1rem 0; text-align:center">L’image ne te plaît pas ?</p>
      <div style="display:flex; gap:.5rem; justify-content:center">
        <button class="btn btn-primary" id="torpille-yes">Oui</button>
        <button class="btn" id="torpille-no">Non</button>
      </div>
    </div>
  </div>
</div>

<style>
  #section-torpille { position: relative; }
  .torpille-head{ display:flex; align-items:center; gap:.5rem; }
  .torpille-head h2{ margin:0; }
  .torpille-head #torpille-top3{ margin-left:auto; }

  .stats-panel{ position:absolute; top:10px; right:10px; z-index:25; }
  .stats-inner{ background:var(--card, #111722); border:1px solid var(--border, #243049); border-radius:12px; min-width:260px; max-height:320px; overflow:auto; box-shadow:0 10px 30px rgba(0,0,0,.35); }
  .stats-title{ padding:.6rem .8rem; font-weight:600; border-bottom:1px solid var(--border, #243049); }
  .stats-list{ padding:.5rem .8rem; }
  .stats-row{ display:grid; grid-template-columns:1fr auto; gap:.75rem; align-items:center; padding:.35rem 0; }
  .stats-row .count{ color:#9fb2d8; }

  /* Upload / preview */
  #section-torpille .upload-area{ display:grid; grid-template-columns:180px 1fr; gap:1rem; align-items:center; margin:.5rem 0 1rem; }
  #section-torpille .preview-box{ position:relative; width:180px; aspect-ratio:3/4; background:#0f1420; border:1px dashed var(--border,#2a3550); border-radius:12px; display:flex; align-items:center; justify-content:center; overflow:hidden }
  #section-torpille .preview-box img{ max-width:100%; max-height:100%; object-fit:contain }

  /* Galerie — 1 ligne desktop */
  .torpille-gallery{
    display:grid;
    grid-auto-flow: column;          /* on déroule horizontalement */
    grid-auto-columns: 1fr;          /* colonnes flexibles qui remplissent la largeur */
    grid-template-rows: 1fr;         /* une seule ligne */
    gap:.75rem;
    align-items: stretch;
  }
  .torpille-gallery .photo{
    border:1px solid var(--border,#243049);
    border-radius:12px; overflow:hidden; background:var(--card);
    display:flex; flex-direction:column;
  }
  .torpille-gallery .photo .wrap{
    width:100%;
    aspect-ratio:3/4;               /* ratio 3:4 à l’affichage */
    overflow:hidden;
  }
  .torpille-gallery .photo img{
    width:100%; height:100%; object-fit:cover; display:block;
  }
  .torpille-gallery .meta{
    display:flex; gap:.5rem; padding:.4rem .6rem; justify-content:space-between; align-items:center
  }

  .pagination{ display:flex; gap:.5rem; justify-content:center; margin-top:.5rem }
  .pagination button{ min-width:2.25rem }

  /* Mobile : 2 items empilés */
  @media (max-width:640px){
    #section-torpille .upload-area{ grid-template-columns:1fr; }
    .torpille-gallery{
      grid-auto-flow: row;           /* on passe en pile verticale */
      grid-template-columns: 1fr;    /* une colonne */
      grid-auto-rows: auto;
    }
  }

  /* Overlay (identique) */
  .torpille-overlay{ position:fixed; inset:0; background:#000; display:grid; place-items:center; z-index:80 }
  .torpille-overlay img{ max-width:100vw; max-height:100vh; object-fit:contain }
  .torpille-close{ position:absolute; top:10px; right:10px; border:0; background:#fff; color:#000; width:40px; height:40px; border-radius:999px; cursor:pointer; font-size:18px }
  .torpille-confirm{ position:fixed; inset:0; display:grid; place-items:center; background:rgba(0,0,0,.55) }
  .confirm-card{ min-width:min(360px,90vw); padding:1rem; }
</style>

<script type="module" src="<?= WEB_BASE ?>/assets/js/sections/torpille.js"></script>
