<?php
// ============================================================
//  GROUPE JNAK SARL – API Stats Dashboard Admin
//  URL : /api/stats.php
//
//  GET /api/stats.php  → Statistiques pour le tableau de bord
// ============================================================

require_once __DIR__ . '/../config/database.php';
setHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Méthode non supportée.', 405);

requireAuth();
$db = getDB();

// Articles publiés
$pub = (int)$db->query("SELECT COUNT(*) FROM articles WHERE status = 'published'")->fetchColumn();

// Brouillons
$draft = (int)$db->query("SELECT COUNT(*) FROM articles WHERE status = 'draft'")->fetchColumn();

// Total likes
$likes = (int)$db->query("SELECT COALESCE(SUM(likes), 0) FROM articles")->fetchColumn();

// Total commentaires
$comments = (int)$db->query("SELECT COUNT(*) FROM comments")->fetchColumn();

// Messages de contact non lus
$unread = (int)$db->query("SELECT COUNT(*) FROM contact_messages WHERE lu = 0")->fetchColumn();

// Articles récents (5 derniers)
$recent = $db->query(
    "SELECT a.id, a.title, a.tag, a.author, a.status, a.likes,
            (SELECT COUNT(*) FROM comments c WHERE c.article_id = a.id) AS comment_count,
            a.created_at
     FROM articles a
     ORDER BY a.created_at DESC
     LIMIT 5"
)->fetchAll();

jsonResponse([
    'success' => true,
    'data'    => [
        'published'      => $pub,
        'drafts'         => $draft,
        'total_likes'    => $likes,
        'total_comments' => $comments,
        'unread_messages'=> $unread,
        'recent_articles'=> $recent,
    ],
]);
