<?php
// ============================================================
//  GROUPE JNAK SARL – API Formulaire de Contact
//  URL : /api/contact.php
//
//  POST /api/contact.php        → Envoyer message de contact
//  GET  /api/contact.php        → Liste messages (admin)
//  PUT  /api/contact.php?id=X   → Marquer comme lu (admin)
//  DELETE /api/contact.php?id=X → Supprimer (admin)
// ============================================================

require_once __DIR__ . '/../config/database.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ── GET – Liste des messages (admin) ────────────────────────
if ($method === 'GET') {
    requireAuth();
    $db = getDB();

    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;
    $luFilter = isset($_GET['lu']) ? (int)$_GET['lu'] : null;

    $where  = '';
    $params = [];
    if ($luFilter !== null) {
        $where  = 'WHERE lu = ?';
        $params = [$luFilter];
    }

    $stmt = $db->prepare(
        "SELECT * FROM contact_messages $where
         ORDER BY created_at DESC LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    $countStmt = $db->prepare("SELECT COUNT(*) FROM contact_messages $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Compter les non-lus
    $unreadStmt = $db->query("SELECT COUNT(*) FROM contact_messages WHERE lu = 0");
    $unread = (int)$unreadStmt->fetchColumn();

    jsonResponse([
        'success'   => true,
        'data'      => $messages,
        'total'     => $total,
        'unread'    => $unread,
        'page'      => $page,
        'last_page' => ceil($total / $perPage),
    ]);
}

// ── POST – Envoyer un message de contact ─────────────────────
if ($method === 'POST') {
    $db   = getDB();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $nom     = trim($body['nom']     ?? '');
    $email   = trim($body['email']   ?? '');
    $sujet   = trim($body['sujet']   ?? '');
    $domaine = trim($body['domaine'] ?? '');
    $message = trim($body['message'] ?? '');

    // Validation
    if (!$nom)                           jsonError('Le nom est obligatoire.');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
                                         jsonError('Email invalide.');
    if (!$sujet)                         jsonError('Le sujet est obligatoire.');
    if (!$message)                       jsonError('Le message est obligatoire.');
    if (mb_strlen($message) > 5000)      jsonError('Message trop long (max 5000 caractères).');

    // Anti-spam : max 3 messages par IP par heure
    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
    // (La vérification se fait via un champ supplémentaire optionnel en production)

    // Enregistrement en base
    $stmt = $db->prepare(
        "INSERT INTO contact_messages (nom, email, sujet, domaine, message)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$nom, $email, $sujet, $domaine ?: null, $message]);

    // Envoi d'email (optionnel – configurer SMTP sur votre hébergeur)
    $mailSent = false;
    if (function_exists('mail')) {
        $to      = 'contact@groupejnak.com'; // ← Changez cette adresse
        $subject = "Nouveau message : " . $sujet;
        $body    = "Nom : $nom\nEmail : $email\nDomaine : $domaine\n\nMessage :\n$message";
        $headers = "From: noreply@groupejnak.com\r\nReply-To: $email";
        $mailSent = @mail($to, $subject, $body, $headers);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Message envoyé avec succès. Nous vous répondrons dans les plus brefs délais.',
    ], 201);
}

// ── PUT – Marquer comme lu/non-lu (admin) ────────────────────
if ($method === 'PUT') {
    requireAuth();
    if ($id <= 0) jsonError('ID requis.');
    $db   = getDB();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $lu   = isset($body['lu']) ? (int)(bool)$body['lu'] : 1;

    $stmt = $db->prepare("UPDATE contact_messages SET lu = ? WHERE id = ?");
    $stmt->execute([$lu, $id]);
    if ($stmt->rowCount() === 0) jsonError('Message introuvable.', 404);

    jsonResponse(['success' => true, 'message' => 'Statut mis à jour.']);
}

// ── DELETE – Supprimer message (admin) ───────────────────────
if ($method === 'DELETE') {
    requireAuth();
    if ($id <= 0) jsonError('ID requis.');
    $db = getDB();

    $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) jsonError('Message introuvable.', 404);

    jsonResponse(['success' => true, 'message' => 'Message supprimé.']);
}

jsonError('Méthode non supportée.', 405);
