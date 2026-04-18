<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require_role('admin');

$err = '';
$edit_id = (int)($_GET['id'] ?? 0);

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $err = 'Güvenlik hatası.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $u = trim($_POST['kullanici_adi'] ?? '');
            $ad = trim($_POST['ad_soyad'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $rol = in_array($_POST['rol'] ?? '', ['admin','operator','viewer'], true) ? $_POST['rol'] : 'operator';
            $pass = $_POST['sifre'] ?? '';

            if (!$u || !preg_match('/^[a-z0-9_]{3,30}$/', $u)) {
                $err = 'Kullanıcı adı 3-30 karakter, sadece küçük harf/rakam/_ olmalı';
            } elseif (strlen($pass) < 8) {
                $err = 'Şifre en az 8 karakter olmalı';
            } else {
                $chk = $pdo->prepare("SELECT id FROM kullanicilar WHERE kullanici_adi=?");
                $chk->execute([$u]);
                if ($chk->fetchColumn()) {
                    $err = 'Bu kullanıcı adı zaten var';
                } else {
                    $pdo->prepare("INSERT INTO kullanicilar (kullanici_adi, sifre_hash, ad_soyad, email, rol, sifre_degistirildi) VALUES (?,?,?,?,?,0)")
                        ->execute([$u, password_hash($pass, PASSWORD_BCRYPT), $ad ?: null, $email ?: null, $rol]);
                    $new_id = (int)$pdo->lastInsertId();
                    audit_log($pdo, 'kullanici.create', "user=$u rol=$rol", null, "kullanici:$new_id");
                    flash_set('success', "Kullanıcı eklendi: $u. İlk girişte şifre değiştirme zorunlu olacak.");
                    redirect(SITE_URL . '/yonetim/kullanici.php');
                }
            }
        } elseif ($action === 'toggle_active') {
            $kid = (int)($_POST['id'] ?? 0);
            if ($kid && $kid !== auth_user()['id']) {
                $pdo->prepare("UPDATE kullanicilar SET aktif = IF(aktif=1,0,1) WHERE id=?")->execute([$kid]);
                audit_log($pdo, 'kullanici.toggle', null, null, "kullanici:$kid");
                flash_set('success', 'Durum güncellendi.');
            }
            redirect(SITE_URL . '/yonetim/kullanici.php');
        } elseif ($action === 'reset_pwd') {
            $kid = (int)($_POST['id'] ?? 0);
            $new_pass = bin2hex(random_bytes(6));
            if ($kid) {
                $pdo->prepare("UPDATE kullanicilar SET sifre_hash=?, sifre_degistirildi=0, yanlis_giris_sayisi=0, kilit_bitis=NULL WHERE id=?")
                    ->execute([password_hash($new_pass, PASSWORD_BCRYPT), $kid]);
                audit_log($pdo, 'kullanici.reset_pwd', null, null, "kullanici:$kid");
                flash_set('success', "Yeni şifre: $new_pass — bu şifreyi kullanıcıya iletin. İlk girişte değiştirmesi zorunlu olacak.");
            }
            redirect(SITE_URL . '/yonetim/kullanici.php');
        } elseif ($action === 'change_role') {
            $kid = (int)($_POST['id'] ?? 0);
            $rol = in_array($_POST['rol'] ?? '', ['admin','operator','viewer'], true) ? $_POST['rol'] : null;
            if ($kid && $rol && $kid !== auth_user()['id']) {
                $pdo->prepare("UPDATE kullanicilar SET rol=? WHERE id=?")->execute([$rol, $kid]);
                audit_log($pdo, 'kullanici.change_role', "rol=$rol", null, "kullanici:$kid");
                flash_set('success', 'Rol güncellendi.');
            }
            redirect(SITE_URL . '/yonetim/kullanici.php');
        }
    }
}

$kullanicilar = $pdo->query("SELECT * FROM kullanicilar ORDER BY id ASC")->fetchAll();
$aktif_sayi = count(array_filter($kullanicilar, fn($k) => $k['aktif']));
$admin_sayi = count(array_filter($kullanicilar, fn($k) => $k['rol'] === 'admin'));

render_header('Kullanıcı Yönetimi', 'kullanici');
?>

<!-- SAYFA BAŞLIĞI -->
<div class="page-head">
    <div>
        <h1>Kullanıcılar</h1>
        <div class="sub"><?= count($kullanicilar) ?> kullanıcı · <?= $aktif_sayi ?> aktif · <?= $admin_sayi ?> admin</div>
    </div>
    <div class="page-actions">
        <a href="<?= SITE_URL ?>/yonetim/ayarlar.php" class="btn btn-ghost"><?= icon('arrow-left', 14) ?> Ayarlar</a>
    </div>
</div>

<?php if ($err): ?>
    <div class="alert alert-danger"><?= icon('alert') ?><div><?= h($err) ?></div></div>
<?php endif; ?>

<!-- YENİ KULLANICI EKLE -->
<div class="card">
    <div class="card-head">
        <?= icon('users') ?>
        <h3>Yeni Kullanıcı Ekle</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="form-row-3">
                <div class="fg">
                    <label>Kullanıcı Adı *</label>
                    <input type="text" name="kullanici_adi" required pattern="[a-z0-9_]{3,30}" maxlength="30" placeholder="ornek_kullanici">
                    <div class="hint">3-30 karakter, küçük harf/rakam/_</div>
                </div>
                <div class="fg">
                    <label>Ad Soyad</label>
                    <input type="text" name="ad_soyad" maxlength="100" placeholder="Ahmet Yılmaz">
                </div>
                <div class="fg">
                    <label>E-posta</label>
                    <input type="email" name="email" maxlength="150" placeholder="kullanici@codega.com.tr">
                </div>
            </div>

            <div class="form-row">
                <div class="fg">
                    <label>Rol *</label>
                    <select name="rol" required>
                        <option value="viewer">Viewer — sadece okuma</option>
                        <option value="operator" selected>Operator — fatura oluşturma/düzenleme</option>
                        <option value="admin">Admin — tam yetki</option>
                    </select>
                </div>
                <div class="fg">
                    <label>İlk Şifre *</label>
                    <input type="text" name="sifre" required minlength="8" maxlength="100" autocomplete="new-password" placeholder="En az 8 karakter">
                    <div class="hint">Kullanıcı ilk girişinde mutlaka değiştirecek</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <?= icon('plus') ?> Kullanıcı Ekle
            </button>
        </form>
    </div>
</div>

<!-- KULLANICI LİSTESİ -->
<div class="card">
    <div class="card-head">
        <?= icon('list') ?>
        <h3>Mevcut Kullanıcılar</h3>
        <span class="badge badge-secondary" style="margin-left:auto"><?= count($kullanicilar) ?></span>
    </div>
    <div class="card-body tight">
        <table class="table">
            <thead>
                <tr>
                    <th>Kullanıcı</th>
                    <th>Ad Soyad / E-posta</th>
                    <th>Rol</th>
                    <th>Son Giriş</th>
                    <th>Durum</th>
                    <th style="text-align:right">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kullanicilar as $k):
                    $is_me = (int)$k['id'] === auth_user()['id'];
                    $is_kilitli = !empty($k['kilit_bitis']) && strtotime($k['kilit_bitis']) > time();
                ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="tb-avatar" style="width:32px;height:32px;font-size:12px">
                                    <?= h(strtoupper(mb_substr($k['ad_soyad'] ?: $k['kullanici_adi'], 0, 1))) ?>
                                </div>
                                <div>
                                    <strong><?= h($k['kullanici_adi']) ?></strong>
                                    <?php if ($is_me): ?>
                                        <span class="badge badge-info" style="margin-left:4px">sen</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:13.5px;font-weight:500"><?= h($k['ad_soyad'] ?: '—') ?></div>
                            <?php if ($k['email']): ?>
                                <div style="font-size:12px;color:#94a3b8;margin-top:2px"><?= h($k['email']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_me): ?>
                                <span class="badge badge-<?= $k['rol']==='admin'?'primary':($k['rol']==='operator'?'info':'secondary') ?>">
                                    <?= h(ucfirst($k['rol'])) ?>
                                </span>
                            <?php else: ?>
                                <form method="POST" style="display:inline;margin:0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                    <select name="rol" onchange="this.form.submit()" style="padding:5px 8px;font-size:12px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;cursor:pointer">
                                        <option value="viewer"   <?= $k['rol']==='viewer'?'selected':'' ?>>Viewer</option>
                                        <option value="operator" <?= $k['rol']==='operator'?'selected':'' ?>>Operator</option>
                                        <option value="admin"    <?= $k['rol']==='admin'?'selected':'' ?>>Admin</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td style="color:#64748b;font-size:12.5px">
                            <?php if ($k['son_giris']): ?>
                                <div style="font-weight:500"><?= fmt_datetime($k['son_giris']) ?></div>
                                <?php if ($k['son_ip']): ?>
                                    <div style="font-size:11px;color:#94a3b8;font-family:monospace;margin-top:2px"><?= h($k['son_ip']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#cbd5e1">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$k['aktif']): ?>
                                <span class="badge badge-secondary"><?= icon('ban') ?> Pasif</span>
                            <?php elseif ($is_kilitli): ?>
                                <span class="badge badge-danger"><?= icon('lock') ?> Kilitli</span>
                            <?php elseif (empty($k['sifre_degistirildi'])): ?>
                                <span class="badge badge-warning"><?= icon('alert') ?> İlk giriş</span>
                            <?php else: ?>
                                <span class="badge badge-success"><?= icon('check') ?> Aktif</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <?php if (!$is_me): ?>
                                <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Şifre sıfırlansın mı? Yeni şifre ekrana çıkacak.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reset_pwd">
                                    <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm" title="Şifre sıfırla">
                                        <?= icon('key') ?> Sıfırla
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;margin:0 0 0 4px" onsubmit="return confirm('<?= $k['aktif']?'Bu kullanıcı pasifleştirilsin mi?':'Bu kullanıcı aktifleştirilsin mi?' ?>')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                    <button type="submit" class="btn btn-<?= $k['aktif']?'ghost':'success' ?> btn-sm" title="<?= $k['aktif']?'Pasifleştir':'Aktifleştir' ?>">
                                        <?= $k['aktif'] ? icon('ban') : icon('check') ?>
                                        <?= $k['aktif'] ? 'Kapat' : 'Aç' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <a href="<?= SITE_URL ?>/yonetim/sifre.php" class="btn btn-outline btn-sm">
                                    <?= icon('key') ?> Şifrem
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ROL AÇIKLAMALARI -->
<div class="card">
    <div class="card-head">
        <?= icon('info') ?>
        <h3>Rol Yetkileri</h3>
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px">
            <div style="padding:14px;background:#f1f5f9;border-radius:8px;border-left:4px solid #64748b">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                    <span class="badge badge-secondary">Viewer</span>
                </div>
                <div style="font-size:12.5px;color:#475569;line-height:1.6">
                    Sadece okuma yetkisi. Faturaları, müşterileri ve logları görüntüleyebilir. Hiçbir değişiklik yapamaz.
                </div>
            </div>
            <div style="padding:14px;background:#eff6ff;border-radius:8px;border-left:4px solid #0284c7">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                    <span class="badge badge-info">Operator</span>
                </div>
                <div style="font-size:12.5px;color:#475569;line-height:1.6">
                    Fatura ve müşteri oluşturma/düzenleme yetkisi. Yönetim paneline erişemez, kullanıcı yönetemez.
                </div>
            </div>
            <div style="padding:14px;background:#f0f9ff;border-radius:8px;border-left:4px solid #ff6b00">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                    <span class="badge badge-primary">Admin</span>
                </div>
                <div style="font-size:12.5px;color:#475569;line-height:1.6">
                    Tam yetki. Sistem ayarları, kullanıcı yönetimi, güncelleme ve tüm modüllere erişim.
                </div>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
