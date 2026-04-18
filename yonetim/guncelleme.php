<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require_role('admin');

// ═══ Manifest oku ═══
$manifest_path = ROOT_PATH . '/manifest.json';
$manifest = null;
if (file_exists($manifest_path)) {
    $manifest = json_decode(file_get_contents($manifest_path), true) ?: [];
}
$current_version = $manifest['version'] ?? '1.0.0';
$repo = $manifest['repo'] ?? 'codegatr/entegrator-portal';
$exclude = $manifest['exclude_from_update'] ?? ['config.php', 'install.lock', 'storage/'];

// ═══ POST handlers ═══
$message = null;
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'apply_update') {
        $zip_url = $_POST['zip_url'] ?? '';
        $target_version = $_POST['target_version'] ?? '';

        if (!$zip_url || !$target_version) {
            $message = 'Geçersiz güncelleme parametresi';
            $message_type = 'danger';
        } else {
            try {
                // 1. Yedek al
                $backup_dir = BACKUP_PATH . '/v' . $current_version . '_' . date('Ymd_His');
                if (!is_dir($backup_dir)) @mkdir($backup_dir, 0755, true);

                // 2. ZIP indir
                $zip_file = sys_get_temp_dir() . '/entegrator-update-' . bin2hex(random_bytes(4)) . '.zip';

                $ctx = stream_context_create([
                    'http' => [
                        'timeout' => 60,
                        'follow_location' => 1,
                        'user_agent' => 'codega-entegrator-portal/'. $current_version,
                    ]
                ]);

                // Önce curl dene (daha güvenilir)
                if (function_exists('curl_init')) {
                    $ch = curl_init($zip_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'codega-entegrator-portal/' . $current_version);
                    $zip_data = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($http_code !== 200 || !$zip_data) {
                        throw new \RuntimeException("ZIP indirilemedi (HTTP $http_code)");
                    }
                } else {
                    $zip_data = file_get_contents($zip_url, false, $ctx);
                    if ($zip_data === false) {
                        throw new \RuntimeException('ZIP indirilemedi (allow_url_fopen kapalı olabilir)');
                    }
                }

                file_put_contents($zip_file, $zip_data);

                // 3. ZIP aç
                $zip = new ZipArchive();
                if ($zip->open($zip_file) !== true) {
                    throw new \RuntimeException('ZIP açılamadı');
                }

                // 4. Her dosyayı exclude kontrolü ile uygula
                $applied = 0;
                $skipped = 0;
                $backed_up = 0;

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->statIndex($i);
                    $name = $entry['name'];

                    // Dizinse atla
                    if (substr($name, -1) === '/') continue;

                    // Exclude kontrol
                    $skip = false;
                    foreach ($exclude as $ex) {
                        if ($name === $ex || (str_ends_with($ex, '/') && str_starts_with($name, $ex))) {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) { $skipped++; continue; }

                    $target = ROOT_PATH . '/' . $name;
                    $dir = dirname($target);
                    if (!is_dir($dir)) @mkdir($dir, 0755, true);

                    // Yedek al
                    if (file_exists($target)) {
                        $backup_target = $backup_dir . '/' . $name;
                        $backup_parent = dirname($backup_target);
                        if (!is_dir($backup_parent)) @mkdir($backup_parent, 0755, true);
                        @copy($target, $backup_target);
                        $backed_up++;
                    }

                    // Yeni dosyayı yaz
                    $content = $zip->getFromIndex($i);
                    if ($content !== false) {
                        file_put_contents($target, $content);
                        $applied++;
                    }
                }

                $zip->close();
                @unlink($zip_file);

                // 5. manifest.json'dan yeni sürümü oku ve ayarlara yaz
                $new_manifest = json_decode(file_get_contents($manifest_path), true);
                if (isset($new_manifest['version'])) {
                    ayar_set($pdo, 'portal_surumu', $new_manifest['version']);
                }

                // 6. Log
                audit_log($pdo, 'system.update', "v$current_version → v$target_version, applied=$applied skipped=$skipped", auth_user()['id'], 'update');

                $message = "✓ Güncelleme başarılı! {$applied} dosya güncellendi, {$skipped} dosya korundu. Yedek: v{$current_version} → <code>" . basename($backup_dir) . "</code>";
                $message_type = 'success';

            } catch (\Throwable $e) {
                $message = 'Güncelleme hatası: ' . $e->getMessage();
                $message_type = 'danger';
                audit_log($pdo, 'system.update_fail', $e->getMessage(), auth_user()['id'], 'update');
            }
        }
    }
}

// ═══ GitHub'dan release bilgisi çek ═══
$releases = [];
$latest = null;
$github_error = null;

try {
    $api_url = "https://api.github.com/repos/$repo/releases";
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'codega-entegrator-portal/' . $current_version,
            'header' => ['Accept: application/vnd.github+json'],
        ]
    ]);

    $data = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'codega-entegrator-portal/' . $current_version);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github+json']);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) {
            throw new \RuntimeException("GitHub API HTTP $code");
        }
        $data = $raw;
    } else {
        $data = @file_get_contents($api_url, false, $ctx);
        if ($data === false) throw new \RuntimeException('GitHub API erişilemedi (curl yok, allow_url_fopen kapalı)');
    }

    $list = json_decode($data, true);
    if (is_array($list)) {
        $releases = array_slice($list, 0, 10);
        foreach ($list as $r) {
            if (empty($r['prerelease']) && empty($r['draft'])) {
                $latest = $r;
                break;
            }
        }
    }
} catch (\Throwable $e) {
    $github_error = $e->getMessage();
}

// Sürüm kıyaslama
$update_available = false;
$latest_version = null;
$latest_zip = null;
if ($latest && !empty($latest['tag_name'])) {
    $latest_version = ltrim($latest['tag_name'], 'v');
    if (version_compare($latest_version, $current_version, '>')) {
        $update_available = true;
    }
    // ZIP asset bul
    if (!empty($latest['assets'])) {
        foreach ($latest['assets'] as $asset) {
            if (str_ends_with($asset['name'], '.zip')) {
                $latest_zip = $asset['browser_download_url'];
                break;
            }
        }
    }
    // Asset yoksa zipball kullan
    if (!$latest_zip && !empty($latest['zipball_url'])) {
        $latest_zip = $latest['zipball_url'];
    }
}

// ═══ Mevcut yedekler ═══
$backups = [];
if (is_dir(BACKUP_PATH)) {
    $dirs = glob(BACKUP_PATH . '/v*', GLOB_ONLYDIR);
    rsort($dirs);
    foreach (array_slice($dirs, 0, 10) as $d) {
        $backups[] = [
            'name' => basename($d),
            'path' => $d,
            'time' => filemtime($d),
            'size' => 0,
        ];
    }
}

render_header('Güncelleme', 'guncelleme');
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>">
        <?= icon($message_type === 'success' ? 'check-circle' : 'alert') ?>
        <div><?= $message /* already contains HTML like <code> */ ?></div>
    </div>
<?php endif; ?>

<!-- ═══ DURUM ÖZETİ ═══ -->
<?php if ($update_available): ?>
    <div class="update-box">
        <div class="update-box-content">
            <h3>🎉 Yeni Sürüm Mevcut!</h3>
            <p>CODEGA Entegratör Portal için yeni bir sürüm yayınlandı. Tek tıkla güncelleyebilirsiniz.</p>
            <div class="ver-row">
                <div class="ver-item">
                    <div class="ver-label">Mevcut Sürüm</div>
                    <div class="ver-val">v<?= h($current_version) ?></div>
                </div>
                <div class="ver-arrow">→</div>
                <div class="ver-item">
                    <div class="ver-label">Yeni Sürüm</div>
                    <div class="ver-val" style="color:#fcd34d">v<?= h($latest_version) ?></div>
                </div>
                <div class="ver-item">
                    <div class="ver-label">Yayın Tarihi</div>
                    <div class="ver-val"><?= date('d.m.Y', strtotime($latest['published_at'] ?? 'now')) ?></div>
                </div>
            </div>
            <form method="POST" onsubmit="return confirm('Güncelleme uygulanacak. Önce otomatik yedek alınacak. Devam?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="apply_update">
                <input type="hidden" name="zip_url" value="<?= h($latest_zip) ?>">
                <input type="hidden" name="target_version" value="<?= h($latest_version) ?>">
                <button type="submit" class="btn btn-primary btn-lg">
                    <?= icon('download') ?> v<?= h($latest_version) ?> Sürümüne Güncelle
                </button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="card" style="border-color:#a7f3d0;background:#ecfdf5">
        <div class="card-body" style="display:flex;align-items:center;gap:16px">
            <div style="width:52px;height:52px;border-radius:14px;background:#10b981;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <?= icon('check-circle', 28) ?>
            </div>
            <div style="flex:1">
                <div style="font-size:16px;font-weight:700;color:#065f46;margin-bottom:2px">Sistem Güncel</div>
                <div style="font-size:13.5px;color:#047857">Portal v<?= h($current_version) ?> — en son sürüm kullanılıyor. Her şey yolunda.</div>
            </div>
            <form method="GET" style="margin:0">
                <button type="submit" class="btn btn-outline"><?= icon('refresh') ?> Yenile</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- ═══ 2 SÜTUN: SÜRÜM GEÇMİŞİ + SİSTEM BİLGİSİ ═══ -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
    <!-- Sürüm Geçmişi -->
    <div class="card">
        <div class="card-head">
            <?= icon('github') ?>
            <h3>GitHub Sürüm Geçmişi</h3>
            <a href="https://github.com/<?= h($repo) ?>/releases" target="_blank" class="card-head-action">
                GitHub'da aç →
            </a>
        </div>
        <div class="card-body tight">
            <?php if ($github_error): ?>
                <div class="alert alert-warning" style="margin:14px 16px">
                    <?= icon('alert') ?>
                    <div>
                        <strong>GitHub API'ye ulaşılamadı:</strong> <?= h($github_error) ?>
                        <br><small style="color:#78350f">Sunucunuzda <code>curl</code> veya <code>allow_url_fopen</code> etkin olmalı.</small>
                    </div>
                </div>
            <?php elseif (empty($releases)): ?>
                <div class="table-empty">
                    <?= icon('package', 40) ?>
                    <h4>Henüz yayınlanmış sürüm yok</h4>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sürüm</th>
                            <th>Yayın Tarihi</th>
                            <th>Tür</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($releases as $r):
                            $v = ltrim($r['tag_name'], 'v');
                            $is_current = version_compare($v, $current_version, '=');
                            $is_newer = version_compare($v, $current_version, '>');
                        ?>
                        <tr<?= $is_current ? ' style="background:#fff7ed"' : '' ?>>
                            <td>
                                <a href="<?= h($r['html_url']) ?>" target="_blank" style="font-family:monospace;font-weight:700">
                                    <?= h($r['tag_name']) ?>
                                </a>
                                <?php if ($is_current): ?>
                                    <span class="badge badge-warning" style="margin-left:6px">Aktif</span>
                                <?php elseif ($is_newer): ?>
                                    <span class="badge badge-success" style="margin-left:6px">Yeni</span>
                                <?php endif; ?>
                                <div style="font-size:12px;color:#64748b;margin-top:2px"><?= h($r['name'] ?? '') ?></div>
                            </td>
                            <td style="color:#64748b;font-size:12.5px;white-space:nowrap">
                                <?= date('d.m.Y', strtotime($r['published_at'] ?? 'now')) ?>
                                <div style="font-size:11px;color:#94a3b8"><?= date('H:i', strtotime($r['published_at'] ?? 'now')) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($r['prerelease'])): ?>
                                    <span class="badge badge-warning">Pre-release</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Stable</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;white-space:nowrap">
                                <a href="<?= h($r['html_url']) ?>" target="_blank" class="btn btn-ghost btn-sm">
                                    <?= icon('eye') ?> İncele
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sistem Bilgisi -->
    <div>
        <div class="card">
            <div class="card-head">
                <?= icon('info') ?>
                <h3>Sistem Bilgisi</h3>
            </div>
            <div class="card-body" style="font-size:13px">
                <div style="padding:7px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                    <span style="color:#64748b">Ürün</span>
                    <strong><?= h($manifest['name'] ?? 'Entegratör Portal') ?></strong>
                </div>
                <div style="padding:7px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                    <span style="color:#64748b">Aktif Sürüm</span>
                    <strong style="font-family:monospace;color:#ff6b00">v<?= h($current_version) ?></strong>
                </div>
                <div style="padding:7px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                    <span style="color:#64748b">Son Güncelleme</span>
                    <strong><?= h($manifest['release_date'] ?? '—') ?></strong>
                </div>
                <div style="padding:7px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                    <span style="color:#64748b">Depo</span>
                    <a href="https://github.com/<?= h($repo) ?>" target="_blank" style="font-family:monospace;font-size:11.5px"><?= h($repo) ?></a>
                </div>
                <div style="padding:7px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                    <span style="color:#64748b">PHP Sürümü</span>
                    <strong><?= PHP_VERSION ?></strong>
                </div>
                <div style="padding:7px 0;display:flex;justify-content:space-between">
                    <span style="color:#64748b">GitHub API</span>
                    <?php if ($github_error): ?>
                        <span class="badge badge-danger"><?= icon('x-circle') ?> Erişilemiyor</span>
                    <?php else: ?>
                        <span class="badge badge-success"><?= icon('check') ?> Bağlı</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Yedekler -->
        <div class="card">
            <div class="card-head">
                <?= icon('package') ?>
                <h3>Son Yedekler</h3>
            </div>
            <div class="card-body" style="padding:10px 16px">
                <?php if (empty($backups)): ?>
                    <div class="text-center text-muted" style="padding:20px 0;font-size:13px">
                        Henüz yedek yok
                    </div>
                <?php else: foreach ($backups as $b): ?>
                    <div style="padding:8px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center">
                        <div>
                            <div style="font-family:monospace;font-size:12px;font-weight:600"><?= h($b['name']) ?></div>
                            <div style="font-size:11px;color:#94a3b8"><?= date('d.m.Y H:i', $b['time']) ?></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Güncelleme Bilgisi -->
        <div class="alert alert-info">
            <?= icon('info') ?>
            <div>
                <strong>Güncelleme nasıl çalışır?</strong><br>
                <span style="font-size:12.5px">
                Güncelleme uygulanmadan önce mevcut tüm dosyalar <code>storage/backups/</code> altına yedeklenir.
                <code>config.php</code>, <code>install.lock</code> ve <code>storage/</code> değiştirilmez.
                </span>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
