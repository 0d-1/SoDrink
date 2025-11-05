// user.js — charge un profil public + sa galerie enrichie
import API from './api.js';
const BASE = window.SODRINK_BASE || '';
const PER = 4;

function $(s, el = document) { return el.querySelector(s); }
function h(html) { const t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; }
function escapeHtml(str = '') {
  return str.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }[c]));
}

const relationshipLabels = {
  single: 'Célibataire',
  relationship: 'En couple',
  married: 'Marié·e',
  complicated: 'C’est compliqué',
  hidden: 'Préférer ne pas dire',
};

function getQuery() {
  const u = new URL(location.href);
  return { id: u.searchParams.get('id'), u: u.searchParams.get('u') };
}

function toImgUrl(p) {
  if (!p) return '';
  if (/^https?:\/\//i.test(p)) return p;
  if (p.startsWith(BASE + '/')) return p;
  if (p.startsWith('/')) return BASE + p;
  return `${BASE}/uploads/gallery/${p}`;
}

function createChip(icon, text, href) {
  const el = document.createElement(href ? 'a' : 'span');
  el.className = 'chip';
  if (href) {
    el.href = href;
    el.target = '_blank';
    el.rel = 'noopener noreferrer';
  }
  el.innerHTML = `<span class="chip-icon">${icon}</span><span>${escapeHtml(text)}</span>`;
  return el;
}

function renderProfile(user) {
  $('#user-title').textContent = `Profil de ${user.pseudo}`;
  $('#pub-avatar').src = user.avatar || (BASE + '/assets/img/ui/avatar-default.svg');
  $('#pub-fullname').textContent = `${user.prenom || ''} ${user.nom || ''}`.trim() || '—';
  const banner = $('#pub-banner');
  if (banner) {
    banner.style.backgroundImage = user.banner ? `url(${user.banner})` : '';
  }

  const meta = $('#pub-meta');
  if (meta) {
    meta.innerHTML = '';
    if (user.location) meta.appendChild(createChip('📍', user.location));
    if (user.relationship_status) meta.appendChild(createChip('❤️', relationshipLabels[user.relationship_status] || user.relationship_status));
    if (user.instagram) meta.appendChild(createChip('📸', '@' + user.instagram.toLowerCase(), `https://instagram.com/${user.instagram}`));
    if (user.website) meta.appendChild(createChip('🌐', user.website.replace(/^https?:\/\//i, ''), user.website));
    if (user.email) meta.appendChild(createChip('✉️', user.email, `mailto:${user.email}`));
    if (!meta.children.length) {
      const empty = document.createElement('p');
      empty.className = 'muted';
      empty.textContent = 'Aucune information supplémentaire';
      meta.appendChild(empty);
    }
  }

  const bio = $('#pub-bio');
  if (bio) {
    const text = (user.bio || '').trim();
    if (text) {
      bio.textContent = text;
      bio.style.whiteSpace = 'pre-line';
      bio.classList.remove('muted');
    } else {
      bio.textContent = 'Cet utilisateur n’a pas encore partagé d’informations.';
      bio.classList.add('muted');
    }
  }

  const linksContainer = $('#pub-links');
  if (linksContainer) {
    linksContainer.innerHTML = '';
    const links = user.links || [];
    if (links.length) {
      links.forEach((link) => {
        const a = document.createElement('a');
        a.className = 'btn btn-sm btn-outline';
        a.href = link.url;
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.textContent = link.label;
        linksContainer.appendChild(a);
      });
    } else {
      const empty = document.createElement('p');
      empty.className = 'muted';
      empty.textContent = 'Aucun lien partagé pour le moment.';
      linksContainer.appendChild(empty);
    }
  }

  const contact = $('#pub-contact');
  if (contact) {
    contact.innerHTML = '';
    const list = document.createElement('dl');
    list.className = 'contact-list';

    const addRow = (label, value, href) => {
      if (!value) return;
      const dt = document.createElement('dt');
      dt.textContent = label;
      const dd = document.createElement('dd');
      if (href) {
        const a = document.createElement('a');
        a.href = href;
        a.textContent = value;
        if (href.startsWith('http')) {
          a.target = '_blank';
          a.rel = 'noopener noreferrer';
        }
        dd.appendChild(a);
      } else {
        dd.textContent = value;
      }
      list.appendChild(dt);
      list.appendChild(dd);
    };

    addRow('Pseudo', user.pseudo);
    addRow('Instagram', user.instagram ? '@' + user.instagram : '', user.instagram ? `https://instagram.com/${user.instagram}` : '');
    addRow('Email', user.email, user.email ? `mailto:${user.email}` : '');
    addRow('Site web', user.website ? user.website.replace(/^https?:\/\//i, '') : '', user.website || '');

    if (list.children.length) {
      contact.appendChild(list);
    }
  }

  const btnMessage = $('#btn-message');
  if (btnMessage) {
    btnMessage.href = `${BASE}/chat.php?u=${encodeURIComponent(user.pseudo)}`;
  }
}

async function loadProfile() {
  const q = getQuery();
  const qs = new URLSearchParams(q.u ? { u: q.u } : { id: q.id || '' }).toString();
  const j = await fetch(`${BASE}/api/users/get.php?${qs}`, { credentials: 'include' }).then((r) => r.json());
  if (!j?.success) {
    $('#user-title').textContent = 'Utilisateur introuvable';
    return null;
  }
  const u = j.data.user;
  renderProfile(u);
  return u;
}

async function fetchGallery(page, authorId) {
  const qs = new URLSearchParams({ page: String(page), per: String(PER), author_id: String(authorId) });
  const j = await API.get(`/api/sections/gallery.php?${qs.toString()}`);
  return j;
}

function renderGallery(container, items) {
  container.innerHTML = '';
  if (!items.length) { container.textContent = 'Aucune photo.'; return; }
  items.forEach((it) => {
    const card = h(`
      <figure class="photo-card photo-card-lg">
        <img loading="lazy" src="${toImgUrl(it.path)}" alt="${(it.title || '').replaceAll('"','&quot;')}">
        <figcaption><strong>${it.title || ''}</strong> <span class="muted">${it.description || ''}</span></figcaption>
      </figure>`);
    container.appendChild(card);
  });
}

function renderPag(container, page, per, total, cb) {
  container.innerHTML = '';
  const pages = Math.max(1, Math.ceil(total / per));
  for (let p = 1; p <= pages; p++) {
    const a = document.createElement('button');
    a.textContent = p;
    a.className = 'btn ' + (p === page ? 'btn-primary' : '');
    a.addEventListener('click', () => cb(p));
    container.appendChild(a);
  }
}

window.addEventListener('DOMContentLoaded', async () => {
  const u = await loadProfile();
  if (!u) return;
  async function loadPage(p = 1) {
    const { items, per, total } = await fetchGallery(p, u.id);
    renderGallery($('#user-gallery'), items);
    renderPag($('#user-gallery-pagination'), p, per, total, loadPage);
  }
  loadPage(1);
});
