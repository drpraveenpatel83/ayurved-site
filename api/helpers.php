<?php
require_once __DIR__ . '/db.php';

function jsonSuccess($data=[], $msg='OK', $code=200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>true,'message'=>$msg,'data'=>$data]); exit;
}
function jsonError($msg, $code=400, $data=null): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>$msg,'data'=>$data]); exit;
}
function setCorsHeaders(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
}
function getBearerToken(): ?string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i',$h,$m)) return trim($m[1]);
    return null;
}
function getAuthUser(): ?array {
    $token = getBearerToken();
    if (!$token) return null;
    $db = getDB();
    $s = $db->prepare("SELECT u.id,u.name,u.email,u.role,u.membership_type FROM user_sessions s JOIN users u ON u.id=s.user_id WHERE s.session_token=? AND s.expires_at>NOW()");
    $s->execute([$token]);
    return $s->fetch() ?: null;
}
function requireAuth(): array {
    $u = getAuthUser(); if (!$u) jsonError('Login required',401); return $u;
}
function requireAdmin(): array {
    $u = requireAuth(); if ($u['role']!=='admin') jsonError('Admin only',403); return $u;
}
function body(): array {
    static $p=null; if($p===null){$p=json_decode(file_get_contents('php://input'),true)??[];}return $p;
}
function str(string $k, string $d=''): string { return trim((string)(body()[$k]??$_POST[$k]??$_GET[$k]??$d)); }
function intParam(string $k, int $d=0): int { return (int)(body()[$k]??$_POST[$k]??$_GET[$k]??$d); }
function generateToken(int $b=32): string { return bin2hex(random_bytes($b)); }
function makeSlug(string $t): string { return trim(preg_replace('/-+/','-',preg_replace('/[^a-z0-9\-]/','',strtolower($t))),'-'); }
