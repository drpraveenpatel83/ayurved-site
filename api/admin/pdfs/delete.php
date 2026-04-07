<?php
require_once dirname(__DIR__,2).'/helpers.php';
setCorsHeaders(); requireAdmin();
if ($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405);
$id = (int)(body()['id'] ?? 0);
if (!$id) jsonError('ID required');
getDB()->prepare("DELETE FROM subject_pdfs WHERE id=?")->execute([$id]);
jsonSuccess([],'PDF deleted');
