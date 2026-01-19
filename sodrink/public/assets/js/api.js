// public/assets/js/api.js — Wrapper fetch + CSRF + BASE URL

const BASE = window.SODRINK_BASE || '';

const API = (() => {
  let csrf = null;
  let csrfPromise = null;

  async function getCSRF() {
    if (csrf) return csrf;
    if (!csrfPromise) {
      csrfPromise = fetch(`${BASE}/api/csrf.php`, { credentials: 'include' })
        .then(r => r.json())
        .then(j => { if (j.success) { csrf = j.data.csrf_token; return csrf; } throw new Error('CSRF'); })
        .catch(e => { csrfPromise = null; throw e; });
    }
    return csrfPromise;
  }

  async function request(method, url, data) {
    const opts = { method, headers: { 'Accept': 'application/json' }, credentials: 'include' };
    if (method !== 'GET' && method !== 'HEAD') {
      const token = await getCSRF();
      opts.headers['Content-Type'] = 'application/json';
      opts.headers['X-CSRF-Token'] = token;
      opts.body = JSON.stringify(data || {});
    }
    const res = await fetch(`${BASE}${url}`, opts);
    const json = await res.json().catch(() => ({ success: false, error: 'Réponse illisible' }));
    if (!json.success) throw new Error(json.error || 'Erreur');
    return json.data;
  }

  async function postForm(url, formData) {
    const token = await getCSRF();
    const opts = {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': token },
      credentials: 'include',
      body: formData,
    };
    const res = await fetch(`${BASE}${url}`, opts);
    const json = await res.json().catch(() => ({ success: false, error: 'Réponse illisible' }));
    if (!json.success) throw new Error(json.error || 'Erreur');
    return json.data;
  }

  return {
    get: (url) => request('GET', url),
    post: (url, data) => request('POST', url, data),
    put: (url, data) => request('PUT', url, data),
    patch: (url, data) => request('PATCH', url, data),
    del: (url, data) => request('DELETE', url, data),
    me: () => request('GET', '/api/users/me.php'),
    postForm,
  };
})();

export default API;
