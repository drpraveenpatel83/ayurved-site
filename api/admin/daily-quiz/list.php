<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders(); requireAdmin();
$s=getDB()->query("SELECT dq.*,COUNT(dqq.id)as question_count FROM daily_quizzes dq LEFT JOIN daily_quiz_questions dqq ON dqq.daily_quiz_id=dq.id GROUP BY dq.id ORDER BY dq.quiz_date DESC LIMIT 100");
jsonSuccess($s->fetchAll());
