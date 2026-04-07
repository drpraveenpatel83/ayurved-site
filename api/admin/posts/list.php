<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders(); requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('GET only', 405);

$limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$search = trim($_GET['search'] ?? '');
$cat    = trim($_GET['category'] ?? '');

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = 'title LIKE ?'; $params[] = "%$search%"; }
if ($cat)    { $where[] = 'category_slug=?'; $params[] = $cat; }

$db   = getDB();

$sql = "SELECT id,title,slug,category,category_slug,author,is_published,is_featured,view_count,published_at
        FROM posts WHERE ".implode(' AND ',$where)."
        ORDER BY published_at DESC LIMIT ".(int)$limit." OFFSET ".(int)$offset;
$stmt = $db->prepare($sql); $stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cntStmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE ".implode(' AND ',$where));
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();

jsonSuccess(['posts'=>$posts,'total'=>$total,'limit'=>$limit,'offset'=>$offset]);
