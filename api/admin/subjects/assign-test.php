<?php
require_once dirname(__DIR__,2).'/helpers.php';
setCorsHeaders(); requireAdmin();
if ($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405);

$b      = body();
$testId = (int)($b['test_id'] ?? 0);
$catIds = $b['category_ids'] ?? [];   // array of subject category ids

if (!$testId) jsonError('test_id required');
if (!is_array($catIds)) jsonError('category_ids must be array');

$db = getDB();

// Remove all existing mappings for this test
$db->prepare("DELETE FROM test_subject_map WHERE test_id=?")->execute([$testId]);

// Insert new ones
if ($catIds) {
    $stmt = $db->prepare("INSERT IGNORE INTO test_subject_map(test_id,category_id) VALUES(?,?)");
    foreach ($catIds as $cid) {
        $cid = (int)$cid;
        if ($cid > 0) $stmt->execute([$testId, $cid]);
    }
}

jsonSuccess(['test_id'=>$testId,'assigned'=>count($catIds)], 'Test subjects updated');
