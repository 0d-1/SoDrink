import API from '../api.js';

const BASE = window.SODRINK_BASE || '';
const $ = (s, el=document) => el.querySelector(s);

const loaderEl   = () => $('#torpille-loader');
const messageEl  = () => $('#torpille-message');
const galleryEl  = () => $('#torpille-gallery');
const emptyEl    = () => $('#torpille-empty');

function setLoading(isLoading){
  const loader = loaderEl();
  if (loader) loader.hidden = !isLoading;
  const gal = galleryEl();
  if (gal) gal.classList.toggle('is-loading', !!isLoading);
}

function showMessage(text='', variant='info'){
  const el = messageEl();
  if (!el) return;
  if (!text){
    el.hidden = true;
    el.textContent = '';
    el.dataset.variant = '';
    return;
  }
  el.hidden = false;
  el.textContent = text;
  el.dataset.variant = variant;
}

let state = null;
let users = [];
let stats = [];
let currentPage = 1;
let perPage = window.matchMedia('(max-width: 640px)').matches ? 2 : 4; // 2 en mobile, 4 en desktop

window.matchMedia('(max-width: 640px)').addEventListener?.('change', (e)=>{
  perPage = e.matches ? 2 : 4;
  // Recharger la page courante avec la nouvelle taille
  fetchData(1).catch(console.error);
});

async function fetchData(page=1){
  setLoading(true);
  showMessage('');
  try {
    const r = await fetch(`${BASE}/api/sections/torpille.php?page=${page}&per_page=${perPage}`, {credentials:'include'});
    const j = await r.json().catch(()=>({success:false}));
    if (!j?.success) throw new Error(j?.error || 'Erreur inattendue.');
    state = j.data.state;
    users = j.data.users || [];
    stats = j.data.stats || [];
    renderHead();
    renderForm();
    renderGallery(j.data.list || {items:[], page:1, pages:1});
    maybeShowOverlay(j.data.latest || null);
  } catch (err) {
    console.error(err);
    showMessage(String(err.message || err), 'error');
  } finally {
    setLoading(false);
  }
}

/* ============================== Top3 + panneau ============================== */
function renderHead(){
  const btn = $('#torpille-top3');
  const panel = $('#torpille-stats-panel');
  const listBox = $('#torpille-stats-list');

  const nameOf = (id) => {
    const u = users.find(x => Number(x.id) === Number(id));
    return u ? u.pseudo : `#${id}`;
  };

  const ordered = (stats || []).slice().sort((a,b)=> b.count - a.count);
  const top = ordered.slice(0,3).map(s => `${nameOf(s.user_id)} ×${s.count}`);
  btn.textContent = top.length ? `Top 3: ${top.join(' · ')}` : 'Top 3';

  listBox.innerHTML = ordered.length
    ? ordered.map(s => `<div class="stats-row"><div>${nameOf(s.user_id)}</div><div class="count">×${s.count}</div></div>`).join('')
    : `<div class="muted">Aucune donnée pour l’instant.</div>`;

  const hide = () => { panel.hidden = true; document.removeEventListener('click', onDoc); };
  const onDoc = (e) => { if (!panel.hidden && !panel.contains(e.target) && !btn.contains(e.target)) hide(); };
  btn.onclick = () => { panel.hidden = !panel.hidden; if (!panel.hidden) document.addEventListener('click', onDoc); };
}

/* ============================== Formulaire & anonymisation ============================== */
function renderForm(){
  const form = $('#torpille-form');
  const info = $('#torpille-state');
  const sel = $('#torpille-next');
  const badge = $('#torpille-status-badge');

  if (badge) badge.hidden = false;

  if (state.mask_current && !state.is_me_torpille) {
    info.textContent = 'Torpillé actuel : torpille en cours';
    if (badge) { badge.textContent = 'En cours'; badge.dataset.variant = 'neutral'; }
  } else if (state.current_user_id){
    const curr = users.find(u=> Number(u.id) === Number(state.current_user_id));
    const label = curr ? `${curr.pseudo}` : `#${state.sequence}`;
    info.textContent = curr
      ? `Torpillé actuel : ${curr.pseudo} · séquence #${state.sequence}`
      : `Torpillé actuel : #${state.sequence}`;
    if (badge) {
      badge.textContent = curr && state.is_me_torpille ? 'C’est toi !' : label;
      badge.dataset.variant = state.is_me_torpille ? 'alert' : 'accent';
    }
  } else {
    info.textContent = `Aucun(e) torpillé(e) en cours.`;
    if (badge) { badge.textContent = 'Disponible'; badge.dataset.variant = 'success'; }
  }

  if (state.is_me_torpille){
    form.hidden = false;
    const meId = (window.__ME_ID__||0);
    sel.innerHTML = ['<option value="">— choisir —</option>']
      .concat(users.filter(u => Number(u.id)!==Number(meId)).map(u => `<option value="${u.id}">${u.pseudo}</option>`))
      .join('');
  } else {
    form.hidden = true;
  }
}

/* ============================== Galerie / pagination (1 ligne) ============================== */
function renderGallery(list){
  const box = $('#torpille-gallery');
  const pag = $('#torpille-pagination');
  const emptyBox = emptyEl();
  if (!box) return;

  const items = list.items || [];
  box.innerHTML = '';
  box.classList.toggle('has-items', items.length > 0);
  if (emptyBox) {
    if (items.length === 0) {
      emptyBox.hidden = false;
      emptyBox.textContent = 'Aucune torpille enregistrée pour le moment.';
    } else {
      emptyBox.hidden = true;
      emptyBox.textContent = '';
    }
  }
  items.forEach(p => {
    const card = document.createElement('div');
    card.className = 'photo card';
    card.innerHTML = `
      <div class="wrap"><img loading="lazy" decoding="async" src="${p.path}" alt="#${p.seq}"></div>
      <div class="meta">
        <span class="muted">#${p.seq}</span>
        <span class="muted">${(p.created_at||'').replace('T',' ').slice(0,16)}</span>
      </div>
    `;
    box.appendChild(card);
  });

  // Pagination
  pag.hidden = (list.pages||1) <= 1;
  pag.innerHTML = '';
  if ((list.pages||1) > 1){
    const prev = document.createElement('button');
    prev.className = 'btn';
    prev.textContent = '←';
    prev.disabled = (list.page||1) <= 1;
    prev.addEventListener('click', ()=>{ currentPage = Math.max(1, list.page-1); fetchData(currentPage); });

    const next = document.createElement('button');
    next.className = 'btn';
    next.textContent = '→';
    next.disabled = (list.page||1) >= (list.pages||1);
    next.addEventListener('click', ()=>{ currentPage = Math.min(list.pages, list.page+1); fetchData(currentPage); });

    const info = document.createElement('span');
    info.className = 'muted';
    info.style.padding = '.4rem .6rem';
    info.textContent = `${list.page}/${list.pages}`;

    pag.appendChild(prev); pag.appendChild(info); pag.appendChild(next);
  }
}

/* ============================== Recadrage 3:4 + Upload ============================== */
function bindUpload(){
  const form = $('#torpille-form');
  const fileIn = $('#torpille-file');
  const btnCam = $('#torpille-btn-camera');
  const btnChoose = $('#torpille-btn-choose');
  const preview = $('#torpille-preview');
  const placeholder = $('#torpille-placeholder');
  const submitBtn = form?.querySelector('button[type="submit"]');

  const setBusy = (flag)=>{
    if (!submitBtn) return;
    submitBtn.disabled = !!flag;
    submitBtn.classList.toggle('is-busy', !!flag);
  };

  btnCam?.addEventListener('click', ()=>{
    fileIn.setAttribute('accept', 'image/*');
    fileIn.setAttribute('capture', 'environment');
    fileIn.click();
  });
  btnChoose?.addEventListener('click', ()=>{
    fileIn.setAttribute('accept', 'image/*');
    fileIn.removeAttribute('capture');
    fileIn.click();
  });

  fileIn?.addEventListener('change', ()=>{
    const f = fileIn.files && fileIn.files[0];
    if (!f){
      preview.style.display = 'none';
      placeholder.style.display = '';
      preview.removeAttribute('src');
      showMessage('Aucune photo sélectionnée.', 'info');
      return;
    }
    if (!f.type || !f.type.startsWith('image/')){
      showMessage("Le fichier sélectionné n'est pas une image.", 'error');
      fileIn.value = '';
      preview.style.display = 'none';
      placeholder.style.display = '';
      preview.removeAttribute('src');
      return;
    }
    const url = URL.createObjectURL(f);
    preview.src = url;
    preview.onload = ()=> URL.revokeObjectURL(url);
    preview.style.display = '';
    placeholder.style.display = 'none';
  });

  form?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const f = fileIn.files && fileIn.files[0];
    const toSel = $('#torpille-next');
    const nextId = Number(toSel?.value||'0');
    if (!f){ showMessage('Choisis ou prends une photo avant de torpiller.', 'error'); return; }
    if (!nextId){ showMessage('Sélectionne la prochaine personne torpillée.', 'error'); return; }

    setBusy(true);
    try{
      // Recadrage 3:4 avant envoi
      const croppedBlob = await cropToThreeFour(f, 1500); // largeur max ~1500px
      const fd = new FormData();
      fd.append('photo', croppedBlob, 'photo.jpg');
      fd.append('next_user_id', String(nextId));

      const tok = await fetch(`${BASE}/api/csrf.php`, {credentials:'include'}).then(r=>r.json()).then(j=>j.data.csrf_token);
      const res = await fetch(`${BASE}/api/sections/torpille.php?action=upload_and_pass`, {
        method:'POST', credentials:'include', headers:{'X-CSRF-Token': tok}, body: fd
      });
      const j = await res.json().catch(()=>({success:false}));
      if (!j?.success) throw new Error(j?.error||'Erreur');

      form.reset();
      preview.style.display = 'none';
      placeholder.style.display = '';
      preview.removeAttribute('src');
      currentPage = 1;
      await fetchData(currentPage);
      showMessage('Torpille enregistrée et nouveau joueur désigné !', 'success');
    }catch(err){
      console.error(err);
      showMessage(String(err.message||err), 'error');
    }finally{
      setBusy(false);
    }
  });
}

/** Recadre un fichier image au ratio 3:4 (portrait), centré, et redimensionne vers width<=maxW */
async function cropToThreeFour(file, maxW=1500){
  const img = await loadImage(file);
  const ratio = 3/4; // w/h
  let sw = img.width, sh = img.height;
  // Déterminer la zone source à recadrer en 3:4 (centrée)
  if (sw/sh > ratio) {
    // trop large, on coupe les côtés
    const targetW = Math.round(sh * ratio);
    const sx = Math.floor((sw - targetW)/2);
    return drawToBlob(img, sx, 0, targetW, sh, maxW);
  } else {
    // trop haut, on coupe en haut/bas
    const targetH = Math.round(sw / ratio);
    const sy = Math.floor((sh - targetH)/2);
    return drawToBlob(img, 0, sy, sw, targetH, maxW);
  }
}
function loadImage(file){
  return new Promise((resolve, reject)=>{
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = ()=>{ URL.revokeObjectURL(url); resolve(img); };
    img.onerror = ()=>{ URL.revokeObjectURL(url); reject(new Error('Impossible de lire l’image')); };
    img.src = url;
  });
}
function drawToBlob(img, sx, sy, sw, sh, maxW){
  const ratio = 3/4;
  // dimension finale souhaitée
  let dw = Math.min(maxW, sw);
  let dh = Math.round(dw / ratio);
  // si le crop source est plus petit que dw, caler sur la source
  if (sw < dw) { dw = sw; dh = Math.round(dw / ratio); }

  const canvas = document.createElement('canvas');
  canvas.width = dw; canvas.height = dh;
  const ctx = canvas.getContext('2d', { alpha:false });
  // Dessin (cover)
  ctx.drawImage(img, sx, sy, sw, sh, 0, 0, dw, dh);
  return new Promise((resolve)=> canvas.toBlob(b => resolve(b), 'image/jpeg', 0.9));
}

/* ============================== Overlay (identique) ============================== */
function maybeShowOverlay(latest){
  try{
    if (!state?.is_me_torpille || !latest?.path) return;
    const seq = Number(latest.seq || latest.id || 0);
    const me = Number(window.__ME_ID__ || 0);
    const storageKey = me ? `torpille_last_seen_seq_${me}` : 'torpille_last_seen_seq';
    const just = sessionStorage.getItem('just_logged_in') === '1';
    const lastSeen = Number(localStorage.getItem(storageKey) || '0');
    if (!just && (!seq || seq <= lastSeen)) return;
    const over = $('#torpille-overlay');
    const img = $('#torpille-overlay-img');
    const confirmBox = $('#torpille-confirm');
    if (!over || !img || !confirmBox) return;
    img.src = latest.path;
    over.hidden = false;
    const close = $('#torpille-close');
    const yes = $('#torpille-yes');
    const no = $('#torpille-no');
    if (!close || !yes || !no) return;
    const markSeen = ()=>{
      if (seq) localStorage.setItem(storageKey, String(seq));
      sessionStorage.removeItem('just_logged_in');
    };
    close.onclick = ()=>{ confirmBox.hidden = false; };
    yes.onclick = ()=>{ confirmBox.hidden = true; };
    const hideOverlay = ()=>{
      over.hidden = true;
      confirmBox.hidden = true;
      markSeen();
      document.removeEventListener('keydown', onKey);
    };
    no.onclick = hideOverlay;
    over.onclick = (ev)=>{
      if (ev.target === over){ confirmBox.hidden = true; }
    };
    const onKey = (ev)=>{
      if (ev.key === 'Escape'){ hideOverlay(); }
    };
    document.addEventListener('keydown', onKey);
  }catch{}
}

/* ============================== Boot ============================== */
window.addEventListener('DOMContentLoaded', async ()=>{
  try { window.__ME_ID__ = (await API.me()).user.id; } catch {}
  bindUpload();
  fetchData(currentPage).catch(console.error);
});
