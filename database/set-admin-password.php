<?php
/**
 * Admin password set karne ke liye
 * URL: https://quiz.yourdomain.com/database/set-admin-password.php
 * Baad mein DELETE karo yeh file!
 */
require_once dirname(__DIR__).'/api/config.php';
require_once dirname(__DIR__).'/api/db.php';

$pass  = 'Admin@1234'; // ← Yahan apna password likhein
$hash  = password_hash($pass, PASSWORD_DEFAULT);
$email = 'admin@ayurvedaquiz.com';

try {
    $db = getDB();
    $s  = $db->prepare("UPDATE users SET password_hash=? WHERE email=?");
    $s->execute([$hash, $email]);
    echo "<!DOCTYPE html><html><body style='font-family:sans-serif;padding:40px;max-width:500px'>";
    echo "<h2 style='color:green'>✅ Password Set Ho Gaya!</h2>";
    echo "<p><b>Email:</b> $email</p>";
    echo "<p><b>Password:</b> $pass</p>";
    echo "<p style='color:red'>⚠️ Ab yeh file DELETE karo!</p>";
    echo "<a href='/admin/' style='background:#E67E22;color:white;padding:10px 20px;border-radius:6px;text-decoration:none'>Admin Panel Kholein →</a>";
    echo "</body></html>";
} catch(Exception $e) {
    echo "<h2 style='color:red'>Error: ".$e->getMessage()."</h2>";
    echo "<p>config.php check karein</p>";
}
