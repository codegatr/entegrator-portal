<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require_role('admin');

$err = ''; $msg = '';
$edit_id = (int)($_GET['id'] ?? 0);
$new = !empty($_GET['new']);

// POST: yeni kullanıcı ekle / güncelle
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

            if (!$u || !preg_match('/^[a-z0-9_]{3,30}$/', $u)) $err = 'Kullanıcı adı 3-30 karakter, sadece küçük harf/rakam/_ olmalı';
            elseif (strlen($pass) < 8) $err = 'Şifre en az 8 karakter olmalı';
            else {
                $chk = $pdo->prepare("SELECT id FROM kullanicilar WHERE kullanici_adi=?");
                $chk->execute([$u]);
                if ($chk->fetchColumn()) $err = 'Bu kullanıcı adı zaten var';
                else {
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

render_header('Kullanıcı Yönetimi', 'yonetim');
?>

<div class="page-head">
    <div>
        <h1>Kullanıcılar</h1>
        <div class="sub"><?= count($kullanicilar) ?> kullanıcı</div>
    </div>
    <div class="page-actions">
        <a href="<?= SITE_URL ?>/yonetim/ayarlar.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Ayarlar</a>
    </div>
</div>

<?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= h($err) ?></div><?php endif; ?>

<!-- Yeni Kullanıcı Formu -->
<div class="card">
    <div class="card-h"><i class="fas fa-user-plus"></i> Yeni Kullanıcı Ekle</div>
    <div class="card-b">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-row-3">
                <div class="fg">
                    <label>Kullanıcı Adı *</label>
                    <input type="text" name="kullanici_adi" required pattern="[a-z0-9_]{3,30}" maxlength="30">
                    <div class="hint">3-30 karakter, küçük harf/rakam/_</div>
                </div>
                <div class="fg">
                    <label>Ad Soyad</label>
                    <input type="text" name="ad_soyad" maxlength="100">
                </div>
                <div class="fg">
                    <label>E-posta</label>
                    <input type="email" name="email" maxlength="150">
                </div>
            </div>
            <div class="form-row">
                <div class="fg">
                    <label>Rol *</label>
                    <select name="rol" required>
                        <option value="viewer">Viewer (sadece okuma)</option>
                        <option value="operator" selected>Operator (fatura oluşturma/düzenleme)</option>
                        <option value="admin">Admin (tam yetki)</option>
                    </select>
                </div>
                <div class="fg">
                    <label>İlk Şifre *</label>
                    <input type="text" name="sifre" required minlength="8" maxlength="100" autocomplete="new-password">
                    <div class="hint">En az 8 karakter. Kullanıcı ilk girişte değiştirecek.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Kullanıcı Ekle</button>
        </form>
    </div>
</div>

<!-- Kullanıcı Listesi -->
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Kullanıcı</th>
                <th>Ad Soyad</th>
                <th>Rol</th>
                <th>Son Giriş</th>
                <th>Durum</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($kullanicilar as $k):
                $is_me = (int)$k['id'] === auth_user()['id'];
                $is_kilitli = !empty($k['kilit_bitis']) && strtotime($k['kilit_bitis']) > time();
            ?>
                <tr>
                    <td>
                        <strong><?= h($k['kullanici_adi']) ?></strong>
                        <?php if ($is_me): ?><span class="badge badge-info" style="margin-left:6px">sen</span><?php endif; ?>
                    </td>
                    <td style="font-size:13px">
                        <?= h($k['ad_soyad'] ?: '—') ?>
                        <?php if ($k['email']): ?><br><small style="color:#94a3b8"><?= h($k['email']) ?></small><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($is_me): ?>
                            <span class="badge badge-<?= $k['rol']==='admin'?'primary':($k['rol']==='operator'?'info':'secondary') ?>"><?= h($k['rol']) ?></span>
                        <?php else: ?>
                            <form method="POST" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="change_role">
                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                <select name="rol" onchange="this.form.submit()" style="padding:4px 8px;font-size:12px;border:1px solid #cbd5e1;border-radius:4px">
                                    <option value="viewer" <?= $k['rol']==='viewer'?'selected':'' ?>>Viewer</option>
                                    <option value="operator" <?= $k['rol']==='operator'?'selected':'' ?>>Operator</option>
                                    <option value="admin" <?= $k['rol']==='admin'?'selected':'' ?>>Admin</option>
                                </select>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:#64748b;white-space:nowrap">
                        <?= $k['son_giris'] ? fmt_datetime($k['son_giris']) : '—' ?>
                        <?php if ($k['son_ip']): ?><br><small style="color:#94a3b8;font-family:monospace"><?= h($k['son_ip']) ?></small><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$k['aktif']): ?>
                            <span class="badge badge-secondary">Pasif</span>
                        <?php elseif ($is_kilitli): ?>
                            <span class="badge badge-danger">Kilitli</span>
                        <?php elseif (empty($k['sifre_degistirildi'])): ?>
                            <span class="badge badge-warning">İlk giriş</span>
                        <?php else: ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap">
                        <?php if (!$is_me): ?>
                            <form method="POST" style="display:inline" data-confirm="Şifre sıfırlansın mı? Yeni şifre ekrana çıkacak.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset_pwd">
                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Şifre sıfırlansın mı? Yeni şifre ekrana çıkacak." title="Şifre sıfırla"><i class="fas fa-key"></i></button>
                            </form>
                            <form method="POST" style="display:inline" data-confirm="Durum değiştirilsin mi?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Durum değiştirilsin mi?" title="<?= $k['aktif']?'Pasifleştir':'Aktifleştir' ?>">
                                    <i class="fas fa-<?= $k['aktif']?'ban':'check' ?>"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="<?= SITE_URL ?>/yonetim/sifre.php" class="btn btn-ghost btn-sm"><i class="fas fa-key"></i> Şifrem</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php render_footer(); ?>
