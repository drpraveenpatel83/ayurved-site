<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

echo "=== PHP OK: " . PHP_VERSION . "\n";

try {
    require_once __DIR__ . '/helpers.php';
    echo "=== helpers.php OK\n";
} catch (Throwable $e) {
    echo "=== helpers.php FAIL: " . $e->getMessage() . "\n";
    exit;
}

try {
    $db = getDB();
    echo "=== DB connected OK\n";
    $r = $db->query("SELECT COUNT(*) as n FROM posts")->fetch();
    echo "=== posts count: " . $r['n'] . "\n";
} catch (Throwable $e) {
    echo "=== DB FAIL: " . $e->getMessage() . "\n";
}

try {
    $db->query("SELECT 1 FROM mock_tests LIMIT 1");
    echo "=== mock_tests table: EXISTS\n";
} catch (Throwable $e) {
    echo "=== mock_tests table: MISSING (run SQL to create)\n";
}

echo "=== Done\n";
