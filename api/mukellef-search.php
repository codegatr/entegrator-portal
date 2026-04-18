<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!auth_check()) {
    http_response_code(401);
    echo json_encode(['error' => 'auth_required']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$search = '%' . $q . '%';
$st = $pdo->prepare("
    SELECT id, unvan, vkn_tckn, vkn_tip, il, ilce, vergi_dairesi, e_fatura_mukellefi
    FROM mukellefler
    WHERE aktif = 1 AND (unvan LIKE ? OR vkn_tckn LIKE ?)
    ORDER BY
        CASE WHEN vkn_tckn LIKE ? THEN 0 ELSE 1 END,
        unvan ASC
    LIMIT 12
");
$st->execute([$search, $search, $search]);
$rows = $st->fetchAll();

echo json_encode(['results' => $rows], JSON_UNESCAPED_UNICODE);
