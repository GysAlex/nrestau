<?php
// ============================================================
//  GROUPE JNAK SARL – API Articles
//  URL : /api/articles.php
//
//  GET    /api/articles.php              → Liste des articles publiés
//  GET    /api/articles.php?id=X         → Un article + commentaires
//  POST   /api/articles.php              → Créer article (admin)
//  PUT    /api/articles.php?id=X         → Modifier article (admin)
//  DELETE /api/articles.php?id=X         → Supprimer article (admin)
//  POST   /api/articles.php?action=like&id=X → Liker un article
// ============================================================

require_once __DIR__ . '/../config/database.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';

// ── GET ─────────────────────────────────────────────────────
if ($method === 'GET') {
    $db = getDB();

    if ($id > 0) {
        // Un article précis + ses commentaires
        $stmt = $db->prepare("SELECT * FROM articles WHERE id = ?");
        $stmt->execute([$id]);
        $article = $stmt->fetch();
        if (!$article) jsonError('Article introuvable.', 404);

        $stmt2 = $db->prepare(
            "SELECT id, name, text, created_at FROM comments
             WHERE article_id = ? ORDER BY created_at DESC"
        );
        $stmt2->execute([$id]);
        $article['comments'] = $stmt2->fetchAll();

        // A-t-on déjà liké depuis cette IP ?
        $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        $stmt3 = $db->prepare(
            "SELECT 1 FROM article_likes WHERE article_id = ? AND ip_hash = ?"
        );
        $stmt3->execute([$id, $ipHash]);
        $article['user_liked'] = (bool)$stmt3->fetchColumn();

        jsonResponse(['success' => true, 'data' => $article]);
    }

    // Liste des articles
    $status  = $_GET['status'] ?? 'published'; // published | all (admin)
    $tag     = $_GET['tag']    ?? '';
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 12;
    $offset  = ($page - 1) * $perPage;

    // Si on demande "all", on vérifie l'auth admin
    if ($status !== 'published') {
        requireAuth();
        $statusClause = "1=1";
    } else {
        $statusClause = "status = 'published'";
    }

    $tagClause  = '';
    $tagParams  = [];
    if ($tag && $tag !== 'all') {
        $tagClause = "AND tag = ?";
        $tagParams = [$tag];
    }

    $sql = "SELECT id, title, author, tag, excerpt, image_url, status, likes, created_at
            FROM articles
            WHERE $statusClause $tagClause
            ORDER BY created_at DESC
            LIMIT $perPage OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($tagParams);
    $articles = $stmt->fetchAll();

    // Total pour pagination
    $countSql = "SELECT COUNT(*) FROM articles WHERE $statusClause $tagClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($tagParams);
    $total = (int)$countStmt->fetchColumn();

    jsonResponse([
        'success'    => true,
        'data'       => $articles,
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $perPage,
        'last_page'  => ceil($total / $perPage),
    ]);
}

// ── POST – Like ──────────────────────────────────────────────
if ($method === 'POST' && $action === 'like') {
    if ($id <= 0) jsonError('ID article requis.');
    $db = getDB();

    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');

    // Vérifier si déjà liké
    $check = $db->prepare(
        "SELECT 1 FROM article_likes WHERE article_id = ? AND ip_hash = ?"
    );
    $check->execute([$id, $ipHash]);

    if ($check->fetchColumn()) {
        // Déjà liké → unlike
        $db->prepare("DELETE FROM article_likes WHERE article_id = ? AND ip_hash = ?")
           ->execute([$id, $ipHash]);
        $db->prepare("UPDATE articles SET likes = GREATEST(0, likes - 1) WHERE id = ?")
           ->execute([$id]);
        $liked = false;
    } else {
        // Nouveau like
        $db->prepare("INSERT INTO article_likes (article_id, ip_hash) VALUES (?, ?)")
           ->execute([$id, $ipHash]);
        $db->prepare("UPDATE articles SET likes = likes + 1 WHERE id = ?")
           ->execute([$id]);
        $liked = true;
    }

    $stmt = $db->prepare("SELECT likes FROM articles WHERE id = ?");
    $stmt->execute([$id]);
    $likes = (int)$stmt->fetchColumn();

    jsonResponse(['success' => true, 'liked' => $liked, 'likes' => $likes]);
}

// ── POST – Créer article (admin) ─────────────────────────────
if ($method === 'POST' && $action === '') {
    requireAuth();
    $db   = getDB();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $title    = trim($body['title']    ?? '');
    $author   = trim($body['author']   ?? '');
    $tag      = trim($body['tag']      ?? 'Management');
    $excerpt  = trim($body['excerpt']  ?? '');
    $content  = trim($body['content']  ?? '');
    $imageUrl = trim($body['image_url'] ?? '');
    $status   = in_array($body['status'] ?? '', ['published', 'draft']) ? $body['status'] : 'draft';

    if (!$title || !$author || !$excerpt || !$content) {
        jsonError('Champs obligatoires manquants (title, author, excerpt, content).');
    }

    $stmt = $db->prepare(
        "INSERT INTO articles (title, author, tag, excerpt, content, image_url, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$title, $author, $tag, $excerpt, $content, $imageUrl ?: null, $status]);
    $newId = $db->lastInsertId();

    jsonResponse(['success' => true, 'id' => $newId,
        'message' => $status === 'published' ? 'Article publié !' : 'Brouillon enregistré.'], 201);
}

// ── PUT – Modifier article (admin) ───────────────────────────
if ($method === 'PUT') {
    requireAuth();
    if ($id <= 0) jsonError('ID article requis.');
    $db   = getDB();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // Vérifier que l'article existe
    $check = $db->prepare("SELECT id FROM articles WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) jsonError('Article introuvable.', 404);

    $fields = [];
    $params = [];
    $allowed = ['title', 'author', 'tag', 'excerpt', 'content', 'image_url', 'status'];
    foreach ($allowed as $field) {
        if (isset($body[$field])) {
            if ($field === 'status' && !in_array($body[$field], ['published', 'draft'])) continue;
            $fields[] = "$field = ?";
            $params[] = trim($body[$field]);
        }
    }
    if (!$fields) jsonError('Aucun champ à mettre à jour.');

    $params[] = $id;
    $db->prepare("UPDATE articles SET " . implode(', ', $fields) . " WHERE id = ?")
       ->execute($params);

    jsonResponse(['success' => true, 'message' => 'Article mis à jour.']);
}

// ── DELETE – Supprimer article (admin) ───────────────────────
if ($method === 'DELETE') {
    requireAuth();
    if ($id <= 0) jsonError('ID article requis.');
    $db = getDB();

    $stmt = $db->prepare("DELETE FROM articles WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) jsonError('Article introuvable.', 404);
    jsonResponse(['success' => true, 'message' => 'Article supprimé.']);
}

jsonError('Méthode non supportée.', 405);
