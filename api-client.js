/* ============================================================
   GROUPE JNAK SARL – API Client JS
   Remplace le système localStorage par des appels PHP+MySQL
   
   INTÉGRATION : Ajoutez cette balise dans votre HTML :
   <script src="/js/api-client.js"></script>
   Puis retirez ou commentez les fonctions saveArticles(),
   loadArticles() et le tableau ARTICLES du fichier HTML.
   ============================================================ */

/* ── Configuration ───────────────────────────────────────── */
const API_BASE = '/api'; // Chemin de votre dossier api/

/* ── Stockage du token admin ─────────────────────────────── */
const Auth = {
  getToken: ()  => sessionStorage.getItem('jnak_token'),
  setToken: (t) => sessionStorage.setItem('jnak_token', t),
  clear:    ()  => sessionStorage.removeItem('jnak_token'),
  headers:  ()  => ({
    'Content-Type': 'application/json',
    ...(Auth.getToken() ? { 'Authorization': 'Bearer ' + Auth.getToken() } : {}),
  }),
};

/* ── Appel générique ─────────────────────────────────────── */
async function apiFetch(path, options = {}) {
  const url = API_BASE + path;
  const res  = await fetch(url, {
    headers: Auth.headers(),
    ...options,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `Erreur ${res.status}`);
  return data;
}

/* ============================================================
   AUTH
   ============================================================ */
const ApiAuth = {
  /** Connexion admin → retourne le token */
  async login(username, password) {
    const data = await apiFetch('/auth.php?action=login', {
      method: 'POST',
      body: JSON.stringify({ username, password }),
    });
    Auth.setToken(data.token);
    return data;
  },

  /** Vérifier si le token est encore valide */
  async check() {
    if (!Auth.getToken()) return false;
    try {
      await apiFetch('/auth.php?action=check');
      return true;
    } catch {
      Auth.clear();
      return false;
    }
  },

  logout() {
    Auth.clear();
  },
};

/* ============================================================
   ARTICLES
   ============================================================ */
const ApiArticles = {
  /** Récupérer les articles publiés (public) */
  async list(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return apiFetch(`/articles.php${qs ? '?' + qs : ''}`);
  },

  /** Récupérer un article + ses commentaires (public) */
  async get(id) {
    return apiFetch(`/articles.php?id=${id}`);
  },

  /** Liker / unliker un article (public) */
  async toggleLike(id) {
    return apiFetch(`/articles.php?action=like&id=${id}`, { method: 'POST' });
  },

  /** Créer un article (admin) */
  async create(article) {
    return apiFetch('/articles.php', {
      method: 'POST',
      body: JSON.stringify(article),
    });
  },

  /** Modifier un article (admin) */
  async update(id, article) {
    return apiFetch(`/articles.php?id=${id}`, {
      method: 'PUT',
      body: JSON.stringify(article),
    });
  },

  /** Supprimer un article (admin) */
  async delete(id) {
    return apiFetch(`/articles.php?id=${id}`, { method: 'DELETE' });
  },
};

/* ============================================================
   COMMENTAIRES
   ============================================================ */
const ApiComments = {
  /** Ajouter un commentaire (public) */
  async add(articleId, name, text) {
    return apiFetch('/comments.php', {
      method: 'POST',
      body: JSON.stringify({ article_id: articleId, name, text }),
    });
  },

  /** Supprimer un commentaire (admin) */
  async delete(id) {
    return apiFetch(`/comments.php?id=${id}`, { method: 'DELETE' });
  },
};

/* ============================================================
   CONTACT
   ============================================================ */
const ApiContact = {
  async send(form) {
    return apiFetch('/contact.php', {
      method: 'POST',
      body: JSON.stringify(form),
    });
  },
};

/* ============================================================
   STATS (admin)
   ============================================================ */
const ApiStats = {
  async get() {
    return apiFetch('/stats.php');
  },
};

/* ============================================================
   REMPLACEMENT DES FONCTIONS LOCALSTORAGE
   Ces fonctions remplacent celles du fichier HTML original.
   ============================================================ */

let ARTICLES     = [];   // Cache local (rechargé depuis l'API)
let currentLiked = new Set();
let activeFilter = 'all';
let openArticleId = null;

function tagCls(tag) {
  const TAG_CLASS = { Agriculture: 'blog-tag-green', Business: 'blog-tag-orange' };
  return TAG_CLASS[tag] || '';
}
function fmtDateNow() {
  return new Date().toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}

/** Charge les articles depuis le serveur */
async function loadArticles() {
  try {
    const res = await ApiArticles.list({ status: 'published' });
    // Adapter le format API → format attendu par renderBlog()
    ARTICLES = (res.data || []).map(a => ({
      id:       parseInt(a.id),
      tag:      a.tag,
      author:   a.author,
      date:     new Date(a.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }),
      title:    a.title,
      excerpt:  a.excerpt,
      content:  a.content || '',
      image:    a.image_url || '',
      likes:    parseInt(a.likes),
      comments: [],  // chargés à l'ouverture de l'article
      status:   a.status,
      user_liked: a.user_liked || false,
    }));
    // Reconstituer le set des articles likés depuis l'API
    currentLiked = new Set(ARTICLES.filter(a => a.user_liked).map(a => a.id));
  } catch (err) {
    console.warn('API indisponible, mode hors-ligne :', err.message);
    // Fallback : données par défaut si l'API est inaccessible
    ARTICLES = [];
  }
}

/** saveArticles() n'est plus nécessaire (tout est en base) */
function saveArticles() { /* no-op */ }
function saveLiked()    { /* no-op */ }

/* ============================================================
   SURCHARGE DE toggleLike – appel API au lieu de localStorage
   ============================================================ */
async function toggleLike(e, id) {
  e.stopPropagation();
  const art = ARTICLES.find(a => a.id === id);
  if (!art) return;
  const btn = e.currentTarget;

  try {
    const res = await ApiArticles.toggleLike(id);
    art.likes = res.likes;
    art.user_liked = res.liked;

    if (res.liked) {
      currentLiked.add(id);
      btn.classList.add('liked');
      btn.querySelector('path').setAttribute('fill', '#e74c3c');
      btn.querySelector('path').setAttribute('stroke', '#e74c3c');
    } else {
      currentLiked.delete(id);
      btn.classList.remove('liked');
      btn.querySelector('path').setAttribute('fill', 'none');
      btn.querySelector('path').setAttribute('stroke', '#999');
    }
    const counter = document.getElementById('like-count-' + id);
    if (counter) counter.textContent = art.likes;
  } catch (err) {
    console.error('Erreur like :', err.message);
  }
}

/* ============================================================
   SURCHARGE DE openArticle – charge l'article complet depuis l'API
   ============================================================ */
async function openArticle(id) {
  try {
    const res = await ApiArticles.get(id);
    const a   = res.data;
    openArticleId = id;

    // Mettre à jour le cache local
    const idx = ARTICLES.findIndex(x => x.id === id);
    if (idx >= 0) {
      ARTICLES[idx].content  = a.content;
      ARTICLES[idx].comments = (a.comments || []).map(c => ({
        name: c.name,
        text: c.text,
        time: new Date(c.created_at).toLocaleDateString('fr-FR'),
      }));
      ARTICLES[idx].user_liked = a.user_liked;
    }

    const modal = document.getElementById('articleModal');
    const cover = document.getElementById('modal-cover');
    cover.src = a.image_url || '';
    cover.style.display = a.image_url ? 'block' : 'none';
    document.getElementById('modal-tag').textContent  = a.tag;
    document.getElementById('modal-tag').className    = 'modal-tag ' + tagCls(a.tag);
    document.getElementById('modal-title').textContent = a.title;
    document.getElementById('modal-meta').textContent  =
      new Date(a.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) +
      ' · Par ' + a.author;
    document.getElementById('modal-body').innerHTML = a.content;

    // Afficher les commentaires
    const artObj = ARTICLES[idx] || { comments: [] };
    renderComments(artObj);
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
  } catch (err) {
    console.error('Impossible de charger l\'article :', err.message);
  }
}

/* ============================================================
   SURCHARGE DE cmt-submit – envoi commentaire via API
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  const cmtBtn = document.getElementById('cmt-submit');
  if (cmtBtn) {
    // Retirer l'ancien listener et en ajouter un nouveau
    cmtBtn.replaceWith(cmtBtn.cloneNode(true));
    document.getElementById('cmt-submit').addEventListener('click', async () => {
      const name = document.getElementById('cmt-name').value.trim();
      const text = document.getElementById('cmt-text').value.trim();
      if (!name || !text) return;
      if (!openArticleId) return;

      try {
        const res = await ApiComments.add(openArticleId, name, text);
        const art = ARTICLES.find(a => a.id === openArticleId);
        if (art) {
          art.comments.unshift({ name, text, time: 'À l\'instant' });
          renderComments(art);
          const cmtCounter = document.querySelector(`.blog-card[data-id="${openArticleId}"] .cmt-btn span`);
          if (cmtCounter) cmtCounter.textContent = art.comments.length;
        }
        document.getElementById('cmt-name').value = '';
        document.getElementById('cmt-text').value = '';
      } catch (err) {
        alert('Erreur : ' + err.message);
      }
    });
  }

  // Surcharge du formulaire de contact
  const cform = document.getElementById('cform');
  if (cform) {
    cform.addEventListener('submit', async (e) => {
      e.preventDefault();
      const csend = document.getElementById('csend');
      const cmsg  = document.getElementById('cmsg');
      csend.disabled   = true;
      csend.textContent = 'Envoi en cours…';
      cmsg.className   = 'cmsg';
      cmsg.textContent = '';
      try {
        await ApiContact.send({
          nom:     document.getElementById('cnom').value.trim(),
          email:   document.getElementById('cemail').value.trim(),
          sujet:   document.getElementById('csujet').value.trim(),
          domaine: document.getElementById('cdomaine').value,
          message: document.getElementById('cmessage').value.trim(),
        });
        cmsg.className   = 'cmsg ok';
        cmsg.textContent = '✅ Message envoyé ! Nous vous répondrons rapidement.';
        cform.reset();
      } catch (err) {
        cmsg.className   = 'cmsg err';
        cmsg.textContent = '❌ ' + err.message;
      }
      csend.disabled   = false;
      csend.textContent = 'Envoyer le message';
    });
  }
});

/* ============================================================
   SURCHARGE DE l'auth admin – appel API au lieu du check JS
   ============================================================ */
async function doAdminLogin() {
  const u   = document.getElementById('al-user').value.trim();
  const p   = document.getElementById('al-pass').value;
  const err = document.getElementById('al-err');
  const btn = document.getElementById('al-btn-text');

  if (!u || !p) { err.textContent = '⚠️ Veuillez remplir les deux champs.'; return; }

  btn.textContent = 'Connexion…';
  try {
    await ApiAuth.login(u, p);
    document.getElementById('adminLoginModal').classList.remove('open');
    document.getElementById('adminDashboard').classList.add('open');
    document.body.style.overflow = 'hidden';
    err.textContent = '';
    document.getElementById('al-pass').value = '';
    refreshAdminData();
    goAdminPage('overview');
  } catch (e) {
    err.textContent = '❌ ' + e.message;
    document.getElementById('al-pass').value = '';
  }
  btn.textContent = 'Se connecter →';
}

/* ============================================================
   SURCHARGE DE adashSubmit – sauvegarde via API
   ============================================================ */
async function adashSubmit(status) {
  const title   = document.getElementById('af-title').value.trim();
  const author  = document.getElementById('af-author').value.trim();
  const tag     = document.getElementById('af-tag').value;
  const excerpt = document.getElementById('af-excerpt').value.trim();
  const content = document.getElementById('af-content-editor').innerHTML.trim();
  const imgUrl  = document.getElementById('af-img').value.trim();
  const editingId = document.getElementById('af-editing-id').value;

  if (!title || !author || !excerpt || !content) {
    adashToast('⚠️ Remplissez tous les champs obligatoires.', 'err');
    return;
  }

  const payload = { title, author, tag, excerpt, content, image_url: imgUrl, status };

  try {
    if (editingId) {
      await ApiArticles.update(parseInt(editingId), payload);
      adashToast(status === 'published' ? '✅ Article mis à jour et publié !' : '💾 Modifications sauvegardées.');
    } else {
      await ApiArticles.create(payload);
      adashToast(status === 'published' ? '🚀 Article publié avec succès !' : '💾 Brouillon enregistré.');
    }
    await loadArticles();
    renderBlog();
    resetAdminForm();
    await refreshAdminData();
    goAdminPage('articles');
  } catch (err) {
    adashToast('❌ ' + err.message, 'err');
  }
}

/* ============================================================
   SURCHARGE DE adashPublishNow
   ============================================================ */
async function adashPublishNow(id) {
  try {
    await ApiArticles.update(id, { status: 'published' });
    await loadArticles();
    renderBlog();
    renderAdashArticles();
    refreshAdminData();
    adashToast('🚀 Article publié !');
  } catch (err) {
    adashToast('❌ ' + err.message, 'err');
  }
}

/* ============================================================
   SURCHARGE DE aconf-ok (suppression)
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  const confOk = document.getElementById('aconf-ok');
  if (confOk) {
    confOk.replaceWith(confOk.cloneNode(true));
    document.getElementById('aconf-ok').addEventListener('click', async () => {
      if (pendingDelId != null) {
        try {
          await ApiArticles.delete(pendingDelId);
          await loadArticles();
          renderBlog();
          renderAdashArticles();
          renderAdashOverview();
          adashToast('🗑️ Article supprimé.', 'err');
        } catch (err) {
          adashToast('❌ ' + err.message, 'err');
        }
      }
      document.getElementById('adash-confirm').classList.remove('open');
      pendingDelId = null;
    });
  }
});

/* ============================================================
   SURCHARGE DE refreshAdminData – utilise l'API stats
   ============================================================ */
async function refreshAdminData() {
  try {
    const res = await ApiStats.get();
    const d   = res.data;

    // Mettre à jour le cache ARTICLES avec tous les statuts
    const allRes = await ApiArticles.list({ status: 'all' });
    ARTICLES = (allRes.data || []).map(a => ({
      id:       parseInt(a.id),
      tag:      a.tag,
      author:   a.author,
      date:     new Date(a.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }),
      title:    a.title,
      excerpt:  a.excerpt,
      content:  a.content || '',
      image:    a.image_url || '',
      likes:    parseInt(a.likes),
      comments: [],
      status:   a.status,
    }));

    // Stats
    document.getElementById('astat-pub').textContent   = d.published;
    document.getElementById('astat-draft').textContent = d.drafts;
    document.getElementById('astat-likes').textContent = d.total_likes;
    document.getElementById('astat-cmt').textContent   = d.total_comments;
    document.getElementById('adash-art-count').textContent = ARTICLES.length;

    renderAdashOverview();
    renderAdashArticles();
  } catch (err) {
    console.warn('Stats API :', err.message);
  }
}

/* ── Initialisation ─────────────────────────────────────── */
(async () => {
  await loadArticles();
  renderBlog();
})();
