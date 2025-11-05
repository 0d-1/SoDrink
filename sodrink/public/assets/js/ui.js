// ui.js — Drawer à droite + recherche utilisateurs (suggestions)
const BASE = window.SODRINK_BASE || '';

function $(s, el=document){ return el.querySelector(s); }
function create(html){ const t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstChild; }

const drawer = $('#right-drawer');
const backdrop = $('#drawer-backdrop');

function openDrawer(){ drawer?.classList.add('open'); backdrop.hidden=false; drawer?.setAttribute('aria-hidden','false'); }
function closeDrawer(){ drawer?.classList.remove('open'); backdrop.hidden=true; drawer?.setAttribute('aria-hidden','true'); }

$('#btn-burger')?.addEventListener('click', openDrawer);
$('#drawer-close')?.addEventListener('click', closeDrawer);
backdrop?.addEventListener('click', closeDrawer);

// Boutons dans drawer
$('#drawer-login')?.addEventListener('click', () => {
  closeDrawer();
  document.getElementById('btn-open-login')?.click();
});
$('#drawer-logout')?.addEventListener('click', async () => {
  try{
    const j = await fetch(`${BASE}/api/csrf.php`,{credentials:'include'}).then(r=>r.json());
    if (!j?.success) throw new Error('CSRF');
    await fetch(`${BASE}/api/auth/logout.php`,{
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':j.data.csrf_token},
      credentials:'include', body:'{}'
    });
    location.reload();
  }catch(e){ alert('Erreur: '+e.message); }
});

// Recherche avec suggestions
const input = $('#user-search');
const box = $('#user-suggest');

let req = 0;
async function searchUsers(q){
  const id = ++req;
  if (!q || q.length < 2){ box.hidden=true; box.innerHTML=''; return; }
  try{
    const res = await fetch(`${BASE}/api/users/search.php?q=`+encodeURIComponent(q), {credentials:'include'});
    const j = await res.json();
    if (id !== req) return;
    if (!j.success){ box.hidden=true; return; }
    const users = j.data.users || [];
    if (!users.length){ box.hidden=true; box.innerHTML=''; return; }
    box.innerHTML = '';
    users.forEach(u => {
      const item = create(`<a class="suggest-item" href="${BASE}/user.php?u=${encodeURIComponent(u.pseudo)}">
        <img src="${u.avatar || (BASE+'/assets/img/ui/avatar-default.svg')}" alt="">
        <div><strong>${u.pseudo}</strong><div class="muted">${u.prenom||''} ${u.nom||''}</div></div>
      </a>`);
      box.appendChild(item);
    });
    box.hidden=false;
  }catch{ box.hidden=true; }
}

input?.addEventListener('input', (e)=> searchUsers(e.target.value));
document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeDrawer(); });
