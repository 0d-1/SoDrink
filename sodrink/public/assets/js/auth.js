// auth.js — Connexion/Inscription/Logout + nav (redirige vers /login.php)
import API from './api.js';

const BASE = window.SODRINK_BASE || '';
const $ = (sel, el=document) => el.querySelector(sel);

async function isLogged() {
  try { await API.me(); return true; } catch { return false; }
}

async function refreshNav() {
  if (await isLogged()) {
    $('#btn-open-login')?.setAttribute('hidden', 'hidden');
    $('#drawer-login')?.setAttribute('hidden', 'hidden');
    $('#btn-logout')?.removeAttribute('hidden');
  } else {
    $('#btn-open-login')?.removeAttribute('hidden');
    $('#drawer-login')?.removeAttribute('hidden');
    $('#btn-logout')?.setAttribute('hidden', 'hidden');
  }
}

/** Nouveau : ces boutons ouvrent la page /login.php au lieu de la modale */
function bindLoginRedirect() {
  const go = (e) => { e.preventDefault(); location.href = `${BASE}/login.php`; };
  $('#btn-open-login')?.addEventListener('click', go);
  $('#drawer-login')?.addEventListener('click', go);
  // support d’un éventuel data-open-login déjà présent
  document.querySelectorAll('[data-open-login]').forEach(el => el.addEventListener('click', go));
}

function bindLogout() {
  document.getElementById('btn-logout')?.addEventListener('click', async () => {
    try { await API.post('/api/auth/logout.php', {}); location.reload(); }
    catch (err) { alert(err.message); }
  });
}

window.addEventListener('DOMContentLoaded', async () => {
  bindLoginRedirect();
  bindLogout();
  await refreshNav();
});
