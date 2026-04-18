<?php
// Müşteri portal: config.php otomatik session başlatmasın (ayrı session kullanıyoruz)
define('CODEGA_NO_AUTO_SESSION', true);

require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require __DIR__ . '/_auth.php';

mp_auth_require();
$user = mp_auth_user();
$mid = (int)$user['mukellef_id'];

$id = (int)($_GET['id'] ?? 0);
$tip = $_GET['tip'] ?? 'xml';

if (!$id) { http_response_code(400); exit('Fatura ID eksik'); }
if (!in_array($tip, ['xml', 'pdf'], true)) { http_response_code(400); exit('Geçersiz tip'); }

// GÜVENLİK: Bu faturaya erişimi var mı?
if (!mp_fatura_ait_mi($pdo, $id, $mid)) {
    mp_audit($pdo, 'musteri.indir_denied', "fatura_id=$id tip=$tip");
    http_response_code(404);
    exit('Fatura bulunamadı veya erişim yetkiniz yok');
}

$q = $pdo->prepare("SELECT fatura_no, xml_path, pdf_path, durum FROM faturalar WHERE id=?");
$q->execute([$id]);
$f = $q->fetch();

// Sadece imzali+ durumları indirilebilir
if (!in_array($f['durum'], ['imzali','gonderildi','kabul'], true)) {
    http_response_code(403);
    exit('Bu fatura henüz indirilebilir durumda değil (durum: ' . $f['durum'] . ')');
}

$path_col = $tip . '_path';
if (empty($f[$path_col])) {
    http_response_code(404);
    exit('Dosya henüz oluşturulmamış');
}

$full = $f[$path_col];
// Path traversal kontrolü — sadece STORAGE_PATH altında
if (!str_starts_with(realpath($full) ?: '', realpath(STORAGE_PATH))) {
    mp_audit($pdo, 'musteri.indir_path_attack', "fatura_id=$id path=$full");
    http_response_code(403);
    exit('Geçersiz dosya yolu');
}
if (!file_exists($full) || !is_readable($full)) {
    http_response_code(404);
    exit('Dosya bulunamadı');
}

mp_audit($pdo, 'musteri.indir', "fatura_id=$id tip=$tip no={$f['fatura_no']}");

$filename = $f['fatura_no'] . '.' . $tip;
$content_type = $tip === 'xml' ? 'application/xml' : 'application/pdf';

header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($full));
header('Cache-Control: private, no-cache');
readfile($full);
exit;
