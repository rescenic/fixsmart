<?php
// pages/master_shift.php
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'hrd'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Master Shift';
$active_menu = 'master_shift';

// ── POST HANDLER ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if (in_array($act, ['simpan', 'update'])) {
        $id    = (int)($_POST['id'] ?? 0);
        $kode  = strtoupper(trim($_POST['kode'] ?? ''));
        $nama  = trim($_POST['nama'] ?? '');
        $err   = [];
        if (!$kode || strlen($kode) > 10) $err[] = 'Kode shift wajib diisi (maks 10 karakter).';
        if (!$nama)                        $err[] = 'Nama shift wajib diisi.';

        if (!$err) {
            $data = [
                'kode'             => $kode,
                'nama'             => $nama,
                'jam_masuk'        => $_POST['jam_masuk']     ?: '00:00',
                'jam_keluar'       => $_POST['jam_keluar']    ?: '00:00',
                'lintas_hari'      => (int)($_POST['lintas_hari']  ?? 0),
                'toleransi_masuk'  => (int)($_POST['toleransi_masuk']  ?? 15),
                'toleransi_pulang' => (int)($_POST['toleransi_pulang'] ?? 0),
                'durasi_istirahat' => (int)($_POST['durasi_istirahat'] ?? 60),
                'warna'            => $_POST['warna']          ?: '#6366f1',
                'jenis'            => $_POST['jenis']          ?: 'reguler',
                'berlaku_untuk'    => $_POST['berlaku_untuk']  ?: 'semua',
                'deskripsi'        => trim($_POST['deskripsi'] ?? '') ?: null,
                'status'           => $_POST['status']         ?: 'aktif',
                'urutan'           => (int)($_POST['urutan']   ?? 0),
                'updated_by'       => (int)$_SESSION['user_id'],
            ];

            try {
                if ($act === 'update' && $id) {
                    $sets = implode(',', array_map(fn($k) => "`$k`=?", array_keys($data)));
                    $vals = array_values($data);
                    $vals[] = $id;
                    $pdo->prepare("UPDATE master_shift SET $sets WHERE id=?")->execute($vals);
                    setFlash('success', "Shift <strong>".htmlspecialchars($nama)."</strong> diperbarui.");
                } else {
                    $data['created_by'] = (int)$_SESSION['user_id'];
                    $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
                    $phs  = implode(',', array_fill(0, count($data), '?'));
                    $pdo->prepare("INSERT INTO master_shift ($cols) VALUES ($phs)")->execute(array_values($data));
                    setFlash('success', "Shift <strong>".htmlspecialchars($nama)."</strong> berhasil ditambahkan.");
                }
            } catch (Exception $e) {
                setFlash('danger', 'Kode shift sudah digunakan. Gunakan kode lain.');
            }
        } else {
            setFlash('danger', implode('<br>', $err));
        }
        redirect(APP_URL.'/pages/master_shift.php');
    }

    if ($act === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            try {
                // Cek apakah sudah dipakai di jadwal
                $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM jadwal_karyawan WHERE shift_id = ?");
                $stmtCek->execute([$id]);
                $pakai = (int)$stmtCek->fetchColumn();

                if ($pakai > 0) {
                    setFlash('warning', "Shift tidak dapat dihapus karena sudah digunakan di <strong>{$pakai}</strong> jadwal.");
                } else {
                    $pdo->prepare("DELETE FROM master_shift WHERE id = ?")->execute([$id]);
                    setFlash('success', 'Shift berhasil dihapus.');
                }
            } catch (Exception $e) {
                // Jika tabel jadwal_karyawan belum ada, langsung hapus
                try {
                    $pdo->prepare("DELETE FROM master_shift WHERE id = ?")->execute([$id]);
                    setFlash('success', 'Shift berhasil dihapus.');
                } catch (Exception $e2) {
                    setFlash('danger', 'Gagal menghapus shift: ' . $e2->getMessage());
                }
            }
        }
        redirect(APP_URL.'/pages/master_shift.php');
    }

    if ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $pdo->prepare("UPDATE master_shift SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]);
        redirect(APP_URL.'/pages/master_shift.php');
    }
}

// ── FETCH ─────────────────────────────────────────────────
$shifts = $pdo->query("SELECT * FROM master_shift ORDER BY urutan ASC, jenis ASC, jam_masuk ASC")->fetchAll(PDO::FETCH_ASSOC);

$jenis_opts = [
    'pagi'    => ['label'=>'Pagi',      'bg'=>'#f0fdf4','tc'=>'#15803d'],
    'siang'   => ['label'=>'Siang',     'bg'=>'#fef9c3','tc'=>'#a16207'],
    'malam'   => ['label'=>'Malam',     'bg'=>'#eef2ff','tc'=>'#4338ca'],
    'reguler' => ['label'=>'Reguler',   'bg'=>'#e0f2fe','tc'=>'#0369a1'],
    'oncall'  => ['label'=>'On-Call',   'bg'=>'#fee2e2','tc'=>'#b91c1c'],
    'custom'  => ['label'=>'Custom',    'bg'=>'#f3f4f6','tc'=>'#374151'],
];

include '../includes/header.php';
?>

<style>
/* ── Master Shift — clean & structured ── */
* { box-sizing: border-box; }
.ms { font-family: 'Inter', 'Segoe UI', sans-serif; color: #1e293b; }

/* Shift card grid */
.ms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}
.ms-card {
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    transition: box-shadow .15s, transform .15s;
    position: relative;
}
.ms-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.08); transform: translateY(-1px); }
.ms-card.nonaktif { opacity: .55; }

.ms-card-top {
    height: 6px;
    border-radius: 12px 12px 0 0;
}
.ms-card-body { padding: 14px 16px 12px; }

.ms-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}
.ms-kode {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 800;
    color: #fff;
    flex-shrink: 0;
    letter-spacing: -.5px;
}
.ms-card-title { font-size: 13.5px; font-weight: 700; color: #0f172a; line-height: 1.2; }
.ms-card-jenis { font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 4px; margin-top: 2px; display: inline-block; }

/* Jam display */
.ms-jam-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 10px;
}
.ms-jam-val {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    font-family: 'JetBrains Mono', 'Courier New', monospace;
    line-height: 1;
}
.ms-jam-sep { color: #94a3b8; font-size: 18px; font-weight: 400; }
.ms-jam-lbl { font-size: 9px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; text-align: center; margin-top: 2px; }
.ms-jam-col { text-align: center; }
.ms-lintas { display: inline-flex; align-items: center; gap: 3px; font-size: 9.5px; font-weight: 700; color: #6366f1; background: #eef2ff; padding: 2px 7px; border-radius: 4px; margin-left: 4px; }

/* Meta row */
.ms-meta-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
.ms-meta-item { display: flex; align-items: center; gap: 4px; font-size: 10.5px; color: #64748b; font-weight: 500; }
.ms-meta-item i { font-size: 9px; color: #94a3b8; }

/* Footer */
.ms-card-foot {
    display: flex;
    gap: 5px;
    padding: 8px 16px;
    border-top: 1px solid #f1f5f9;
    background: #fafbfc;
    justify-content: flex-end;
}

/* Status badge */
.ms-status { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; }
.ms-status.aktif    { background: #dcfce7; color: #15803d; }
.ms-status.nonaktif { background: #f1f5f9; color: #64748b; }

/* Add card */
.ms-add-card {
    background: #fff;
    border: 1.5px dashed #cbd5e1;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-direction: column;
    gap: 8px;
    padding: 32px 16px;
    cursor: pointer;
    transition: all .15s;
    color: #94a3b8;
    min-height: 200px;
    text-decoration: none;
}
.ms-add-card:hover { border-color: #00c896; color: #00c896; background: #f0fdf9; }
.ms-add-card i { font-size: 24px; }
.ms-add-card span { font-size: 12.5px; font-weight: 600; }

/* Empty state */
.ms-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 48px 20px;
    color: #94a3b8;
}
.ms-empty i { font-size: 40px; margin-bottom: 12px; display: block; }
.ms-empty p { font-size: 13px; font-weight: 500; margin: 0 0 4px; color: #64748b; }
.ms-empty span { font-size: 11px; }

/* ── MODAL ── */
.ms-ov {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.55); z-index: 99999;
    align-items: flex-start; justify-content: center;
    padding: 20px 16px;
    backdrop-filter: blur(2px);
    overflow-y: auto;
}
.ms-ov.open { display: flex; }
.ms-modal {
    background: #fff; border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    width: 100%; max-width: 560px;
    display: flex; flex-direction: column;
    animation: msIn .2s ease;
    overflow: visible;
    margin: auto;
}
@keyframes msIn { from { opacity:0; transform:translateY(14px) scale(.97); } to { opacity:1; transform:none; } }

.ms-mhd {
    background: linear-gradient(130deg, #0d1b2e, #163354);
    padding: 14px 18px;
    display: flex; align-items: center; gap: 12px; color: #fff; flex-shrink: 0;
    border-radius: 12px 12px 0 0;
}
.ms-mhd-ico { width: 36px; height: 36px; border-radius: 9px; background: rgba(255,255,255,.1); display: flex; align-items: center; justify-content: center; }
.ms-mhd-ico i { color: #00c896; font-size: 14px; }
.ms-mhd-title { font-size: 14px; font-weight: 700; }
.ms-mhd-sub   { font-size: 11px; color: rgba(255,255,255,.45); margin-top: 1px; }
.ms-close { margin-left: auto; width: 28px; height: 28px; border-radius: 50%; background: rgba(255,255,255,.1); border: none; color: #fff; cursor: pointer; font-size: 11px; display: flex; align-items: center; justify-content: center; transition: background .12s; }
.ms-close:hover { background: #ef4444; }

.ms-mbody { padding: 18px 20px; overflow: visible; }

.ms-mft { padding: 12px 18px; border-top: 1px solid #e2e8f0; display: flex; gap: 8px; justify-content: flex-end; background: #fafbfc; flex-shrink: 0; border-radius: 0 0 12px 12px; }

/* Form */
.ms-g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.ms-g3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
.ms-fg { display: flex; flex-direction: column; gap: 3px; margin-bottom: 10px; }
.ms-fg label { font-size: 11px; font-weight: 700; color: #374151; }
.ms-fg label .opt { font-size: 9.5px; color: #94a3b8; font-weight: 400; }
.ms-fg input, .ms-fg select, .ms-fg textarea {
    border: 1.5px solid #e2e8f0; border-radius: 7px;
    padding: 0 10px; font-size: 12.5px; font-family: inherit;
    background: #f8fafc; color: #111827;
    transition: border-color .15s, background .15s;
    width: 100%;
}
.ms-fg input, .ms-fg select { height: 36px; }
.ms-fg textarea { padding: 8px 10px; resize: vertical; min-height: 56px; }
.ms-fg input:focus, .ms-fg select:focus, .ms-fg textarea:focus {
    outline: none; border-color: #00c896; background: #fff;
    box-shadow: 0 0 0 3px rgba(0,200,150,.09);
}
.ms-sec-label {
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px;
    color: #64748b; margin: 14px 0 10px;
    padding-bottom: 6px; border-bottom: 1px solid #f0f2f7;
    display: flex; align-items: center; gap: 6px;
}
.ms-sec-label:first-child { margin-top: 0; }
.ms-sec-label i { color: #00c896; }

/* Color picker row */
.ms-colors { display: flex; gap: 7px; flex-wrap: wrap; margin-top: 4px; }
.ms-color-opt { width: 28px; height: 28px; border-radius: 7px; cursor: pointer; border: 2.5px solid transparent; transition: transform .12s, border-color .12s; flex-shrink: 0; }
.ms-color-opt:hover { transform: scale(1.15); }
.ms-color-opt.selected { border-color: #0f172a; transform: scale(1.12); }

/* Preview jam */
.ms-preview-jam {
    display: flex; align-items: center; justify-content: center; gap: 10px;
    padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 8px; margin-bottom: 12px;
}
.ms-preview-jam .jam { font-size: 22px; font-weight: 800; font-family: 'JetBrains Mono', monospace; }
.ms-preview-jam .sep { color: #94a3b8; font-size: 18px; }

/* durasi calc */
.ms-durasi-info { font-size: 11px; color: #6366f1; font-weight: 600; display: flex; align-items: center; gap: 4px; margin-top: 4px; }

@media (max-width: 560px) {
    .ms-g2, .ms-g3 { grid-template-columns: 1fr; }
    .ms-ov { padding: 12px; }
    .ms-modal { border-radius: 10px; }
}
</style>

<div class="page-header">
    <h4><i class="fa fa-clock text-primary"></i> &nbsp;Master Shift</h4>
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span>
        <a href="<?= APP_URL ?>/pages/master_karyawan.php">SDM</a><span class="sep">/</span>
        <span class="cur">Master Shift</span>
    </div>
</div>

<div class="content ms">
    <?= showFlash() ?>

    <!-- INFO STRIP -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:38px;height:38px;border-radius:9px;background:#f0fdf9;border:1px solid #d1fae5;display:flex;align-items:center;justify-content:center;">
                <i class="fa fa-clock" style="color:#00c896;font-size:15px;"></i>
            </div>
            <div>
                <div style="font-size:13px;font-weight:700;color:#0f172a;">Manajemen Shift Kerja</div>
                <div style="font-size:11px;color:#94a3b8;">Atur jam, toleransi, dan kode warna untuk setiap shift</div>
            </div>
        </div>
        <div style="margin-left:auto;display:flex;gap:10px;align-items:center;">
            <div style="text-align:center;padding:0 14px;border-right:1px solid #e2e8f0;">
                <div style="font-size:20px;font-weight:800;color:#0f172a;font-family:monospace;"><?= count($shifts) ?></div>
                <div style="font-size:10px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px;">Total Shift</div>
            </div>
            <div style="text-align:center;padding:0 14px;">
                <div style="font-size:20px;font-weight:800;color:#10b981;font-family:monospace;"><?= count(array_filter($shifts, fn($s) => $s['status'] === 'aktif')) ?></div>
                <div style="font-size:10px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px;">Aktif</div>
            </div>
        </div>
    </div>

    <!-- SHIFT CARDS -->
    <div class="ms-grid">
        <?php if (empty($shifts)): ?>
        <div class="ms-empty">
            <i class="fa fa-clock"></i>
            <p>Belum ada data shift</p>
            <span>Klik tombol di bawah untuk menambahkan shift pertama</span>
        </div>
        <?php endif; ?>

        <?php foreach ($shifts as $sh):
            $ji = $jenis_opts[$sh['jenis']] ?? $jenis_opts['custom'];
            // Hitung durasi
            $mi = strtotime($sh['jam_masuk']);
            $mk = strtotime($sh['jam_keluar']);
            if ($sh['lintas_hari'] && $mk <= $mi) $mk += 86400;
            $dur = $sh['jenis'] === 'oncall' ? '—' : gmdate('G\j i\m', $mk - $mi);
        ?>
        <div class="ms-card <?= $sh['status'] === 'nonaktif' ? 'nonaktif' : '' ?>">
            <div class="ms-card-top" style="background:<?= htmlspecialchars($sh['warna']) ?>;"></div>
            <div class="ms-card-body">
                <div class="ms-card-header">
                    <div class="ms-kode" style="background:<?= htmlspecialchars($sh['warna']) ?>;">
                        <?= htmlspecialchars($sh['kode']) ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="ms-card-title"><?= htmlspecialchars($sh['nama']) ?></div>
                        <span class="ms-card-jenis" style="background:<?= $ji['bg'] ?>;color:<?= $ji['tc'] ?>;">
                            <?= $ji['label'] ?>
                        </span>
                        <?php if ($sh['lintas_hari']): ?>
                        <span class="ms-lintas"><i class="fa fa-moon" style="font-size:8px;"></i>Lintas hari</span>
                        <?php endif; ?>
                    </div>
                    <span class="ms-status <?= $sh['status'] ?>"><?= $sh['status'] === 'aktif' ? '● Aktif' : '○ Nonaktif' ?></span>
                </div>

                <?php if ($sh['jenis'] !== 'oncall'): ?>
                <div class="ms-jam-row">
                    <div class="ms-jam-col">
                        <div class="ms-jam-val"><?= substr($sh['jam_masuk'], 0, 5) ?></div>
                        <div class="ms-jam-lbl">Masuk</div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                        <span class="ms-jam-sep">→</span>
                        <span style="font-size:9px;color:#94a3b8;font-weight:600;"><?= $dur ?></span>
                    </div>
                    <div class="ms-jam-col">
                        <div class="ms-jam-val"><?= substr($sh['jam_keluar'], 0, 5) ?></div>
                        <div class="ms-jam-lbl">Keluar</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="ms-jam-row" style="justify-content:center;">
                    <i class="fa fa-phone-volume" style="color:#ef4444;font-size:18px;margin-right:8px;"></i>
                    <span style="font-size:12px;font-weight:600;color:#64748b;">Siaga / On-Call 24 jam</span>
                </div>
                <?php endif; ?>

                <div class="ms-meta-row">
                    <div class="ms-meta-item">
                        <i class="fa fa-hourglass-half"></i>
                        <span>Toleransi: +<?= $sh['toleransi_masuk'] ?>m</span>
                    </div>
                    <div class="ms-meta-item">
                        <i class="fa fa-mug-hot"></i>
                        <span>Istirahat: <?= $sh['durasi_istirahat'] ?>m</span>
                    </div>
                    <div class="ms-meta-item">
                        <i class="fa fa-users"></i>
                        <span><?= $sh['berlaku_untuk'] === 'semua' ? 'Semua' : ucfirst($sh['berlaku_untuk']) ?></span>
                    </div>
                </div>

                <?php if ($sh['deskripsi']): ?>
                <div style="font-size:11px;color:#94a3b8;font-style:italic;margin-bottom:6px;"><?= htmlspecialchars($sh['deskripsi']) ?></div>
                <?php endif; ?>
            </div>
            <div class="ms-card-foot">
                <button type="button" class="btn btn-sm btn-primary btn-edit-shift"
                    data-id="<?= $sh['id'] ?>"
                    data-kode="<?= htmlspecialchars($sh['kode']) ?>"
                    data-nama="<?= htmlspecialchars($sh['nama']) ?>"
                    data-jam_masuk="<?= $sh['jam_masuk'] ?>"
                    data-jam_keluar="<?= $sh['jam_keluar'] ?>"
                    data-lintas_hari="<?= $sh['lintas_hari'] ?>"
                    data-toleransi_masuk="<?= $sh['toleransi_masuk'] ?>"
                    data-toleransi_pulang="<?= $sh['toleransi_pulang'] ?>"
                    data-durasi_istirahat="<?= $sh['durasi_istirahat'] ?>"
                    data-warna="<?= htmlspecialchars($sh['warna']) ?>"
                    data-jenis="<?= $sh['jenis'] ?>"
                    data-berlaku_untuk="<?= $sh['berlaku_untuk'] ?>"
                    data-deskripsi="<?= htmlspecialchars($sh['deskripsi'] ?? '') ?>"
                    data-status="<?= $sh['status'] ?>"
                    data-urutan="<?= $sh['urutan'] ?>">
                    <i class="fa fa-pen"></i> Edit
                </button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $sh['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-default" title="Toggle status">
                        <i class="fa <?= $sh['status'] === 'aktif' ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                    </button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus shift <?= addslashes(htmlspecialchars($sh['nama'])) ?>?')">
                    <input type="hidden" name="action" value="hapus">
                    <input type="hidden" name="id" value="<?= $sh['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Hapus"><i class="fa fa-trash"></i></button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Tambah baru -->
        <div class="ms-add-card" id="btn-add-shift">
            <i class="fa fa-plus-circle"></i>
            <span>Tambah Shift Baru</span>
        </div>
    </div>

    <!-- TABEL ringkas -->
    <div class="panel">
        <div class="panel-hd">
            <h5><i class="fa fa-table text-primary"></i> Ringkasan Shift</h5>
        </div>
        <div class="panel-bd np tbl-wrap">
            <?php if (empty($shifts)): ?>
            <div style="text-align:center;padding:32px;color:#94a3b8;font-size:13px;">
                <i class="fa fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                Belum ada data shift
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width:50px;">Kode</th>
                        <th>Nama Shift</th>
                        <th>Jam Masuk</th>
                        <th>Jam Keluar</th>
                        <th>Durasi</th>
                        <th>Toleransi</th>
                        <th>Jenis</th>
                        <th>Berlaku Untuk</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($shifts as $sh):
                    $ji = $jenis_opts[$sh['jenis']] ?? $jenis_opts['custom'];
                    $mi = strtotime($sh['jam_masuk']);
                    $mk = strtotime($sh['jam_keluar']);
                    if ($sh['lintas_hari'] && $mk <= $mi) $mk += 86400;
                    $dur_mnt = ($mk - $mi) / 60;
                    $dur_str = $sh['jenis'] === 'oncall' ? '—' : floor($dur_mnt/60).'j '.($dur_mnt%60).'m';
                ?>
                <tr>
                    <td>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;background:<?= htmlspecialchars($sh['warna']) ?>;color:#fff;font-size:10px;font-weight:800;">
                            <?= htmlspecialchars($sh['kode']) ?>
                        </span>
                    </td>
                    <td style="font-weight:600;font-size:12.5px;"><?= htmlspecialchars($sh['nama']) ?></td>
                    <td style="font-family:monospace;font-size:12px;"><?= $sh['jenis']==='oncall'?'—':substr($sh['jam_masuk'],0,5) ?></td>
                    <td style="font-family:monospace;font-size:12px;"><?= $sh['jenis']==='oncall'?'—':substr($sh['jam_keluar'],0,5) ?><?= $sh['lintas_hari']?' <span style="font-size:9px;color:#6366f1;font-family:sans-serif;">+1</span>':'' ?></td>
                    <td style="font-size:12px;"><?= $dur_str ?></td>
                    <td style="font-size:12px;">+<?= $sh['toleransi_masuk'] ?> menit</td>
                    <td><span style="font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:4px;background:<?= $ji['bg'] ?>;color:<?= $ji['tc'] ?>;"><?= $ji['label'] ?></span></td>
                    <td style="font-size:12px;"><?= ucfirst($sh['berlaku_untuk']) ?></td>
                    <td><span style="font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:4px;background:<?= $sh['status']==='aktif'?'#dcfce7':'#f1f5f9' ?>;color:<?= $sh['status']==='aktif'?'#15803d':'#64748b' ?>;"><?= $sh['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.content -->

<!-- ══ MODAL FORM SHIFT ══ -->
<div id="ms-modal" class="ms-ov">
    <div class="ms-modal">
        <div class="ms-mhd">
            <div class="ms-mhd-ico"><i class="fa fa-clock"></i></div>
            <div>
                <div class="ms-mhd-title" id="ms-mtitle">Tambah Shift Baru</div>
                <div class="ms-mhd-sub" id="ms-msub">Isi detail jam kerja dan konfigurasi shift</div>
            </div>
            <button type="button" class="ms-close" id="ms-close"><i class="fa fa-times"></i></button>
        </div>

        <form method="POST" id="ms-form">
            <input type="hidden" name="action" id="ms-action" value="simpan">
            <input type="hidden" name="id" id="ms-id" value="">

            <div class="ms-mbody">
                <!-- Preview -->
                <div class="ms-preview-jam" id="ms-preview">
                    <div style="text-align:center;">
                        <div class="jam" id="prev-masuk" style="color:#10b981;">07:00</div>
                        <div style="font-size:9px;color:#94a3b8;font-weight:600;margin-top:2px;">MASUK</div>
                    </div>
                    <span class="sep">→</span>
                    <div style="text-align:center;">
                        <div class="jam" id="prev-keluar" style="color:#ef4444;">14:00</div>
                        <div style="font-size:9px;color:#94a3b8;font-weight:600;margin-top:2px;">KELUAR</div>
                    </div>
                    <div id="prev-durasi" style="font-size:11px;color:#6366f1;font-weight:700;background:#eef2ff;padding:3px 9px;border-radius:5px;margin-left:6px;">7j 0m</div>
                </div>

                <!-- Identitas -->
                <div class="ms-sec-label"><i class="fa fa-tag"></i> Identitas Shift</div>
                <div class="ms-g2">
                    <div class="ms-fg">
                        <label>Kode Shift <span style="color:#ef4444;">*</span> <span class="opt">(maks 10 huruf)</span></label>
                        <input type="text" name="kode" id="ms-kode" placeholder="P / S / M / R" maxlength="10" style="text-transform:uppercase;" required>
                    </div>
                    <div class="ms-fg">
                        <label>Nama Shift <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="nama" id="ms-nama" placeholder="Shift Pagi" required>
                    </div>
                </div>
                <div class="ms-g2">
                    <div class="ms-fg">
                        <label>Jenis Shift</label>
                        <select name="jenis" id="ms-jenis">
                            <option value="pagi">Pagi</option>
                            <option value="siang">Siang</option>
                            <option value="malam">Malam</option>
                            <option value="reguler" selected>Reguler</option>
                            <option value="oncall">On-Call / Jaga</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="ms-fg">
                        <label>Berlaku Untuk</label>
                        <select name="berlaku_untuk" id="ms-berlaku">
                            <option value="semua">Semua Karyawan</option>
                            <option value="medis">Medis</option>
                            <option value="non-medis">Non-Medis</option>
                        </select>
                    </div>
                </div>

                <!-- Jam -->
                <div class="ms-sec-label"><i class="fa fa-clock"></i> Pengaturan Jam</div>
                <div class="ms-g2">
                    <div class="ms-fg">
                        <label>Jam Masuk <span style="color:#ef4444;">*</span></label>
                        <input type="time" name="jam_masuk" id="ms-jam_masuk" value="07:00">
                    </div>
                    <div class="ms-fg">
                        <label>Jam Keluar <span style="color:#ef4444;">*</span></label>
                        <input type="time" name="jam_keluar" id="ms-jam_keluar" value="14:00">
                    </div>
                </div>
                <div class="ms-fg">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="lintas_hari" id="ms-lintas" value="1" style="width:auto;height:auto;">
                        <span>Shift lintas hari tengah malam <span class="opt">(centang untuk shift malam: 21:00 → 07:00)</span></span>
                    </label>
                    <div id="ms-durasi-info" class="ms-durasi-info" style="display:none;"></div>
                </div>

                <!-- Toleransi -->
                <div class="ms-sec-label"><i class="fa fa-hourglass-half"></i> Toleransi & Istirahat</div>
                <div class="ms-g3">
                    <div class="ms-fg">
                        <label>Toleransi Terlambat <span class="opt">(menit)</span></label>
                        <input type="number" name="toleransi_masuk" id="ms-tol_masuk" value="15" min="0" max="120">
                    </div>
                    <div class="ms-fg">
                        <label>Toleransi Pulang Awal <span class="opt">(menit)</span></label>
                        <input type="number" name="toleransi_pulang" id="ms-tol_pulang" value="0" min="0" max="120">
                    </div>
                    <div class="ms-fg">
                        <label>Durasi Istirahat <span class="opt">(menit)</span></label>
                        <input type="number" name="durasi_istirahat" id="ms-istirahat" value="60" min="0" max="240">
                    </div>
                </div>

                <!-- Warna & Lain -->
                <div class="ms-sec-label"><i class="fa fa-palette"></i> Warna & Tampilan</div>
                <div class="ms-fg">
                    <label>Warna Shift <span class="opt">(untuk kalender jadwal)</span></label>
                    <div class="ms-colors" id="ms-color-btns">
                        <?php foreach (['#10b981','#f59e0b','#6366f1','#0ea5e9','#ef4444','#8b5cf6','#ec4899','#f97316','#14b8a6','#64748b'] as $clr): ?>
                        <div class="ms-color-opt" style="background:<?= $clr ?>;" data-color="<?= $clr ?>" onclick="pickColor('<?= $clr ?>')"></div>
                        <?php endforeach; ?>
                        <input type="color" name="warna" id="ms-warna" value="#6366f1" style="width:28px;height:28px;padding:0;border:1.5px solid #e2e8f0;border-radius:7px;cursor:pointer;background:none;" title="Pilih warna custom">
                    </div>
                </div>
                <div class="ms-g2">
                    <div class="ms-fg">
                        <label>Urutan Tampil</label>
                        <input type="number" name="urutan" id="ms-urutan" value="0" min="0">
                    </div>
                    <div class="ms-fg">
                        <label>Status</label>
                        <select name="status" id="ms-status">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="ms-fg">
                    <label>Deskripsi <span class="opt">(opsional)</span></label>
                    <textarea name="deskripsi" id="ms-deskripsi" placeholder="Keterangan tambahan tentang shift ini…"></textarea>
                </div>
            </div>

            <div class="ms-mft">
                <button type="button" class="btn btn-default" id="ms-cancel"><i class="fa fa-times"></i> Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Shift</button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
    var modal  = document.getElementById('ms-modal');
    var form   = document.getElementById('ms-form');

    /* ── Buka modal ── */
    function openModal(data) {
        form.reset();
        var isEdit = !!data.id;
        document.getElementById('ms-action').value  = isEdit ? 'update' : 'simpan';
        document.getElementById('ms-id').value      = data.id     || '';
        document.getElementById('ms-mtitle').textContent = isEdit ? 'Edit Shift' : 'Tambah Shift Baru';
        document.getElementById('ms-msub').textContent   = isEdit ? 'Perbarui konfigurasi shift' : 'Isi detail jam kerja dan konfigurasi';

        if (isEdit) {
            setVal('ms-kode',      data.kode);
            setVal('ms-nama',      data.nama);
            setVal('ms-jam_masuk', data.jam_masuk ? data.jam_masuk.substring(0,5) : '');
            setVal('ms-jam_keluar',data.jam_keluar? data.jam_keluar.substring(0,5): '');
            document.getElementById('ms-lintas').checked = data.lintas_hari === '1';
            setVal('ms-tol_masuk', data.toleransi_masuk);
            setVal('ms-tol_pulang',data.toleransi_pulang);
            setVal('ms-istirahat', data.durasi_istirahat);
            setVal('ms-warna',     data.warna);
            setVal('ms-jenis',     data.jenis);
            setVal('ms-berlaku',   data.berlaku_untuk);
            setVal('ms-deskripsi', data.deskripsi);
            setVal('ms-status',    data.status);
            setVal('ms-urutan',    data.urutan);
            pickColor(data.warna, false);
        }

        updatePreview();
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

    /* ── Event listeners ── */
    document.getElementById('ms-close').onclick  = closeModal;
    document.getElementById('ms-cancel').onclick = closeModal;
    modal.addEventListener('click', function(e){ if(e.target===modal) closeModal(); });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape'&&modal.classList.contains('open')) closeModal(); });

    /* Tambah baru */
    document.getElementById('btn-add-shift').onclick = function(){ openModal({}); };

    /* Edit */
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.btn-edit-shift');
        if (!btn) return;
        openModal({
            id: btn.dataset.id, kode: btn.dataset.kode, nama: btn.dataset.nama,
            jam_masuk: btn.dataset.jam_masuk, jam_keluar: btn.dataset.jam_keluar,
            lintas_hari: btn.dataset.lintas_hari, toleransi_masuk: btn.dataset.toleransi_masuk,
            toleransi_pulang: btn.dataset.toleransi_pulang, durasi_istirahat: btn.dataset.durasi_istirahat,
            warna: btn.dataset.warna, jenis: btn.dataset.jenis, berlaku_untuk: btn.dataset.berlaku_untuk,
            deskripsi: btn.dataset.deskripsi, status: btn.dataset.status, urutan: btn.dataset.urutan,
        });
    });

    /* ── Preview jam realtime ── */
    function updatePreview() {
        var mi = document.getElementById('ms-jam_masuk').value;
        var mk = document.getElementById('ms-jam_keluar').value;
        var lintas = document.getElementById('ms-lintas').checked;
        document.getElementById('prev-masuk').textContent  = mi || '--:--';
        document.getElementById('prev-keluar').textContent = mk || '--:--';

        if (mi && mk) {
            var tMi = toMin(mi), tMk = toMin(mk);
            if (lintas && tMk <= tMi) tMk += 1440;
            var diff = tMk - tMi;
            if (diff > 0) {
                var h = Math.floor(diff/60), m = diff%60;
                document.getElementById('prev-durasi').textContent = h+'j '+m+'m';
                document.getElementById('ms-durasi-info').style.display='flex';
                document.getElementById('ms-durasi-info').innerHTML='<i class="fa fa-clock" style="font-size:9px;"></i> Durasi kerja: <strong>'+h+' jam '+m+' menit</strong>';
            }
        }
    }
    function toMin(t){ var p=t.split(':'); return parseInt(p[0])*60+parseInt(p[1]); }

    document.getElementById('ms-jam_masuk').oninput  = updatePreview;
    document.getElementById('ms-jam_keluar').oninput = updatePreview;
    document.getElementById('ms-lintas').onchange    = updatePreview;

    /* ── Color picker ── */
    window.pickColor = function(clr, sync) {
        document.querySelectorAll('.ms-color-opt').forEach(function(el){
            el.classList.toggle('selected', el.dataset.color === clr);
        });
        if (sync !== false) document.getElementById('ms-warna').value = clr;
    };
    document.getElementById('ms-warna').oninput = function(){ pickColor(this.value); };

    /* Kode uppercase auto */
    document.getElementById('ms-kode').oninput = function(){ this.value = this.value.toUpperCase(); };

})();
</script>

<?php include '../includes/footer.php'; ?>