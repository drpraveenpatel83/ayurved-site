<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders();
$t=getBearerToken();
if($t) getDB()->prepare("DELETE FROM user_sessions WHERE session_token=?")->execute([$t]);
jsonSuccess([],'Logged out');
