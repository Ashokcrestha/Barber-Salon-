<?php
/**
 * ClassicCuts - Reviews API
 * Returns reviews from the database as JSON
 */
define('IS_API', true);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

$limit = (int)($_GET['limit'] ?? 0); // 0 = all

try {
    $reviews = [];
    $sql = "SELECT * FROM reviews ORDER BY id DESC" . ($limit > 0 ? " LIMIT $limit" : "");

    if ($pdo) {
        $reviews = $pdo->query($sql)->fetchAll();
    } elseif ($mysqli) {
        $res = $mysqli->query($sql);
        $reviews = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    // Star display helper
    $totalCount = 0;
    if ($pdo) {
        $totalCount = (int)$pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
    } elseif ($mysqli) {
        $res2 = $mysqli->query("SELECT COUNT(*) as cnt FROM reviews");
        $row2 = $res2 ? $res2->fetch_assoc() : ['cnt' => 0];
        $totalCount = (int)($row2['cnt'] ?? 0);
    }

    echo json_encode([
        'result'      => 'success',
        'reviews'     => $reviews,
        'total_count' => $totalCount
    ]);
} catch (Exception $e) {
    echo json_encode(['result' => 'error', 'message' => $e->getMessage(), 'reviews' => []]);
}
