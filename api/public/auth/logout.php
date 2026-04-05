<?php
require_once dirname(__DIR__, 2) . '/helpers.php';
setCorsHeaders();
$token = getBearerToken();
if ($token) {
    getDB()->prepare("DELETE FROM user_sessions WHERE session_token = ?")->execute([$token]);
}
jsonSuccess([], 'Logged out');
