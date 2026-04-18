<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require_role('admin');

$err = '';

// ═══ POST İŞLEMLERİ ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $err = 'Güvenlik hatası.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_mp_user') {
            $mukellef_id = (int)($_POST['mukellef_id'] ?? 0);
            $kullanici = trim(mb_strtolower($_POST['kullanici_adi'] ?? ''));
            $ad = trim($_POST['ad_soyad'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefon = trim($_POST['telefon'] ?? '');
            $sifre = $_POST['sifre'] ?? '';

            if (!$mukellef_id) {
                $err = 'Müşteri seçilmedi';
            } elseif (!preg_match('/^[a-z0-9_.@-]{3,100}$/', $kullanici)) {
                $err = 'Kullanıcı adı 3-100 karakter, küçük harf/rakam/_/-/@/. olmalı';
            } elseif (strlen($sifre) < 8) {
                $err = 'Şifre en az 8 karakter olmalı';
            } else {
                $chk = $pdo->prepare("SELECT id FROM musteri_portal_kullanicilar WHERE kullanici_adi=?");
                $chk->execute([$kullanici]);
                if ($chk->fetchColumn()) {
                    $err = 'Bu kullanıcı adı zaten var';
                } else {
                    $pdo->prepare("
                        INSERT INTO musteri_portal_kullanicilar
                        (mukellef_id, kullanici_adi, sifre_hash, ad_soyad, email, telefon, sifre_degistirildi)
                        VALUES (?, ?, ?, ?, ?, ?, 0)
                    ")->execute([
                        $mukellef_id, $kullanici, password_hash($sifre, PASSWORD_BCRYPT),
                        $ad ?: null, $email ?: null, $telefon ?: null,
                    ]);
                    audit_log($pdo, 'mp_user.create', "user=$kullanici mukellef_id=$mukellef_id", null, "mp_user");
                    flash_set('success', "Müşteri portal kullanıcısı oluşturuldu: <strong>$kullanici</strong> — şifre: <code style='background:#fff;padding:2px 6px'>$sifre</code>. Müşteriye iletin, ilk girişte değiştirecek.");
                    redirect(SITE_URL . '/yonetim/musteri-kullanici.php');
                }
            }
        } elseif ($action === 'toggle_active') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare("UPDATE musteri_portal_kullanicilar SET aktif = IF(aktif=1,0,1) WHERE id=?")->execute([$id]);
                audit_log($pdo, 'mp_user.toggle', null, null, "mp_user:$id");
                flash_set('success', 'Durum güncellendi.');
            }
            redirect(SITE_URL . '/yonetim/musteri-kullanici.php');
        } elseif ($action === 'reset_pwd') {
            $id = (int)($_POST['id'] ?? 0);
            $yeni = bin2hex(random_bytes(6));
            if ($id) {
                $pdo->prepare("
                    UPDATE musteri_portal_kullanicilar
                    SET sifre_hash=?, sifre_degistirildi=0, yanlis_giris_sayisi=0, kilit_bitis=NULL
                    WHERE id=?
                ")->execute([password_hash($yeni, PASSWORD_BCRYPT), $id]);
                audit_log($pdo, 'mp_user.reset_pwd', null, null, "mp_user:$id");
                flash_set('success', "Yeni şifre: <code style='background:#fff;padding:2px 6px'>$yeni</code> — müşteriye iletin.");
            }
            redirect(SITE_URL . '/yonetim/musteri-kullanici.php');
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare("DELETE FROM musteri_portal_kullanicilar WHERE id=?")->execute([$id]);
                audit_log($pdo, 'mp_user.delete', null, null, "mp_user:$id");
                flash_set('success', 'Kullanıcı silindi.');
            }
            redirect(SITE_URL . '/yonetim/musteri-kullanici.php');
        }
    }
}

// ═══ VERİ ═══
$mp_users = $pdo->query("
    SELECT mpk.*, m.unvan AS mukellef_unvan, m.vkn_tckn, m.aktif AS mukellef_aktif
    FROM musteri_portal_kullanicilar mpk
    LEFT JOIN mukellefler m ON m.id = mpk.mukellef_id
    ORDER BY mpk.created_at DESC
")->fetchAll();

// Aktif mükellefler (dropdown için)
$mukellefler = $pdo->query("SELECT id, vkn_tckn, unvan FROM mukellefler WHERE aktif=1 ORDER BY unvan ASC")->fetchAll();

// İstatistikler
$toplam = count($mp_users);
$aktif = count(array_filter($mp_users, fn($u) => $u['aktif']));

render_header('Müşteri Portalı Kullanıcıları', 'musteri-kullanici');
?>

<div class="page-head">
    <div>
        <h1>Müşteri Portalı Kullanıcıları</h1>
        <div class="sub"><?= $toplam ?> kullanıcı · <?= $aktif ?> aktif</div>
    </div>
    <div class="page-actions">
        <a href="<?= SITE_URL ?>/musteri-portal/login.php" target="_blank" class="btn btn-outline">
            <?= icon('eye') ?> Portal'ı Önizle
        </a>
        <a href="<?= SITE_URL ?>/yonetim/ayarlar.php" class="btn btn-ghost">← Yönetim</a>
    </div>
</div>

<?php if ($err): ?>
    <div class="alert alert-danger"><?= icon('alert') ?><div><?= h($err) ?></div></div>
<?php endif; ?>

<!-- BİLGİ KARTI -->
<div class="alert alert-info">
    <?= icon('info') ?>
    <div>
        <strong>Müşteri Portalı nedir?</strong>
        Müşterilerinize (fatura kestiğiniz firmalara) ayrı bir kullanıcı açabilirsiniz. Müşteri
        <code><?= h(SITE_URL) ?>/musteri-portal/</code> adresinden giriş yaparak <strong>sadece
        kendi adına düzenlenen faturaları</strong> görüntüleyebilir ve XML indirebilir. Admin panelinize
        erişemez.
    </div>
</div>

<!-- YENİ KULLANICI EKLE -->
<div class="card">
    <div class="card-head">
        <?= icon('plus') ?>
        <h3>Yeni Müşteri Portal Kullanıcısı</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_mp_user">

            <div class="fg">
                <label>Müşteri Firma *</label>
                <select name="mukellef_id" required>
                    <option value="">Seçin...</option>
                    <?php foreach ($mukellefler as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= h($m['unvan']) ?> (<?= h($m['vkn_tckn']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Müşterinin hangi firmaya ait olduğu bilgisi. Bu firmaya ait faturaları görecek.</div>
            </div>

            <div class="form-row-3">
                <div class="fg">
                    <label>Kullanıcı Adı *</label>
                    <input type="text" name="kullanici_adi" required pattern="[a-z0-9_.@-]{3,100}" maxlength="100" placeholder="ornek@firma.com veya kullanici_adi">
                    <div class="hint">Genelde müşterinin e-postası kullanılır</div>
                </div>
                <div class="fg">
                    <label>Ad Soyad</label>
                    <input type="text" name="ad_soyad" maxlength="100" placeholder="Ahmet Yılmaz">
                </div>
                <div class="fg">
                    <label>Telefon</label>
                    <input type="tel" name="telefon" maxlength="30" placeholder="+90 5XX ...">
                </div>
            </div>

            <div class="form-row">
                <div class="fg">
                    <label>E-posta (iletişim için)</label>
                    <input type="email" name="email" maxlength="150">
                </div>
                <div class="fg">
                    <label>İlk Şifre *</label>
                    <input type="text" name="sifre" required minlength="8" maxlength="100" autocomplete="new-password">
                    <div class="hint">En az 8 karakter. Müşteri ilk girişte zorunlu değiştirecek.</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <?= icon('plus') ?> Kullanıcı Oluştur
            </button>
        </form>
    </div>
</div>

<!-- KULLANICI LİSTESİ -->
<div class="card">
    <div class="card-head">
        <?= icon('users') ?>
        <h3>Mevcut Müşteri Kullanıcıları</h3>
        <span class="badge badge-secondary" style="margin-left:auto"><?= $toplam ?></span>
    </div>
    <div class="card-body tight">
        <table class="table">
            <thead>
                <tr>
                    <th>Kullanıcı</th>
                    <th>Müşteri Firma</th>
                    <th>İletişim</th>
                    <th>Son Giriş</th>
                    <th>Durum</th>
                    <th style="text-align:right">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mp_users)): ?>
                    <tr><td colspan="6"><div class="table-empty">
                        <?= icon('users', 40) ?>
                        <h4>Henüz müşteri kullanıcısı yok</h4>
                        <p>Yukarıdaki formdan bir müşteri portal kullanıcısı oluşturabilirsiniz</p>
                    </div></td></tr>
                <?php else: foreach ($mp_users as $u):
                    $is_kilitli = !empty($u['kilit_bitis']) && strtotime($u['kilit_bitis']) > time();
                ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="tb-avatar" style="width:32px;height:32px;font-size:12px">
                                    <?= h(strtoupper(mb_substr($u['ad_soyad'] ?: $u['kullanici_adi'], 0, 1))) ?>
                                </div>
                                <div>
                                    <strong style="font-family:monospace;font-size:12.5px"><?= h($u['kullanici_adi']) ?></strong>
                                    <?php if ($u['ad_soyad']): ?>
                                        <div style="font-size:12px;color:#64748b;margin-top:2px"><?= h($u['ad_soyad']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong style="font-size:13px"><?= h($u['mukellef_unvan']) ?></strong>
                            <div style="font-size:11px;color:#94a3b8;font-family:monospace;margin-top:2px"><?= h($u['vkn_tckn']) ?></div>
                        </td>
                        <td style="font-size:12.5px">
                            <?php if ($u['email']): ?><div><?= h($u['email']) ?></div><?php endif; ?>
                            <?php if ($u['telefon']): ?><div style="color:#94a3b8"><?= h($u['telefon']) ?></div><?php endif; ?>
                        </td>
                        <td style="color:#64748b;font-size:12.5px;white-space:nowrap">
                            <?php if ($u['son_giris']): ?>
                                <?= fmt_datetime($u['son_giris']) ?>
                                <?php if ($u['son_ip']): ?>
                                    <div style="font-size:11px;color:#94a3b8;font-family:monospace"><?= h($u['son_ip']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#cbd5e1">Hiç giriş yapmadı</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$u['mukellef_aktif']): ?>
                                <span class="badge badge-danger"><?= icon('ban') ?> Firma Pasif</span>
                            <?php elseif (!$u['aktif']): ?>
                                <span class="badge badge-secondary"><?= icon('ban') ?> Pasif</span>
                            <?php elseif ($is_kilitli): ?>
                                <span class="badge badge-danger"><?= icon('lock') ?> Kilitli</span>
                            <?php elseif (empty($u['sifre_degistirildi'])): ?>
                                <span class="badge badge-warning"><?= icon('alert') ?> İlk giriş bekliyor</span>
                            <?php else: ?>
                                <span class="badge badge-success"><?= icon('check') ?> Aktif</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Şifre sıfırlansın mı? Yeni şifre ekrana çıkacak.')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset_pwd">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" title="Şifre sıfırla">
                                    <?= icon('key') ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('<?= $u['aktif']?'Pasifleştirilsin mi?':'Aktifleştirilsin mi?' ?>')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" title="<?= $u['aktif']?'Pasifleştir':'Aktifleştir' ?>">
                                    <?= $u['aktif'] ? icon('ban') : icon('check') ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Bu kullanıcı TAMAMEN silinsin mi? Bu işlem geri alınamaz.')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" title="Sil" style="color:#dc2626">
                                    <?= icon('trash') ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
