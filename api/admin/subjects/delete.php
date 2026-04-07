<?php
require_once dirname(__DIR__,2).'/helpers.php';
setCorsHeaders(); requireAdmin();
if ($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405);

$id = (int)(body()['id'] ?? 0);
if (!$id) jsonError('ID required');

$db = getDB();
// Only delete if type=subject
$row = $db->prepare("SELECT id FROM categories WHERE id=? AND type='subject'")->execute([$id]);
$db->prepare("DELETE FROM categories WHERE id=? AND type='subject'")->execute([$id]);
jsonSuccess([], 'Subject deleted');
