<?php
require_once dirname(__DIR__).'/helpers.php'; setCorsHeaders(); requireAuth();
$catId=intParam('category_id'); if(!$catId) jsonError('category_id required');
$s=getDB()->prepare("SELECT id,title,type,question_count,duration_mins FROM quizzes WHERE category_id=? AND is_active=1 ORDER BY display_order,id");
$s->execute([$catId]); jsonSuccess($s->fetchAll());
