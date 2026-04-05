<?php
require_once dirname(__DIR__, 2) . '/helpers.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$name     = trim(str('name'));
$email    = strtolower(trim(str('email')));
$phone    = preg_replace('/\D/', '', str('phone'));
$password = str('password');

if (!$name || !$email || !$password) jsonError('Naam, email aur password required hai');
if (strlen($name) < 2)               jsonError('Naam kam se kam 2 characters ka hona chahiye');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Valid email enter karein');
if (strlen($password) < 6)           jsonError('Password kam se kam 6 characters ka hona chahiye');
if ($phone && strlen($phone) !== 10) jsonError('Valid 10-digit phone number enter karein');

$db = getDB();

// Check duplicate email
$check = $db->prepare("SELECT id FROM users WHERE email = ?");
$check->execute([$email]);
if ($check->fetch()) jsonError('Yeh email already registered hai');

$hash      = password_hash($password, PASSWORD_DEFAULT);
$stmt      = $db->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?,?,?,?,'student')");
$stmt->execute([$name, $email, $phone ?: null, $hash]);
$userId    = (int)$db->lastInsertId();

// Create session
$token     = generateToken(32);
$expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
$db->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?,?,?,?,?)")
   ->execute([$userId, $token, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, $expiresAt]);

$user = ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'student', 'membership_type' => 'free'];

jsonSuccess(['token' => $token, 'user' => $user], 'Account successfully ban gaya!', 201);
