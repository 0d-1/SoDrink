// public/assets/js/notifications.js
// Badge + dropdown + permission + notifications locales (avec DÉDUP pour éviter le spam)

const BASE = window.SODRINK_BASE || '';
const $ = (s, el=document) => el.querySelector(s);
const isSmallScreen = () => window.matchMedia('(max-width: 640px)').matches;

async function csrf(){
  const j = await fetch(`${BASE}/api/csrf.php`, {credentials:'include'}).then(r=>r.json());
  return j.data.csrf_token;
}

const btn = $('#btn-notif'),
      dd  = $('#notif-dropdown'),
      list= $('#notif-list'),
      badge = $('#notif-count');

let stream = null;
let isStreaming = false;
let pollTimer = null;
let latestKnownId = 0;

// ---- Déduplication (on mémorise les IDs déjà montrés)
const SHOWN_KEY = 'sodrink_notif_shown_v1';
let shownIds = new Set();
try {
  const raw = localStorage.getItem(SHOWN_KEY);
  if (raw) shownIds = new Set((JSON.parse(raw) || []).map(Number).filter(Number.isFinite));
} catch {}
function rememberShown(ids){
  let changed = false;
  ids.forEach(rawId => {
    const id = Number(rawId);
    if (!Number.isFinite(id)) return;
    if (!shownIds.has(id)) { shownIds.add(id); changed = true; }
  });
  if (changed) localStorage.setItem(SHOWN_KEY, JSON.stringify([...shownIds]));
}
function clearShown(){ shownIds = new Set(); localStorage.removeItem(SHOWN_KEY); }

// ---- Permission : on ne demande qu'une seule fois
const PERM_KEY = 'sodrink_notif_perm_asked';
async function ensurePermissionOnce(){
  try {
    if (!('Notification' in window)) return false;
    if (Notification.permission === 'default') {
      const asked = localStorage.getItem(PERM_KEY) === '1';
      if (!asked) {
        await Notification.requestPermission();
        localStorage.setItem(PERM_KEY, '1');
        return Notification.permission === 'granted';
      }
    }
    return Notification.permission === 'granted';
  } catch { return false; }
}

// ---- UI de base
function toggle(){ dd.hidden = !dd.hidden; }
document.addEventListener('click', (e)=>{
  if (!dd) return;
  if (!dd.contains(e.target) && !btn.contains(e.target)) dd.hidden = true;
});
btn?.addEventListener('click', async ()=>{
  toggle();
  // opportunité de demander la permission lors d'une action utilisateur
  await ensurePermissionOnce();
  // Mise à jour immédiate de la liste au clic sur la cloche
  await render();
});

// ---- Rendu liste + badge
function applyDropdownLayout(){
  if (!dd) return;
  if (isSmallScreen()) { dd.classList.add('notif-mobile'); }
  else { dd.classList.remove('notif-mobile'); }
}

function updateBadge(unread){
  if (!badge) return;
  if (unread > 0) { badge.textContent = unread; badge.hidden = false; }
  else { badge.hidden = true; }
}

function drawList(items){
  if (!list) return;
  list.innerHTML = items.length ? '' : '<div class="muted">Aucune notification.</div>';
  items.forEach(n=>{
    const a = document.createElement('a');
    a.href = n.link || '#';
    a.className = 'notif-item' + (n.read ? ' read' : '');
    a.innerHTML = `
      <span class="notif-dot"></span>
      <div class="notif-msg">${n.message}</div>
      ${isSmallScreen() ? '' : `<time>${new Date(n.created_at).toLocaleString()}</time>`}
    `;
    a.addEventListener('click', async (ev)=>{
      ev.preventDefault();
      await fetch(`${BASE}/api/notifications.php`, {
        method:'POST', credentials:'include',
        headers:{'Content-Type':'application/json','X-CSRF-Token':await csrf()},
        body: JSON.stringify({action:'read', id:n.id})
      });
      if (n.link) location.href = n.link;
      else await render();
    });
    list.appendChild(a);
  });
}

function trackLatestId(items){
  const base = latestKnownId;
  const maxId = (items || []).reduce((max, n) => {
    const id = Number(n.id) || 0;
    return id > max ? id : max;
  }, base);
  if (maxId > latestKnownId) latestKnownId = maxId;
  return latestKnownId;
}

async function render(){
  const j = await fetch(`${BASE}/api/notifications.php`, {credentials:'include'}).then(r=>r.json());
  if (!j?.success) return;
  const items = j.data.items || [];
  const unread = j.data.unread || 0;

  updateBadge(unread);
  drawList(items);
  trackLatestId(items);

  return items;
}

// ---- Notifications locales (dédup)
async function maybeNotify(items){
  try {
    if (!('Notification' in window)) return;
    if (Notification.permission !== 'granted') return;
    const ids = (items||[]).map(n=>Number(n.id)).filter(Number.isFinite);
    const unseen = ids.filter(id => !shownIds.has(id));
    if (unseen.length === 0) return;
    rememberShown(unseen);
    items
      .filter(n=>unseen.includes(Number(n.id)))
      .forEach(n=>{
        new Notification('SoDrink', {
          body: n.message || 'Nouvelle notification',
          icon: `${BASE}/assets/img/ui/avatar-default.svg`
        });
      });
  } catch {}
}

// ---- Polling (badge + notifs locales)
async function pollOnce(){
  const j = await fetch(`${BASE}/api/notifications.php`, {credentials:'include'}).then(r=>r.json());
  if (j?.success){
    const items = j.data.items || [];
    updateBadge(j.data.unread || 0);
    if (!isStreaming) await maybeNotify(items);
    trackLatestId(items);
  }
}

// ---- Marquer tout lu
$('#notif-readall')?.addEventListener('click', async ()=>{
  await fetch(`${BASE}/api/notifications.php`, {
    method:'POST', credentials:'include',
    headers:{'Content-Type':'application/json','X-CSRF-Token':await csrf()},
    body: JSON.stringify({action:'read_all'})
  });
  await render();
});

// ---- Boot
async function boot(){
  await ensurePermissionOnce();     // demande une fois (silencieux si déjà répondu)
  await render();                   // remplit le dropdown + badge
  // Adapter la dropdown aux mobiles sans toucher au CSS global
  applyDropdownLayout();
  if (!startStream()) {
    startPolling();
  }
}

function startPolling(){
  if (pollTimer) return;
  pollTimer = setInterval(pollOnce, 20000);
  pollOnce();
}

function stopPolling(){
  if (!pollTimer) return;
  clearInterval(pollTimer);
  pollTimer = null;
}

function startStream(){
  if (!('EventSource' in window)) return false;
  try {
    const lastId = latestKnownId > 0 ? `?last_id=${latestKnownId}` : '';
    const src = new EventSource(`${BASE}/api/notifications/stream.php${lastId}`);
    stream = src;
    src.addEventListener('open', ()=>{
      isStreaming = true;
      stopPolling();
    });
    src.addEventListener('error', ()=>{
      isStreaming = false;
      startPolling();
    });
    src.addEventListener('notification', async (ev)=>{
      try {
        const data = JSON.parse(ev.data || '{}');
        const items = Array.isArray(data.items) ? data.items : [];
        const newIds = Array.isArray(data.new_ids) ? data.new_ids.map(Number) : [];
        updateBadge(data.unread || 0);
        if (items.length) {
          drawList(items);
          if (newIds.length) {
            const fresh = items.filter(n => newIds.includes(Number(n.id)));
            if (fresh.length) await maybeNotify(fresh);
          } else {
            await maybeNotify(items);
          }
          trackLatestId(items);
        }
      } catch (err) {
        console.error('Notification stream error', err);
      }
    });
    return true;
  } catch (err) {
    console.error('EventSource init failed', err);
    return false;
  }
}

boot();

// Adapter dynamiquement si on rotate le téléphone / redimensionne
window.addEventListener('resize', applyDropdownLayout);
