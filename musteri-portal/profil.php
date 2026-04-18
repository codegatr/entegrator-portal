<?php
// Müşteri portal: config.php otomatik session başlatmasın (ayrı session kullanıyoruz)
define('CODEGA_NO_AUTO_SESSION', true);

require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

mp_auth_require();
$user = mp_auth_user();

$ilk = !empty($_GET['ilk']) || !empty($user['force_pwd']);
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!mp_csrf_verify($_POST['csrf'] ?? '')) {
        $err = 'Güvenlik hatası. Sayfayı yenileyin.';
    } else {
        $mevcut = $_POST['mevcut'] ?? '';
        $yeni = $_POST['yeni'] ?? '';
        $yeni2 = $_POST['yeni2'] ?? '';

        // Mevcut kullanıcıyı al (şifre kontrol için)
        $q = $pdo->prepare("SELECT sifre_hash FROM musteri_portal_kullanicilar WHERE id=?");
        $q->execute([$user['id']]);
        $row = $q->fetch();

        if (!password_verify($mevcut, $row['sifre_hash'])) {
            $err = 'Mevcut şifre hatalı';
        } elseif (strlen($yeni) < 8) {
            $err = 'Yeni şifre en az 8 karakter olmalı';
        } elseif ($yeni !== $yeni2) {
            $err = 'Şifreler eşleşmiyor';
        } elseif ($yeni === $mevcut) {
            $err = 'Yeni şifre mevcut şifre ile aynı olamaz';
        } else {
            $pdo->prepare("UPDATE musteri_portal_kullanicilar SET sifre_hash=?, sifre_degistirildi=1 WHERE id=?")
                ->execute([password_hash($yeni, PASSWORD_BCRYPT), $user['id']]);
            mp_audit($pdo, 'musteri.sifre_degistir');
            $_SESSION['mp_force_pwd'] = false;
            mp_flash_set('success', 'Şifreniz başarıyla değiştirildi.');
            header('Location: ' . SITE_URL . '/musteri-portal/profil.php');
            exit;
        }
    }
}

// Müşteri firma bilgileri
$firma = $pdo->prepare("SELECT * FROM mukellefler WHERE id=?");
$firma->execute([$user['mukellef_id']]);
$f = $firma->fetch();

// Son girişler
$log = $pdo->prepare("SELECT * FROM musteri_portal_log WHERE musteri_kullanici_id=? AND olay IN ('musteri.login_ok','musteri.logout') ORDER BY id DESC LIMIT 5");
$log->execute([$user['id']]);
$loglar = $log->fetchAll();

mp_render_header('Profilim', 'profil');
?>

<?php if ($ilk): ?>
    <div class="mp-alert mp-alert-warning">
        <?= mp_icon('alert') ?>
        <div>
            <strong>İlk giriş:</strong> Güvenliğiniz için varsayılan şifrenizi değiştirmeniz gerekmektedir.
            Aşağıdaki formu doldurarak yeni bir şifre belirleyin.
        </div>
    </div>
<?php endif; ?>

<div class="mp-page-head">
    <div>
        <h1>Profilim</h1>
        <div class="sub">Hesap bilgileri ve güvenlik ayarları</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
    <!-- Sol: Şifre Değiştir -->
    <div>
        <div class="mp-card">
            <div class="mp-card-head">
                <?= mp_icon('key') ?>
                <h3>Şifre Değiştir</h3>
            </div>
            <div class="mp-card-body">
                <?php if ($err): ?>
                    <div class="mp-alert mp-alert-danger"><?= mp_icon('x-circle') ?><div><?= h($err) ?></div></div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <?= mp_csrf_field() ?>

                    <div class="mp-fg">
                        <label>Mevcut Şifre *</label>
                        <input type="password" name="mevcut" required autocomplete="current-password">
                    </div>

                    <div class="mp-fg">
                        <label>Yeni Şifre *</label>
                        <input type="password" name="yeni" required minlength="8" autocomplete="new-password">
                        <div class="hint">En az 8 karakter. Harf, rakam ve sembol karışımı önerilir.</div>
                    </div>

                    <div class="mp-fg">
                        <label>Yeni Şifre (tekrar) *</label>
                        <input type="password" name="yeni2" required minlength="8" autocomplete="new-password">
                    </div>

                    <button type="submit" class="mp-btn mp-btn-primary">
                        <?= mp_icon('lock', 14) ?> Şifreyi Değiştir
                    </button>
                </form>
            </div>
        </div>

        <div class="mp-card">
            <div class="mp-card-head">
                <?= mp_icon('user') ?>
                <h3>Hesap Bilgileri</h3>
            </div>
            <div class="mp-card-body">
                <div style="font-size:13.5px">
                    <div style="padding:10px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                        <span style="color:#64748b">Kullanıcı Adı</span>
                        <strong style="font-family:monospace"><?= h($user['user']) ?></strong>
                    </div>
                    <div style="padding:10px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                        <span style="color:#64748b">Ad Soyad</span>
                        <strong><?= h($user['ad_soyad'] ?: '—') ?></strong>
                    </div>
                    <?php
                    $q = $pdo->prepare("SELECT email, telefon, son_giris, son_ip FROM musteri_portal_kullanicilar WHERE id=?");
                    $q->execute([$user['id']]);
                    $u = $q->fetch();
                    ?>
                    <div style="padding:10px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                        <span style="color:#64748b">E-posta</span>
                        <strong><?= h($u['email'] ?: '—') ?></strong>
                    </div>
                    <div style="padding:10px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                        <span style="color:#64748b">Telefon</span>
                        <strong><?= h($u['telefon'] ?: '—') ?></strong>
                    </div>
                    <div style="padding:10px 0;display:flex;justify-content:space-between">
                        <span style="color:#64748b">Son Giriş</span>
                        <strong><?= $u['son_giris'] ? fmt_datetime($u['son_giris']) : '—' ?></strong>
                    </div>
                </div>
                <div style="margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;font-size:12.5px;color:#94a3b8">
                    Bilgilerinizi güncellemek için CODEGA ile iletişime geçin:
                    <a href="mailto:<?= h(CONTACT_EMAIL) ?>" style="color:#0b5cff"><?= h(CONTACT_EMAIL) ?></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sağ: Firma + Giriş Geçmişi -->
    <div>
        <div class="mp-card">
            <div class="mp-card-head">
                <?= mp_icon('building') ?>
                <h3>Firma Bilgileriniz</h3>
            </div>
            <div class="mp-card-body" style="font-size:13px">
                <div style="padding:6px 0;border-bottom:1px solid #f1f5f9">
                    <div style="color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px">Ünvan</div>
                    <strong><?= h($f['unvan']) ?></strong>
                </div>
                <div style="padding:6px 0;border-bottom:1px solid #f1f5f9">
                    <div style="color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px"><?= $f['vkn_tip'] ?></div>
                    <strong style="font-family:monospace"><?= h($f['vkn_tckn']) ?></strong>
                </div>
                <?php if ($f['vergi_dairesi']): ?>
                    <div style="padding:6px 0;border-bottom:1px solid #f1f5f9">
                        <div style="color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px">Vergi Dairesi</div>
                        <strong><?= h($f['vergi_dairesi']) ?></strong>
                    </div>
                <?php endif; ?>
                <?php if ($f['adres']): ?>
                    <div style="padding:6px 0">
                        <div style="color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px">Adres</div>
                        <span><?= h($f['adres']) ?><?= $f['ilce'] ? '<br>'.h($f['ilce']).' / '.h($f['il']) : '' ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mp-card">
            <div class="mp-card-head">
                <?= mp_icon('clock') ?>
                <h3>Son Girişler</h3>
            </div>
            <div class="mp-card-body" style="padding:12px 22px">
                <?php if (empty($loglar)): ?>
                    <div style="text-align:center;color:#94a3b8;font-size:13px;padding:14px 0">Henüz kayıt yok</div>
                <?php else: foreach ($loglar as $l): ?>
                    <div style="padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:12.5px">
                        <div style="display:flex;justify-content:space-between">
                            <strong style="color:#0f172a">
                                <?= $l['olay'] === 'musteri.login_ok' ? '✓ Giriş' : '→ Çıkış' ?>
                            </strong>
                            <span style="color:#94a3b8"><?= fmt_datetime($l['created_at']) ?></span>
                        </div>
                        <?php if ($l['ip']): ?>
                            <div style="color:#94a3b8;font-family:monospace;font-size:11px;margin-top:2px"><?= h($l['ip']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php mp_render_footer(); ?>
