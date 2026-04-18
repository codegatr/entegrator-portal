<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require_role('operator');

$id = (int)($_GET['id'] ?? 0);
$is_new = !empty($_GET['new']) || $id === 0;

$m = [
    'id'=>0,'vkn_tckn'=>'','vkn_tip'=>'VKN','unvan'=>'','adi'=>'','soyadi'=>'',
    'vergi_dairesi'=>'','adres'=>'','ilce'=>'','il'=>'','posta_kodu'=>'','ulke'=>'Türkiye',
    'telefon'=>'','email'=>'','website'=>'','e_fatura_mukellefi'=>0,'notlar'=>'','aktif'=>1,
];

if (!$is_new) {
    $q = $pdo->prepare("SELECT * FROM mukellefler WHERE id=?");
    $q->execute([$id]);
    $r = $q->fetch();
    if (!$r) { flash_set('danger', 'Müşteri bulunamadı.'); redirect(SITE_URL.'/musteri/'); }
    $m = array_merge($m, $r);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $errors['_'] = 'Güvenlik hatası, sayfa yenileyin.';
    } else {
        foreach (['vkn_tckn','unvan','adi','soyadi','vergi_dairesi','adres','ilce','il','posta_kodu','ulke','telefon','email','website','notlar'] as $k) {
            $m[$k] = trim($_POST[$k] ?? '');
        }
        $m['e_fatura_mukellefi'] = !empty($_POST['e_fatura_mukellefi']) ? 1 : 0;
        $m['aktif'] = !empty($_POST['aktif']) ? 1 : 0;

        // Validasyon
        if (strlen($m['vkn_tckn']) === 10) $m['vkn_tip'] = 'VKN';
        elseif (strlen($m['vkn_tckn']) === 11) $m['vkn_tip'] = 'TCKN';
        else $errors['vkn_tckn'] = 'VKN 10 hane veya TCKN 11 hane olmalı';

        if ($m['vkn_tckn'] && !ctype_digit($m['vkn_tckn'])) $errors['vkn_tckn'] = 'Sadece rakam olmalı';
        if (!$m['unvan']) $errors['unvan'] = 'Ünvan zorunlu';
        if ($m['vkn_tip'] === 'VKN' && !$m['vergi_dairesi']) $errors['vergi_dairesi'] = 'Tüzel kişi için vergi dairesi zorunlu';
        if (!$m['il']) $errors['il'] = 'İl zorunlu';
        if ($m['email'] && !filter_var($m['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Geçersiz e-posta';

        // Unique VKN/TCKN
        if (empty($errors['vkn_tckn'])) {
            $chk = $pdo->prepare("SELECT id FROM mukellefler WHERE vkn_tckn=? AND id<>?");
            $chk->execute([$m['vkn_tckn'], $m['id']]);
            if ($chk->fetchColumn()) $errors['vkn_tckn'] = 'Bu VKN/TCKN zaten kayıtlı';
        }

        if (!$errors) {
            if ($is_new) {
                $sql = "INSERT INTO mukellefler (vkn_tckn,vkn_tip,unvan,adi,soyadi,vergi_dairesi,adres,ilce,il,posta_kodu,ulke,telefon,email,website,e_fatura_mukellefi,notlar,aktif) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $pdo->prepare($sql)->execute([
                    $m['vkn_tckn'],$m['vkn_tip'],$m['unvan'],$m['adi']?:null,$m['soyadi']?:null,
                    $m['vergi_dairesi']?:null,$m['adres']?:null,$m['ilce']?:null,$m['il'],$m['posta_kodu']?:null,
                    $m['ulke'],$m['telefon']?:null,$m['email']?:null,$m['website']?:null,
                    $m['e_fatura_mukellefi'],$m['notlar']?:null,$m['aktif']
                ]);
                $new_id = (int)$pdo->lastInsertId();
                audit_log($pdo, 'mukellef.create', "vkn={$m['vkn_tckn']}", null, "mukellef:$new_id");
                flash_set('success', 'Müşteri kaydedildi.');
                redirect(SITE_URL . '/musteri/duzenle.php?id=' . $new_id);
            } else {
                $sql = "UPDATE mukellefler SET vkn_tckn=?, vkn_tip=?, unvan=?, adi=?, soyadi=?, vergi_dairesi=?, adres=?, ilce=?, il=?, posta_kodu=?, ulke=?, telefon=?, email=?, website=?, e_fatura_mukellefi=?, notlar=?, aktif=? WHERE id=?";
                $pdo->prepare($sql)->execute([
                    $m['vkn_tckn'],$m['vkn_tip'],$m['unvan'],$m['adi']?:null,$m['soyadi']?:null,
                    $m['vergi_dairesi']?:null,$m['adres']?:null,$m['ilce']?:null,$m['il'],$m['posta_kodu']?:null,
                    $m['ulke'],$m['telefon']?:null,$m['email']?:null,$m['website']?:null,
                    $m['e_fatura_mukellefi'],$m['notlar']?:null,$m['aktif'], $m['id']
                ]);
                audit_log($pdo, 'mukellef.update', "vkn={$m['vkn_tckn']}", null, "mukellef:{$m['id']}");
                flash_set('success', 'Değişiklikler kaydedildi.');
                redirect(SITE_URL . '/musteri/duzenle.php?id=' . $m['id']);
            }
        }
    }
}

render_header($is_new ? 'Yeni Müşteri' : 'Müşteri: '.$m['unvan'], 'musteri');
?>

<div class="page-head">
    <div>
        <h1><?= $is_new ? 'Yeni Müşteri' : h($m['unvan']) ?></h1>
        <?php if(!$is_new): ?><div class="sub"><?= h($m['vkn_tip']) ?>: <?= h($m['vkn_tckn']) ?></div><?php endif; ?>
    </div>
    <div class="page-actions">
        <a href="<?= SITE_URL ?>/musteri/" class="btn btn-ghost"><?= icon('arrow-left', 14) ?> Geri</a>
    </div>
</div>

<?php if (!empty($errors['_'])): ?>
    <div class="alert alert-danger"><?= icon('alert', 14) ?> <?= h($errors['_']) ?></div>
<?php endif; ?>

<form method="POST">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-head"><?= icon('info') ?><h3>Vergi Bilgileri</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="fg">
                    <label>VKN (10) / TCKN (11) *</label>
                    <input type="text" name="vkn_tckn" value="<?= h($m['vkn_tckn']) ?>" required maxlength="11" pattern="\d{10,11}">
                    <?php if(!empty($errors['vkn_tckn'])): ?><div class="err"><?= h($errors['vkn_tckn']) ?></div><?php endif; ?>
                    <div class="hint">Uzunluğa göre tür otomatik belirlenir</div>
                </div>
                <div class="fg">
                    <label>Vergi Dairesi (tüzel için zorunlu)</label>
                    <input type="text" name="vergi_dairesi" value="<?= h($m['vergi_dairesi']) ?>" maxlength="100">
                    <?php if(!empty($errors['vergi_dairesi'])): ?><div class="err"><?= h($errors['vergi_dairesi']) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="fg">
                <label>Ünvan / Firma Adı *</label>
                <input type="text" name="unvan" value="<?= h($m['unvan']) ?>" required maxlength="200">
                <?php if(!empty($errors['unvan'])): ?><div class="err"><?= h($errors['unvan']) ?></div><?php endif; ?>
            </div>
            <div class="form-row">
                <div class="fg">
                    <label>Ad (gerçek kişi için)</label>
                    <input type="text" name="adi" value="<?= h($m['adi']) ?>" maxlength="50">
                </div>
                <div class="fg">
                    <label>Soyad (gerçek kişi için)</label>
                    <input type="text" name="soyadi" value="<?= h($m['soyadi']) ?>" maxlength="50">
                </div>
            </div>
            <label class="check-row" style="margin-top:4px">
                <input type="checkbox" name="e_fatura_mukellefi" value="1" <?= $m['e_fatura_mukellefi']?'checked':'' ?>>
                <span>e-Fatura mükellefi (TEMELFATURA/TICARIFATURA kullanılacak)</span>
            </label>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><?= icon('info') ?><h3>Adres</h3></div>
        <div class="card-body">
            <div class="fg">
                <label>Açık Adres</label>
                <textarea name="adres" rows="2" maxlength="500"><?= h($m['adres']) ?></textarea>
            </div>
            <div class="form-row-3">
                <div class="fg">
                    <label>İlçe</label>
                    <input type="text" name="ilce" value="<?= h($m['ilce']) ?>" maxlength="50">
                </div>
                <div class="fg">
                    <label>İl *</label>
                    <input type="text" name="il" value="<?= h($m['il']) ?>" required maxlength="50">
                    <?php if(!empty($errors['il'])): ?><div class="err"><?= h($errors['il']) ?></div><?php endif; ?>
                </div>
                <div class="fg">
                    <label>Posta Kodu</label>
                    <input type="text" name="posta_kodu" value="<?= h($m['posta_kodu']) ?>" maxlength="10">
                </div>
            </div>
            <div class="fg">
                <label>Ülke</label>
                <input type="text" name="ulke" value="<?= h($m['ulke'] ?: 'Türkiye') ?>" maxlength="50">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><?= icon('info') ?><h3>İletişim</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div class="fg">
                    <label>Telefon</label>
                    <input type="tel" name="telefon" value="<?= h($m['telefon']) ?>" maxlength="50">
                </div>
                <div class="fg">
                    <label>E-posta</label>
                    <input type="email" name="email" value="<?= h($m['email']) ?>" maxlength="150">
                    <?php if(!empty($errors['email'])): ?><div class="err"><?= h($errors['email']) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="fg">
                <label>Website</label>
                <input type="url" name="website" value="<?= h($m['website']) ?>" maxlength="255" placeholder="https://">
            </div>
            <div class="fg">
                <label>Notlar (iç kullanım)</label>
                <textarea name="notlar" rows="2" maxlength="500"><?= h($m['notlar']) ?></textarea>
            </div>
            <label class="check-row" style="margin-top:4px">
                <input type="checkbox" name="aktif" value="1" <?= $m['aktif']?'checked':'' ?>>
                <span>Aktif (fatura kesiminde seçilebilir)</span>
            </label>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px">
        <a href="<?= SITE_URL ?>/musteri/" class="btn btn-ghost">Vazgeç</a>
        <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> Kaydet</button>
    </div>
</form>

<?php render_footer(); ?>
