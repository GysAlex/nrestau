<?php
// ============================================================
//  GROUPE JNAK SARL – API Auth Admin
//  URL : /api/auth.php
//
//  POST /api/auth.php?action=login   → Connexion admin
//  POST /api/auth.php?action=logout  → Déconnexion
//  GET  /api/auth.php?action=check   → Vérifier token
// ============================================================

require_once __DIR__ . '/../config/database.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── Vérifier le token ────────────────────────────────────────
if ($action === 'check' && $method === 'GET') {
    $payload = null;
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (str_starts_with($auth, 'Bearer ')) {
        $payload = verifyToken(substr($auth, 7));
    }
    if ($payload) {
        jsonResponse(['success' => true, 'user' => $payload['user']]);
    } else {
        jsonResponse(['success' => false, 'error' => 'Token invalide ou expiré.'], 401);
    }
}

// ── Connexion ────────────────────────────────────────────────
if ($action === 'login' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $user = trim($body['username'] ?? '');
    $pass = trim($body['password'] ?? '');

    if (!$user || !$pass) {
        jsonError('Identifiant et mot de passe requis.');
    }

    // Anti-brute-force simple : vérifier tentatives récentes en session
    session_start();
    if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
    if (!isset($_SESSION['login_blocked_until'])) $_SESSION['login_blocked_until'] = 0;

    if (time() < $_SESSION['login_blocked_until']) {
        $waitSecs = $_SESSION['login_blocked_until'] - time();
        jsonError("Trop de tentatives. Réessayez dans $waitSecs secondes.", 429);
    }

    // Vérification identifiants
    $validUser = ($user === ADMIN_USER);
    $validPass = ($pass === ADMIN_PASS);

    if (!$validUser || !$validPass) {
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_blocked_until'] = time() + 300; // blocage 5 minutes
            $_SESSION['login_attempts'] = 0;
            jsonError('Trop de tentatives. Compte bloqué 5 minutes.', 429);
        }
        jsonError('Identifiant ou mot de passe incorrect.', 401);
    }

    // Succès
    $_SESSION['login_attempts'] = 0;
    $token = generateToken($user);

    jsonResponse([
        'success' => true,
        'token'   => $token,
        'user'    => $user,
        'message' => 'Connexion réussie.',
    ]);
}

// ── Déconnexion ──────────────────────────────────────────────
if ($action === 'logout' && $method === 'POST') {
    // Côté serveur il n'y a pas grand-chose à faire (JWT stateless)
    // Le client doit supprimer le token de son côté
    jsonResponse(['success' => true, 'message' => 'Déconnecté.']);
}

jsonError('Action non reconnue.', 400);
