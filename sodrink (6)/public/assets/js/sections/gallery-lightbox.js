// public/assets/js/sections/gallery-lightbox.js
// Lightbox Galerie : plein écran, flèches, swipe, téléchargement
// ➜ Navigation inter-pages robuste : click pagination si dispo, sinon fetch/swap
//    avec détection d'URL (?page= / ?p= / #page= / /page/N) et reconstruction.

const $  = (s, el=document) => el.querySelector(s);
const $$ = (s, el=document) => [...el.querySelectorAll(s)];

let items = [];          // [{img, src, full, title, desc}]
let current = -1;
let overlay, imgEl, titleEl, descEl, btnPrev, btnNext, btnClose, btnDl;

/* --------------------------- Boot --------------------------- */
window.addEventListener('DOMContentLoaded', () => {
  ensureOverlay();
  indexGallery();
  bindReindexOnDomChanges();
});

/* ------------------- Indexation de la galerie ------------------- */
function galleryRoot() {
  return $('#gallery-grid') || $('#section-gallery') || document;
}

function pickFullUrl(img) {
  const box = img.closest('[data-gallery-item], .photo, figure, .card') || img.parentElement;
  const a   = box && $('a[href]', box);
  const cands = [
    img.getAttribute('data-full'),
    img.getAttribute('data-original'),
    img.getAttribute('data-large'),
    img.getAttribute('data-src'),
    a && a.getAttribute('href'),
    img.currentSrc,
    img.src,
  ].filter(Boolean);
  const good = cands.find(u => u && u !== '#' && !/^javascript:/i.test(u));
  return good || img.currentSrc || img.src;
}

function pickTitle(img, box) {
  return (
    img.getAttribute('data-title') ||
    (box && (box.getAttribute?.('data-title'))) ||
    (box && ($('.title, .caption, figcaption .title, figcaption .t, figcaption', box)?.textContent?.trim())) ||
    img.alt || ''
  );
}

function pickDesc(img, box) {
  return (
    img.getAttribute('data-desc') ||
    (box && ($('.desc, .description, figcaption .desc, figcaption .d', box)?.textContent?.trim())) ||
    img.title || ''
  );
}

function indexGallery() {
  const root = galleryRoot();
  const thumbs = $$(
    '#gallery-grid img, #section-gallery .gallery-grid img, #section-gallery .photo img, #section-gallery figure img',
    root
  ).filter(img => img.tagName === 'IMG' && img.offsetParent !== null);

  items = thumbs.map((img) => {
    const box = img.closest('[data-gallery-item], .photo, figure, .card') || img.parentElement;
    const full = pickFullUrl(img);
    const title = pickTitle(img, box);
    const desc  = pickDesc(img, box);

    img.style.cursor = 'zoom-in';
    img.onclick = (e) => { e.preventDefault(); openAt(thumbs.indexOf(img)); };
    const link = img.closest('a[href]');
    if (link) link.onclick = (e) => { e.preventDefault(); openAt(thumbs.indexOf(img)); };

    return { img, src: img.currentSrc || img.src, full, title, desc };
  });
}

function bindReindexOnDomChanges() {
  const root = galleryRoot();
  const mo = new MutationObserver(() => indexGallery());
  mo.observe(root, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['src','srcset','data-src','data-full','data-original','href','data-large']
  });
}

/* ---------------------- Overlay (lightbox) ---------------------- */
function ensureOverlay() {
  if (overlay) return;

  const css = `
  .glbx{position:fixed;inset:0;background:rgba(0,0,0,.95);display:none;z-index:9999;color:#fff}
  .glbx.show{display:grid;grid-template-columns:1fr;grid-template-rows:1fr auto}
  .glbx .stage{position:relative;display:grid;place-items:center;padding:16px}
  .glbx figure{margin:0;max-width:min(96vw,1400px);max-height:86vh;display:flex;flex-direction:column}
  .glbx img{max-width:100%;max-height:86vh;object-fit:contain;border-radius:10px}
  .glbx .meta{display:flex;gap:.75rem;justify-content:space-between;align-items:flex-start;padding:10px 6px 14px;color:#d9e2ff}
  .glbx .meta .t{font-weight:700}
  .glbx .meta .d{opacity:.85}
  .glbx .nav{position:absolute;top:50%;transform:translateY(-50%);border:0;background:transparent;color:#fff;font-size:42px;line-height:1;width:54px;height:54px;cursor:pointer;opacity:.8}
  .glbx .nav:hover{opacity:1}
  .glbx .prev{left:8px}
  .glbx .next{right:8px}
  .glbx .close{position:absolute;top:10px;left:10px;border:0;background:#fff;color:#000;width:40px;height:40px;border-radius:999px;font-size:18px;cursor:pointer}
  .glbx .dl{position:absolute;top:10px;right:10px;border:0;background:#1a2232;color:#fff;width:42px;height:42px;border-radius:10px;display:grid;place-items:center;text-decoration:none}
  .glbx .dl svg{width:22px;height:22px}
  @media (max-width:640px){ .glbx .nav{font-size:36px;width:48px;height:48px} }
  `;
  const style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  overlay = document.createElement('div');
  overlay.className = 'glbx';
  overlay.innerHTML = `
    <div class="stage">
      <button class="nav prev" aria-label="Précédente">‹</button>
      <figure>
        <img alt="">
        <figcaption class="meta">
          <div class="t"></div>
          <div class="d"></div>
        </figcaption>
      </figure>
      <button class="nav next" aria-label="Suivante">›</button>
      <button class="close" aria-label="Fermer">✕</button>
      <a class="dl" aria-label="Télécharger" download>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
      </a>
    </div>
  `;
  document.body.appendChild(overlay);

  imgEl   = $('img', overlay);
  titleEl = $('.t', overlay);
  descEl  = $('.d', overlay);
  btnPrev = $('.prev', overlay);
  btnNext = $('.next', overlay);
  btnClose= $('.close', overlay);
  btnDl   = $('.dl', overlay);

  btnPrev.onclick = () => go(-1);
  btnNext.onclick = () => go(+1);
  btnClose.onclick= () => hide();
  overlay.addEventListener('click', (e) => {
    const fig = $('figure', overlay);
    if (!fig.contains(e.target) && !e.target.closest('.nav') && !e.target.closest('.dl')) hide();
  });

  // clavier
  document.addEventListener('keydown', (e) => {
    if (!overlay.classList.contains('show')) return;
    if (e.key === 'Escape') hide();
    if (e.key === 'ArrowLeft') go(-1);
    if (e.key === 'ArrowRight') go(+1);
  });

  // swipe mobile
  let tX=0, tY=0, sw=false;
  overlay.addEventListener('touchstart', (e)=>{ const t=e.changedTouches[0]; tX=t.clientX; tY=t.clientY; sw=true; }, {passive:true});
  overlay.addEventListener('touchend',   (e)=>{ if(!sw) return; sw=false; const t=e.changedTouches[0]; const dx=t.clientX-tX; const dy=Math.abs(t.clientY-tY);
    if (Math.abs(dx)>40 && dy<80){ if(dx<0) go(+1); else go(-1); } }, {passive:true});
}

/* --------------------- Ouverture / Navigation --------------------- */
function openAt(index) {
  if (!items.length) return;
  current = Math.max(0, Math.min(items.length - 1, index));
  renderCurrent(true);
  overlay.classList.add('show');
}

function hide() {
  overlay.classList.remove('show');
  current = -1;
}

// NAV inter-pages (click puis fetch/swap)
async function go(delta) {
  if (!items.length) return;

  const newIndex = current + delta;

  // Cas 1 : on reste sur la page courante
  if (newIndex >= 0 && newIndex < items.length) {
    current = newIndex;
    renderCurrent(true);
    return;
  }

  // Cas 2 : il faut changer de page
  const dir = newIndex < 0 ? 'prev' : 'next';
  const ok = await changePage(dir);
  if (!ok) return;

  // Réindexer
  indexGallery();
  current = (dir === 'prev') ? (items.length ? items.length - 1 : 0) : 0;
  renderCurrent(true);
}

/* ----------------- Pagination helpers (click + fetch) ----------------- */
function findPagination(dir) {
  const scope = $('#gallery-pagination') || $('#section-gallery') || document;

  // 1) Liens explicites next/prev
  const selectors = dir === 'next'
    ? ['[rel="next"]','[data-page-next]','[data-next]','.next a','.next button','a[aria-label*="Suiv"]','button[aria-label*="Suiv"]']
    : ['[rel="prev"]','[data-page-prev]','[data-prev]','.prev a','.prev button','a[aria-label*="Préc"]','button[aria-label*="Préc"]'];
  for (const sel of selectors) {
    const el = scope.querySelector(sel);
    if (el) {
      let url = el.getAttribute('data-url') || el.getAttribute('href') || el.dataset?.url || '';
      if (url && url !== '#' && !/^javascript:/i.test(url)) return { el, url };
      return { el, url: null };
    }
  }

  // 2) Numéros de page : extraire pages & actif
  const anchors = $$('a[href], button[data-url], a[data-url]', scope).filter(a => a.offsetParent !== null);
  const pages = anchors
    .map(a => pageInfoFromHref(a.getAttribute('href') || a.getAttribute('data-url') || '', a.textContent))
    .filter(p => p && Number.isFinite(p.page))
    .sort((a,b) => a.page - b.page);

  if (pages.length) {
    const currentPage = detectCurrentPage(scope, pages);
    if (!Number.isFinite(currentPage)) return { el:null, url:null };

    const targetPage = dir === 'next' ? currentPage + 1 : currentPage - 1;
    const tpl = pages.find(p => p.hrefTemplate) || pages[0];
    const url = buildUrlFromTemplate(tpl, targetPage);
    return { el:null, url };
  }

  // 3) Fallback : utiliser l'URL de la page courante et incrémenter param (?page=, ?p=, #page=, /page/N)
  const here = pageInfoFromHref(location.href, String(getVisiblePageNumber(scope) || ''));
  if (here && here.page) {
    const targetPage = dir === 'next' ? here.page + 1 : here.page - 1;
    const url = buildUrlFromTemplate(here, targetPage);
    return { el:null, url };
  }

  return { el:null, url:null };
}

// Détecte la page active via aria-current / .active / [data-active] / bouton disabled / texte
function detectCurrentPage(scope, pages) {
  let active = scope.querySelector('a[aria-current="page"], .active, .is-active, [data-active="true"], button[disabled]');
  if (active) {
    const pi = pageInfoFromHref(active.getAttribute('href') || active.getAttribute('data-url') || '', active.textContent);
    if (pi && Number.isFinite(pi.page)) return pi.page;
  }
  const numFromText = getVisiblePageNumber(scope);
  if (Number.isFinite(numFromText)) return numFromText;

  // Essayer via location ?page=
  const urlPi = pageInfoFromHref(location.href, '');
  if (urlPi && Number.isFinite(urlPi.page)) return urlPi.page;

  // à défaut, la plus petite page cliquable
  return pages[0]?.page ?? NaN;
}

// Extrait un numéro de page depuis l'UI (ex: "Page 3 / 10")
function getVisiblePageNumber(scope) {
  const txt = (scope.textContent || '').replace(/\s+/g,' ').trim();
  const m = txt.match(/(?:Page|Pg|p)\s*[:\-]?\s*(\d+)\s*(?:\/|\s+sur\s+|\s+of\s+)?/i);
  if (m) return parseInt(m[1], 10);
  return NaN;
}

// Analyse une href en différents formats pour en tirer un "template"
function pageInfoFromHref(href, labelText='') {
  if (!href) return null;
  try {
    const u = new URL(href, location.href);

    // 1) Query ?page= / ?p=
    for (const pname of ['page','p']) {
      const val = u.searchParams.get(pname);
      if (val && /^\d+$/.test(val)) {
        return {
          page: parseInt(val,10),
          param: pname,
          hrefTemplate: (target) => {
            const copy = new URL(u.href);
            copy.searchParams.set(pname, String(target));
            return copy.toString();
          }
        };
      }
    }

    // 2) Hash #page=2
    if (u.hash && /page=\d+/.test(u.hash)) {
      const m = u.hash.match(/page=(\d+)/);
      if (m) {
        return {
          page: parseInt(m[1],10),
          hash: true,
          hrefTemplate: (target) => {
            const copy = new URL(u.href);
            copy.hash = `#page=${target}`;
            return copy.toString();
          }
        };
      }
    }

    // 3) Path /page/2 (ou .../galerie/2)
    const segs = u.pathname.split('/').filter(Boolean);
    const idx = segs.findIndex(s => s.toLowerCase() === 'page' || /^\d+$/.test(s));
    if (idx >= 0) {
      const maybePage = parseInt(segs[idx] === 'page' ? segs[idx+1] : segs[idx], 10);
      if (Number.isFinite(maybePage)) {
        return {
          page: maybePage,
          pathIndex: segs[idx] === 'page' ? idx+1 : idx,
          hrefTemplate: (target) => {
            const copy = new URL(u.href);
            const parts = copy.pathname.split('/').filter(Boolean);
            const i = segs[idx] === 'page' ? idx+1 : idx;
            parts[i] = String(target);
            copy.pathname = '/' + parts.join('/');
            return copy.toString();
          }
        };
      }
    }

    // 4) Sinon essayer le label (texte du lien)
    if (labelText && /^\d+$/.test(labelText.trim())) {
      const num = parseInt(labelText.trim(), 10);
      return {
        page: num,
        hrefTemplate: (target) => {
          // si on n'a pas de template fiable, fallback query ?page=
          const copy = new URL(u.href);
          copy.searchParams.set('page', String(target));
          return copy.toString();
        }
      };
    }
  } catch {}
  return null;
}

function buildUrlFromTemplate(pi, targetPage) {
  if (!pi || !Number.isFinite(targetPage) || targetPage < 1) return null;
  if (pi.hrefTemplate) return pi.hrefTemplate(targetPage);

  // Fallback : ajouter/écraser ?page=
  try {
    const u = new URL(location.href);
    u.searchParams.set('page', String(targetPage));
    return u.toString();
  } catch {
    return null;
  }
}

function waitForGalleryChange(timeoutMs = 4000) {
  const root = galleryRoot();
  return new Promise((resolve) => {
    let done = false;
    const to = setTimeout(() => { if (!done) { done = true; mo.disconnect(); resolve(false); } }, timeoutMs);
    const mo = new MutationObserver(() => {
      if (done) return;
      done = true; clearTimeout(to); mo.disconnect(); resolve(true);
    });
    mo.observe(root, { childList: true, subtree: true });
  });
}

async function fetchAndSwap(url) {
  try {
    const res = await fetch(url, { credentials: 'include' });
    if (!res.ok) return false;
    const html = await res.text();
    const doc = new DOMParser().parseFromString(html, 'text/html');

    const newGrid = doc.querySelector('#gallery-grid') || doc.querySelector('#section-gallery .gallery-grid');
    const newPag  = doc.querySelector('#gallery-pagination') || doc.querySelector('#section-gallery .pagination');

    const grid = $('#gallery-grid') || $('#section-gallery .gallery-grid');
    const pag  = $('#gallery-pagination') || $('#section-gallery .pagination');

    if (newGrid && grid) grid.innerHTML = newGrid.innerHTML;
    if (newPag  && pag ) pag.innerHTML  = newPag.innerHTML;

    const evt = new CustomEvent('gallery:page-changed', { bubbles: true });
    (grid || document).dispatchEvent(evt);

    return true;
  } catch {
    return false;
  }
}

async function changePage(dir) {
  // 1) Essayer click sur pagination existante
  const { el, url } = findPagination(dir);
  if (el) {
    const changedPromise = waitForGalleryChange();
    // simuler un clic natif (même si le bouton est masqué par l'overlay)
    el.dispatchEvent(new MouseEvent('click', { bubbles:true, cancelable:true }));
    const changed = await changedPromise;
    if (changed) return true;
  }

  // 2) Fallback : fetch/swap avec URL calculée
  if (url) {
    const swapped = await fetchAndSwap(url);
    if (swapped) return true;
  }

  return false;
}

/* --------------------- Rendu image courante --------------------- */
function filenameFromUrl(u){
  try { const p = new URL(u, location.href).pathname; return p.split('/').pop() || 'image'; }
  catch { return 'image'; }
}

function renderCurrent(preloadNeighbors=false) {
  const it = items[current];
  if (!it) return;

  const liveFull = pickFullUrl(it.img);
  const href = (liveFull && liveFull !== '#') ? liveFull : (it.img.currentSrc || it.img.src);

  imgEl.src = href;
  imgEl.alt = it.title || '';
  titleEl.textContent = it.title || '';
  descEl.textContent  = it.desc || '';
  btnDl.href = href;
  btnDl.setAttribute('download', filenameFromUrl(href));

  imgEl.onerror = () => {
    if (href !== it.img.currentSrc && href !== it.img.src) {
      imgEl.src = it.img.currentSrc || it.img.src;
      btnDl.href = imgEl.src;
      btnDl.setAttribute('download', filenameFromUrl(imgEl.src));
    }
  };

  if (preloadNeighbors) {
    const n1 = items[(current+1)%items.length];
    const p1 = items[(current-1+items.length)%items.length];
    [n1, p1].forEach(n => { if(!n) return; const i=new Image(); i.src = pickFullUrl(n.img) || n.img.currentSrc || n.img.src; });
  }
}
