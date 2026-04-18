<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require_role('admin');

$err = '';
$edit_id = (int)($_GET['id'] ?? 0);

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $err = 'Güvenlik hatası.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id       = (int)($_POST['id'] ?? 0);
            $baslik   = trim($_POST['baslik'] ?? '');
            $icerik   = trim($_POST['icerik'] ?? '');
            $tip      = in_array($_POST['tip'] ?? '', ['bilgi','uyari','onemli','bakim'], true) ? $_POST['tip'] : 'bilgi';
            $hedef    = in_array($_POST['hedef'] ?? '', ['musteri','admin','her_ikisi'], true) ? $_POST['hedef'] : 'musteri';
            $aktif    = !empty($_POST['aktif']) ? 1 : 0;
            $bitis    = $_POST['bitis_tarihi'] ?? '';

            if (!$baslik || mb_strlen($baslik) < 3) {
                $err = 'Başlık en az 3 karakter olmalı';
            } elseif (!$icerik || mb_strlen($icerik) < 10) {
                $err = 'İçerik en az 10 karakter olmalı';
            } else {
                $bitis_clean = null;
                if ($bitis && preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/', $bitis)) {
                    $bitis_clean = str_replace('T', ' ', $bitis) . (strlen($bitis) < 16 ? ' 23:59:59' : ':00');
                }

                if ($id > 0) {
                    $pdo->prepare("
                        UPDATE duyurular
                        SET baslik=?, icerik=?, tip=?, hedef=?, aktif=?, bitis_tarihi=?
                        WHERE id=?
                    ")->execute([$baslik, $icerik, $tip, $hedef, $aktif, $bitis_clean, $id]);
                    audit_log($pdo, 'duyuru.update', "baslik=$baslik", null, "duyuru:$id");
                    flash_set('success', 'Duyuru güncellendi.');
                } else {
                    $pdo->prepare("
                        INSERT INTO duyurular (baslik, icerik, tip, hedef, aktif, bitis_tarihi, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$baslik, $icerik, $tip, $hedef, $aktif, $bitis_clean, auth_user()['id']]);
                    audit_log($pdo, 'duyuru.create', "baslik=$baslik", null, 'duyuru');
                    flash_set('success', 'Duyuru yayınlandı. Müşteriler dashboard\'da görecek.');
                }
                redirect(SITE_URL . '/yonetim/duyurular.php');
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare("UPDATE duyurular SET aktif = IF(aktif=1,0,1) WHERE id=?")->execute([$id]);
                audit_log($pdo, 'duyuru.toggle', null, null, "duyuru:$id");
                flash_set('success', 'Durum güncellendi.');
            }
            redirect(SITE_URL . '/yonetim/duyurular.php');
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare("DELETE FROM duyurular WHERE id=?")->execute([$id]);
                audit_log($pdo, 'duyuru.delete', null, null, "duyuru:$id");
                flash_set('success', 'Duyuru silindi.');
            }
            redirect(SITE_URL . '/yonetim/duyurular.php');
        }
    }
}

// Düzenlenecek duyuru
$edit_duyuru = null;
if ($edit_id > 0) {
    $q = $pdo->prepare("SELECT * FROM duyurular WHERE id=?");
    $q->execute([$edit_id]);
    $edit_duyuru = $q->fetch() ?: null;
}

// Liste
$duyurular = $pdo->query("SELECT * FROM duyurular ORDER BY aktif DESC, id DESC")->fetchAll();
$toplam = count($duyurular);
$aktif_sayi = count(array_filter($duyurular, fn($d) => $d['aktif']));

render_header('Duyurular', 'duyurular');
?>

<div class="page-head">
    <div>
        <h1>Duyurular</h1>
        <div class="sub"><?= $toplam ?> duyuru · <?= $aktif_sayi ?> aktif</div>
    </div>
    <div class="page-actions">
        <a href="<?= SITE_URL ?>/yonetim/ayarlar.php" class="btn btn-ghost">← Yönetim</a>
    </div>
</div>

<?php if ($err): ?>
    <div class="alert alert-danger"><?= icon('alert') ?><div><?= h($err) ?></div></div>
<?php endif; ?>

<div class="alert alert-info">
    <?= icon('info') ?>
    <div>
        Duyurular, <strong>müşteri portalına giriş yapan firmalarda</strong> veya <strong>admin dashboard'da</strong> üstte gösterilir.
        Planlı bakım bildirimi, önemli duyuru, yeni özellik tanıtımı için kullanılabilir.
    </div>
</div>

<!-- EKLEME/DÜZENLEME FORMU -->
<div class="card">
    <div class="card-head">
        <?= $edit_duyuru ? icon('edit') : icon('plus') ?>
        <h3><?= $edit_duyuru ? 'Duyuruyu Düzenle' : 'Yeni Duyuru' ?></h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <?php if ($edit_duyuru): ?>
                <input type="hidden" name="id" value="<?= (int)$edit_duyuru['id'] ?>">
            <?php endif; ?>

            <div class="fg">
                <label>Başlık *</label>
                <input type="text" name="baslik" required minlength="3" maxlength="200"
                       value="<?= h($edit_duyuru['baslik'] ?? '') ?>"
                       placeholder="Örn: Yıl sonu fatura kesim hatırlatması">
            </div>

            <div class="fg">
                <label>İçerik *</label>
                <textarea name="icerik" required rows="4" style="font-family:inherit;resize:vertical"
                          placeholder="Müşterilere gösterilecek duyuru metni..."><?= h($edit_duyuru['icerik'] ?? '') ?></textarea>
                <div class="hint">Basit metin. HTML gösterilmez.</div>
            </div>

            <div class="form-row-3">
                <div class="fg">
                    <label>Tip *</label>
                    <select name="tip" required>
                        <?php foreach (['bilgi'=>'💡 Bilgi','uyari'=>'⚠️ Uyarı','onemli'=>'🔴 Önemli','bakim'=>'🔧 Bakım'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($edit_duyuru['tip'] ?? 'bilgi')===$k?'selected':'' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg">
                    <label>Hedef Kitle *</label>
                    <select name="hedef" required>
                        <option value="musteri"    <?= ($edit_duyuru['hedef'] ?? 'musteri')==='musteri'?'selected':'' ?>>👥 Müşteriler (portal)</option>
                        <option value="admin"      <?= ($edit_duyuru['hedef'] ?? '')==='admin'?'selected':'' ?>>👤 Admin (dashboard)</option>
                        <option value="her_ikisi"  <?= ($edit_duyuru['hedef'] ?? '')==='her_ikisi'?'selected':'' ?>>🌐 Her İkisi</option>
                    </select>
                </div>
                <div class="fg">
                    <label>Bitiş Tarihi</label>
                    <input type="datetime-local" name="bitis_tarihi"
                           value="<?= $edit_duyuru && $edit_duyuru['bitis_tarihi'] ? str_replace(' ', 'T', substr($edit_duyuru['bitis_tarihi'], 0, 16)) : '' ?>">
                    <div class="hint">Boş = süresiz</div>
                </div>
            </div>

            <div class="fg">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="aktif" value="1" <?= ($edit_duyuru === null || !empty($edit_duyuru['aktif']))?'checked':'' ?>>
                    <span>Duyuru aktif (yayında)</span>
                </label>
            </div>

            <div style="display:flex;gap:8px">
                <button type="submit" class="btn btn-primary">
                    <?= $edit_duyuru ? icon('check') : icon('plus') ?>
                    <?= $edit_duyuru ? 'Güncelle' : 'Yayınla' ?>
                </button>
                <?php if ($edit_duyuru): ?>
                    <a href="<?= SITE_URL ?>/yonetim/duyurular.php" class="btn btn-ghost">İptal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- LİSTE -->
<div class="card">
    <div class="card-head">
        <?= icon('list') ?>
        <h3>Mevcut Duyurular</h3>
        <span class="badge badge-secondary" style="margin-left:auto"><?= $toplam ?></span>
    </div>
    <div class="card-body tight">
        <?php if (empty($duyurular)): ?>
            <div class="table-empty" style="padding:40px">
                <h4 style="margin:0 0 6px">Henüz duyuru yok</h4>
                <p style="color:#64748b">Yukarıdaki formdan ilk duyurunuzu ekleyebilirsiniz</p>
            </div>
        <?php else: ?>
            <?php foreach ($duyurular as $d):
                $tip_map = [
                    'bilgi'   => ['💡', 'Bilgi',   '#0284c7', '#e0f2fe'],
                    'uyari'   => ['⚠️', 'Uyarı',   '#d97706', '#fef3c7'],
                    'onemli'  => ['🔴', 'Önemli',  '#dc2626', '#fee2e2'],
                    'bakim'   => ['🔧', 'Bakım',   '#7c3aed', '#ede9fe'],
                ];
                $tip = $tip_map[$d['tip']] ?? $tip_map['bilgi'];
                $hedef_map = [
                    'musteri'   => 'Müşteriler',
                    'admin'     => 'Admin',
                    'her_ikisi' => 'Her İkisi',
                ];
                $is_expired = $d['bitis_tarihi'] && strtotime($d['bitis_tarihi']) < time();
            ?>
                <div style="padding:18px 22px;border-bottom:1px solid #f1f5f9;display:flex;gap:16px">
                    <div style="flex-shrink:0;width:48px;height:48px;background:<?= $tip[3] ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px">
                        <?= $tip[0] ?>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
                            <strong style="font-size:14.5px"><?= h($d['baslik']) ?></strong>
                            <span class="badge badge-secondary" style="font-size:10.5px;background:<?= $tip[3] ?>;color:<?= $tip[2] ?>"><?= $tip[1] ?></span>
                            <span class="badge badge-info" style="font-size:10.5px"><?= $hedef_map[$d['hedef']] ?></span>
                            <?php if (!$d['aktif']): ?>
                                <span class="badge badge-secondary" style="font-size:10.5px">Pasif</span>
                            <?php elseif ($is_expired): ?>
                                <span class="badge badge-danger" style="font-size:10.5px">Süresi Dolmuş</span>
                            <?php else: ?>
                                <span class="badge badge-success" style="font-size:10.5px">Yayında</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:13px;color:#475569;line-height:1.5;margin-bottom:6px;white-space:pre-wrap"><?= h($d['icerik']) ?></div>
                        <div style="font-size:11.5px;color:#94a3b8">
                            Oluşturulma: <?= fmt_datetime($d['created_at']) ?>
                            <?php if ($d['bitis_tarihi']): ?>
                                · Bitiş: <?= fmt_datetime($d['bitis_tarihi']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="flex-shrink:0;display:flex;gap:4px;align-items:flex-start">
                        <a href="?id=<?= $d['id'] ?>" class="btn btn-ghost btn-sm" title="Düzenle">
                            <?= icon('edit', 14) ?>
                        </a>
                        <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Durum değiştirilsin mi?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" title="<?= $d['aktif']?'Pasifleştir':'Aktifleştir' ?>">
                                <?= $d['aktif'] ? icon('ban', 14) : icon('check', 14) ?>
                            </button>
                        </form>
                        <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Bu duyuru silinsin mi? Bu işlem geri alınamaz.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" title="Sil" style="color:#dc2626">
                                <?= icon('trash', 14) ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
