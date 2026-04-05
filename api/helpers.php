<?php
require_once __DIR__ . '/db.php';

// ── Output helpers ──────────────────────────────────────────
function jsonSuccess(mixed $data = [], string $message = 'OK', int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
    exit;
}

function jsonError(string $message, int $code = 400, mixed $data = null): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message, 'data' => $data]);
    exit;
}

// ── CORS / JSON headers ─────────────────────────────────────
function setCorsHeaders(): void {
    $allowedOrigins = [SITE_URL, 'http://localhost'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins) || APP_ENV === 'development') {
        header("Access-Control-Allow-Origin: $origin");
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

// ── Auth helpers ────────────────────────────────────────────
function getAuthUser(): ?array {
    $token = getBearerToken();
    if (!$token) return null;
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.role, u.membership_type, u.is_active
        FROM user_sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.session_token = ? AND s.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user || !$user['is_active']) return null;
    return $user;
}

function requireAuth(): array {
    $user = getAuthUser();
    if (!$user) jsonError('Login required', 401);
    return $user;
}

function requireAdmin(): array {
    $user = requireAuth();
    if ($user['role'] !== 'admin') jsonError('Admin access required', 403);
    return $user;
}

function getBearerToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) return trim($m[1]);
    return $_COOKIE['aq_token'] ?? null;
}

// ── Input helpers ───────────────────────────────────────────
function body(): array {
    static $parsed = null;
    if ($parsed === null) {
        $raw = file_get_contents('php://input');
        $parsed = json_decode($raw, true) ?? [];
    }
    return $parsed;
}

function str(string $key, string $default = ''): string {
    $b = body();
    $v = $b[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
    return trim((string)$v);
}

function intVal(string $key, int $default = 0): int {
    return (int)(body()[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default);
}

// ── Slug generator ──────────────────────────────────────────
function makeSlug(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

// ── Generate secure token ───────────────────────────────────
function generateToken(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}
