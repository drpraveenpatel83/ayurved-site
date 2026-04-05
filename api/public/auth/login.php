<?php
require_once dirname(__DIR__, 2) . '/helpers.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$email    = strtolower(trim(str('email')));
$password = str('password');

if (!$email || !$password) jsonError('Email aur password required hai');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Valid email enter karein');

$db   = getDB();
$stmt = $db->prepare("SELECT id, name, email, role, password_hash, is_active, membership_type FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonError('Email ya password galat hai', 401);
}
if (!$user['is_active']) {
    jsonError('Aapka account disabled hai. Admin se contact karein', 403);
}

// Create session token
$token     = generateToken(32);
$expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

$db->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?,?,?,?,?)")
   ->execute([$user['id'], $token, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, $expiresAt]);

// Update last login
$db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

unset($user['password_hash'], $user['is_active']);

jsonSuccess(['token' => $token, 'user' => $user], 'Login successful');
