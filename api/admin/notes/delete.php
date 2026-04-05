<?php
require_once dirname(__DIR__, 2) . '/helpers.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);
requireAdmin();
$id = intVal('id');
if (!$id) jsonError('id required');
getDB()->prepare("DELETE FROM notes WHERE id = ?")->execute([$id]);
jsonSuccess([], 'Notes deleted');
