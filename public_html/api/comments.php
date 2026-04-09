<?php
// ============================================================
//  GROUPE JNAK SARL – API Commentaires
//  URL : /api/comments.php
//
//  GET    /api/comments.php?article_id=X  → Commentaires d'un article
//  POST   /api/comments.php               → Ajouter un commentaire
//  DELETE /api/comments.php?id=X          → Supprimer (admin)
// ============================================================

require_once __DIR__ . '/../config/database.php';
setHeaders();

$method     = $_SERVER['REQUEST_METHOD'];
$id         = isset($_GET['id'])         ? (int)$_GET['id']         : 0;
$articleId  = isset($_GET['article_id']) ? (int)$_GET['article_id'] : 0;

// ── GET ─────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($articleId <= 0) jsonError('article_id requis.');
    $db = getDB();

    $stmt = $db->prepare(
        "SELECT id, article_id, name, text, created_at
         FROM comments
         WHERE article_id = ?
         ORDER BY created_at DESC"
    );
    $stmt->execute([$articleId]);
    $comments = $stmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $comments, 'total' => count($comments)]);
}

// ── POST – Ajouter commentaire ───────────────────────────────
if ($method === 'POST') {
    $db   = getDB();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $articleId = (int)($body['article_id'] ?? 0);
    $name      = trim($body['name'] ?? '');
    $text      = trim($body['text'] ?? '');

    if ($articleId <= 0) jsonError('article_id requis.');
    if (!$name)          jsonError('Le nom est obligatoire.');
    if (!$text)          jsonError('Le commentaire est obligatoire.');
    if (mb_strlen($name) > 100) jsonError('Le nom est trop long (max 100 caractères).');
    if (mb_strlen($text) > 2000) jsonError('Le commentaire est trop long (max 2000 caractères).');

    // Vérifier que l'article existe et est publié
    $check = $db->prepare("SELECT id FROM articles WHERE id = ? AND status = 'published'");
    $check->execute([$articleId]);
    if (!$check->fetch()) jsonError('Article introuvable ou non publié.', 404);

    // Anti-spam simple : max 3 commentaires par IP par article par heure
    $ipHash  = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
    $spamChk = $db->prepare(
        "SELECT COUNT(*) FROM comments
         WHERE article_id = ? AND ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );

    // Si la colonne ip_hash n'existe pas encore, on ignore le check
    try {
        $spamChk->execute([$articleId, $ipHash]);
        if ((int)$spamChk->fetchColumn() >= 3) {
            jsonError('Trop de commentaires. Réessayez dans une heure.', 429);
        }
    } catch (\Throwable $e) {
        // ip_hash non encore présente, on insère sans check
    }

    $stmt = $db->prepare(
        "INSERT INTO comments (article_id, name, text) VALUES (?, ?, ?)"
    );
    $stmt->execute([$articleId, $name, $text]);
    $newId = $db->lastInsertId();

    // Mise à jour compteur (non stocké mais utile pour stats admin)
    // Les commentaires sont comptés en live via COUNT(*)

    $newComment = [
        'id'         => $newId,
        'article_id' => $articleId,
        'name'       => $name,
        'text'       => $text,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    jsonResponse(['success' => true, 'data' => $newComment, 'message' => 'Commentaire ajouté.'], 201);
}

// ── DELETE – Supprimer commentaire (admin) ───────────────────
if ($method === 'DELETE') {
    requireAuth();
    if ($id <= 0) jsonError('ID commentaire requis.');
    $db = getDB();

    $stmt = $db->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) jsonError('Commentaire introuvable.', 404);
    jsonResponse(['success' => true, 'message' => 'Commentaire supprimé.']);
}

jsonError('Méthode non supportée.', 405);
