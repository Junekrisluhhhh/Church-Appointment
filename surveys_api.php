<?php
session_start();
include "db.php";
header('Content-Type: application/json; charset=utf-8');

// Initialize structure
$ratings = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0];
$helpful = ['yes' => 0, 'no' => 0];
$recent = [];
$total = 0;
$sum = 0;

// totals by rating (ensure keys 1..5 present)
$res = $conn->query("SELECT rating, COUNT(*) AS cnt FROM surveys GROUP BY rating");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $k = strval(intval($r['rating']));
        if (!isset($ratings[$k])) $ratings[$k] = 0;
        $ratings[$k] = intval($r['cnt']);
        $sum += intval($r['cnt']) * intval($k);
        $total += intval($r['cnt']);
    }
}

// helpful yes/no (ensure yes/no)
$res2 = $conn->query("SELECT helpful, COUNT(*) AS cnt FROM surveys GROUP BY helpful");
if ($res2) {
    while ($h = $res2->fetch_assoc()) {
        $k = ($h['helpful'] === 'yes') ? 'yes' : 'no';
        $helpful[$k] = intval($h['cnt']);
    }
}

// recent comments (with username if available)
$res3 = $conn->query("SELECT s.comments, s.rating, s.helpful, u.name AS user_name, s.created_at 
                      FROM surveys s
                      LEFT JOIN users u ON s.user_id = u.id
                      WHERE s.comments IS NOT NULL AND TRIM(s.comments) <> ''
                      ORDER BY s.created_at DESC
                      LIMIT 10");
if ($res3) {
    while ($c = $res3->fetch_assoc()) {
        $recent[] = [
            'comments' => $c['comments'],
            'rating' => intval($c['rating']),
            'helpful' => $c['helpful'],
            'user_name' => $c['user_name'] ?: 'Anonymous',
            'created_at' => $c['created_at']
        ];
    }
}

// total (if not computed above)
if ($total === 0) {
    $resTotal = $conn->query("SELECT COUNT(*) AS total FROM surveys");
    if ($resTotal) $total = intval($resTotal->fetch_assoc()['total']);
}

// average
$avg = $total ? round($sum / $total, 2) : 0.00;

echo json_encode([
    'ratings' => $ratings,    // keys '1'..'5'
    'helpful' => $helpful,    // keys 'yes','no'
    'recent' => $recent,
    'total' => $total,
    'average' => $avg
], JSON_UNESCAPED_UNICODE);