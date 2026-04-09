# 🌍 Groupe Jnak SARL – Site Web Complet

> Site vitrine + Blog + Dashboard Admin  
> Stack : HTML/CSS/JS (Frontend) + PHP 8+ / MySQL (Backend)

---

## 📁 Structure du projet

```
groupe-jnak/
│
├── 📄 README.md                    ← Ce fichier
├── 📄 install.sql                  ← Script MySQL (à exécuter 1 seule fois)
│
└── 📂 public_html/                 ← 📤 CE DOSSIER va entier sur l'hébergeur
    │
    ├── 📄 index.html               ← Site complet (frontend intégré)
    ├── 📄 .htaccess                ← Sécurité Apache + CORS
    │
    ├── 📂 js/
    │   └── 📄 api-client.js        ← Client API (connecte HTML ↔ PHP)
    │
    ├── 📂 api/                     ← Endpoints PHP REST
    │   ├── 📄 articles.php         ← CRUD articles
    │   ├── 📄 comments.php         ← Commentaires
    │   ├── 📄 auth.php             ← Login admin JWT
    │   ├── 📄 contact.php          ← Formulaire de contact
    │   └── 📄 stats.php            ← Statistiques dashboard
    │
    ├── 📂 config/
    │   ├── 📄 database.php         ← ⚠️ Config BDD (jamais public)
    │   └── 📄 .env.example         ← Modèle de variables
    │
    └── 📂 assets/
        ├── 📂 images/
        └── 📂 css/
```

---

## 🚀 Installation en 5 étapes

### Étape 1 — Créer la base de données MySQL
1. Connectez-vous à **cPanel** (Hostinger / Namecheap)
2. **MySQL Databases** → Créez `jnak_db` + utilisateur `jnak_user` avec tous les droits
3. **phpMyAdmin** → sélectionnez `jnak_db` → onglet **SQL** → collez `install.sql` → Exécuter

### Étape 2 — Configurer `config/database.php`
Modifiez DB_HOST, DB_NAME, DB_USER, DB_PASS, JWT_SECRET, ADMIN_PASS, SITE_URL

### Étape 3 — Uploader `public_html/` sur votre serveur
Via FTP (FileZilla) ou le Gestionnaire de fichiers cPanel — permissions : fichiers 644, dossiers 755

### Étape 4 — Tester
- `https://votre-site.com` → le site s'affiche
- `https://votre-site.com/api/articles.php` → retourne du JSON
- `https://votre-site.com/config/database.php` → doit retourner Forbidden

### Étape 5 — Sécurité
- Supprimez `install.sql` du serveur
- Changez le mot de passe admin
- Activez HTTPS (Let's Encrypt via cPanel)

---

## 🔐 Accès Admin

| Champ | Valeur par défaut |
|-------|-------------------|
| Bouton | "Espace Admin" dans la navbar |
| Identifiant | `admin` |
| Mot de passe | `jnak2024` |

> ⚠️ Changez le mot de passe dans `config/database.php` avant la mise en ligne !

---

## 🔌 API REST — Résumé

| Endpoint | Public | Admin |
|----------|--------|-------|
| GET /api/articles.php | ✅ Liste publiés | ✅ Tous statuts |
| POST /api/articles.php | — | ✅ Créer |
| PUT /api/articles.php?id=X | — | ✅ Modifier |
| DELETE /api/articles.php?id=X | — | ✅ Supprimer |
| POST /api/articles.php?action=like&id=X | ✅ Like/unlike | — |
| POST /api/comments.php | ✅ Commenter | — |
| DELETE /api/comments.php?id=X | — | ✅ Supprimer |
| POST /api/auth.php?action=login | ✅ Login | — |
| POST /api/contact.php | ✅ Envoyer | — |
| GET /api/stats.php | — | ✅ Dashboard |

---

*Groupe Jnak SARL – Douala, Cameroun*
