<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders(); requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('GET only', 405);

$id = (int)($_GET['id'] ?? 0);
if (!$id) jsonError('id required');

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM posts WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$post) jsonError('Post not found', 404);
jsonSuccess($post);
