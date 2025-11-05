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

// ---- Déduplication (on mémorise les IDs déjà montrés)
const SHOWN_KEY = 'sodrink_notif_shown_v1';
let shownIds = new Set();
try {
  const raw = localStorage.getItem(SHOWN_KEY);
  if (raw) shownIds = new Set(JSON.parse(raw));
} catch {}
function rememberShown(ids){
  let changed = false;
  ids.forEach(id => { if (!shownIds.has(id)) { shownIds.add(id); changed = true; } });
  if (changed) localStorage.setItem(SHOWN_KEY, JSON.stringify([...shownIds]));
}
function clearShown(){ shownIds = new Set(); localStorage.removeItem(SHOWN_KEY); }

// ---- Permission : on ne demande qu'une seule fois
const PERM_KEY = 'sodrink_notif_perm_asked';
async function ensurePermissionOnce(){
  try {
    if (Notification && Notification.permission === 'default') {
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
async function render(){
  const j = await fetch(`${BASE}/api/notifications.php`, {credentials:'include'}).then(r=>r.json());
  if (!j?.success) return;
  const items = j.data.items || [];
  const unread = j.data.unread || 0;

  if (unread > 0) { badge.textContent = unread; badge.hidden = false; }
  else { badge.hidden = true; }

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

  return items;
}

// ---- Notifications locales (dédup)
async function maybeNotify(items){
  try {
    if (!('Notification' in window)) return;
    if (Notification.permission !== 'granted') return;
    const ids = (items||[]).map(n=>n.id);
    const unseen = ids.filter(id => !shownIds.has(id));
    if (unseen.length === 0) return;
    rememberShown(unseen);
    items.filter(n=>unseen.includes(n.id)).forEach(n=>{
      new Notification('SoDrink', { body: n.message || 'Nouvelle notification', icon: `${BASE}/assets/img/ui/avatar-default.svg` });
    });
  } catch {}
}

// ---- Polling (badge + notifs locales)
async function pollOnce(){
  const j = await fetch(`${BASE}/api/notifications.php`, {credentials:'include'}).then(r=>r.json());
  if (j?.success){
    const unread = j.data.unread || 0;
    if (unread > 0) { badge.textContent = unread; badge.hidden = false; } else { badge.hidden = true; }
    await maybeNotify(j.data.items);
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
  if (dd) {
    if (isSmallScreen()) { dd.style.width = '95vw'; dd.style.maxWidth = '95vw'; dd.style.right = '2.5vw'; }
    else { dd.style.removeProperty('width'); dd.style.removeProperty('max-width'); dd.style.removeProperty('right'); }
  }
  setInterval(pollOnce, 20000);     // rafraîchit le badge + notifs (dédupliquées)
}

boot();

// Adapter dynamiquement si on rotate le téléphone / redimensionne
window.addEventListener('resize', ()=>{
  if (!dd) return;
  if (isSmallScreen()) { dd.style.width='95vw'; dd.style.maxWidth='95vw'; dd.style.right='2.5vw'; }
  else { dd.style.removeProperty('width'); dd.style.removeProperty('max-width'); dd.style.removeProperty('right'); }
});
