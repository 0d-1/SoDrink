// chat.js — messagerie inspirée d’Instagram
import API from './api.js';

const BASE = window.SODRINK_BASE || '';
const ME = window.SODRINK_ME || {};
const CURRENT_URL = new URL(window.location.href);

function $(sel, scope = document) { return scope.querySelector(sel); }
function $all(sel, scope = document) { return Array.from(scope.querySelectorAll(sel)); }
function escapeHtml(str = '') { return str.replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }

let conversations = [];
let filterQuery = '';
let activeConversation = null;
let messages = [];
let pollTimer = null;
let fetchingMessages = false;
let pendingTargetPseudo = CURRENT_URL.searchParams.get('u') || CURRENT_URL.searchParams.get('user') || '';
let pendingConversationId = parseInt(CURRENT_URL.searchParams.get('conversation') || '', 10);
if (Number.isNaN(pendingConversationId)) pendingConversationId = null;
let initialTargetChecked = false;

const relationshipLabels = {
  single: 'Célibataire',
  relationship: 'En couple',
  married: 'Marié·e',
  complicated: 'C’est compliqué',
  hidden: 'Préférer ne pas dire',
};

function relativeTime(dateStr) {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  if (Number.isNaN(date.getTime())) return '';
  const now = new Date();
  const diff = (now - date) / 1000;
  if (diff < 60) return "à l’instant";
  if (diff < 3600) return `il y a ${Math.round(diff / 60)} min`;
  if (diff < 86400) return `il y a ${Math.round(diff / 3600)} h`;
  if (diff < 86400 * 7) return date.toLocaleDateString('fr-FR', { weekday: 'short', hour: '2-digit', minute: '2-digit' });
  return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
}

function fullDateTime(dateStr) {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleString('fr-FR', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short' });
}

function conversationName(conv) {
  if (conv.title) return conv.title;
  const others = (conv.participants || []).filter((p) => p.id !== ME.id);
  if (!others.length && conv.participants?.length) return conv.participants[0].pseudo;
  return others.map((p) => p.pseudo).join(', ') || 'Conversation';
}

function participantsLabel(conv) {
  const parts = (conv.participants || []).filter((p) => p.id !== ME.id).map((p) => p.pseudo);
  if (!parts.length && conv.participants?.length) {
    return conv.participants.map((p) => p.pseudo).join(' • ');
  }
  return parts.join(' • ');
}

function snippetFrom(conv) {
  if (!conv.last_message) return 'Aucun message pour l’instant';
  const sender = conv.last_message.sender?.pseudo || (conv.last_message.sender_id === ME.id ? 'Moi' : 'Quelqu’un');
  const content = conv.last_message.content || '';
  const text = `${sender}: ${content}`.trim();
  return text.length > 80 ? `${text.slice(0, 77)}…` : text;
}

function renderConversationList() {
  const list = $('#conversation-list');
  if (!list) return;
  list.innerHTML = '';
  const query = filterQuery.trim().toLowerCase();
  const items = conversations.filter((conv) => {
    if (!query) return true;
    const name = conversationName(conv).toLowerCase();
    const participants = (conv.participants || []).map((p) => `${p.pseudo} ${p.prenom || ''} ${p.nom || ''}`.toLowerCase()).join(' ');
    return name.includes(query) || participants.includes(query);
  });
  if (!items.length) {
    const empty = document.createElement('p');
    empty.className = 'muted';
    empty.textContent = 'Aucune conversation. Lance-toi !';
    list.appendChild(empty);
    return;
  }
  items.forEach((conv) => {
    const item = document.createElement('button');
    item.type = 'button';
    item.className = 'conversation-item' + (activeConversation?.id === conv.id ? ' is-active' : '');
    item.innerHTML = `
      <div class="conversation-main">
        <div class="conversation-title">${escapeHtml(conversationName(conv))}</div>
        <div class="conversation-snippet">${escapeHtml(snippetFrom(conv))}</div>
      </div>
      <div class="conversation-meta">${relativeTime(conv.last_message_at || conv.updated_at)}</div>
    `;
    item.addEventListener('click', () => openConversation(conv.id));
    list.appendChild(item);
  });
}

async function loadConversations() {
  try {
    const { conversations: list } = await API.get('/api/chat/conversations.php');
    conversations = list;
    renderConversationList();
    if (!initialTargetChecked) {
      if (pendingConversationId && conversations.some((conv) => conv.id === pendingConversationId)) {
        initialTargetChecked = true;
        openConversation(pendingConversationId);
        pendingConversationId = null;
      } else if (pendingTargetPseudo) {
        initialTargetChecked = true;
        const match = conversations.find((conv) => (conv.participants || []).some((p) => p.pseudo?.toLowerCase() === pendingTargetPseudo.toLowerCase()));
        if (match) {
          openConversation(match.id);
        } else {
          preselectFromPseudo(pendingTargetPseudo);
        }
      }
    }
  } catch (err) {
    console.error(err);
  }
}

async function fetchMessages(conversationId, { scroll = true } = {}) {
  if (fetchingMessages) return;
  fetchingMessages = true;
  try {
    const data = await API.get(`/api/chat/messages.php?conversation_id=${conversationId}`);
    activeConversation = data.conversation;
    messages = data.messages || [];
    renderConversation();
    if (scroll) scrollMessagesToBottom();
  } catch (err) {
    alert(err.message);
  } finally {
    fetchingMessages = false;
  }
}

function renderConversation() {
  const placeholder = $('#chat-placeholder');
  const room = $('#chat-room');
  if (!activeConversation) {
    placeholder.hidden = false;
    room.hidden = true;
    return;
  }
  placeholder.hidden = true;
  room.hidden = false;
  $('#chat-title').textContent = conversationName(activeConversation);
  $('#chat-participants').textContent = participantsLabel(activeConversation);
  const messagesContainer = $('#chat-messages');
  messagesContainer.innerHTML = '';
  messages.forEach((msg) => {
    const mine = msg.sender_id === ME.id;
    const bubble = document.createElement('div');
    bubble.className = 'message ' + (mine ? 'message-out' : 'message-in');
    const author = mine ? 'Moi' : (msg.sender?.pseudo || 'Inconnu');
    bubble.innerHTML = `
      <div class="message-meta">
        <span class="message-author">${escapeHtml(author)}</span>
        <time datetime="${escapeHtml(msg.created_at || '')}">${escapeHtml(fullDateTime(msg.created_at))}</time>
      </div>
      <div class="message-body">${escapeHtml(msg.content || '')}</div>
    `;
    messagesContainer.appendChild(bubble);
  });
}

function scrollMessagesToBottom() {
  const messagesContainer = $('#chat-messages');
  messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

async function openConversation(conversationId) {
  await fetchMessages(conversationId);
  renderConversationList();
  startPolling(conversationId);
}

function startPolling(conversationId) {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(() => {
    if (!activeConversation || activeConversation.id !== conversationId) return;
    fetchMessages(conversationId, { scroll: false });
    loadConversations();
  }, 8000);
}

function setupFilter() {
  $('#conversation-filter')?.addEventListener('input', (e) => {
    filterQuery = e.target.value;
    renderConversationList();
  });
}

function setupRefreshButton() {
  $('#chat-refresh')?.addEventListener('click', () => {
    if (activeConversation) {
      fetchMessages(activeConversation.id);
      loadConversations();
    }
  });
}

function setupSendMessage() {
  const form = $('#chat-form');
  if (!form) return;
  const textarea = $('#chat-input');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!activeConversation) return;
    const content = textarea.value.trim();
    if (!content) return;
    textarea.disabled = true;
    form.querySelector('button[type=submit]').disabled = true;
    try {
      const { message } = await API.post('/api/chat/messages.php', {
        conversation_id: activeConversation.id,
        content,
      });
      messages.push(message);
      textarea.value = '';
      renderConversation();
      scrollMessagesToBottom();
      loadConversations();
    } catch (err) {
      alert(err.message);
    } finally {
      textarea.disabled = false;
      form.querySelector('button[type=submit]').disabled = false;
      textarea.focus();
    }
  });

  textarea.addEventListener('input', () => {
    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(160, textarea.scrollHeight)}px`;
  });
}

// ===== Nouvelle conversation =====
const participantMap = new Map();

function renderSelectedParticipants() {
  const container = $('#selected-participants');
  if (!container) return;
  container.innerHTML = '';
  if (!participantMap.size) {
    const info = document.createElement('p');
    info.className = 'muted';
    info.textContent = 'Ajoute des membres via la recherche ci-dessus.';
    container.appendChild(info);
    return;
  }
  participantMap.forEach((user) => {
    const chip = document.createElement('div');
    chip.className = 'participant-chip';
    chip.innerHTML = `
      <img src="${escapeHtml(user.avatar || (BASE + '/assets/img/ui/avatar-default.svg'))}" alt="">
      <span>${escapeHtml(user.pseudo)}</span>
      <button type="button" class="btn btn-sm btn-outline" aria-label="Retirer ${escapeHtml(user.pseudo)}">✕</button>
    `;
    chip.querySelector('button').addEventListener('click', () => {
      participantMap.delete(user.id);
      renderSelectedParticipants();
    });
    container.appendChild(chip);
  });
}

function openModal() {
  const modal = $('#modal-conversation');
  if (!modal) return;
  modal.hidden = false;
  modal.querySelector('input[type=search]')?.focus();
}

function closeModal() {
  const modal = $('#modal-conversation');
  if (!modal) return;
  modal.hidden = true;
  participantMap.clear();
  renderSelectedParticipants();
  $('#form-conversation')?.reset();
  $('#participant-suggest')?.setAttribute('hidden', 'true');
}

function selectParticipant(user) {
  if (!user || !user.id || user.id === ME.id) return;
  if (participantMap.has(user.id)) return;
  participantMap.set(user.id, user);
  renderSelectedParticipants();
}

let suggestTimer = null;
function setupParticipantSearch() {
  const searchInput = $('#participant-search');
  const box = $('#participant-suggest');
  if (!searchInput || !box) return;

  searchInput.addEventListener('input', () => {
    const q = searchInput.value.trim();
    if (suggestTimer) clearTimeout(suggestTimer);
    if (q.length < 2) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    suggestTimer = setTimeout(async () => {
      try {
        const res = await fetch(`${BASE}/api/users/search.php?q=${encodeURIComponent(q)}`, { credentials: 'include' });
        const j = await res.json();
        if (!j.success) { box.hidden = true; return; }
        const users = (j.data.users || []).filter((u) => u.id !== ME.id);
        if (!users.length) { box.hidden = true; box.innerHTML = ''; return; }
        box.innerHTML = '';
        users.forEach((u) => {
          const item = document.createElement('button');
          item.type = 'button';
          item.className = 'suggest-item';
          item.innerHTML = `
            <img src="${escapeHtml(u.avatar || (BASE + '/assets/img/ui/avatar-default.svg'))}" alt="">
            <div><strong>${escapeHtml(u.pseudo)}</strong><div class="muted">${escapeHtml((u.prenom || '') + ' ' + (u.nom || ''))}</div></div>
          `;
          item.addEventListener('click', () => {
            selectParticipant(u);
            searchInput.value = '';
            box.hidden = true;
          });
          box.appendChild(item);
        });
        box.hidden = false;
      } catch {
        box.hidden = true;
      }
    }, 200);
  });

  document.addEventListener('click', (e) => {
    if (!box || box.hidden) return;
    if (!box.contains(e.target) && e.target !== searchInput) {
      box.hidden = true;
    }
  });
}

function setupModalButtons() {
  $('#btn-new-conversation')?.addEventListener('click', () => {
    openModal();
  });
  $('#modal-conversation-close')?.addEventListener('click', closeModal);
  $('#modal-conversation-cancel')?.addEventListener('click', closeModal);
  document.addEventListener('keydown', (e) => {
    const modal = $('#modal-conversation');
    if (!modal || modal.hidden) return;
    if (e.key === 'Escape') closeModal();
  });
}

function setupConversationForm() {
  const form = $('#form-conversation');
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const participants = Array.from(participantMap.values()).map((u) => u.id);
    if (!participants.length) {
      alert('Ajoute au moins un participant.');
      return;
    }
    const title = form.title.value.trim();
    try {
      const { conversation } = await API.post('/api/chat/conversations.php', {
        title,
        participants,
      });
      closeModal();
      await loadConversations();
      openConversation(conversation.id);
    } catch (err) {
      alert(err.message);
    }
  });
}

async function preselectFromPseudo(pseudo) {
  try {
    const res = await fetch(`${BASE}/api/users/get.php?u=${encodeURIComponent(pseudo)}`, { credentials: 'include' });
    const j = await res.json();
    if (!j?.success) return;
    const user = j.data.user;
    selectParticipant(user);
    openModal();
    pendingTargetPseudo = '';
  } catch {
    // ignore
  }
}

function cleanup() {
  window.addEventListener('beforeunload', () => {
    if (pollTimer) clearInterval(pollTimer);
  });
}

window.addEventListener('DOMContentLoaded', () => {
  renderConversationList();
  loadConversations();
  setupFilter();
  setupRefreshButton();
  setupSendMessage();
  setupModalButtons();
  setupParticipantSearch();
  setupConversationForm();
  cleanup();
});
