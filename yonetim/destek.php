<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require_role('operator');

// ═══ DEFENSIVE: Tablo varlık kontrolü ═══
try {
    $tbl_check = $pdo->query("SHOW TABLES LIKE 'destek_talepleri'")->fetchColumn();
    if (!$tbl_check) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS destek_talepleri (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            talep_no VARCHAR(20) UNIQUE NOT NULL,
            mukellef_id INT UNSIGNED NOT NULL,
            musteri_kullanici_id INT UNSIGNED DEFAULT NULL,
            konu VARCHAR(200) NOT NULL,
            kategori ENUM('fatura_sorunu','teknik_destek','bilgi_talebi','iptal_iade','diger') DEFAULT 'diger',
            oncelik ENUM('dusuk','normal','yuksek','acil') DEFAULT 'normal',
            durum ENUM('acik','cevaplandi','beklemede','kapali') DEFAULT 'acik',
            ilgili_fatura_id INT UNSIGNED DEFAULT NULL,
            atanan_admin_id INT UNSIGNED DEFAULT NULL,
            son_mesaj_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
            son_mesaj_tarafi ENUM('musteri','admin') DEFAULT 'musteri',
            musteri_okundu TINYINT(1) DEFAULT 1,
            admin_okundu TINYINT(1) DEFAULT 0,
            kapali_tarihi DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_mukellef (mukellef_id),
            KEY idx_durum (durum),
            KEY idx_son_mesaj (son_mesaj_tarihi)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS destek_mesajlari (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            talep_id INT UNSIGNED NOT NULL,
            taraf ENUM('musteri','admin') NOT NULL,
            kullanici_id INT UNSIGNED DEFAULT NULL,
            kullanici_adi VARCHAR(100) DEFAULT NULL,
            mesaj TEXT NOT NULL,
            sistem_mesaji TINYINT(1) DEFAULT 0,
            ip VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_talep (talep_id),
            KEY idx_tarih (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
} catch (\Exception $e) {
    error_log('destek tablo: ' . $e->getMessage());
}

// Admin layout'ta mp_icon yok, icon() var. Fallback:
if (!function_exists('mp_icon_safe')) {
    function mp_icon_safe($n, $s = 14) { return icon($n, $s) ?: ''; }
}

// Filtre
$durum_filtre = $_GET['durum'] ?? '';
$kategori_filtre = $_GET['kategori'] ?? '';
$where = '1=1';
$par = [];
if (in_array($durum_filtre, ['acik','cevaplandi','beklemede','kapali'], true)) {
    $where .= ' AND dt.durum=?'; $par[] = $durum_filtre;
}
if (in_array($kategori_filtre, ['fatura_sorunu','teknik_destek','bilgi_talebi','iptal_iade','diger'], true)) {
    $where .= ' AND dt.kategori=?'; $par[] = $kategori_filtre;
}

$st = $pdo->prepare("
    SELECT dt.*,
           m.unvan AS musteri_unvan,
           m.vkn_tckn AS musteri_vkn,
           mpk.kullanici_adi AS olusturan,
           f.fatura_no AS ilgili_fatura_no,
           (SELECT COUNT(*) FROM destek_mesajlari WHERE talep_id = dt.id) AS mesaj_sayisi
    FROM destek_talepleri dt
    LEFT JOIN mukellefler m ON m.id = dt.mukellef_id
    LEFT JOIN musteri_portal_kullanicilar mpk ON mpk.id = dt.musteri_kullanici_id
    LEFT JOIN faturalar f ON f.id = dt.ilgili_fatura_id
    WHERE $where
    ORDER BY
        CASE dt.durum WHEN 'beklemede' THEN 1 WHEN 'acik' THEN 2 WHEN 'cevaplandi' THEN 3 ELSE 4 END,
        CASE dt.oncelik WHEN 'acil' THEN 1 WHEN 'yuksek' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END,
        dt.son_mesaj_tarihi DESC
    LIMIT 100
");
$st->execute($par);
$talepler = $st->fetchAll();

// Sayımlar
$say_q = $pdo->query("SELECT durum, COUNT(*) AS c FROM destek_talepleri GROUP BY durum");
$sayimlar = [];
foreach ($say_q->fetchAll() as $r) $sayimlar[$r['durum']] = (int)$r['c'];
$toplam = array_sum($sayimlar);

// Okunmamış (admin için yeni mesaj var)
$okun_q = $pdo->query("SELECT COUNT(*) FROM destek_talepleri WHERE admin_okundu=0");
$okunmamis = (int)$okun_q->fetchColumn();

render_header('Destek Talepleri', 'destek-admin');
?>

<div class="page-head">
    <div>
        <h1>Destek Talepleri</h1>
        <div class="sub">
            <?= $toplam ?> talep
            <?php if ($okunmamis > 0): ?>
                · <strong style="color:#dc2626">🔴 <?= $okunmamis ?> yeni mesaj bekliyor</strong>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- FİLTRE SEKMELERI -->
<div class="card" style="padding:0">
    <div style="padding:14px 22px;display:flex;gap:8px;flex-wrap:wrap;border-bottom:1px solid #e2e8f0">
        <?php
        $filtreler = [
            ''           => ['Tümü', $toplam, '#475569'],
            'beklemede'  => ['⏳ Beklemede (yanıt ver!)', $sayimlar['beklemede'] ?? 0, '#dc2626'],
            'acik'       => ['🟠 Açık', $sayimlar['acik'] ?? 0, '#d97706'],
            'cevaplandi' => ['✓ Cevaplandı (müşteri okuyacak)', $sayimlar['cevaplandi'] ?? 0, '#059669'],
            'kapali'     => ['⚫ Kapalı', $sayimlar['kapali'] ?? 0, '#64748b'],
        ];
        foreach ($filtreler as $k => [$lbl, $cnt, $clr]):
            $is_active = $durum_filtre === $k;
            $url = $k ? '?durum='.$k : '?';
        ?>
            <a href="<?= h($url) ?>" style="padding:7px 14px;border-radius:20px;font-size:12.5px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;<?= $is_active ? 'background:'.$clr.';color:#fff' : 'background:#f8fafc;color:#475569;border:1px solid #e2e8f0' ?>">
                <span><?= $lbl ?></span>
                <span style="background:<?= $is_active ? 'rgba(255,255,255,0.2)' : '#e2e8f0' ?>;padding:1px 7px;border-radius:10px;font-size:11px"><?= $cnt ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($talepler)): ?>
        <div class="table-empty" style="padding:60px 20px">
            <h4 style="margin:0 0 6px">Bu filtreyle talep bulunamadı</h4>
        </div>
    <?php else: ?>
        <?php foreach ($talepler as $t):
            $kategori_map = [
                'fatura_sorunu' => ['🧾', 'Fatura Sorunu'],
                'teknik_destek' => ['⚙️', 'Teknik Destek'],
                'bilgi_talebi'  => ['💬', 'Bilgi Talebi'],
                'iptal_iade'    => ['↩️', 'İptal/İade'],
                'diger'         => ['📋', 'Diğer'],
            ];
            $kat = $kategori_map[$t['kategori']] ?? $kategori_map['diger'];

            $durum_map = [
                'acik'       => ['🟠 Açık', '#fef3c7', '#78350f'],
                'cevaplandi' => ['✓ Cevaplandı', '#d1fae5', '#065f46'],
                'beklemede'  => ['⏳ Sizi Bekliyor', '#fee2e2', '#7f1d1d'],
                'kapali'     => ['⚫ Kapalı', '#f1f5f9', '#475569'],
            ];
            $dr = $durum_map[$t['durum']] ?? $durum_map['acik'];

            $oncelik_map = [
                'acil'   => ['🔴 ACİL', '#dc2626'],
                'yuksek' => ['🟠 Yüksek', '#d97706'],
                'normal' => ['', ''],
                'dusuk'  => ['⬇ Düşük', '#64748b'],
            ];
            $onc = $oncelik_map[$t['oncelik']] ?? $oncelik_map['normal'];

            $yeni = !$t['admin_okundu'];
            $urgent = ($t['durum'] === 'beklemede');
        ?>
            <a href="<?= SITE_URL ?>/yonetim/destek-detay.php?id=<?= $t['id'] ?>"
               style="display:block;padding:16px 22px;border-bottom:1px solid #f1f5f9;text-decoration:none;color:inherit;<?= $urgent ? 'background:#fff5f5;border-left:3px solid #dc2626' : ($yeni ? 'background:#eff6ff' : '') ?>">
                <div style="display:flex;gap:14px;align-items:flex-start">
                    <div style="font-size:26px;flex-shrink:0;width:42px;height:42px;display:flex;align-items:center;justify-content:center;background:#f8fafc;border-radius:10px">
                        <?= $kat[0] ?>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;flex-wrap:wrap">
                            <span style="font-family:monospace;font-size:11px;color:#94a3b8;font-weight:600"><?= h($t['talep_no']) ?></span>
                            <span style="background:<?= $dr[1] ?>;color:<?= $dr[2] ?>;padding:2px 8px;border-radius:10px;font-size:10.5px;font-weight:700"><?= $dr[0] ?></span>
                            <?php if ($onc[0]): ?>
                                <span style="color:<?= $onc[1] ?>;font-size:10.5px;font-weight:700"><?= $onc[0] ?></span>
                            <?php endif; ?>
                            <?php if ($yeni && !$urgent): ?>
                                <span style="background:#3b82f6;color:#fff;padding:1px 7px;border-radius:10px;font-size:9.5px;font-weight:700">OKUNMAMIS</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:14px;font-weight:600;color:#0f172a;margin-bottom:4px"><?= h($t['konu']) ?></div>
                        <div style="font-size:12px;color:#64748b;display:flex;gap:12px;flex-wrap:wrap">
                            <span style="font-weight:500"><?= h($t['musteri_unvan']) ?></span>
                            <span style="font-family:monospace;color:#94a3b8"><?= h($t['musteri_vkn']) ?></span>
                            <span><?= mp_icon_safe('message', 11) ?> <?= (int)$t['mesaj_sayisi'] ?> mesaj</span>
                            <span><?= fmt_datetime($t['son_mesaj_tarihi']) ?></span>
                            <?php if ($t['ilgili_fatura_no']): ?>
                                <span style="color:#0b5cff;font-family:monospace">↗ <?= h($t['ilgili_fatura_no']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="color:#cbd5e1;flex-shrink:0;font-size:20px">→</div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php render_footer(); ?>
