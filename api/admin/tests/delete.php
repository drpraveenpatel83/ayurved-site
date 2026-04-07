<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders(); requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') jsonError('DELETE only', 405);

$id = (int)(body()['id'] ?? 0);
if (!$id) jsonError('id required');

$db = getDB();
$db->prepare("DELETE FROM mock_tests WHERE id=?")->execute([$id]);
if ($db->prepare("SELECT ROW_COUNT() as n")->execute() === false) jsonError('Delete failed');
jsonSuccess(['deleted'=>$id]);
