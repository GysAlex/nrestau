<?php
// ============================================================
//  GROUPE JNAK SARL – Configuration Base de Données
//  Modifiez ces valeurs selon votre hébergeur (cPanel/Hostinger)
// ============================================================

define('DB_HOST',     'localhost');       // Généralement "localhost"
define('DB_NAME',     'jnak_db');         // Nom de votre base MySQL
define('DB_USER',     'jnak_user');       // Utilisateur MySQL
define('DB_PASS',     'VotreMotDePasse'); // Mot de passe MySQL
define('DB_CHARSET',  'utf8mb4');

// Clé secrète pour les tokens JWT (changez-la !)
define('JWT_SECRET',  'jnak_secret_key_2024_changez_moi');

// Identifiants admin (vous pouvez les changer ici)
define('ADMIN_USER',  'admin');
define('ADMIN_PASS',  'jnak2024'); // Sera hashé à la 1ère connexion

// URL de votre site (sans slash final)
define('SITE_URL',    'https://www.groupejnak.com');

// ============================================================
//  Connexion PDO
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Connexion base de données impossible.']));
        }
    }
    return $pdo;
}

// ============================================================
//  Headers CORS + JSON
// ============================================================
function setHeaders(): void {
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// ============================================================
//  Réponse JSON
// ============================================================
function jsonResponse(mixed $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function jsonError(string $message, int $code = 400): void {
    jsonResponse(['success' => false, 'error' => $message], $code);
}

// ============================================================
//  Token JWT simple (sans librairie externe)
// ============================================================
function generateToken(string $user): string {
    $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'user' => $user,
        'iat'  => time(),
        'exp'  => time() + 3600 * 8, // 8 heures
    ]));
    $sig = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$sig";
}

function verifyToken(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payload, $sig] = $parts;
    $expected = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $data = json_decode(base64_decode($payload), true);
    if (!$data || $data['exp'] < time()) return null;
    return $data;
}

function requireAuth(): array {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (str_starts_with($auth, 'Bearer ')) {
        $token = substr($auth, 7);
        $payload = verifyToken($token);
        if ($payload) return $payload;
    }
    jsonError('Non autorisé. Veuillez vous connecter.', 401);
}
