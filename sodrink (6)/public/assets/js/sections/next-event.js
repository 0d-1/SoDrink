import API from '../api.js';

const BASE = window.SODRINK_BASE || '';
let currentUser = null;

const $=(s,el=document)=>el.querySelector(s);
const el=(t,c)=>{const n=document.createElement(t); if(c) n.className=c; return n;};
const h=(html)=>{const t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstChild;};

async function me(){ try{ return (await API.me()).user; }catch{ return null; } }
async function list(){ return API.get('/api/sections/next-event.php?limit=20'); }

function canEdit(ev){
  if (!currentUser) return false;
  if (currentUser.role==='admin') return true;
  return Number(ev.created_by||-1) === Number(currentUser.id||0);
}

function formatDateInput(v){
  // normalise yyyy-mm-dd
  if (!v) return '';
  const d = new Date(v);
  const y = d.getFullYear();
  const m = String(d.getMonth()+1).padStart(2,'0');
  const day = String(d.getDate()).padStart(2,'0');
  return `${y}-${m}-${day}`;
}

function rsvpButtons(ev){
  const wrap = el('div','rsvp');
  const iamIn = (ev.participants||[]).some(u=>Number(u.id)===Number(currentUser?.id||-1));
  if (!currentUser) return wrap;
  if (iamIn){
    wrap.innerHTML = `<button class="btn btn-outline" data-leave="${ev.id}">Se désinscrire</button>
                      <button class="btn btn-light" data-participants="${ev.id}">Participants (${ev.participants_count ?? (ev.participants?.length||0)})</button>`;
  }else{
    wrap.innerHTML = `<button class="btn btn-primary" data-join="${ev.id}">J’y serai !</button>
                      <button class="btn btn-light" data-participants="${ev.id}">Participants (${ev.participants_count ?? (ev.participants?.length||0)})</button>`;
  }
  return wrap;
}

function renderMiniCalendar(container, events){
  container.innerHTML='';
  const now = new Date(), y = now.getFullYear(), m = now.getMonth();
  const first=new Date(y,m,1), start=first.getDay()||7, days=new Date(y,m+1,0).getDate();
  const marks = new Set(events.map(e=>e.date));
  const grid=el('div','cal-grid');
  for (let i=1;i<start;i++) grid.appendChild(el('div','cal-cell cal-empty'));
  for (let d=1; d<=days; d++){
    const cell=el('div','cal-cell'); cell.textContent=d;
    const dateStr=`${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    if (marks.has(dateStr)) cell.classList.add('cal-mark');
    grid.appendChild(cell);
  }
  container.appendChild(grid);
}

function renderList(container, list){
  container.innerHTML='';
  if (!list?.length){ container.innerHTML='<em class="muted">Rien de plus pour l’instant.</em>'; return; }
  list.forEach(ev=>{
    const can = canEdit(ev);
    const card = h(`
      <article class="event-item card" data-id="${ev.id}">
        <div class="event-head">
          <div class="event-head-left">
            <div class="event-date">${ev.date}</div>
            <div class="event-title"><strong>${ev.theme||'Soirée'}</strong> — <span class="muted">${ev.lieu||'—'}</span></div>
            <div class="event-author muted">par <a href="${BASE}/user.php?id=${encodeURIComponent(ev.author?.id||'')}">${ev.author?.pseudo||'—'}</a></div>
          </div>
          <div class="event-actions">
            ${can ? `<button class="btn btn-outline btn-sm" data-edit="${ev.id}">Modifier</button>
                     <button class="btn btn-danger btn-sm" data-del="${ev.id}">Supprimer</button>` : ''}
          </div>
        </div>
        <p class="event-desc">${ev.description||''}</p>
        ${currentUser? `<div class="rsvp">${rsvpButtons(ev).innerHTML}</div>`:''}
        <form class="form event-edit" data-form="${ev.id}" hidden>
          <div class="event-form-grid">
            <label>Date <input type="date" name="date" value="${formatDateInput(ev.date)}" required></label>
            <label>Lieu <input type="text" name="lieu" maxlength="120" value="${ev.lieu||''}" required></label>
            <label>Thème <input type="text" name="theme" maxlength="120" value="${ev.theme||''}"></label>
          </div>
          <label>Description
            <textarea name="description" rows="3" maxlength="800">${ev.description||''}</textarea>
          </label>
          <div class="row actions">
            <button class="btn btn-primary btn-sm" data-save="${ev.id}">Enregistrer</button>
            <button class="btn btn-light btn-sm" data-cancel="${ev.id}">Annuler</button>
          </div>
        </form>
      </article>`);
    container.appendChild(card);
  });
}

async function getCSRF(){
  try{
    const r = await fetch(`${BASE}/api/csrf.php`, {credentials:'include'});
    const j = await r.json().catch(()=>({}));
    if (j?.success && j?.token) return j.token;
  }catch{}
  return '';
}

function toast(msg, type='info'){
  const t = h(`<div class="toast ${type}">${msg}</div>`);
  document.body.appendChild(t);
  setTimeout(()=>{ t.classList.add('show'); }, 10);
  setTimeout(()=>{ t.classList.remove('show'); t.remove(); }, 3000);
}

function bindCreateForm(){
  const form = $('#event-form');
  if (!form) return;
  form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    try{
      const res = await API.post('/api/sections/next-event.php', data);
      if (!res?.success) throw new Error(res?.error||'Erreur');
      form.reset();
      toast('Évènement créé ✅','success');
      loadAll();
    }catch(err){ toast(String(err.message||err),'error'); }
  });
}

function bindListActions(){
  const list = $('#event-list');
  if (!list) return;
  list.addEventListener('click', async (e)=>{
    const card = e.target.closest('.event-item'); if (!card) return;
    const id = card.dataset.id;

    if (e.target.hasAttribute('data-edit')){
      card.querySelector('.event-edit').hidden=false;
      return;
    }
    if (e.target.hasAttribute('data-cancel')){
      card.querySelector('.event-edit').hidden=true;
      return;
    }
    if (e.target.hasAttribute('data-save')){
      e.preventDefault();
      const f = card.querySelector('.event-edit');
      const data = Object.fromEntries(new FormData(f).entries());
      try{
        const res = await API.put(`/api/sections/next-event.php?id=${id}`, data);
        if (!res?.success) throw new Error(res?.error||'Erreur');
        toast('Évènement mis à jour ✅','success');
        loadAll();
      }catch(err){ toast(String(err.message||err),'error'); }
      return;
    }
    if (e.target.hasAttribute('data-del')){
      if (!confirm('Supprimer cet évènement ?')) return;
      try{
        const res = await API.delete(`/api/sections/next-event.php?id=${id}`);
        if (!res?.success) throw new Error(res?.error||'Erreur');
        toast('Évènement supprimé ✅','success');
        loadAll();
      }catch(err){ toast(String(err.message||err),'error'); }
      return;
    }
    if (e.target.hasAttribute('data-join')) {
      try{
        const res = await API.post(`/api/sections/event-participation.php?event_id=${id}`, {});
        if (!res?.success) throw new Error(res?.error||'Erreur');
        loadAll();
      }catch(err){ toast(String(err.message||err),'error'); }
      return;
    }
    if (e.target.hasAttribute('data-leave')) {
      try{
        const tok = await getCSRF();
        const r = await fetch(`${BASE}/api/sections/event-participation.php?event_id=${id}`, {
          method:'DELETE',
          headers:{'X-CSRF-Token':tok},
          credentials:'include'
        });
        const j = await r.json().catch(()=>({success:false, error:'Erreur'}));
        if (!j?.success) throw new Error(j?.error||'Erreur');
        loadAll();
      }catch(err){ toast(String(err.message||err),'error'); }
      return;
    }
    if (e.target.hasAttribute('data-participants')) {
      try{
        const r = await fetch(`${BASE}/api/sections/event-participation.php?event_id=${id}`, {credentials:'include'});
        const j = await r.json().catch(()=>({success:false}));
        if (!j?.success) return;
        const modal = participantsModal();
        const body = modal.querySelector('#participants-list');
        body.innerHTML = (j.data.participants||[]).map(p=>`
          <div class="user-line">
            <img src="${p.avatar || (BASE+'/assets/img/ui/avatar-default.svg')}" alt="">
            <span>${p.pseudo}</span>
          </div>
        `).join('') || '<div class="muted">Personne pour le moment.</div>';
        modal.hidden=false;
      }catch{}
      return;
    }
  });
}

function participantsModal(){ let modal=$('#participants-modal'); if (modal) return modal;
  modal = h(`<div class="modal" id="participants-modal" hidden>
    <div class="modal-card">
      <div class="modal-head"><strong>Participants</strong><button class="btn btn-sm" data-close>✕</button></div>
      <div class="modal-body" id="participants-list"></div>
    </div></div>`);
  document.body.appendChild(modal);
  modal.addEventListener('click', (e)=>{
    if (e.target.matches('[data-close], .modal')) modal.hidden=true;
  });
  return modal;
}

async function loadAll(){
  currentUser = await me();
  $('#event-form').hidden = !currentUser;
  const data = await list();
  // on n’affiche plus “prochain évènement” au-dessus : uniquement calendrier + liste
  renderMiniCalendar($('#mini-calendar'), data.upcoming||[]);
  renderList($('#event-list'), data.upcoming||[]);
}

function bindAll(){
  bindCreateForm();
  bindListActions();
}

window.addEventListener('DOMContentLoaded', ()=>{ loadAll(); bindAll(); });
