<?php
// skl/api/database.php
// ── Timezone ──────────────────────────────────────────────────
date_default_timezone_set('Asia/Jakarta');

// ── Konfigurasi Database ──────────────────────────────────────
// Sesuaikan nilai-nilai di bawah ini dengan environment Anda
define('DB_HOST', 'localhost');
define('DB_NAME', 'skl_db');       // nama database
define('DB_USER', 'root');         // user MySQL
define('DB_PASS', '');             // password MySQL
define('DB_CHARSET', 'utf8mb4');

// ── Auth / Session ────────────────────────────────────────────
define('SESSION_NAME', 'skl_session');
define('SESSION_LIFETIME', 86400); // 24 jam (detik)

// ── CORS (sesuaikan jika frontend di domain berbeda) ──────────
$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $allowedOrigin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// ── Singleton PDO ─────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Samakan timezone MySQL dengan PHP (WIB = UTC+7)
        $pdo->exec("SET time_zone = '+07:00'");
    }
    return $pdo;
}

// ── JSON Response ─────────────────────────────────────────────
function jsonResponse(bool $success, $data = null, string $message = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── Parse Request Body ────────────────────────────────────────
function getInput(): array {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) return $json;
    }
    return $_POST ?: [];
}

// ── Session / Auth ────────────────────────────────────────────
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function requireAuth(): void {
    startSession();
    if (empty($_SESSION['user_id'])) {
        jsonResponse(false, null, 'Unauthorized. Silakan login terlebih dahulu.', 401);
    }
}

function currentUser(): ?array {
    startSession();
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'] ?? 'admin',
    ];
}
