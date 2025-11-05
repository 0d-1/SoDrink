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

  <div class="torpille-status-line">
    <div id="torpille-state" class="muted"></div>
    <span id="torpille-status-badge" class="status-pill" data-variant="neutral" hidden>—</span>
  </div>

  <div class="torpille-feedback">
    <div id="torpille-loader" class="torpille-loader" role="status" hidden>
      <span class="spinner" aria-hidden="true"></span>
      <span>Chargement…</span>
    </div>
    <div id="torpille-message" class="torpille-message" hidden></div>
  </div>

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
  <div id="torpille-empty" class="torpille-empty muted" hidden></div>
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
  .torpille-head{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
  .torpille-head h2{ margin:0; }
  .torpille-head #torpille-top3{ margin-left:auto; }

  .torpille-status-line{ display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.5rem; flex-wrap:wrap; }
  .status-pill{ display:inline-flex; align-items:center; gap:.35rem; padding:.3rem .65rem; border-radius:999px; font-size:.85rem; font-weight:600; background:rgba(159,178,216,.12); color:#9fb2d8; }
  .status-pill[data-variant="alert"]{ background:rgba(255,90,90,.18); color:#ffb3b3; }
  .status-pill[data-variant="success"]{ background:rgba(90,214,90,.16); color:#b0e5b0; }
  .status-pill[data-variant="accent"]{ background:rgba(132,160,255,.18); color:#c3d0ff; }
  .status-pill[data-variant="neutral"]{ background:rgba(158,170,200,.1); color:#aab7d4; }

  .torpille-feedback{ display:flex; flex-wrap:wrap; gap:.75rem; align-items:center; margin-bottom:.75rem; }
  .torpille-loader{ display:inline-flex; align-items:center; gap:.45rem; color:#9fb2d8; font-size:.9rem; }
  .torpille-loader .spinner{ width:18px; height:18px; border-radius:50%; border:2px solid rgba(159,178,216,.25); border-top-color:#9fb2d8; animation:torpille-spin 0.9s linear infinite; }
  .torpille-message{ font-size:.9rem; padding:.45rem .65rem; border-radius:8px; background:rgba(255,255,255,.06); color:#d2dcf0; }
  .torpille-message[data-variant="error"]{ background:rgba(255,84,84,.15); color:#ffbaba; }
  .torpille-message[data-variant="success"]{ background:rgba(90,214,140,.18); color:#d2ffe3; }
  .torpille-message[data-variant="info"]{ background:rgba(160,185,255,.14); color:#dbe6ff; }

  @keyframes torpille-spin{ to { transform:rotate(360deg); } }

  .stats-panel{ position:absolute; top:10px; right:10px; z-index:25; }
  .stats-inner{ background:var(--card, #111722); border:1px solid var(--border, #243049); border-radius:12px; min-width:260px; max-height:320px; overflow:auto; box-shadow:0 10px 30px rgba(0,0,0,.35); }
  .stats-title{ padding:.6rem .8rem; font-weight:600; border-bottom:1px solid var(--border, #243049); }
  .stats-list{ padding:.5rem .8rem; }
  .stats-row{ display:grid; grid-template-columns:1fr auto; gap:.75rem; align-items:center; padding:.35rem 0; }
  .stats-row .count{ color:#9fb2d8; }

  /* Upload / preview */
  #section-torpille .upload-area{ display:grid; grid-template-columns:180px 1fr; gap:1rem; align-items:center; margin:.5rem 0 1rem; }
  #section-torpille .preview-box{ position:relative; width:180px; aspect-ratio:3/4; background:#0f1420; border:1px dashed var(--border,#2a3550); border-radius:12px; display:flex; align-items:center; justify-content:center; overflow:hidden }
  #section-torpille .preview-box img{ width:100%; height:100%; object-fit:cover }
  #section-torpille .btn.is-busy{ opacity:.65; pointer-events:none; position:relative; }
  #section-torpille .btn.is-busy::after{ content:'…'; position:absolute; inset:0; display:grid; place-items:center; font-weight:600; letter-spacing:.2em; color:inherit; }

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

  .torpille-gallery.is-loading{ opacity:.55; filter:saturate(.8); transition:opacity .2s ease; }
  .torpille-empty{ margin-top:.5rem; text-align:center; }

  .pagination{ display:flex; gap:.5rem; justify-content:center; margin-top:.75rem }
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
