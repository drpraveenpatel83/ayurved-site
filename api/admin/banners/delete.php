<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405); requireAdmin();
$id=intParam('id'); if(!$id) jsonError('id required');
getDB()->prepare("DELETE FROM banners WHERE id=?")->execute([$id]);
jsonSuccess([],'Deleted');
