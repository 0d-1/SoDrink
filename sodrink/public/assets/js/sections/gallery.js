import API from '../api.js';

const BASE = window.SODRINK_BASE || '';
const PER = 9;
const STORAGE_VIEW_KEY = 'sodrink.gallery.view';
const BLANK_IMG = 'data:image/gif;base64,R0lGODlhAQABAAAAACw=';

let currentUser = null;
let perPage = PER;
let requestSeq = 0;

const state = {
  page: 1,
  query: '',
  sort: 'recent',
  mine: false,
  view: 'grid',
};

const els = {};

const h = (html) => {
  const t = document.createElement('template');
  t.innerHTML = html.trim();
  return t.content.firstChild;
};

const ESCAPE_MAP = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ESCAPE_MAP[ch] || ch);

function toImgUrl(p) {
  if (!p) return '';
  if (/^https?:\/\//i.test(p)) return p;
  if (p.startsWith(`${BASE}/`)) return p;
  if (p.startsWith('/')) return `${BASE}${p}`;
  if (p.startsWith('uploads/')) return `${BASE}/${p}`;
  return `${BASE}/uploads/gallery/${p}`;
}

async function me() {
  try {
    return (await API.me()).user;
  } catch {
    return null;
  }
}

function canManage(item) {
  if (!currentUser) return false;
  if (currentUser.role === 'admin') return true;
  return Number(item.author?.id || 0) === Number(currentUser.id || -1);
}

function commentLineHtml(c) {
  const pseudo = esc(c.pseudo || '—');
  const text = esc(c.text || '').replace(/\n/g, '<br>');
  const avatar = c.avatar || `${BASE}/assets/img/ui/avatar-default.svg`;
  return `
    <div class="comment-line">
      <img src="${avatar}" alt="">
      <div><strong>${pseudo}</strong><div>${text}</div></div>
    </div>`;
}

function renderCommentForm(id) {
  if (!currentUser) {
    return '<div class="muted">Connectez-vous pour commenter.</div>';
  }
  return `
    <form class="form comments-form" data-add-comment="${id}">
      <input name="text" maxlength="600" placeholder="Écrire un commentaire…">
      <button class="btn btn-primary btn-sm">Publier</button>
    </form>`;
}

function renderEditControls(it) {
  const title = esc(it.title || '');
  const desc = esc(it.description || '');
  return `
    <div class="admin-actions">
      <button class="btn btn-sm" data-edit="${it.id}" type="button">Modifier</button>
      <button class="btn btn-danger btn-sm" data-del="${it.id}" type="button">Supprimer</button>
    </div>
    <form class="form edit-form" data-form="${it.id}" hidden>
      <div class="inline">
        <input name="title" maxlength="120" placeholder="Titre" value="${title}">
        <input name="description" maxlength="500" placeholder="Description" value="${desc}">
      </div>
      <div class="inline">
        <button class="btn btn-primary btn-sm" data-save="${it.id}">Enregistrer</button>
        <button class="btn btn-sm" data-cancel="${it.id}" type="button">Annuler</button>
      </div>
    </form>`;
}

function cardHtml(it) {
  const src = toImgUrl(it.path);
  const author = it.author || {};
  const manage = canManage(it);
  const likeActive = it.liked_by_me ? ' is-active' : '';
  const likePressed = it.liked_by_me ? 'true' : 'false';
  const authorName = author.pseudo ? esc(author.pseudo) : '—';
  const desc = esc(it.description || '').replace(/\n/g, '<br>');
  const title = esc(it.title || '');
  const commentsPreview = (it.comments_preview || []).map((c) => commentLineHtml(c)).join('');
  const authorHtml = author.pseudo
    ? `Par <a class="author-link" href="${BASE}/user.php?u=${encodeURIComponent(author.pseudo)}">${authorName}</a>`
    : '<span class="muted">Auteur inconnu</span>';

  return `
  <figure class="photo-card photo-card-lg" data-id="${it.id}">
    <img loading="lazy" src="${src}" alt="${title}">
    <figcaption>
      <div class="grid-row">
        <div class="text">
          <strong class="title">${title}</strong>
          <span class="muted desc">${desc || '—'}</span>
        </div>
        <div class="author">${authorHtml}</div>
      </div>

      <div class="inline photo-card__actions">
        <button class="btn btn-sm btn-like${likeActive}" type="button" data-like="${it.id}" aria-pressed="${likePressed}" aria-label="${it.liked_by_me ? 'Retirer le like' : 'Aimer cette photo'}">
          <span class="icon" aria-hidden="true">❤️</span>
          <span data-like-count="${it.id}">${it.likes_count || 0}</span>
        </button>
        <button class="btn btn-sm" type="button" data-comments-toggle="${it.id}" aria-expanded="false">
          💬 <span data-comm-count="${it.id}">${it.comments_count || 0}</span>
        </button>
      </div>

      <div class="comments" data-comments="${it.id}" hidden>
        <div class="comments-list">
          ${commentsPreview}
        </div>
        ${renderCommentForm(it.id)}
      </div>

      ${manage ? renderEditControls(it) : ''}
    </figcaption>
  </figure>`;
}

function renderItems(container, items) {
  if (!container) return;
  container.innerHTML = '';
  container.classList.remove('is-empty');
  if (!items.length) {
    container.classList.add('is-empty');
    container.appendChild(h(`
      <div class="gallery-empty">
        <strong>Aucune photo ne correspond à vos filtres.</strong>
        <span>Essayez d’élargir la recherche ou partagez votre premier souvenir !</span>
      </div>`));
    return;
  }
  items.forEach((it) => container.appendChild(h(cardHtml(it))));
}

function createPageButton(label, targetPage, ariaLabel = null, isCurrent = false) {
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.textContent = label;
  btn.className = 'btn btn-sm btn-pill';
  if (ariaLabel) btn.setAttribute('aria-label', ariaLabel);
  if (isCurrent) {
    btn.classList.add('btn-primary');
    btn.disabled = true;
    btn.setAttribute('aria-current', 'page');
  } else if (targetPage) {
    btn.addEventListener('click', () => load(targetPage));
  } else {
    btn.disabled = true;
  }
  return btn;
}

function createEllipsis() {
  const span = document.createElement('span');
  span.className = 'ellipsis';
  span.textContent = '…';
  return span;
}

function renderPagination(container, page, per, total) {
  if (!container) return;
  container.innerHTML = '';
  const totalPages = Math.max(1, Math.ceil(total / per));
  if (totalPages <= 1) {
    container.hidden = true;
    return;
  }
  container.hidden = false;

  container.appendChild(createPageButton('‹', page > 1 ? page - 1 : null, 'Page précédente'));

  const windowSize = 2;
  const start = Math.max(1, page - windowSize);
  const end = Math.min(totalPages, page + windowSize);

  if (start > 1) container.appendChild(createPageButton('1', 1, 'Page 1'));
  if (start > 2) container.appendChild(createEllipsis());

  for (let p = start; p <= end; p += 1) {
    container.appendChild(createPageButton(String(p), p, `Page ${p}`, p === page));
  }

  if (end < totalPages - 1) container.appendChild(createEllipsis());
  if (end < totalPages) container.appendChild(createPageButton(String(totalPages), totalPages, `Page ${totalPages}`));

  container.appendChild(createPageButton('›', page < totalPages ? page + 1 : null, 'Page suivante'));
}

function status(message = '', type = 'info') {
  if (!els.status) return;
  if (!message) {
    els.status.hidden = true;
    els.status.textContent = '';
    els.status.removeAttribute('data-type');
    return;
  }
  els.status.hidden = false;
  els.status.textContent = message;
  els.status.dataset.type = type;
}

function renderLoading(container) {
  if (!container) return;
  container.innerHTML = '';
  container.classList.remove('is-empty');
  const count = Math.max(3, Math.min(PER, Math.ceil((container.offsetWidth || 780) / 260)));
  const cards = Array.from({ length: count }, () => `
    <figure class="photo-card photo-card-lg is-skeleton">
      <img src="${BLANK_IMG}" alt="" aria-hidden="true">
      <figcaption>
        <div class="skeleton-line" style="width:85%"></div>
        <div class="skeleton-line" style="width:65%"></div>
        <div class="skeleton-line" style="width:40%"></div>
      </figcaption>
    </figure>`);
  cards.forEach((markup) => container.appendChild(h(markup)));
}

async function fetchPage() {
  const qs = new URLSearchParams({ page: String(state.page), per: String(PER) });
  if (state.query) qs.set('q', state.query);
  if (state.sort && state.sort !== 'recent') qs.set('sort', state.sort);
  if (state.mine && currentUser) qs.set('author_id', String(currentUser.id));
  return API.get(`/api/sections/gallery.php?${qs}`);
}

async function load(page = 1) {
  if (!els.grid) return;
  state.page = page;
  const seq = ++requestSeq;
  renderLoading(els.grid);
  status('Chargement…', 'info');

  try {
    const { items, total, per } = await fetchPage();
    if (seq !== requestSeq) return;
    perPage = per;

    if (!items.length && total > 0 && state.page > 1) {
      const lastPage = Math.max(1, Math.ceil(total / perPage));
      if (state.page !== lastPage) {
        state.page = lastPage;
        load(state.page);
        return;
      }
    }

    renderItems(els.grid, items);
    renderPagination(els.pagination, state.page, perPage, total);

    if (!total) {
      status('Aucune photo trouvée pour le moment.', 'empty');
    } else {
      const start = (state.page - 1) * perPage + 1;
      const end = Math.min(total, start + items.length - 1);
      status(`${total} photo${total > 1 ? 's' : ''} • ${start}-${end}`, 'info');
    }
  } catch (err) {
    if (seq !== requestSeq) return;
    console.error(err);
    els.grid.innerHTML = '';
    els.grid.classList.add('is-empty');
    els.grid.appendChild(h(`
      <div class="gallery-empty">
        <strong>Oups…</strong>
        <span>Impossible de charger la galerie pour le moment.</span>
      </div>`));
    if (els.pagination) els.pagination.hidden = true;
    status('Une erreur est survenue lors du chargement.', 'error');
  }
}

async function getCSRF() {
  const j = await fetch(`${BASE}/api/csrf.php`, { credentials: 'include' }).then((r) => r.json());
  return j.data.csrf_token;
}

async function checkLogged() {
  currentUser = await me();
  return !!currentUser;
}

function updateMineToggle() {
  if (!els.filterMine) return;
  const wrap = els.filterMine.closest('.toolbar-toggle');
  if (!currentUser) {
    state.mine = false;
    els.filterMine.checked = false;
    els.filterMine.disabled = true;
    if (wrap) wrap.classList.add('is-disabled');
  } else {
    els.filterMine.disabled = false;
    if (wrap) wrap.classList.remove('is-disabled');
  }
}

function setView(view, persist = true) {
  if (!els.grid) return;
  state.view = view;
  els.grid.dataset.view = view;
  if (els.viewButtons) {
    els.viewButtons.forEach((btn) => {
      const active = btn.dataset.galleryView === view;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }
  if (persist) {
    try { localStorage.setItem(STORAGE_VIEW_KEY, view); } catch (_) { /* ignore */ }
  }
}

function restoreViewPreference() {
  let stored = null;
  try { stored = localStorage.getItem(STORAGE_VIEW_KEY); } catch (_) { stored = null; }
  if (stored === 'grid' || stored === 'list') {
    setView(stored, false);
  } else {
    setView(state.view, false);
  }
}

function syncControls() {
  if (els.search) els.search.value = state.query;
  if (els.sort) els.sort.value = state.sort;
  if (els.filterMine) els.filterMine.checked = state.mine && !!currentUser;
}

function debounce(fn, delay = 250) {
  let to = null;
  return (...args) => {
    clearTimeout(to);
    to = setTimeout(() => fn(...args), delay);
  };
}

function bindUpload() {
  const form = document.getElementById('form-gallery-upload');
  if (!form) return;
  (async () => {
    if (!(await checkLogged())) {
      form.innerHTML = '<em class="muted">Connectez-vous pour publier une photo.</em>';
      form.classList.add('disabled');
      return;
    }
    updateMineToggle();
  })();

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    fd.append('csrf_token', await getCSRF());
    try {
      const res = await fetch(`${BASE}/api/sections/gallery.php`, { method: 'POST', body: fd, credentials: 'include' });
      const j = await res.json();
      if (!j.success) throw new Error(j.error || 'Erreur');
      form.reset();
      load(1);
      status('Votre photo a bien été publiée ✅', 'info');
    } catch (err) {
      alert(err.message || 'Erreur');
    }
  });
}

function bindFilters() {
  if (els.search) {
    const applySearch = debounce((value) => {
      const next = value.trim();
      if (state.query === next) return;
      state.query = next;
      state.page = 1;
      load(1);
    }, 280);
    els.search.addEventListener('input', (e) => applySearch(e.target.value));
  }

  if (els.sort) {
    els.sort.addEventListener('change', () => {
      const val = els.sort.value;
      state.sort = ['recent', 'popular', 'commented'].includes(val) ? val : 'recent';
      state.page = 1;
      load(1);
    });
  }

  if (els.filterMine) {
    els.filterMine.addEventListener('change', () => {
      if (!currentUser) {
        els.filterMine.checked = false;
        status('Connectez-vous pour afficher vos publications.', 'error');
        return;
      }
      state.mine = els.filterMine.checked;
      state.page = 1;
      load(1);
    });
  }

  if (els.viewButtons) {
    els.viewButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        const view = btn.dataset.galleryView;
        if (view === 'grid' || view === 'list') {
          setView(view);
        }
      });
    });
  }
}

function bindActions() {
  const grid = els.grid;
  if (!grid) return;

  grid.addEventListener('click', async (event) => {
    const target = event.target.closest('[data-like], [data-comments-toggle], [data-edit], [data-del], [data-save], [data-cancel]');
    if (!target || !grid.contains(target)) return;

    const id = target.getAttribute('data-like') ||
      target.getAttribute('data-comments-toggle') ||
      target.getAttribute('data-edit') ||
      target.getAttribute('data-del') ||
      target.getAttribute('data-save') ||
      target.getAttribute('data-cancel');
    if (!id) return;

    if (target.hasAttribute('data-like')) {
      if (!currentUser) {
        status('Connectez-vous pour aimer les photos.', 'error');
        return;
      }
      try {
        const r = await API.post(`/api/sections/gallery-like.php?id=${id}`, {});
        const countEl = grid.querySelector(`[data-like-count="${id}"]`);
        if (countEl) countEl.textContent = r.count;
        target.classList.toggle('is-active', !!r.liked);
        target.setAttribute('aria-pressed', r.liked ? 'true' : 'false');
      } catch (err) {
        console.error(err);
        status('Impossible de mettre à jour votre like.', 'error');
      }
      return;
    }

    if (target.hasAttribute('data-comments-toggle')) {
      const box = grid.querySelector(`[data-comments="${id}"]`);
      if (!box) return;
      const willOpen = box.hidden;
      box.hidden = !box.hidden;
      target.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      if (willOpen && !box.dataset.loaded) {
        try {
          const j = await fetch(`${BASE}/api/sections/gallery-comments.php?id=${id}`, { credentials: 'include' }).then((r) => r.json());
          if (j?.success) {
            const list = box.querySelector('.comments-list');
            list.innerHTML = (j.data.items || []).map((c) => commentLineHtml(c)).join('');
            box.dataset.loaded = '1';
          }
        } catch (err) {
          console.error(err);
        }
      }
      return;
    }

    if (target.hasAttribute('data-edit')) {
      const form = grid.querySelector(`[data-form="${id}"]`);
      if (form) form.hidden = !form.hidden;
      return;
    }

    if (target.hasAttribute('data-cancel')) {
      const form = target.closest('form');
      if (form) form.hidden = true;
      return;
    }

    if (target.hasAttribute('data-del')) {
      if (!confirm('Supprimer cette photo ?')) return;
      try {
        await API.del(`/api/sections/gallery.php?id=${id}`);
        load(1);
      } catch (err) {
        console.error(err);
        status('Suppression impossible.', 'error');
      }
      return;
    }

    if (target.hasAttribute('data-save')) {
      event.preventDefault();
      const form = grid.querySelector(`[data-form="${id}"]`);
      if (!form) return;
      const fd = new FormData(form);
      const payload = Object.fromEntries(fd.entries());
      try {
        await API.put(`/api/sections/gallery.php?id=${id}`, payload);
        form.hidden = true;
        load(state.page);
      } catch (err) {
        console.error(err);
        status('Impossible d’enregistrer les modifications.', 'error');
      }
    }
  });

  grid.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-add-comment]');
    if (!form) return;
    event.preventDefault();
    const id = form.getAttribute('data-add-comment');
    const input = form.querySelector('input[name="text"]');
    if (!input) return;
    const text = input.value.trim();
    if (!text) return;

    try {
      const res = await fetch(`${BASE}/api/sections/gallery-comments.php?id=${id}`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': await getCSRF(),
        },
        body: JSON.stringify({ text }),
      });
      const j = await res.json();
      if (!j.success) throw new Error(j.error || 'Erreur');
      form.reset();
      const list = form.parentElement.querySelector('.comments-list');
      const c = j.data.comment;
      list.appendChild(h(commentLineHtml(c)));
      const cnt = grid.querySelector(`[data-comm-count="${id}"]`);
      if (cnt) cnt.textContent = String(Number(cnt.textContent || 0) + 1);
    } catch (err) {
      alert(err.message || 'Erreur');
    }
  });
}

function assignElements() {
  els.grid = document.getElementById('gallery-grid');
  els.pagination = document.getElementById('gallery-pagination');
  els.status = document.getElementById('gallery-status');
  els.search = document.getElementById('gallery-search');
  els.sort = document.getElementById('gallery-sort');
  els.filterMine = document.getElementById('gallery-filter-mine');
  els.viewButtons = Array.from(document.querySelectorAll('[data-gallery-view]'));
}

window.addEventListener('DOMContentLoaded', async () => {
  assignElements();
  await checkLogged();
  updateMineToggle();
  restoreViewPreference();
  syncControls();
  bindUpload();
  bindFilters();
  bindActions();
  load(1);

  document.addEventListener('gallery:page-changed', () => {
    load(state.page);
  });
});
