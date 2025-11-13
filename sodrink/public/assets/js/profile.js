// profile.js — édition profil avancée + upload avatar/bannière
import API from './api.js';
const BASE = window.SODRINK_BASE || '';

function $(selector, scope = document) {
  return scope.querySelector(selector);
}
function $all(selector, scope = document) {
  return Array.from(scope.querySelectorAll(selector));
}
function escapeHtml(str = '') {
  return str.replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
}

const MAX_LINKS = 5;
let currentUser = null;
let currentBanner = null;
let savedBanner = null;
let currentAvatar = null;
let uclAutomations = [];
let editingAutomationId = null;
let editingAutomationHasPassword = false;

const NOTIF_KEYS = ['messages', 'events', 'gallery', 'torpille', 'announcements'];

const relationshipLabels = {
  single: 'Célibataire',
  relationship: 'En couple',
  married: 'Marié·e',
  complicated: 'C’est compliqué',
  hidden: 'Préférer ne pas dire',
};

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

function updateAddLinkState() {
  const btn = $('#add-link');
  if (!btn) return;
  const count = $all('.link-row').length;
  btn.disabled = count >= MAX_LINKS;
}

function createLinkRow(link = { label: '', url: '' }) {
  const row = document.createElement('div');
  row.className = 'link-row';
  row.innerHTML = `
    <input type="text" class="link-label" placeholder="Titre" maxlength="40" value="${escapeHtml(link.label || '')}">
    <input type="url" class="link-url" placeholder="https://..." value="${escapeHtml(link.url || '')}">
    <button type="button" class="btn btn-sm btn-outline remove-link" aria-label="Retirer le lien">✕</button>
  `;
  row.querySelector('.remove-link').addEventListener('click', () => {
    row.remove();
    updateAddLinkState();
    refreshPreview();
  });
  row.querySelector('.link-label').addEventListener('input', refreshPreview);
  row.querySelector('.link-url').addEventListener('input', refreshPreview);
  return row;
}

function renderLinks(links = []) {
  const container = $('#profile-links');
  if (!container) return;
  container.innerHTML = '';
  (links.length ? links : [{}]).forEach((link) => {
    container.appendChild(createLinkRow(link));
  });
  updateAddLinkState();
}

function applyNotificationSettings(settings = {}) {
  const form = $('#form-notifications');
  if (!form) return;
  NOTIF_KEYS.forEach((key) => {
    const input = form.querySelector(`input[data-setting="${key}"]`);
    if (input) {
      input.checked = Boolean(settings[key]);
    }
  });
}

function collectNotificationSettingsForSubmit() {
  const form = $('#form-notifications');
  const result = {};
  if (!form) return result;
  NOTIF_KEYS.forEach((key) => {
    const input = form.querySelector(`input[data-setting="${key}"]`);
    if (input) {
      result[key] = Boolean(input.checked);
    }
  });
  return result;
}

function collectLinksForSubmit() {
  const links = [];
  $all('.link-row').forEach((row) => {
    const label = row.querySelector('.link-label').value.trim();
    const url = row.querySelector('.link-url').value.trim();
    if (!label && !url) return;
    if (!label || !url) {
      throw new Error('Complète chaque lien avec un titre et une URL.');
    }
    links.push({ label, url });
  });
  return links;
}

function collectLinksForPreview() {
  const links = [];
  $all('.link-row').forEach((row) => {
    const label = row.querySelector('.link-label').value.trim();
    const url = row.querySelector('.link-url').value.trim();
    if (!label || !url) return;
    links.push({ label, url });
  });
  return links;
}

function formDataSnapshot() {
  const form = $('#form-profile');
  if (!form) return {};
  const data = {
    pseudo: form.pseudo?.value.trim() || '',
    prenom: form.prenom?.value.trim() || '',
    nom: form.nom?.value.trim() || '',
    instagram: form.instagram?.value.trim() || '',
    email: form.email?.value.trim() || '',
    location: form.location?.value.trim() || '',
    website: form.website?.value.trim() || '',
    relationship_status: form.relationship_status?.value || 'none',
    bio: form.bio?.value || '',
    links: collectLinksForPreview(),
    avatar: currentAvatar,
    banner: currentBanner,
  };
  return data;
}

function updatePreviewFrom(data) {
  $('#preview-avatar').src = data.avatar || (BASE + '/assets/img/ui/avatar-default.svg');
  $('#preview-pseudo').textContent = data.pseudo || '—';
  const fullName = `${data.prenom || ''} ${data.nom || ''}`.trim();
  $('#preview-fullname').textContent = fullName || '—';

  const bioEl = $('#preview-bio');
  if (data.bio && data.bio.trim()) {
    bioEl.textContent = data.bio.trim();
    bioEl.classList.remove('muted');
  } else {
    bioEl.textContent = 'Complète ta bio pour la partager avec tes amis.';
    bioEl.classList.add('muted');
  }
  bioEl.style.whiteSpace = 'pre-line';

  const bannerEl = $('#preview-banner');
  if (bannerEl) {
    bannerEl.style.backgroundImage = data.banner ? `url(${data.banner})` : '';
  }

  const meta = $('#preview-meta');
  if (meta) {
    meta.innerHTML = '';
    if (data.location) meta.appendChild(createChip('📍', data.location));
    const rel = data.relationship_status;
    if (rel && rel !== 'none') {
      meta.appendChild(createChip('❤️', relationshipLabels[rel] || rel));
    }
    if (data.email) meta.appendChild(createChip('✉️', data.email, `mailto:${data.email}`));
    if (data.website) meta.appendChild(createChip('🌐', data.website.replace(/^https?:\/\//i, ''), data.website));
    if (data.instagram) meta.appendChild(createChip('📸', '@' + data.instagram.toLowerCase(), `https://instagram.com/${data.instagram}`));
    if (!meta.children.length) {
      const empty = document.createElement('p');
      empty.className = 'muted';
      empty.textContent = 'Ajoute des infos pour les afficher ici.';
      meta.appendChild(empty);
    }
  }

  const linksEl = $('#preview-links');
  if (linksEl) {
    linksEl.innerHTML = '';
    const links = data.links || [];
    links.forEach((link) => {
      const a = document.createElement('a');
      a.className = 'btn btn-sm btn-outline';
      a.href = link.url;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      a.textContent = link.label;
      linksEl.appendChild(a);
    });
    if (!links.length) {
      const empty = document.createElement('p');
      empty.className = 'muted';
      empty.textContent = 'Ajoute tes liens préférés pour les afficher ici.';
      linksEl.appendChild(empty);
    }
  }
}

function refreshPreview() {
  updatePreviewFrom(formDataSnapshot());
}

async function loadMe() {
  try {
    const { user } = await API.me();
    currentUser = user;
    currentAvatar = user.avatar || (BASE + '/assets/img/ui/avatar-default.svg');
    currentBanner = user.banner || '';
    savedBanner = currentBanner;
    const form = $('#form-profile');
    if (form) {
      form.pseudo.value = user.pseudo || '';
      form.prenom.value = user.prenom || '';
      form.nom.value = user.nom || '';
      form.instagram.value = user.instagram || '';
      form.email.value = user.email || '';
      form.location.value = user.location || '';
      form.website.value = user.website || '';
      form.relationship_status.value = user.relationship_status || 'none';
      form.password.value = '';
      form.bio.value = user.bio || '';
    }
    renderLinks(user.links || []);
    applyNotificationSettings(user.notification_settings || {});
    const banner = $('#profile-banner');
    if (banner) {
      banner.style.backgroundImage = currentBanner ? `url(${currentBanner})` : '';
    }
    $('#profile-avatar').src = currentAvatar;
    $('#preview-avatar').src = currentAvatar;
    refreshPreview();
  } catch (e) {
    alert('Veuillez vous connecter.');
    location.href = BASE + '/';
  }
}

function bindProfileSave() {
  const form = $('#form-profile');
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = {
      pseudo: form.pseudo.value.trim(),
      prenom: form.prenom.value.trim(),
      nom: form.nom.value.trim(),
      instagram: form.instagram.value.trim(),
      email: form.email.value.trim(),
      location: form.location.value.trim(),
      website: form.website.value.trim(),
      relationship_status: form.relationship_status.value,
      bio: form.bio.value,
      links: [],
    };
    if (form.password.value.trim()) {
      body.password = form.password.value.trim();
    }
    try {
      body.links = collectLinksForSubmit();
      await API.put('/api/users/me.php', body);
      alert('Profil mis à jour');
      await loadMe();
    } catch (err) {
      alert(err.message);
    }
  });

  ['input', 'change'].forEach((eventName) => {
    form.addEventListener(eventName, (event) => {
      if (event.target.matches('input, textarea, select')) {
        if (event.target.name !== 'password') {
          refreshPreview();
        }
      }
    });
  });
}

async function getCSRF() {
  const j = await fetch(`${BASE}/api/csrf.php`, { credentials: 'include' }).then((r) => r.json());
  if (j?.success) return j.data.csrf_token;
  throw new Error('CSRF');
}

function bindAvatar() {
  const form = $('#form-avatar');
  if (!form) return;
  const input = form.querySelector('input[type=file]');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!input.files?.length) {
      alert('Choisis une image.');
      return;
    }
    const fd = new FormData(form);
    fd.append('csrf_token', await getCSRF());
    try {
      const res = await fetch(`${BASE}/api/users/avatar-upload.php`, {
        method: 'POST',
        body: fd,
        credentials: 'include',
      });
      const j = await res.json();
      if (!j.success) throw new Error(j.error || 'Erreur');
      currentAvatar = j.data.avatar;
      $('#profile-avatar').src = currentAvatar;
      refreshPreview();
      alert('Avatar mis à jour');
      form.reset();
    } catch (err) {
      alert(err.message);
    }
  });
}

function bindBanner() {
  const form = $('#form-banner');
  if (!form) return;
  const input = form.querySelector('input[type=file]');
  input?.addEventListener('change', () => {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = (ev) => {
        currentBanner = ev.target.result;
        $('#profile-banner').style.backgroundImage = `url(${currentBanner})`;
        refreshPreview();
      };
      reader.readAsDataURL(input.files[0]);
    }
  });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!input.files?.length) {
      alert('Choisis une bannière.');
      return;
    }
    const fd = new FormData();
    fd.append('banner', input.files[0]);
    fd.append('csrf_token', await getCSRF());
    const previous = savedBanner;
    try {
      const res = await fetch(`${BASE}/api/users/banner-upload.php`, {
        method: 'POST',
        body: fd,
        credentials: 'include',
      });
      const j = await res.json();
      if (!j.success) throw new Error(j.error || 'Erreur');
      currentBanner = j.data.banner;
      $('#profile-banner').style.backgroundImage = currentBanner ? `url(${currentBanner})` : '';
      refreshPreview();
      alert('Bannière mise à jour');
      savedBanner = currentBanner;
      form.reset();
    } catch (err) {
      currentBanner = previous;
      $('#profile-banner').style.backgroundImage = previous ? `url(${previous})` : '';
      refreshPreview();
      alert(err.message);
    }
  });
}

function bindAddLink() {
  $('#add-link')?.addEventListener('click', () => {
    const container = $('#profile-links');
    if (!container) return;
    if ($all('.link-row').length >= MAX_LINKS) return;
    container.appendChild(createLinkRow());
    updateAddLinkState();
  });
}

function bindNotificationsForm() {
  const form = $('#form-notifications');
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const { user } = await API.put('/api/users/me.php', {
        notification_settings: collectNotificationSettingsForSubmit(),
      });
      currentUser = user;
      applyNotificationSettings(user.notification_settings || {});
      alert('Préférences mises à jour');
    } catch (err) {
      alert(err.message);
    }
  });
}

function setUclStatus(message = '', isError = false) {
  const el = $('#ucl-automation-status');
  if (!el) return;
  el.textContent = message;
  el.style.color = isError && message ? '#b91c1c' : '';
}

function renderUclAutomations() {
  const table = $('#ucl-automation-table');
  const tbody = $('#ucl-automation-table tbody');
  const empty = $('#ucl-automation-empty');
  if (!table || !tbody || !empty) return;

  tbody.innerHTML = '';
  if (!uclAutomations.length) {
    table.style.display = 'none';
    empty.hidden = false;
    return;
  }

  table.style.display = '';
  empty.hidden = true;

  uclAutomations.forEach((item) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><strong>${escapeHtml(item.sport || '')}</strong><br><span class="muted">${escapeHtml(item.ucl_username || '')}</span></td>
      <td>${escapeHtml(item.session_date || '')}</td>
      <td>${escapeHtml(item.time_slot || '')}</td>
      <td>${escapeHtml(item.campus || '')}</td>
      <td>${item.weekly ? 'Oui' : 'Non'}</td>
      <td class="inline" style="gap:.35rem">
        <button type="button" class="btn btn-sm" data-edit="${item.id}">Modifier</button>
        <button type="button" class="btn btn-sm danger" data-delete="${item.id}">Supprimer</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function startEditAutomation(item) {
  const form = $('#ucl-automation-form');
  if (!form || !item) return;
  editingAutomationId = item.id;
  editingAutomationHasPassword = Boolean(item.has_password);
  const hiddenId = form.querySelector('input[name="id"]');
  if (hiddenId) hiddenId.value = item.id;
  form.ucl_username.value = item.ucl_username || '';
  form.ucl_password.value = '';
  form.sport.value = item.sport || '';
  form.campus.value = item.campus || '';
  form.session_date.value = item.session_date || '';
  form.time_slot.value = item.time_slot || '';
  form.weekly.checked = Boolean(item.weekly);
  form.headless.checked = Boolean(item.headless);
  setUclStatus(`Modification de l’automatisation « ${item.sport} » (ID ${item.id}).`);
  form.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function resetUclForm() {
  const form = $('#ucl-automation-form');
  if (!form) return;
  editingAutomationId = null;
  editingAutomationHasPassword = false;
  const hiddenId = form.querySelector('input[name="id"]');
  if (hiddenId) hiddenId.value = '';
  form.ucl_username.value = '';
  form.ucl_password.value = '';
  form.sport.value = '';
  form.campus.value = '';
  form.session_date.value = '';
  form.time_slot.value = '';
  if (form.weekly) form.weekly.checked = false;
  if (form.headless) form.headless.checked = true;
  setUclStatus('');
}

async function loadUclAutomations() {
  try {
    const { automations } = await API.get('/api/uclsport/automations.php');
    uclAutomations = Array.isArray(automations) ? automations : [];
    renderUclAutomations();
  } catch (err) {
    setUclStatus(err.message || 'Impossible de charger les automatisations.', true);
  }
}

function bindUclAutomations() {
  const form = $('#ucl-automation-form');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = {
      ucl_username: form.ucl_username.value.trim(),
      sport: form.sport.value.trim(),
      campus: form.campus.value.trim(),
      session_date: form.session_date.value.trim(),
      time_slot: form.time_slot.value.trim(),
      weekly: Boolean(form.weekly.checked),
      headless: Boolean(form.headless.checked),
    };
    const pwd = form.ucl_password.value;
    if (pwd && pwd.trim()) {
      body.ucl_password = pwd.trim();
    }

    try {
      if (editingAutomationId) {
        if (!pwd && !editingAutomationHasPassword) {
          setUclStatus('Ajoute un mot de passe pour cette automatisation.', true);
          return;
        }
        await API.put(`/api/uclsport/automations.php?id=${editingAutomationId}`, body);
        setUclStatus('Automatisation mise à jour.');
      } else {
        await API.post('/api/uclsport/automations.php', body);
        setUclStatus('Automatisation enregistrée.');
      }
      resetUclForm();
      await loadUclAutomations();
    } catch (err) {
      setUclStatus(err.message || 'Erreur lors de l’enregistrement.', true);
    }
  });

  form.addEventListener('reset', (e) => {
    e.preventDefault();
    resetUclForm();
  });

  const table = $('#ucl-automation-table');
  table?.addEventListener('click', async (e) => {
    const editBtn = e.target.closest('button[data-edit]');
    const deleteBtn = e.target.closest('button[data-delete]');
    if (editBtn) {
      const id = Number(editBtn.getAttribute('data-edit'));
      const item = uclAutomations.find((a) => Number(a.id) === id);
      if (item) startEditAutomation(item);
    }
    if (deleteBtn) {
      const id = Number(deleteBtn.getAttribute('data-delete'));
      if (!id) return;
      const confirmDelete = window.confirm('Supprimer cette automatisation ?');
      if (!confirmDelete) return;
      try {
        await API.del(`/api/uclsport/automations.php?id=${id}`);
        setUclStatus('Automatisation supprimée.');
        if (editingAutomationId === id) {
          resetUclForm();
        }
        await loadUclAutomations();
      } catch (err) {
        setUclStatus(err.message || 'Suppression impossible.', true);
      }
    }
  });
}

window.addEventListener('DOMContentLoaded', () => {
  renderLinks([]);
  renderUclAutomations();
  loadMe();
  bindProfileSave();
  bindAvatar();
  bindBanner();
  bindAddLink();
  bindNotificationsForm();
  bindUclAutomations();
  loadUclAutomations();
});
