// public/assets/js/admin.js
// Admin : Utilisateurs / Sections / Torpille / Notifications (broadcast)

import API from './api.js';

const BASE = window.SODRINK_BASE || '';
const $ = (s, el=document) => el.querySelector(s);
const $$ = (s, el=document) => [...el.querySelectorAll(s)];
const h = (html) => { const t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstChild; };

/* ============================== Onglets ============================== */

function openTab(key){
  ['users','sections','torpille','notifs'].forEach(k=>{
    const sect = $('#tab-'+k);
    const btn  = document.querySelector(`.tabs [data-tab="${k}"]`);
    if (sect) sect.hidden = (k !== key);
    if (btn)  btn.classList.toggle('btn-primary', k === key);
  });
}

function bindTabs(){
  $$('.tabs [data-tab]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const t = btn.getAttribute('data-tab');
      openTab(t);
      history.replaceState(null, '', '#'+t);
    });
  });
}

/* ============================== Utilisateurs ============================== */

async function loadUsers(){
  const tbody = $('#users-table tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="5" class="muted">Chargement…</td></tr>';

  try{
    const { users } = await API.get('/api/users/admin-users.php');
    tbody.innerHTML = '';
    (users || []).forEach(u=>{
      const tr = h(`<tr>
        <td>${u.id}</td>
        <td>${u.pseudo}</td>
        <td>${(u.prenom||'')} ${(u.nom||'')}</td>
        <td>
          <select data-role data-id="${u.id}">
            <option value="user" ${u.role==='user'?'selected':''}>user</option>
            <option value="admin" ${u.role==='admin'?'selected':''}>admin</option>
          </select>
        </td>
        <td class="inline">
          <button class="btn btn-sm" data-reset data-id="${u.id}">Reset mdp</button>
          <button class="btn btn-sm danger" data-del data-id="${u.id}">Supprimer</button>
        </td>
      </tr>`);
      tbody.appendChild(tr);
    });

    // Changement de rôle
    tbody.addEventListener('change', async (e)=>{
      const sel = e.target.closest('select[data-role]');
      if (!sel) return;
      const id = sel.getAttribute('data-id');
      const role = sel.value;
      try{
        await API.put(`/api/users/admin-users.php?id=${id}`, { role });
      }catch(err){
        alert(err.message || err);
        await loadUsers(); // rollback visuel si échec
      }
    });

    // Reset mdp / suppression
    tbody.addEventListener('click', async (e)=>{
      const btn = e.target.closest('button[data-reset], button[data-del]');
      if (!btn) return;
      const id = btn.getAttribute('data-id');
      if (btn.hasAttribute('data-reset')){
        const ok = confirm('Confirmer le reset du mot de passe ?');
        if (!ok) return;
        try{
          await API.post(`/api/users/admin-users.php?id=${id}&action=reset_password`, {});
          alert('Mot de passe réinitialisé.');
        }catch(err){
          alert(err.message || err);
        }
      }
      if (btn.classList.contains('danger')){
        const ok = confirm('Supprimer cet utilisateur ?');
        if (!ok) return;
        try{
          await API.del(`/api/users/admin-users.php?id=${id}`);
          await loadUsers();
        }catch(err){
          alert(err.message || err);
        }
      }
    });

    // Création
    const form = $('#form-user-create');
    if (form){
      form.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const fd = new FormData(form);
        const payload = Object.fromEntries([...fd.entries()].map(([k,v])=>[k,(v||'').toString()]));
        try{
          await API.post('/api/users/admin-users.php', payload);
          form.reset();
          await loadUsers();
        }catch(err){
          alert(err.message || err);
        }
      });
    }

  }catch(err){
    tbody.innerHTML = `<tr><td colspan="5" class="muted">Erreur: ${err.message || err}</td></tr>`;
  }
}

/* ============================== Sections ============================== */

async function loadSections(){
  const list = $('#sections-list');
  if (!list) return;
  list.innerHTML = '<div class="muted">Chargement…</div>';

  try{
    const { sections } = await API.get('/api/sections-config.php');
    list.innerHTML = '';
    (sections || []).forEach(s=>{
      const row = h(`<div class="card inline" data-id="${s.id}">
        <input type="checkbox" ${s.enabled?'checked':''} data-enabled aria-label="Activer ${s.title || s.key}">
        <strong>${s.title || s.key}</strong> <span class="muted">(${s.key})</span>
        <div style="margin-left:auto;display:flex;gap:.25rem">
          <button class="btn btn-sm" data-up title="Monter">↑</button>
          <button class="btn btn-sm" data-down title="Descendre">↓</button>
        </div>
      </div>`);
      list.appendChild(row);
    });

    const doSave = async ()=>{
      const items = $$('#sections-list [data-id]').map((el, idx)=>({
        id: Number(el.getAttribute('data-id')),
        enabled: el.querySelector('[data-enabled]').checked,
        order: idx + 1
      }));
      try{
        await API.put('/api/sections-config.php', { items });
        const status = $('#sections-save-status');
        if (status){ status.textContent = 'Enregistré'; setTimeout(()=> status.textContent='', 1500); }
      }catch(err){
        alert("Impossible d'enregistrer les sections : " + (err.message || err));
      }
    };

    list.addEventListener('change', async (e)=>{
      if (e.target.matches('[data-enabled]')) await doSave();
    });

    list.addEventListener('click', async (e)=>{
      const row = e.target.closest('[data-id]');
      if (!row) return;
      const rows = $$('#sections-list [data-id]');
      const i = rows.indexOf(row);
      if (e.target.matches('[data-up]') && i > 0){
        row.parentNode.insertBefore(row, rows[i-1]);
        await doSave();
      }
      if (e.target.matches('[data-down]') && i < rows.length - 1){
        row.parentNode.insertBefore(rows[i+1], row);
        await doSave();
      }
    });

  }catch(err){
    list.innerHTML = `<div class="muted">Erreur: ${err.message || err}</div>`;
  }
}

/* ============================== Torpille (admin) ============================== */

async function loadTorpilleAdmin(){
  const $cur = $('#torpille-current');
  const $upd = $('#torpille-updated');
  const $firstSel = $('#torpille-first-user');
  const $toSel = $('#torpille-transfer-to');
  const $latest = $('#torpille-latest');
  if (!$cur) return; // onglet absent

  try{
    const data = await API.get('/api/sections/torpille.php');
    const state = data.state || {};
    const users = data.users || [];
    const latest = data.latest || null;

    // remplir selects
    const options = ['<option value="">— choisir —</option>']
      .concat(users.map(u => `<option value="${u.id}">${u.pseudo}</option>`))
      .join('');
    if ($firstSel) $firstSel.innerHTML = options;
    if ($toSel) {
      $toSel.innerHTML = options;
      // éviter de re-sélectionner le même que le courant
      if (state.current_user_id){
        $toSel.querySelectorAll('option').forEach(o=>{
          if (Number(o.value) === Number(state.current_user_id)) o.disabled = true;
        });
      }
    }

    // état
    const currentUser = users.find(u => Number(u.id) === Number(state.current_user_id));
    $cur.textContent = currentUser ? currentUser.pseudo : '—';
    $upd.textContent = (state.updated_at || '').replace('T',' ').slice(0,16) || '—';

    // latest
    if (latest){
      $latest.innerHTML = `
        <div style="display:flex; gap:1rem; align-items:center">
          <img src="${latest.path}" alt="#${latest.seq}" style="max-width:180px; border-radius:8px">
          <div>
            <div class="muted">Dernière photo</div>
            <div>#${latest.seq} · ${(latest.created_at||'').replace('T',' ').slice(0,16)}</div>
          </div>
        </div>`;
    } else {
      $latest.textContent = 'Aucune photo pour l’instant.';
    }

  }catch(err){
    console.error(err);
  }

  // bind forms (une seule fois)
  const initOnce = (el, fn) => {
    if (!el || el.__bound) return;
    el.__bound = true;
    fn(el);
  };

  initOnce($('#form-torpille-start'), (form)=>{
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const user_id = Number(($firstSel?.value||'0'));
      if (!user_id) { alert('Choisis un utilisateur'); return; }
      try{
        await API.post('/api/sections/torpille.php?action=set_initial', { user_id });
        $('#torpille-start-status').textContent = 'Enregistré';
        setTimeout(()=> $('#torpille-start-status').textContent='', 1500);
        await loadTorpilleAdmin();
      }catch(err){
        alert(err.message || err);
      }
    });
  });

  initOnce($('#form-torpille-transfer'), (form)=>{
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const to_user_id = Number(($toSel?.value||'0'));
      if (!to_user_id) { alert('Choisis un utilisateur'); return; }
      try{
        await API.post('/api/sections/torpille.php?action=admin_transfer', { to_user_id });
        $('#torpille-transfer-status').textContent = 'Transféré';
        setTimeout(()=> $('#torpille-transfer-status').textContent='', 1500);
        await loadTorpilleAdmin();
      }catch(err){
        alert(err.message || err);
      }
    });
  });
}

/* ============================== Notifications ============================== */

function populateNotifUsers(users){
  const box = $('#notif-users-list');
  if (!box) return;
  box.innerHTML = (users||[]).map(u => `
    <label style="display:flex; gap:.5rem; align-items:center; padding:.25rem 0">
      <input type="checkbox" value="${u.id}" data-userchk>
      <span class="muted" style="width:40px">#${u.id}</span>
      <strong>${u.pseudo}</strong>
      <span class="muted" style="margin-left:.5rem">${(u.prenom||'')} ${(u.nom||'')}</span>
      <span class="muted" style="margin-left:auto">${u.role}</span>
    </label>
  `).join('');
}

async function bindNotifTab(){
  const form = $('#form-broadcast');
  if (!form) return;

  let fullUsers = [];
  try{
    const { users } = await API.get('/api/users/admin-users.php');
    fullUsers = users || [];
    populateNotifUsers(fullUsers);
  }catch(err){
    console.error(err);
  }

  // filtre rôle
  $('#notif-role')?.addEventListener('change', (e)=>{
    const role = e.target.value;
    const filtered = role ? fullUsers.filter(u=>u.role===role) : fullUsers;
    populateNotifUsers(filtered);
  });

  // tout cocher / décocher
  $('#notif-select-all')?.addEventListener('click', ()=>{
    $$('#notif-users-list input[type="checkbox"]').forEach(c=> c.checked = true);
  });
  $('#notif-unselect-all')?.addEventListener('click', ()=>{
    $$('#notif-users-list input[type="checkbox"]').forEach(c=> c.checked = false);
  });

  // envoi
  form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(form);
    const message = (fd.get('message')||'').toString().trim();
    const link    = (fd.get('link')||'').toString().trim();
    const title   = (fd.get('title')||'').toString().trim();
    if (!message || !title) { alert('Titre et message requis.'); return; }

    const ids = $$('#notif-users-list input[type="checkbox"]:checked').map(c=> Number(c.value));
    const payload = { title, message, link: link || null, user_ids: ids };

    try{
      const r = await API.post('/api/notifications/admin-broadcast.php', payload);
      const count = r?.sent_count ?? ids.length;
      alert(`Notification envoyée à ${count} destinataire(s).`);
      form.reset();
      updateManual();
    }catch(err){
      alert(err.message || err);
    }
  });
}

/* ============================== Boot ============================== */

window.addEventListener('DOMContentLoaded', async ()=>{
  bindTabs();

  // charger contenu des onglets
  await Promise.all([
    loadUsers().catch(console.error),
    loadSections().catch(console.error),
    loadTorpilleAdmin().catch(console.error),
    bindNotifTab().catch(console.error),
  ]);

  // onglet par hash
  const hash = (location.hash || '#users').replace('#','');
  openTab(['users','sections','torpille','notifs'].includes(hash) ? hash : 'users');

  window.addEventListener('hashchange', () => {
    const h = (location.hash || '#users').replace('#','');
    openTab(['users','sections','torpille','notifs'].includes(h) ? h : 'users');
  });
});
