// user.js — charge un profil public + sa galerie
import API from './api.js';
const BASE = window.SODRINK_BASE || '';
const PER = 4;

function $(s, el=document){ return el.querySelector(s); }
function h(html){ const t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; }

function getQuery(){ const u=new URL(location.href); return { id: u.searchParams.get('id'), u: u.searchParams.get('u') }; }

function toImgUrl(p){
  if (!p) return '';
  if (/^https?:\/\//i.test(p)) return p;
  if (p.startsWith(BASE + '/')) return p;
  if (p.startsWith('/')) return BASE + p;
  return `${BASE}/uploads/gallery/${p}`;
}

async function loadProfile(){
  const q = getQuery();
  const qs = new URLSearchParams(q.u ? {u:q.u} : {id:q.id||''}).toString();
  const j = await fetch(`${BASE}/api/users/get.php?${qs}`, {credentials:'include'}).then(r=>r.json());
  if (!j?.success){ $('#user-title').textContent = 'Utilisateur introuvable'; return null; }
  const u = j.data.user;
  $('#user-title').textContent = `Profil de ${u.pseudo}`;
  $('#pub-avatar').src = u.avatar || (BASE + '/assets/img/ui/avatar-default.svg');
  $('#pub-pseudo').textContent = u.pseudo || '—';
  $('#pub-nom').textContent = u.nom || '—';
  $('#pub-prenom').textContent = u.prenom || '—';
  $('#pub-ig').textContent = u.instagram ? '@'+u.instagram : '—';
  return u;
}

async function fetchGallery(page, authorId){
  const qs = new URLSearchParams({ page:String(page), per:String(PER), author_id:String(authorId) });
  const j = await API.get(`/api/sections/gallery.php?${qs.toString()}`);
  return j;
}

function renderGallery(container, items){
  container.innerHTML = '';
  if (!items.length){ container.textContent = 'Aucune photo.'; return; }
  items.forEach(it => {
    const card = h(`
      <figure class="photo-card photo-card-lg">
        <img loading="lazy" src="${toImgUrl(it.path)}" alt="${(it.title||'').replaceAll('"','&quot;')}">
        <figcaption><strong>${it.title||''}</strong> <span class="muted">${it.description||''}</span></figcaption>
      </figure>`);
    container.appendChild(card);
  });
}

function renderPag(container, page, per, total, cb){
  container.innerHTML = '';
  const pages = Math.max(1, Math.ceil(total/per));
  for (let p=1; p<=pages; p++){
    const a = document.createElement('button');
    a.textContent = p;
    a.className = 'btn ' + (p===page? 'btn-primary' : '');
    a.addEventListener('click', () => cb(p));
    container.appendChild(a);
  }
}

window.addEventListener('DOMContentLoaded', async () => {
  const u = await loadProfile();
  if (!u) return;
  async function loadPage(p=1){
    const { items, per, total } = await fetchGallery(p, u.id);
    renderGallery($('#user-gallery'), items);
    renderPag($('#user-gallery-pagination'), p, per, total, loadPage);
  }
  loadPage(1);
});
