<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders(); requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') jsonError('DELETE only', 405);

$id = intVal('id', body());
if (!$id) jsonError('id required');

$db   = getDB();
$stmt = $db->prepare("DELETE FROM posts WHERE id=?");
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) jsonError('Post not found', 404);
jsonSuccess(['deleted' => $id]);
