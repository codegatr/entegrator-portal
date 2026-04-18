<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require();

$user = auth_user();
$force = !empty($_GET['force']) || !empty($user['force_pwd']);
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $err = 'Güvenlik hatası.';
    } else {
        $eski = $_POST['eski'] ?? '';
        $yeni = $_POST['yeni'] ?? '';
        $tek  = $_POST['tekrar'] ?? '';

        // Eski şifre kontrolü (force değilse)
        $q = $pdo->prepare("SELECT sifre_hash FROM kullanicilar WHERE id=?");
        $q->execute([$user['id']]);
        $hash = $q->fetchColumn();

        if (!$force && !password_verify($eski, $hash)) {
            $err = 'Mevcut şifre yanlış';
        } elseif (strlen($yeni) < 8) {
            $err = 'Yeni şifre en az 8 karakter olmalı';
        } elseif ($yeni !== $tek) {
            $err = 'Yeni şifreler eşleşmiyor';
        } elseif (password_verify($yeni, $hash)) {
            $err = 'Yeni şifre mevcut şifreyle aynı olamaz';
        } else {
            $pdo->prepare("UPDATE kullanicilar SET sifre_hash=?, sifre_degistirildi=1 WHERE id=?")
                ->execute([password_hash($yeni, PASSWORD_BCRYPT), $user['id']]);
            audit_log($pdo, 'auth.password_change', null, null, "kullanici:{$user['id']}");
            $_SESSION['force_pwd'] = false;
            flash_set('success', 'Şifre başarıyla değiştirildi.');
            redirect(SITE_URL . '/index.php');
        }
    }
}

render_header('Şifre Değiştir', 'yonetim');
?>

<div class="page-head">
    <div>
        <h1>Şifre Değiştir</h1>
        <div class="sub"><?= $force ? 'İlk giriş için güvenli bir şifre belirle' : 'Hesap güvenliği için güçlü bir şifre kullan' ?></div>
    </div>
</div>

<?php if ($force): ?>
    <div class="alert alert-warning">
        <i class="fas fa-lock"></i>
        <strong>İlk giriş:</strong> Şifreni değiştirene kadar diğer sayfalara erişemezsin.
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= h($err) ?></div>
<?php endif; ?>

<div style="max-width:520px">
    <div class="card">
        <div class="card-h"><i class="fas fa-key"></i> Yeni Şifre</div>
        <div class="card-b">
            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <?php if (!$force): ?>
                <div class="fg">
                    <label>Mevcut Şifre *</label>
                    <input type="password" name="eski" required autocomplete="current-password" autofocus>
                </div>
                <?php endif; ?>
                <div class="fg">
                    <label>Yeni Şifre *</label>
                    <input type="password" name="yeni" required minlength="8" maxlength="100" autocomplete="new-password" <?= $force ? 'autofocus' : '' ?>>
                    <div class="hint">En az 8 karakter. Büyük/küçük harf + rakam + özel karakter kombinasyonu öneririz.</div>
                </div>
                <div class="fg">
                    <label>Yeni Şifre (Tekrar) *</label>
                    <input type="password" name="tekrar" required minlength="8" maxlength="100" autocomplete="new-password">
                </div>
                <div style="display:flex;justify-content:flex-end;gap:8px">
                    <?php if (!$force): ?>
                        <a href="<?= SITE_URL ?>/index.php" class="btn btn-ghost">Vazgeç</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Şifreyi Değiştir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php render_footer(); ?>
