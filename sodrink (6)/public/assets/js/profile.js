// profile.js — édition profil + upload avatar
import API from './api.js';
const BASE = window.SODRINK_BASE || '';

function $(s, el=document){ return el.querySelector(s); }

async function loadMe(){
  try{
    const { user } = await API.me();
    $('#form-profile [name=pseudo]').value = user.pseudo || '';
    $('#form-profile [name=prenom]').value = user.prenom || '';
    $('#form-profile [name=nom]').value = user.nom || '';
    $('#form-profile [name=instagram]').value = user.instagram || '';
    if (user.avatar) $('#profile-avatar').src = user.avatar;
  }catch(e){
    alert('Veuillez vous connecter.');
    location.href = BASE + '/';
  }
}

function bindProfileSave(){
  document.getElementById('form-profile')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const body = Object.fromEntries(fd.entries());
    try {
      await API.put('/api/users/me.php', body);
      alert('Profil mis à jour');
      await loadMe();
    } catch(err) { alert(err.message); }
  });
}

async function getCSRF(){
  const j = await fetch(`${BASE}/api/csrf.php`, {credentials:'include'}).then(r=>r.json());
  if (j?.success) return j.data.csrf_token;
  throw new Error('CSRF');
}

function bindAvatar(){
  document.getElementById('form-avatar')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    fd.append('csrf_token', await getCSRF());
    try{
      const res = await fetch(`${BASE}/api/users/avatar-upload.php`, { method:'POST', body: fd, credentials:'include' });
      const j = await res.json();
      if (!j.success) throw new Error(j.error || 'Erreur');
      document.getElementById('profile-avatar').src = j.data.avatar;
      alert('Avatar mis à jour');
    }catch(err){ alert(err.message); }
  });
}

window.addEventListener('DOMContentLoaded', () => { loadMe(); bindProfileSave(); bindAvatar(); });
