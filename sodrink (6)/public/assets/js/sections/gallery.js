import API from '../api.js';
const BASE = window.SODRINK_BASE || '';
const PER = 4;

let currentUser = null;
const $=(s,el=document)=>el.querySelector(s);
const h=(html)=>{const t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstChild; };

function toImgUrl(p){ if(!p) return ''; if(/^https?:\/\//i.test(p)) return p; if(p.startsWith(BASE+'/')) return p; if(p.startsWith('/')) return BASE+p; if(p.startsWith('uploads/')) return BASE+'/'+p; return `${BASE}/uploads/gallery/${p}`; }
async function me(){ try{ return (await API.me()).user; }catch{ return null; } }

function canManage(item){ if(!currentUser) return false; if(currentUser.role==='admin') return true; return Number(item.author?.id||0)===Number(currentUser.id||-1); }

async function fetchPage(page=1, authorId=null){
  const qs = new URLSearchParams({page:String(page), per:String(PER)}); if(authorId) qs.set('author_id',String(authorId));
  return API.get(`/api/sections/gallery.php?${qs}`);
}

function cardHtml(it){
  const src=toImgUrl(it.path), author=it.author||{}, manage=canManage(it);
  return `
  <figure class="photo-card photo-card-lg" data-id="${it.id}">
    <img loading="lazy" src="${src}" alt="${(it.title||'').replaceAll('"','&quot;')}">
    <figcaption>
      <div class="grid-row">
        <div class="text">
          <strong class="title">${it.title||''}</strong>
          <span class="muted desc">${it.description||''}</span>
        </div>
        <div class="author">Par <a class="author-link" href="${BASE}/user.php?u=${encodeURIComponent(author.pseudo||'')}">${author.pseudo||'—'}</a></div>
      </div>

      <div class="inline" style="margin-top:.5rem">
        <button class="btn btn-sm" data-like="${it.id}">❤️ <span data-like-count="${it.id}">${it.likes_count||0}</span></button>
        <button class="btn btn-sm" data-comments-toggle="${it.id}">Commentaires (<span data-comm-count="${it.id}">${it.comments_count||0}</span>)</button>
      </div>

      <div class="comments" data-comments="${it.id}" hidden>
        <div class="comments-list">
          ${(it.comments_preview||[]).map(c=>`
            <div class="comment-line">
              <img src="${c.avatar || (BASE+'/assets/img/ui/avatar-default.svg')}" alt="">
              <div><strong>${c.pseudo||'—'}</strong><div>${c.text||''}</div></div>
            </div>`).join('')}
        </div>
        ${currentUser? `
        <form class="form comments-form" data-add-comment="${it.id}">
          <input name="text" maxlength="600" placeholder="Écrire un commentaire…">
          <button class="btn btn-primary btn-sm">Publier</button>
        </form>`:'<div class="muted">Connectez-vous pour commenter.</div>'}
      </div>

      ${manage? `
      <div class="admin-actions">
        <button class="btn btn-sm" data-edit="${it.id}">Modifier</button>
        <button class="btn btn-danger btn-sm" data-del="${it.id}">Supprimer</button>
      </div>
      <form class="form edit-form" data-form="${it.id}" hidden>
        <div class="inline">
          <input name="title" maxlength="120" placeholder="Titre" value="${(it.title||'').replaceAll('"','&quot;')}">
          <input name="description" maxlength="500" placeholder="Description" value="${(it.description||'').replaceAll('"','&quot;')}">
        </div>
        <div class="inline">
          <button class="btn btn-primary btn-sm" data-save="${it.id}">Enregistrer</button>
          <button class="btn btn-sm" data-cancel="${it.id}">Annuler</button>
        </div>
      </form>`:''}
    </figcaption>
  </figure>`;
}

function renderItems(container, items){
  container.innerHTML=''; if(!items.length){ container.textContent='Aucune photo pour l’instant.'; return; }
  items.forEach(it=>container.appendChild(h(cardHtml(it))));
}

function renderPagination(container, page, per, total){
  container.innerHTML=''; const pages=Math.max(1,Math.ceil(total/per));
  for(let p=1;p<=pages;p++){ const a=document.createElement('button'); a.textContent=p; a.className='btn '+(p===page?'btn-primary':''); a.addEventListener('click',()=>load(p)); container.appendChild(a); }
}

async function load(page=1){
  const grid=$('#gallery-grid'), pag=$('#gallery-pagination');
  try{ const {items,total,per}=await fetchPage(page); renderItems(grid, items); renderPagination(pag, page, per, total); }
  catch{ grid.textContent='Erreur de chargement.'; }
}

async function getCSRF(){ const j=await fetch(`${BASE}/api/csrf.php`,{credentials:'include'}).then(r=>r.json()); return j.data.csrf_token; }
async function checkLogged(){ currentUser = await me(); return !!currentUser; }

function bindUpload(){
  const form=document.getElementById('form-gallery-upload'); if(!form) return;
  (async()=>{ if(!(await checkLogged())){ form.innerHTML='<em class="muted">Connectez-vous pour publier une photo.</em>'; form.classList.add('disabled'); return; } })();
  form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd=new FormData(form); fd.append('csrf_token', await getCSRF());
    const res=await fetch(`${BASE}/api/sections/gallery.php`,{method:'POST',body:fd,credentials:'include'}); const j=await res.json();
    if(!j.success) return alert(j.error||'Erreur'); form.reset(); load(1);
  });
}

function bindActions(){
  const grid = document.getElementById('gallery-grid');
  grid.addEventListener('click', async (e)=>{
    const t=e.target;
    const id = t.getAttribute('data-like') || t.getAttribute('data-comments-toggle') || t.getAttribute('data-edit') || t.getAttribute('data-del') || t.getAttribute('data-save') || t.getAttribute('data-cancel');
    if (!id) return;

    if (t.hasAttribute('data-like')){
      const r = await API.post(`/api/sections/gallery-like.php?id=${id}`, {}); const cEl=grid.querySelector(`[data-like-count="${id}"]`); if(cEl) cEl.textContent = r.count;
      return;
    }
    if (t.hasAttribute('data-comments-toggle')){
      const box = grid.querySelector(`[data-comments="${id}"]`); if (!box) return;
      box.hidden = !box.hidden;
      if (!box.dataset.loaded){
        const j = await fetch(`${BASE}/api/sections/gallery-comments.php?id=${id}`, {credentials:'include'}).then(r=>r.json());
        if (j?.success){
          const list = box.querySelector('.comments-list');
          list.innerHTML = (j.data.items||[]).map(c=>`
            <div class="comment-line">
              <img src="${c.avatar || (BASE+'/assets/img/ui/avatar-default.svg')}" alt="">
              <div><strong>${c.pseudo||'—'}</strong><div>${c.text||''}</div></div>
            </div>`).join('');
          box.dataset.loaded = '1';
        }
      }
      return;
    }
    if (t.hasAttribute('data-edit')){
      const f=grid.querySelector(`[data-form="${id}"]`); if(f) f.hidden=!f.hidden; return;
    }
    if (t.hasAttribute('data-cancel')){
      t.closest('form').hidden=true; return;
    }
    if (t.hasAttribute('data-del')){
      if(!confirm('Supprimer cette photo ?')) return;
      await API.del(`/api/sections/gallery.php?id=${id}`); load(1); return;
    }
    if (t.hasAttribute('data-save')){
      e.preventDefault();
      const f=grid.querySelector(`[data-form="${id}"]`); const fd=new FormData(f); const payload=Object.fromEntries(fd.entries());
      await API.put(`/api/sections/gallery.php?id=${id}`, payload); load(1); return;
    }
  });

  grid.addEventListener('submit', async (e)=>{
    const form=e.target.closest('[data-add-comment]'); if(!form) return;
    e.preventDefault();
    const id=form.getAttribute('data-add-comment'); const text=form.querySelector('input[name="text"]').value.trim(); if(!text) return;
    const res = await fetch(`${BASE}/api/sections/gallery-comments.php?id=${id}`, {method:'POST', credentials:'include',
      headers:{'Content-Type':'application/json','X-CSRF-Token':await getCSRF()}, body: JSON.stringify({text})});
    const j = await res.json(); if(!j.success) return alert(j.error||'Erreur');
    form.reset();
    const list = form.parentElement.querySelector('.comments-list');
    const c = j.data.comment;
    list.appendChild(h(`<div class="comment-line"><img src="${c.avatar || (BASE+'/assets/img/ui/avatar-default.svg')}" alt=""><div><strong>${c.pseudo||'—'}</strong><div>${c.text||''}</div></div></div>`));
    const cnt = grid.querySelector(`[data-comm-count="${id}"]`); if (cnt) cnt.textContent = String(Number(cnt.textContent||0)+1);
  });
}

window.addEventListener('DOMContentLoaded', async ()=>{ await checkLogged(); bindUpload(); bindActions(); load(1); });
