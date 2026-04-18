<?php
require __DIR__ . '/../../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';

auth_require();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(404); exit; }

$q = $pdo->prepare("SELECT fatura_no, ettn, xml_path FROM faturalar WHERE id=?");
$q->execute([$id]);
$f = $q->fetch();
if (!$f || !$f['xml_path']) { http_response_code(404); exit; }

// Path traversal koruma
$full = safe_path(STORAGE_PATH, $f['xml_path']);
if (!$full || !file_exists($full)) { http_response_code(404); exit; }

audit_log($pdo, 'fatura.xml_download', "no={$f['fatura_no']}", null, "fatura:$id");

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $f['fatura_no'] . '.xml"');
header('Content-Length: ' . filesize($full));
header('X-Robots-Tag: noindex');
readfile($full);
