<?php
// pages/lokasi_absen.php
// Manajemen Lokasi Absen — CRUD untuk Admin & HRD
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'hrd', 'manager'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Lokasi Absen';
$active_menu = 'lokasi_absen';

// ── Buat tabel jika belum ada ─────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `lokasi_absen` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `nama`          VARCHAR(100) NOT NULL,
        `alamat`        VARCHAR(255) DEFAULT NULL,
        `lat`           DECIMAL(10,7) NOT NULL,
        `lon`           DECIMAL(10,7) NOT NULL,
        `radius`        INT NOT NULL DEFAULT 100 COMMENT 'meter',
        `status`        ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
        `keterangan`    VARCHAR(255) DEFAULT NULL,
        `created_by`    INT UNSIGNED DEFAULT NULL,
        `updated_by`    INT UNSIGNED DEFAULT NULL,
        `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) {}

// ── Tambah kolom yang mungkin belum ada (ALTER safe) ──────
$cols_needed = [
    "ALTER TABLE `lokasi_absen` ADD COLUMN IF NOT EXISTS `alamat`      VARCHAR(255) DEFAULT NULL AFTER `nama`",
    "ALTER TABLE `lokasi_absen` ADD COLUMN IF NOT EXISTS `keterangan`  VARCHAR(255) DEFAULT NULL AFTER `status`",
    "ALTER TABLE `lokasi_absen` ADD COLUMN IF NOT EXISTS `created_by`  INT UNSIGNED DEFAULT NULL AFTER `keterangan`",
    "ALTER TABLE `lokasi_absen` ADD COLUMN IF NOT EXISTS `updated_by`  INT UNSIGNED DEFAULT NULL AFTER `created_by`",
];
foreach ($cols_needed as $sql) {
    try { $pdo->exec($sql); } catch(Exception $e) {}
}

// ── POST HANDLER ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if (in_array($act, ['simpan', 'update'])) {
        $id  = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $lat  = trim($_POST['lat'] ?? '');
        $lon  = trim($_POST['lon'] ?? '');
        $err  = [];

        if (!$nama)                          $err[] = 'Nama lokasi wajib diisi.';
        if (!is_numeric($lat))               $err[] = 'Latitude tidak valid.';
        if (!is_numeric($lon))               $err[] = 'Longitude tidak valid.';
        if (is_numeric($lat) && ((float)$lat < -90  || (float)$lat > 90))  $err[] = 'Latitude harus antara -90 dan 90.';
        if (is_numeric($lon) && ((float)$lon < -180 || (float)$lon > 180)) $err[] = 'Longitude harus antara -180 dan 180.';

        if (!$err) {
            $data = [
                'nama'       => $nama,
                'alamat'     => trim($_POST['alamat'] ?? '') ?: null,
                'lat'        => (float)$lat,
                'lon'        => (float)$lon,
                'radius'     => max(10, (int)($_POST['radius'] ?? 100)),
                'status'     => $_POST['status'] ?? 'aktif',
                'keterangan' => trim($_POST['keterangan'] ?? '') ?: null,
                'updated_by' => (int)$_SESSION['user_id'],
            ];
            try {
                if ($act === 'update' && $id) {
                    $sets = implode(',', array_map(fn($k) => "`$k`=?", array_keys($data)));
                    $vals = array_values($data);
                    $vals[] = $id;
                    $pdo->prepare("UPDATE lokasi_absen SET $sets WHERE id=?")->execute($vals);
                    setFlash('success', "Lokasi <strong>".htmlspecialchars($nama)."</strong> diperbarui.");
                } else {
                    $data['created_by'] = (int)$_SESSION['user_id'];
                    $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
                    $phs  = implode(',', array_fill(0, count($data), '?'));
                    $pdo->prepare("INSERT INTO lokasi_absen ($cols) VALUES ($phs)")->execute(array_values($data));
                    setFlash('success', "Lokasi <strong>".htmlspecialchars($nama)."</strong> berhasil ditambahkan.");
                }
            } catch(Exception $e) {
                setFlash('danger', 'Gagal menyimpan: '.$e->getMessage());
            }
        } else {
            setFlash('danger', implode('<br>', $err));
        }
        redirect(APP_URL.'/pages/lokasi_absen.php');
    }

    if ($act === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM lokasi_absen WHERE id=?")->execute([$id]);
            setFlash('success', 'Lokasi berhasil dihapus.');
        }
        redirect(APP_URL.'/pages/lokasi_absen.php');
    }

    if ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $pdo->prepare("UPDATE lokasi_absen SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]);
        redirect(APP_URL.'/pages/lokasi_absen.php');
    }
}

// ── FETCH ─────────────────────────────────────────────────
$lokasi_list = $pdo->query("SELECT * FROM lokasi_absen ORDER BY status ASC, nama ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<style>
* { box-sizing: border-box; }
.lo { font-family: 'Inter','Segoe UI',sans-serif; color: #1e293b; }

.lo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px; margin-bottom: 20px;
}
.lo-card { background:#fff; border:1.5px solid #e2e8f0; border-radius:12px; overflow:hidden; transition:box-shadow .15s,transform .15s; }
.lo-card:hover { box-shadow:0 6px 20px rgba(0,0,0,.08); transform:translateY(-1px); }
.lo-card.nonaktif { opacity:.55; }
.lo-card-top { height:5px; }
.lo-card-body { padding:14px 16px 10px; }
.lo-card-header { display:flex; align-items:flex-start; gap:10px; margin-bottom:10px; }
.lo-icon { width:38px; height:38px; border-radius:10px; background:#f0fdf9; border:1.5px solid #a7f3d0; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.lo-card-nama   { font-size:13.5px; font-weight:700; color:#0f172a; line-height:1.2; }
.lo-card-alamat { font-size:11px; color:#94a3b8; margin-top:2px; line-height:1.4; }

.lo-coord-row { background:#f8fafc; border:1px solid #e2e8f0; border-radius:7px; padding:8px 10px; margin-bottom:8px; display:flex; align-items:center; gap:8px; cursor:pointer; transition:background .1s; }
.lo-coord-row:hover { background:#f0fdf9; }
.lo-coord-val  { font-size:11.5px; font-family:'JetBrains Mono','Courier New',monospace; font-weight:600; color:#0369a1; flex:1; }
.lo-coord-copy { font-size:9px; color:#94a3b8; white-space:nowrap; }

.lo-meta-row { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px; }
.lo-meta-item { display:flex; align-items:center; gap:4px; font-size:10.5px; color:#64748b; font-weight:500; }
.lo-meta-item i { font-size:9px; color:#94a3b8; }

.lo-badge { font-size:10px; font-weight:700; padding:2px 8px; border-radius:4px; }
.lo-badge.aktif      { background:#dcfce7; color:#15803d; }
.lo-badge.nonaktif   { background:#f1f5f9; color:#64748b; }
.lo-badge.wajib      { background:#fee2e2; color:#b91c1c; }
.lo-badge.peringatan { background:#fef3c7; color:#a16207; }

.lo-card-foot { display:flex; gap:5px; padding:8px 16px; border-top:1px solid #f1f5f9; background:#fafbfc; justify-content:flex-end; }

.lo-add-card { background:#fff; border:1.5px dashed #cbd5e1; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:8px; padding:32px 16px; cursor:pointer; transition:all .15s; color:#94a3b8; min-height:200px; }
.lo-add-card:hover { border-color:#00c896; color:#00c896; background:#f0fdf9; }
.lo-add-card i { font-size:24px; }
.lo-add-card span { font-size:12.5px; font-weight:600; }

/* MODAL */
.lo-ov { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:99999; align-items:flex-start; justify-content:center; padding:20px 16px; backdrop-filter:blur(2px); overflow-y:auto; }
.lo-ov.open { display:flex; }
.lo-modal { background:#fff; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.25); width:100%; max-width:580px; display:flex; flex-direction:column; animation:loIn .2s ease; margin:auto; }
@keyframes loIn { from{opacity:0;transform:translateY(14px) scale(.97);}to{opacity:1;transform:none;} }

.lo-mhd { background:linear-gradient(130deg,#0d1b2e,#163354); padding:14px 18px; border-radius:12px 12px 0 0; display:flex; align-items:center; gap:12px; color:#fff; flex-shrink:0; }
.lo-mhd-ico { width:36px; height:36px; border-radius:9px; background:rgba(255,255,255,.1); display:flex; align-items:center; justify-content:center; }
.lo-mhd-ico i { color:#00c896; font-size:14px; }
.lo-mhd-title { font-size:14px; font-weight:700; }
.lo-mhd-sub   { font-size:11px; color:rgba(255,255,255,.45); margin-top:1px; }
.lo-close { margin-left:auto; width:28px; height:28px; border-radius:50%; background:rgba(255,255,255,.1); border:none; color:#fff; cursor:pointer; font-size:11px; display:flex; align-items:center; justify-content:center; transition:background .12s; }
.lo-close:hover { background:#ef4444; }

.lo-mbody { padding:18px 20px; }
.lo-mft { padding:12px 18px; border-top:1px solid #e2e8f0; display:flex; gap:8px; justify-content:flex-end; background:#fafbfc; border-radius:0 0 12px 12px; }

.lo-g2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.lo-fg { display:flex; flex-direction:column; gap:3px; margin-bottom:10px; }
.lo-fg label { font-size:11px; font-weight:700; color:#374151; }
.lo-fg label .opt { font-size:9.5px; color:#94a3b8; font-weight:400; }
.lo-fg input,.lo-fg select,.lo-fg textarea { border:1.5px solid #e2e8f0; border-radius:7px; padding:0 10px; font-size:12.5px; font-family:inherit; background:#f8fafc; color:#111827; transition:border-color .15s; width:100%; }
.lo-fg input,.lo-fg select { height:36px; }
.lo-fg textarea { padding:8px 10px; resize:vertical; min-height:56px; }
.lo-fg input:focus,.lo-fg select:focus,.lo-fg textarea:focus { outline:none; border-color:#00c896; background:#fff; box-shadow:0 0 0 3px rgba(0,200,150,.09); }

.lo-sec-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#64748b; margin:14px 0 10px; padding-bottom:6px; border-bottom:1px solid #f0f2f7; display:flex; align-items:center; gap:6px; }
.lo-sec-label:first-child { margin-top:0; }
.lo-sec-label i { color:#00c896; }

.lo-map-preview { width:100%; height:200px; border-radius:8px; border:1px solid #e2e8f0; overflow:hidden; margin-bottom:10px; background:#f0f9ff; position:relative; }
.lo-map-preview iframe { width:100%; height:100%; border:none; }
.lo-map-placeholder { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; font-size:12px; color:#94a3b8; }
.lo-map-placeholder i { font-size:28px; color:#cbd5e1; }

.lo-radius-preview { display:flex; align-items:center; gap:10px; margin-top:6px; padding:8px 10px; background:#f0fdf9; border-radius:6px; border:1px solid #a7f3d0; }
.lo-radius-circle { width:36px; height:36px; border-radius:50%; border:2.5px solid #00c896; display:flex; align-items:center; justify-content:center; font-size:10px; color:#00c896; font-weight:700; flex-shrink:0; transition:all .2s; }
.lo-radius-info { font-size:11px; color:#064e3b; font-weight:600; }
.lo-radius-info span { color:#94a3b8; font-weight:400; }

.lo-btn-gps { width:100%; height:36px; border-radius:7px; border:1.5px solid #00c896; background:#f0fdf9; color:#059669; font-size:12px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; font-family:inherit; transition:all .12s; margin-bottom:10px; }
.lo-btn-gps:hover { background:#00c896; color:#fff; }

.lo-toast { position:fixed; bottom:20px; left:50%; transform:translateX(-50%); padding:10px 18px; border-radius:9px; background:#0d1b2e; color:#fff; font-size:13px; font-weight:600; z-index:99999; display:none; align-items:center; gap:7px; box-shadow:0 6px 24px rgba(0,0,0,.25); max-width:90vw; animation:loToast .2s ease; }
.lo-toast.ok  i { color:#00e5b0; }
.lo-toast.err i { color:#fca5a5; }
@keyframes loToast { from{opacity:0;transform:translateX(-50%) translateY(8px);}to{opacity:1;transform:translateX(-50%);} }

@media(max-width:560px){ .lo-g2 { grid-template-columns:1fr; } }
</style>

<div class="page-header">
    <h4><i class="fa fa-map-marker-alt text-primary"></i> &nbsp;Lokasi Absen</h4>
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span>
        <span class="cur">Lokasi Absen</span>
    </div>
</div>

<div class="content lo">
    <?= showFlash() ?>

    <!-- INFO STRIP -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:38px;height:38px;border-radius:9px;background:#f0fdf9;border:1px solid #d1fae5;display:flex;align-items:center;justify-content:center;">
                <i class="fa fa-map-marker-alt" style="color:#00c896;font-size:15px;"></i>
            </div>
            <div>
                <div style="font-size:13px;font-weight:700;color:#0f172a;">Manajemen Lokasi Absen</div>
                <div style="font-size:11px;color:#94a3b8;">Daftarkan lokasi & radius yang diizinkan saat karyawan absen kamera</div>
            </div>
        </div>
        <div style="margin-left:auto;display:flex;gap:10px;align-items:center;">
            <div style="text-align:center;padding:0 14px;border-right:1px solid #e2e8f0;">
                <div style="font-size:20px;font-weight:800;color:#0f172a;font-family:monospace;"><?= count($lokasi_list) ?></div>
                <div style="font-size:10px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px;">Total</div>
            </div>
            <div style="text-align:center;padding:0 14px;">
                <div style="font-size:20px;font-weight:800;color:#10b981;font-family:monospace;"><?= count(array_filter($lokasi_list, fn($l)=>$l['status']==='aktif')) ?></div>
                <div style="font-size:10px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px;">Aktif</div>
            </div>
        </div>
    </div>

    <?php if (empty($lokasi_list)): ?>
    <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-start;">
        <i class="fa fa-triangle-exclamation" style="color:#f97316;margin-top:1px;flex-shrink:0;"></i>
        <div>
            <div style="font-size:12.5px;font-weight:700;color:#c2410c;">Belum ada lokasi terdaftar</div>
            <div style="font-size:11.5px;color:#9a3412;margin-top:2px;">
                Selama belum ada lokasi aktif, karyawan bisa absen dari mana saja.
                Tambahkan lokasi kantor untuk membatasi area absen.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- KARTU LOKASI -->
    <div class="lo-grid">
        <?php foreach ($lokasi_list as $lok): ?>
        <div class="lo-card <?= $lok['status']==='nonaktif'?'nonaktif':'' ?>">
            <div class="lo-card-top" style="background:<?= $lok['status']==='aktif'?'#00c896':'#94a3b8' ?>;"></div>
            <div class="lo-card-body">
                <div class="lo-card-header">
                    <div class="lo-icon">📍</div>
                    <div style="flex:1;min-width:0;">
                        <div class="lo-card-nama"><?= htmlspecialchars($lok['nama']) ?></div>
                        <?php if ($lok['alamat']): ?>
                        <div class="lo-card-alamat"><?= htmlspecialchars($lok['alamat']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="lo-badge <?= $lok['status'] ?>"><?= $lok['status']==='aktif'?'● Aktif':'○ Nonaktif' ?></span>
                </div>

                <div class="lo-coord-row" onclick="copyCoord('<?= $lok['lat'] ?>,<?= $lok['lon'] ?>')" title="Klik untuk salin koordinat">
                    <i class="fa fa-crosshairs" style="font-size:11px;color:#0369a1;flex-shrink:0;"></i>
                    <span class="lo-coord-val"><?= number_format($lok['lat'],6) ?>, <?= number_format($lok['lon'],6) ?></span>
                    <span class="lo-coord-copy"><i class="fa fa-copy"></i> Salin</span>
                </div>

                <div class="lo-meta-row">
                    <div class="lo-meta-item">
                        <i class="fa fa-circle-dot"></i>
                        <span>Radius <strong><?= $lok['radius'] ?> m</strong></span>
                    </div>
                </div>

                <?php if ($lok['keterangan']): ?>
                <div style="font-size:11px;color:#94a3b8;font-style:italic;margin-bottom:6px;"><?= htmlspecialchars($lok['keterangan']) ?></div>
                <?php endif; ?>

                <a href="https://www.google.com/maps?q=<?= $lok['lat'] ?>,<?= $lok['lon'] ?>" target="_blank"
                   style="font-size:10.5px;color:#0369a1;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                    <i class="fa fa-map" style="font-size:9px;"></i> Lihat di Google Maps
                </a>
            </div>
            <div class="lo-card-foot">
                <button type="button" class="btn btn-sm btn-primary btn-edit-lokasi"
                    data-id="<?= $lok['id'] ?>"
                    data-nama="<?= htmlspecialchars($lok['nama']) ?>"
                    data-alamat="<?= htmlspecialchars($lok['alamat']??'') ?>"
                    data-lat="<?= $lok['lat'] ?>"
                    data-lon="<?= $lok['lon'] ?>"
                    data-radius="<?= $lok['radius'] ?>"
                    data-status="<?= $lok['status'] ?>"
                    data-keterangan="<?= htmlspecialchars($lok['keterangan']??'') ?>">
                    <i class="fa fa-pen"></i> Edit
                </button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $lok['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-default" title="Toggle status">
                        <i class="fa <?= $lok['status']==='aktif'?'fa-eye-slash':'fa-eye' ?>"></i>
                    </button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus lokasi <?= addslashes(htmlspecialchars($lok['nama'])) ?>?')">
                    <input type="hidden" name="action" value="hapus">
                    <input type="hidden" name="id" value="<?= $lok['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="lo-add-card" id="btn-add-lokasi">
            <i class="fa fa-plus-circle"></i>
            <span>Tambah Lokasi Baru</span>
        </div>
    </div>

    <!-- TABEL -->
    <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-table text-primary"></i> Daftar Lokasi</h5></div>
        <div class="panel-bd np tbl-wrap">
            <?php if (empty($lokasi_list)): ?>
            <div style="text-align:center;padding:32px;color:#94a3b8;font-size:13px;">
                <i class="fa fa-map-marker-alt" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                Belum ada lokasi terdaftar
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nama Lokasi</th>
                        <th>Alamat</th>
                        <th>Koordinat (lat, lon)</th>
                        <th>Radius</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lokasi_list as $lok): ?>
                <tr>
                    <td style="font-weight:600;font-size:12.5px;">📍 <?= htmlspecialchars($lok['nama']) ?></td>
                    <td style="font-size:12px;color:#64748b;"><?= htmlspecialchars($lok['alamat']??'—') ?></td>
                    <td style="font-family:monospace;font-size:11px;color:#0369a1;">
                        <?= number_format($lok['lat'],6) ?>, <?= number_format($lok['lon'],6) ?>
                    </td>
                    <td style="font-size:12px;font-weight:700;"><?= $lok['radius'] ?> m</td>
                    <td><span class="lo-badge <?= $lok['status'] ?>"><?= $lok['status'] ?></span></td>
                    <td>
                        <a href="https://www.google.com/maps?q=<?= $lok['lat'] ?>,<?= $lok['lon'] ?>" target="_blank" class="btn btn-sm btn-default" style="font-size:10px;" title="Buka di Google Maps">
                            <i class="fa fa-map"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL FORM -->
<div id="lo-modal" class="lo-ov">
    <div class="lo-modal">
        <div class="lo-mhd">
            <div class="lo-mhd-ico"><i class="fa fa-map-marker-alt"></i></div>
            <div>
                <div class="lo-mhd-title" id="lo-mtitle">Tambah Lokasi Baru</div>
                <div class="lo-mhd-sub" id="lo-msub">Isi koordinat dan radius lokasi absen</div>
            </div>
            <button type="button" class="lo-close" id="lo-close"><i class="fa fa-times"></i></button>
        </div>

        <form method="POST" id="lo-form">
            <input type="hidden" name="action" id="lo-action" value="simpan">
            <input type="hidden" name="id" id="lo-id" value="">

            <div class="lo-mbody">
                <!-- Preview peta -->
                <div class="lo-map-preview">
                    <div class="lo-map-placeholder" id="lo-map-ph">
                        <i class="fa fa-map-location-dot"></i>
                        <span>Masukkan koordinat untuk melihat peta</span>
                    </div>
                    <iframe id="lo-map-iframe" style="display:none;width:100%;height:100%;border:none;"></iframe>
                </div>

                <!-- GPS button -->
                <button type="button" class="lo-btn-gps" id="btn-get-gps">
                    <i class="fa fa-crosshairs"></i> Gunakan Lokasi Saya Saat Ini
                </button>

                <!-- Identitas -->
                <div class="lo-sec-label"><i class="fa fa-tag"></i> Identitas Lokasi</div>
                <div class="lo-fg">
                    <label>Nama Lokasi <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="nama" id="lo-nama" placeholder="Kantor Pusat / Cabang A" required>
                </div>
                <div class="lo-fg">
                    <label>Alamat <span class="opt">(opsional)</span></label>
                    <textarea name="alamat" id="lo-alamat" placeholder="Jl. Contoh No.1, Kota…"></textarea>
                </div>

                <!-- Koordinat -->
                <div class="lo-sec-label"><i class="fa fa-crosshairs"></i> Koordinat GPS</div>
                <div class="lo-g2">
                    <div class="lo-fg">
                        <label>Latitude <span style="color:#ef4444;">*</span> <span class="opt">(-90 s/d 90)</span></label>
                        <input type="number" name="lat" id="lo-lat" placeholder="-6.200000" step="0.0000001" required>
                    </div>
                    <div class="lo-fg">
                        <label>Longitude <span style="color:#ef4444;">*</span> <span class="opt">(-180 s/d 180)</span></label>
                        <input type="number" name="lon" id="lo-lon" placeholder="106.816666" step="0.0000001" required>
                    </div>
                </div>
                <div style="font-size:10.5px;color:#94a3b8;margin-top:-6px;margin-bottom:10px;">
                    <i class="fa fa-lightbulb" style="color:#f59e0b;margin-right:3px;"></i>
                    Cara mudah: buka Google Maps → klik kanan lokasi → salin koordinat
                </div>

                <!-- Radius & Aturan -->
                <div class="lo-sec-label"><i class="fa fa-circle-dot"></i> Radius & Aturan</div>
                <div class="lo-g2">
                    <div class="lo-fg">
                        <label>Radius <span class="opt">(meter)</span></label>
                        <input type="number" name="radius" id="lo-radius" value="100" min="10" max="5000">
                    </div>
                    <div class="lo-fg">
                        <label>Status</label>
                        <select name="status" id="lo-status">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="lo-radius-preview">
                    <div class="lo-radius-circle" id="lo-radius-circle">100m</div>
                    <div class="lo-radius-info">
                        Radius <strong id="lo-radius-disp">100</strong> meter dari titik lokasi.<br>
                        <span>Absen ditolak jika karyawan berada di luar area ini.</span>
                    </div>
                </div>

                <!-- Pengaturan -->
                <div class="lo-sec-label" style="margin-top:14px;"><i class="fa fa-sliders"></i> Pengaturan</div>
                <div class="lo-fg">
                    <label>Keterangan <span class="opt">(opsional)</span></label>
                    <input type="text" name="keterangan" id="lo-keterangan" placeholder="Catatan tambahan">
                </div>
            </div>

            <div class="lo-mft">
                <button type="button" class="btn btn-default" id="lo-cancel"><i class="fa fa-times"></i> Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Lokasi</button>
            </div>
        </form>
    </div>
</div>

<div class="lo-toast" id="lo-toast"><i></i><span></span></div>

<script>
(function(){
    var modal = document.getElementById('lo-modal');
    var form  = document.getElementById('lo-form');
    var latEl = document.getElementById('lo-lat');
    var lonEl = document.getElementById('lo-lon');

    function openModal(data) {
        form.reset();
        var isEdit = !!(data && data.id);
        setVal('lo-action', isEdit ? 'update' : 'simpan');
        setVal('lo-id',     isEdit ? data.id : '');
        document.getElementById('lo-mtitle').textContent = isEdit ? 'Edit Lokasi' : 'Tambah Lokasi Baru';
        document.getElementById('lo-msub').textContent   = isEdit ? 'Perbarui data lokasi absen' : 'Isi koordinat dan radius lokasi';
        if (isEdit) {
            setVal('lo-nama',       data.nama);
            setVal('lo-alamat',     data.alamat);
            setVal('lo-lat',        data.lat);
            setVal('lo-lon',        data.lon);
            setVal('lo-radius',     data.radius);
            setVal('lo-status',     data.status);
            setVal('lo-keterangan', data.keterangan);
            updateMap(data.lat, data.lon);
        } else {
            resetMap();
        }
        updateRadiusPreview();
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
    function setVal(id, val) {
        var el = document.getElementById(id);
        if (el) el.value = val || '';
    }

    document.getElementById('lo-close').onclick  = closeModal;
    document.getElementById('lo-cancel').onclick = closeModal;
    modal.addEventListener('click', function(e){ if(e.target===modal) closeModal(); });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape'&&modal.classList.contains('open')) closeModal(); });
    document.getElementById('btn-add-lokasi').onclick = function(){ openModal({}); };
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.btn-edit-lokasi');
        if (!btn) return;
        openModal({ id:btn.dataset.id, nama:btn.dataset.nama, alamat:btn.dataset.alamat,
            lat:btn.dataset.lat, lon:btn.dataset.lon, radius:btn.dataset.radius,
            status:btn.dataset.status, keterangan:btn.dataset.keterangan });
    });

    /* Peta preview */
    function updateMap(lat, lon) {
        if (!lat || !lon || isNaN(lat) || isNaN(lon)) { resetMap(); return; }
        var fLat = parseFloat(lat), fLon = parseFloat(lon);
        if (fLat < -90 || fLat > 90 || fLon < -180 || fLon > 180) { resetMap(); return; }
        var url = 'https://www.openstreetmap.org/export/embed.html?bbox='+
            (fLon-0.003)+','+(fLat-0.003)+','+(fLon+0.003)+','+(fLat+0.003)+
            '&layer=mapnik&marker='+fLat+','+fLon;
        var iframe = document.getElementById('lo-map-iframe');
        iframe.src = url;
        iframe.style.display = 'block';
        document.getElementById('lo-map-ph').style.display = 'none';
    }
    function resetMap() {
        document.getElementById('lo-map-iframe').style.display = 'none';
        document.getElementById('lo-map-ph').style.display     = 'flex';
    }
    var mapTimer;
    latEl.addEventListener('input', function(){ clearTimeout(mapTimer); mapTimer=setTimeout(function(){ updateMap(latEl.value,lonEl.value); },800); });
    lonEl.addEventListener('input', function(){ clearTimeout(mapTimer); mapTimer=setTimeout(function(){ updateMap(latEl.value,lonEl.value); },800); });

    /* Radius preview */
    function updateRadiusPreview() {
        var r = parseInt(document.getElementById('lo-radius').value)||100;
        var circ = document.getElementById('lo-radius-circle');
        circ.textContent = r>=1000?(r/1000).toFixed(1)+'km':r+'m';
        document.getElementById('lo-radius-disp').textContent = r;
    }
    document.getElementById('lo-radius').addEventListener('input', updateRadiusPreview);

    /* GPS button */
    document.getElementById('btn-get-gps').addEventListener('click', function(){
        var btn = this;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Mendapatkan lokasi…';
        btn.disabled  = true;
        if (!navigator.geolocation) {
            showToast('GPS tidak didukung browser ini','err');
            btn.innerHTML='<i class="fa fa-crosshairs"></i> Gunakan Lokasi Saya Saat Ini';
            btn.disabled=false; return;
        }
        navigator.geolocation.getCurrentPosition(function(pos){
            latEl.value = pos.coords.latitude.toFixed(7);
            lonEl.value = pos.coords.longitude.toFixed(7);
            updateMap(latEl.value, lonEl.value);
            showToast('Lokasi berhasil didapatkan (±'+Math.round(pos.coords.accuracy)+'m)','ok');
            btn.innerHTML='<i class="fa fa-check" style="color:#00c896;"></i> Lokasi berhasil diambil';
            setTimeout(function(){ btn.innerHTML='<i class="fa fa-crosshairs"></i> Gunakan Lokasi Saya Saat Ini'; btn.disabled=false; },2500);
        },function(err){
            var msgs={1:'Akses lokasi ditolak.',2:'Sinyal GPS lemah.',3:'Timeout GPS.'};
            showToast(msgs[err.code]||'GPS error','err');
            btn.innerHTML='<i class="fa fa-crosshairs"></i> Gunakan Lokasi Saya Saat Ini';
            btn.disabled=false;
        },{ enableHighAccuracy:true, timeout:10000 });
    });

    window.copyCoord = function(coord) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(coord).then(function(){ showToast('Koordinat disalin: '+coord,'ok'); });
        }
    };

    function showToast(msg, type) {
        var t = document.getElementById('lo-toast');
        t.className = 'lo-toast '+(type||'ok');
        t.querySelector('i').className = 'fa '+(type==='err'?'fa-circle-xmark':'fa-circle-check');
        t.querySelector('span').textContent = msg;
        t.style.display = 'flex';
        clearTimeout(t._t);
        t._t = setTimeout(function(){ t.style.display='none'; }, 3000);
    }

    updateRadiusPreview();
})();
</script>

<?php include '../includes/footer.php'; ?>