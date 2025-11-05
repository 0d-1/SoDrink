// public/assets/js/login-page.js — logique de la page de connexion/inscription
import API from './api.js';

const $ = (s, el=document)=> el.querySelector(s);
const container = document.querySelector('.login-page .container-ui');
const loginLink    = document.querySelector('.SignInLink');
const registerLink = document.querySelector('.SignUpLink');

registerLink?.addEventListener('click', (e)=>{ e.preventDefault(); container?.classList.add('active'); });
loginLink?.addEventListener('click', (e)=>{ e.preventDefault(); container?.classList.remove('active'); });

/* ---------- Connexion ---------- */
const formLogin   = $('#form-login');
const loginError  = $('#login-error');
const loginBtn    = $('#login-submit');
const pseudoInput = $('#login-pseudo');
const passInput   = $('#login-password');
const rememberCb  = $('#login-remember');

function setLoginSubmitting(on){
  loginBtn?.toggleAttribute('disabled', on);
  loginBtn?.querySelector('.btn-label')?.toggleAttribute('hidden', on);
  loginBtn?.querySelector('.btn-spinner')?.toggleAttribute('hidden', !on);
  pseudoInput?.toggleAttribute('disabled', on);
  passInput?.toggleAttribute('disabled', on);
  rememberCb?.toggleAttribute('disabled', on);
}
function showLoginError(msg){
  if (!loginError) return;
  loginError.textContent = msg;
  loginError.hidden = !msg;
}

formLogin?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  showLoginError('');
  const pseudo = (pseudoInput?.value||'').trim();
  const password = (passInput?.value||'');
  const remember = !!rememberCb?.checked;

  if (pseudo.length < 3){ pseudoInput?.focus(); return showLoginError('Le pseudo doit faire au moins 3 caractères.'); }
  if (password.length < 8){ passInput?.focus(); return showLoginError('Le mot de passe doit faire au moins 8 caractères.'); }

  setLoginSubmitting(true);
  try{
    await API.post('/api/auth/login.php', { pseudo, password, remember: remember ? 1 : 0 });
    sessionStorage.setItem('just_logged_in','1');
    location.href = `${window.SODRINK_BASE || ''}/`;
  }catch(err){
    showLoginError(err?.message || 'Identifiants incorrects');
    setLoginSubmitting(false);
    passInput?.select?.();
  }
});

/* ---------- Inscription ---------- */
const formReg   = $('#form-register');
const regBtn    = $('#register-submit');
const regErr    = $('#register-error');
const regOk     = $('#register-ok');

const regPseudo = $('#reg-pseudo');
const regPrenom = $('#reg-prenom');
const regNom    = $('#reg-nom');
const regPass   = $('#reg-password');
const regInsta  = $('#reg-instagram');

function setRegSubmitting(on){
  regBtn?.toggleAttribute('disabled', on);
  regBtn?.querySelector('.btn-label')?.toggleAttribute('hidden', on);
  regBtn?.querySelector('.btn-spinner')?.toggleAttribute('hidden', !on);
  [regPseudo, regPrenom, regNom, regPass, regInsta].forEach(i=> i?.toggleAttribute('disabled', on));
}
function showRegError(msg){ if (regErr){ regErr.textContent = msg||''; regErr.hidden = !msg; } }
function showRegOk(show){ if (regOk){ regOk.hidden = !show; } }

formReg?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  showRegError(''); showRegOk(false);

  const payload = {
    pseudo:   (regPseudo?.value||'').trim(),
    prenom:   (regPrenom?.value||'').trim(),
    nom:      (regNom?.value||'').trim(),
    password: (regPass?.value||''),
    instagram: (regInsta?.value||'').trim() || null,
  };

  if (payload.pseudo.length < 3)   return showRegError('Pseudo invalide (min 3).');
  if (payload.prenom.length < 2)   return showRegError('Prénom invalide.');
  if (payload.nom.length < 2)      return showRegError('Nom invalide.');
  if (payload.password.length < 8) return showRegError('Mot de passe trop court (min 8).');

  setRegSubmitting(true);
  try{
    await API.post('/api/auth/register.php', payload);
    showRegOk(true);
    // L’utilisateur est connecté automatiquement par l’API d’inscription
    setTimeout(()=> { location.href = `${window.SODRINK_BASE || ''}/`; }, 650);
  }catch(err){
    showRegError(err?.message || 'Erreur lors de la création du compte.');
    setRegSubmitting(false);
  }
});
