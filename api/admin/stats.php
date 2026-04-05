<?php
require_once dirname(__DIR__).'/helpers.php'; setCorsHeaders(); requireAdmin();
$db=getDB();
jsonSuccess(['total_questions'=>(int)$db->query("SELECT COUNT(*) FROM questions WHERE is_active=1")->fetchColumn(),'total_users'=>(int)$db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),'total_attempts'=>(int)$db->query("SELECT COUNT(*) FROM quiz_attempts WHERE completed_at IS NOT NULL")->fetchColumn(),'attempts_today'=>(int)$db->query("SELECT COUNT(*) FROM quiz_attempts WHERE DATE(completed_at)=CURDATE()")->fetchColumn(),'daily_quizzes'=>(int)$db->query("SELECT COUNT(*) FROM daily_quizzes WHERE is_published=1")->fetchColumn(),'total_notes'=>(int)$db->query("SELECT COUNT(*) FROM notes WHERE is_published=1")->fetchColumn()]);
