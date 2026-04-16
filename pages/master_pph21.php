<?php
// pages/master_pph21.php
ob_start();
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'keuangan'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Master PPh 21 TER';
$active_menu = 'master_pph21';

// ── Auto-create tabel ─────────────────────────────────────────────────────────
try {
    // Tabel kategori PTKP (TER A, TER B, TER C)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `master_pph21_ptkp` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `kode`        VARCHAR(10)  NOT NULL UNIQUE COMMENT 'misal: TER_A, TER_B, TER_C',
        `nama`        VARCHAR(100) NOT NULL COMMENT 'misal: TER A',
        `status_kawin`VARCHAR(200) NOT NULL COMMENT 'misal: TK/0',
        `ptkp_setahun`DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Nilai PTKP per tahun',
        `keterangan`  VARCHAR(255) DEFAULT NULL,
        `aktif`       TINYINT(1)  NOT NULL DEFAULT 1,
        `urutan`      TINYINT     NOT NULL DEFAULT 0,
        `created_by`  INT UNSIGNED DEFAULT NULL,
        `created_at`  DATETIME    DEFAULT NULL,
        `updated_by`  INT UNSIGNED DEFAULT NULL,
        `updated_at`  DATETIME    DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabel tarif TER per rentang penghasilan
    $pdo->exec("CREATE TABLE IF NOT EXISTS `master_pph21_ter` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `ptkp_id`         INT UNSIGNED NOT NULL COMMENT 'FK ke master_pph21_ptkp',
        `penghasilan_dari`DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Batas bawah bruto bulanan',
        `penghasilan_sd`  DECIMAL(15,2) DEFAULT NULL COMMENT 'Batas atas, NULL = tak terbatas',
        `tarif_persen`    DECIMAL(5,2)  NOT NULL DEFAULT 0 COMMENT 'Tarif efektif %',
        `aktif`           TINYINT(1)   NOT NULL DEFAULT 1,
        `created_by`      INT UNSIGNED DEFAULT NULL,
        `created_at`      DATETIME     DEFAULT NULL,
        `updated_by`      INT UNSIGNED DEFAULT NULL,
        `updated_at`      DATETIME     DEFAULT NULL,
        KEY `idx_ptkp` (`ptkp_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed data default TER PMK 168/2023 jika belum ada
    $cek = $pdo->query("SELECT COUNT(*) FROM master_pph21_ptkp")->fetchColumn();
    if ($cek == 0) {
        // Insert kategori PTKP
        $pdo->exec("INSERT INTO master_pph21_ptkp (kode,nama,status_kawin,ptkp_setahun,keterangan,aktif,urutan) VALUES
            ('TER_A','TER A','TK/0',54000000,'Tidak Kawin tanpa tanggungan',1,1),
            ('TER_B','TER B','TK/1, K/0',58500000,'Tidak Kawin 1 tanggungan / Kawin tanpa tanggungan',1,2),
            ('TER_C','TER C','TK/2, TK/3, K/1, K/2, K/3',63000000,'Kawin dengan tanggungan',1,3)");

        // Ambil ID yang baru dibuat
        $idA = $pdo->query("SELECT id FROM master_pph21_ptkp WHERE kode='TER_A'")->fetchColumn();
        $idB = $pdo->query("SELECT id FROM master_pph21_ptkp WHERE kode='TER_B'")->fetchColumn();
        $idC = $pdo->query("SELECT id FROM master_pph21_ptkp WHERE kode='TER_C'")->fetchColumn();

        // Seed TER A (PMK 168/2023)
        $pdo->exec("INSERT INTO master_pph21_ter (ptkp_id,penghasilan_dari,penghasilan_sd,tarif_persen) VALUES
            ($idA,0,5400000,0),
            ($idA,5400000,5650000,0.25),
            ($idA,5650000,5950000,0.50),
            ($idA,5950000,6300000,0.75),
            ($idA,6300000,6750000,1.00),
            ($idA,6750000,7500000,1.25),
            ($idA,7500000,8550000,1.50),
            ($idA,8550000,9650000,2.00),
            ($idA,9650000,10050000,2.50),
            ($idA,10050000,10350000,3.00),
            ($idA,10350000,10700000,3.50),
            ($idA,10700000,11050000,4.00),
            ($idA,11050000,11600000,4.50),
            ($idA,11600000,12500000,5.00),
            ($idA,12500000,13750000,5.50),
            ($idA,13750000,15100000,6.00),
            ($idA,15100000,16950000,7.00),
            ($idA,16950000,19750000,7.50),
            ($idA,19750000,24150000,8.50),
            ($idA,24150000,26450000,9.50),
            ($idA,26450000,28000000,10.00),
            ($idA,28000000,30050000,11.00),
            ($idA,30050000,32400000,12.00),
            ($idA,32400000,35400000,13.00),
            ($idA,35400000,39100000,14.00),
            ($idA,39100000,43850000,15.00),
            ($idA,43850000,47800000,16.00),
            ($idA,47800000,51400000,17.00),
            ($idA,51400000,56300000,18.00),
            ($idA,56300000,62200000,19.00),
            ($idA,62200000,77100000,20.00),
            ($idA,77100000,103600000,21.00),
            ($idA,103600000,134000000,22.00),
            ($idA,134000000,169000000,23.00),
            ($idA,169000000,213000000,24.00),
            ($idA,213000000,337000000,25.00),
            ($idA,337000000,405000000,26.00),
            ($idA,405000000,405000000,27.00),
            ($idA,405000000,NULL,30.00)");

        // Seed TER B (PMK 168/2023)
        $pdo->exec("INSERT INTO master_pph21_ter (ptkp_id,penghasilan_dari,penghasilan_sd,tarif_persen) VALUES
            ($idB,0,6200000,0),
            ($idB,6200000,6500000,0.25),
            ($idB,6500000,6850000,0.50),
            ($idB,6850000,7300000,0.75),
            ($idB,7300000,9200000,1.00),
            ($idB,9200000,10750000,1.50),
            ($idB,10750000,11250000,2.00),
            ($idB,11250000,11600000,2.50),
            ($idB,11600000,12600000,3.00),
            ($idB,12600000,13600000,3.50),
            ($idB,13600000,14950000,4.00),
            ($idB,14950000,16400000,5.00),
            ($idB,16400000,18450000,6.00),
            ($idB,18450000,21850000,7.00),
            ($idB,21850000,26000000,8.00),
            ($idB,26000000,27700000,9.00),
            ($idB,27700000,29350000,10.00),
            ($idB,29350000,31450000,11.00),
            ($idB,31450000,33950000,12.00),
            ($idB,33950000,37100000,13.00),
            ($idB,37100000,41100000,14.00),
            ($idB,41100000,45800000,15.00),
            ($idB,45800000,49500000,16.00),
            ($idB,49500000,53800000,17.00),
            ($idB,53800000,58500000,18.00),
            ($idB,58500000,64000000,19.00),
            ($idB,64000000,71000000,20.00),
            ($idB,71000000,80000000,21.00),
            ($idB,80000000,93000000,22.00),
            ($idB,93000000,133000000,23.00),
            ($idB,133000000,173000000,24.00),
            ($idB,173000000,NULL,25.00)");

        // Seed TER C (PMK 168/2023)
        $pdo->exec("INSERT INTO master_pph21_ter (ptkp_id,penghasilan_dari,penghasilan_sd,tarif_persen) VALUES
            ($idC,0,6600000,0),
            ($idC,6600000,6950000,0.25),
            ($idC,6950000,7350000,0.50),
            ($idC,7350000,7800000,0.75),
            ($idC,7800000,8850000,1.00),
            ($idC,8850000,9800000,1.25),
            ($idC,9800000,10950000,1.50),
            ($idC,10950000,11200000,2.00),
            ($idC,11200000,12050000,2.50),
            ($idC,12050000,12950000,3.00),
            ($idC,12950000,14150000,3.50),
            ($idC,14150000,15550000,4.00),
            ($idC,15550000,17050000,5.00),
            ($idC,17050000,19500000,6.00),
            ($idC,19500000,22700000,7.00),
            ($idC,22700000,26600000,8.00),
            ($idC,26600000,28100000,9.00),
            ($idC,28100000,30100000,10.00),
            ($idC,30100000,32600000,11.00),
            ($idC,32600000,35400000,12.00),
            ($idC,35400000,38900000,13.00),
            ($idC,38900000,43000000,14.00),
            ($idC,43000000,47400000,15.00),
            ($idC,47400000,51200000,16.00),
            ($idC,51200000,55800000,17.00),
            ($idC,55800000,60400000,18.00),
            ($idC,60400000,66700000,19.00),
            ($idC,66700000,74500000,20.00),
            ($idC,74500000,83200000,21.00),
            ($idC,83200000,95600000,22.00),
            ($idC,95600000,110000000,23.00),
            ($idC,110000000,134000000,24.00),
            ($idC,134000000,NULL,25.00)");
    }
} catch (Exception $e) {}

// ── POST HANDLER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    // ── PTKP CRUD ──
    if ($act === 'tambah_ptkp' || $act === 'edit_ptkp') {
        $kode         = strtoupper(trim($_POST['kode'] ?? ''));
        $nama         = trim($_POST['nama'] ?? '');
        $status_kawin = trim($_POST['status_kawin'] ?? '');
        $ptkp_setahun = (float)str_replace([',','.'], ['',''], $_POST['ptkp_setahun'] ?? 0);
        $keterangan   = trim($_POST['keterangan'] ?? '');
        $aktif        = isset($_POST['aktif']) ? 1 : 0;
        $urutan       = (int)($_POST['urutan'] ?? 0);

        if (!$kode || !$nama) {
            setFlash('danger', 'Kode dan Nama wajib diisi.');
        } else {
            try {
                if ($act === 'tambah_ptkp') {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM master_pph21_ptkp WHERE kode=?");
                    $chk->execute([$kode]);
                    if ($chk->fetchColumn() > 0) {
                        setFlash('danger', "Kode <strong>$kode</strong> sudah digunakan.");
                    } else {
                        $pdo->prepare("INSERT INTO master_pph21_ptkp
                            (kode,nama,status_kawin,ptkp_setahun,keterangan,aktif,urutan,created_by,created_at)
                            VALUES (?,?,?,?,?,?,?,?,NOW())")
                            ->execute([$kode,$nama,$status_kawin,$ptkp_setahun,$keterangan,$aktif,$urutan,$_SESSION['user_id']]);
                        setFlash('success', 'Kategori PTKP berhasil ditambahkan.');
                    }
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    $pdo->prepare("UPDATE master_pph21_ptkp
                        SET kode=?,nama=?,status_kawin=?,ptkp_setahun=?,keterangan=?,aktif=?,urutan=?,updated_by=?,updated_at=NOW()
                        WHERE id=?")
                        ->execute([$kode,$nama,$status_kawin,$ptkp_setahun,$keterangan,$aktif,$urutan,$_SESSION['user_id'],$id]);
                    setFlash('success', 'Kategori PTKP berhasil diperbarui.');
                }
            } catch (Exception $e) {
                setFlash('danger', 'Gagal: ' . htmlspecialchars($e->getMessage()));
            }
        }
        redirect(APP_URL . '/pages/master_pph21.php');
    }

    if ($act === 'hapus_ptkp') {
        $id   = (int)($_POST['id'] ?? 0);
        $used = (int)$pdo->prepare("SELECT COUNT(*) FROM master_pph21_ter WHERE ptkp_id=?")->execute([$id]) ? 0 : 0;
        $cu   = $pdo->prepare("SELECT COUNT(*) FROM master_pph21_ter WHERE ptkp_id=?");
        $cu->execute([$id]);
        $used = (int)$cu->fetchColumn();
        if ($used > 0) {
            setFlash('danger', "Tidak bisa dihapus, kategori memiliki $used baris tarif TER.");
        } else {
            $pdo->prepare("DELETE FROM master_pph21_ptkp WHERE id=?")->execute([$id]);
            setFlash('success', 'Kategori PTKP berhasil dihapus.');
        }
        redirect(APP_URL . '/pages/master_pph21.php');
    }

    // ── TER CRUD ──
    if ($act === 'tambah_ter' || $act === 'edit_ter') {
        $ptkp_id         = (int)($_POST['ptkp_id'] ?? 0);
        $penghasilan_dari = (float)str_replace(',', '', $_POST['penghasilan_dari'] ?? 0);
        $penghasilan_sd_raw = trim($_POST['penghasilan_sd'] ?? '');
        $penghasilan_sd  = $penghasilan_sd_raw !== '' ? (float)str_replace(',', '', $penghasilan_sd_raw) : null;
        $tarif_persen    = (float)($_POST['tarif_persen'] ?? 0);
        $aktif           = isset($_POST['aktif']) ? 1 : 0;

        if (!$ptkp_id) {
            setFlash('danger', 'Kategori PTKP wajib dipilih.');
        } else {
            try {
                if ($act === 'tambah_ter') {
                    $pdo->prepare("INSERT INTO master_pph21_ter
                        (ptkp_id,penghasilan_dari,penghasilan_sd,tarif_persen,aktif,created_by,created_at)
                        VALUES (?,?,?,?,?,?,NOW())")
                        ->execute([$ptkp_id,$penghasilan_dari,$penghasilan_sd,$tarif_persen,$aktif,$_SESSION['user_id']]);
                    setFlash('success', 'Baris tarif TER berhasil ditambahkan.');
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    $pdo->prepare("UPDATE master_pph21_ter
                        SET ptkp_id=?,penghasilan_dari=?,penghasilan_sd=?,tarif_persen=?,aktif=?,updated_by=?,updated_at=NOW()
                        WHERE id=?")
                        ->execute([$ptkp_id,$penghasilan_dari,$penghasilan_sd,$tarif_persen,$aktif,$_SESSION['user_id'],$id]);
                    setFlash('success', 'Baris tarif TER berhasil diperbarui.');
                }
            } catch (Exception $e) {
                setFlash('danger', 'Gagal: ' . htmlspecialchars($e->getMessage()));
            }
        }
        $back_ptkp = (int)($_POST['back_ptkp'] ?? 0);
        redirect(APP_URL . '/pages/master_pph21.php' . ($back_ptkp ? '?tab='.$back_ptkp : ''));
    }

    if ($act === 'hapus_ter') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM master_pph21_ter WHERE id=?")->execute([$id]);
        setFlash('success', 'Baris tarif TER berhasil dihapus.');
        $back_ptkp = (int)($_POST['back_ptkp'] ?? 0);
        redirect(APP_URL . '/pages/master_pph21.php' . ($back_ptkp ? '?tab='.$back_ptkp : ''));
    }

    if ($act === 'toggle_ter') {
        $id  = (int)($_POST['id'] ?? 0);
        $val = (int)($_POST['val'] ?? 0);
        $pdo->prepare("UPDATE master_pph21_ter SET aktif=? WHERE id=?")->execute([$val,$id]);
        setFlash('success', 'Status berhasil diubah.');
        $back_ptkp = (int)($_POST['back_ptkp'] ?? 0);
        redirect(APP_URL . '/pages/master_pph21.php' . ($back_ptkp ? '?tab='.$back_ptkp : ''));
    }
}

// ── FETCH DATA ────────────────────────────────────────────────────────────────
$ptkp_list = $pdo->query("SELECT * FROM master_pph21_ptkp ORDER BY urutan ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

$active_tab = (int)($_GET['tab'] ?? ($ptkp_list[0]['id'] ?? 0));

// Tarif per kategori yang sedang aktif ditampilkan
$ter_rows = [];
if ($active_tab) {
    $s = $pdo->prepare("SELECT t.*, p.kode as ptkp_kode FROM master_pph21_ter t
        JOIN master_pph21_ptkp p ON p.id = t.ptkp_id
        WHERE t.ptkp_id=? ORDER BY t.penghasilan_dari ASC");
    $s->execute([$active_tab]);
    $ter_rows = $s->fetchAll(PDO::FETCH_ASSOC);
}

include '../includes/header.php';

function fmt($n) {
    return 'Rp ' . number_format($n, 0, ',', '.');
}
?>

<div class="page-header">
  <h4><i class="fa fa-percent" style="color:#7c3aed;"></i> &nbsp;Master PPh 21 TER</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Master PPh 21 TER</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- INFO BANNER -->
  <div style="background:linear-gradient(135deg,#7c3aed11,#4f46e511);border:1px solid #c4b5fd;border-radius:12px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:flex-start;gap:12px;">
    <i class="fa fa-circle-info" style="color:#7c3aed;font-size:18px;margin-top:2px;"></i>
    <div>
      <div style="font-weight:700;font-size:13px;color:#5b21b6;margin-bottom:3px;">Tarif Efektif Rata-rata (TER) PPh 21 — PMK 168/2023</div>
      <div style="font-size:12px;color:#6d28d9;">PPh 21 dihitung: <strong>Penghasilan Bruto Bulanan × Tarif TER</strong> sesuai kategori status PTKP karyawan. Berlaku mulai Januari 2024.</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:220px 1fr;gap:16px;align-items:flex-start;">

    <!-- SIDEBAR: Daftar Kategori PTKP -->
    <div>
      <div class="panel">
        <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;">
          <h5 style="margin:0;font-size:12px;"><i class="fa fa-layer-group" style="color:#7c3aed;"></i> Kategori PTKP</h5>
          <button onclick="openModal('m-tambah-ptkp')" class="btn btn-sm" style="background:#7c3aed;color:#fff;border:none;font-size:11px;padding:3px 8px;border-radius:6px;">
            <i class="fa fa-plus"></i>
          </button>
        </div>
        <div class="panel-bd" style="padding:6px 0;">
          <?php foreach ($ptkp_list as $p): ?>
          <a href="?tab=<?= $p['id'] ?>" style="display:flex;align-items:center;justify-content:space-between;padding:9px 14px;text-decoration:none;border-left:3px solid <?= $p['id']==$active_tab ? '#7c3aed' : 'transparent' ?>;background:<?= $p['id']==$active_tab ? '#f5f3ff' : 'transparent' ?>;transition:all .15s;">
            <div>
              <div style="font-weight:700;font-size:13px;color:<?= $p['id']==$active_tab ? '#7c3aed' : '#374151' ?>;">
                <?= htmlspecialchars($p['nama']) ?>
              </div>
              <div style="font-size:10px;color:#9ca3af;"><?= htmlspecialchars($p['status_kawin']) ?></div>
            </div>
            <?php if (!$p['aktif']): ?>
            <span style="font-size:9px;color:#9ca3af;background:#f3f4f6;padding:1px 6px;border-radius:10px;">off</span>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
          <?php if (!$ptkp_list): ?>
          <div style="padding:14px;text-align:center;font-size:12px;color:#9ca3af;">Belum ada kategori</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Edit/Hapus kategori yang aktif -->
      <?php
      $cur_ptkp = null;
      foreach ($ptkp_list as $p) { if ($p['id'] == $active_tab) { $cur_ptkp = $p; break; } }
      ?>
      <?php if ($cur_ptkp): ?>
      <div class="panel" style="margin-top:10px;">
        <div class="panel-bd" style="padding:10px 12px;">
          <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:6px;">
            <i class="fa fa-gear" style="color:#7c3aed;"></i> <?= htmlspecialchars($cur_ptkp['nama']) ?>
          </div>
          <div style="font-size:11px;color:#6b7280;margin-bottom:2px;">Status: <?= htmlspecialchars($cur_ptkp['status_kawin']) ?></div>
          <div style="font-size:11px;color:#6b7280;margin-bottom:8px;">PTKP/tahun: <?= fmt($cur_ptkp['ptkp_setahun']) ?></div>
          <div style="display:flex;gap:6px;">
            <button onclick="editPtkp(<?= htmlspecialchars(json_encode($cur_ptkp), ENT_QUOTES) ?>)" class="btn btn-sm btn-primary" style="font-size:11px;flex:1;">
              <i class="fa fa-pen"></i> Edit
            </button>
            <button onclick="hapusPtkp(<?= $cur_ptkp['id'] ?>, '<?= addslashes(htmlspecialchars($cur_ptkp['nama'])) ?>')" class="btn btn-sm btn-danger" style="font-size:11px;">
              <i class="fa fa-trash"></i>
            </button>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- MAIN: Tabel Tarif TER -->
    <div>
      <?php if ($cur_ptkp): ?>
      <div class="panel">
        <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
          <h5 style="margin:0;">
            <i class="fa fa-table" style="color:#7c3aed;"></i>
            Tabel Tarif &nbsp;
            <span style="background:#7c3aed;color:#fff;font-size:11px;font-weight:700;padding:2px 10px;border-radius:20px;"><?= htmlspecialchars($cur_ptkp['nama']) ?></span>
            <span style="color:#aaa;font-weight:400;font-size:12px;">&nbsp;(<?= count($ter_rows) ?> baris)</span>
          </h5>
          <button onclick="openTambahTer(<?= $active_tab ?>)" class="btn btn-sm" style="background:#7c3aed;color:#fff;border:none;font-size:12px;padding:5px 12px;border-radius:7px;">
            <i class="fa fa-plus"></i> Tambah Baris
          </button>
        </div>
        <div class="panel-bd np tbl-wrap">
          <table>
            <thead>
              <tr>
                <th style="width:36px;">#</th>
                <th>Penghasilan Bruto Dari</th>
                <th>Sampai Dengan</th>
                <th style="text-align:center;">Tarif TER (%)</th>
                <th style="text-align:center;">Status</th>
                <th style="text-align:center;width:100px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$ter_rows): ?>
              <tr><td colspan="6" class="td-empty"><i class="fa fa-table"></i> Belum ada baris tarif</td></tr>
              <?php else: foreach ($ter_rows as $i => $t): ?>
              <tr style="<?= !$t['aktif'] ? 'opacity:.45;' : '' ?>">
                <td style="color:#bbb;font-size:12px;"><?= $i+1 ?></td>
                <td style="font-size:13px;font-weight:500;color:#374151;">
                  <?= fmt($t['penghasilan_dari']) ?>
                </td>
                <td style="font-size:13px;font-weight:500;color:#374151;">
                  <?= $t['penghasilan_sd'] !== null ? fmt($t['penghasilan_sd']) : '<span style="color:#7c3aed;font-weight:700;">Tak terbatas</span>' ?>
                </td>
                <td style="text-align:center;">
                  <span style="background:<?= $t['tarif_persen'] == 0 ? '#f0fdf4' : '#f5f3ff' ?>;
                    color:<?= $t['tarif_persen'] == 0 ? '#166534' : '#5b21b6' ?>;
                    font-size:13px;font-weight:700;padding:3px 12px;border-radius:20px;display:inline-block;">
                    <?= number_format($t['tarif_persen'],2) ?>%
                  </span>
                </td>
                <td style="text-align:center;">
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="act" value="toggle_ter">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <input type="hidden" name="val" value="<?= $t['aktif'] ? 0 : 1 ?>">
                    <input type="hidden" name="back_ptkp" value="<?= $active_tab ?>">
                    <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;">
                      <?php if ($t['aktif']): ?>
                      <span style="background:#d1fae5;color:#065f46;font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px;border:1px solid #6ee7b7;">● Aktif</span>
                      <?php else: ?>
                      <span style="background:#f3f4f6;color:#9ca3af;font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px;border:1px solid #e5e7eb;">○ Off</span>
                      <?php endif; ?>
                    </button>
                  </form>
                </td>
                <td style="text-align:center;white-space:nowrap;">
                  <button onclick="editTer(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>, <?= $active_tab ?>)" class="btn btn-primary btn-sm" title="Edit">
                    <i class="fa fa-pen"></i>
                  </button>
                  <button onclick="hapusTer(<?= $t['id'] ?>, <?= $active_tab ?>)" class="btn btn-danger btn-sm" title="Hapus">
                    <i class="fa fa-trash"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($ter_rows): ?>
        <div style="padding:10px 16px;border-top:1px solid #f3f4f6;font-size:11px;color:#9ca3af;">
          <i class="fa fa-circle-info"></i> &nbsp;Rumus: <strong>PPh 21 = Penghasilan Bruto Bulanan × Tarif TER</strong> &nbsp;|&nbsp; Kolom "Sampai Dengan" kosong = penghasilan tidak dibatasi ke atas
        </div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="panel">
        <div class="panel-bd" style="padding:40px;text-align:center;color:#9ca3af;">
          <i class="fa fa-percent" style="font-size:32px;margin-bottom:10px;display:block;color:#c4b5fd;"></i>
          Pilih kategori PTKP di sebelah kiri untuk melihat tabel tarif TER
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════ MODALS ═══ -->

<!-- MODAL TAMBAH PTKP -->
<div class="modal-ov" id="m-tambah-ptkp">
  <div style="background:#fff;width:100%;max-width:460px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mIn .2s ease;overflow:hidden;">
    <div style="padding:15px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
      <div style="font-size:14px;font-weight:700;"><i class="fa fa-plus" style="color:#7c3aed;"></i> Tambah Kategori PTKP</div>
      <button onclick="closeModal('m-tambah-ptkp')" class="btn btn-sm btn-default"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="tambah_ptkp">
      <div style="padding:20px;display:flex;flex-direction:column;gap:12px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Kode * <span style="color:#9ca3af;">(cth: TER_D)</span></label>
            <input type="text" name="kode" placeholder="TER_D" maxlength="10" required class="form-control" style="text-transform:uppercase;">
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Urutan Tampil</label>
            <input type="number" name="urutan" placeholder="4" min="0" class="form-control">
          </div>
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Nama Kategori *</label>
          <input type="text" name="nama" placeholder="cth: TER D" required class="form-control">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Status Kawin yang Termasuk</label>
          <input type="text" name="status_kawin" placeholder="cth: TK/0, K/0" class="form-control">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">PTKP Setahun (Rp)</label>
          <input type="number" name="ptkp_setahun" placeholder="54000000" min="0" step="500000" class="form-control">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Keterangan</label>
          <input type="text" name="keterangan" class="form-control" placeholder="Keterangan singkat…">
        </div>
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;">
          <input type="checkbox" name="aktif" value="1" checked> Aktif
        </label>
      </div>
      <div style="padding:12px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" onclick="closeModal('m-tambah-ptkp')" class="btn btn-default">Batal</button>
        <button type="submit" style="background:#7c3aed;color:#fff;" class="btn"><i class="fa fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDIT PTKP -->
<div class="modal-ov" id="m-edit-ptkp">
  <div style="background:#fff;width:100%;max-width:460px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mIn .2s ease;overflow:hidden;">
    <div style="padding:15px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
      <div style="font-size:14px;font-weight:700;"><i class="fa fa-pen" style="color:#3b82f6;"></i> Edit Kategori PTKP</div>
      <button onclick="closeModal('m-edit-ptkp')" class="btn btn-sm btn-default"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="edit_ptkp">
      <input type="hidden" name="id" id="ep-id">
      <div style="padding:20px;display:flex;flex-direction:column;gap:12px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Kode *</label>
            <input type="text" name="kode" id="ep-kode" maxlength="10" required class="form-control" style="text-transform:uppercase;">
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Urutan Tampil</label>
            <input type="number" name="urutan" id="ep-urutan" min="0" class="form-control">
          </div>
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Nama Kategori *</label>
          <input type="text" name="nama" id="ep-nama" required class="form-control">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Status Kawin yang Termasuk</label>
          <input type="text" name="status_kawin" id="ep-status" class="form-control">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">PTKP Setahun (Rp)</label>
          <input type="number" name="ptkp_setahun" id="ep-ptkp" min="0" step="500000" class="form-control">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Keterangan</label>
          <input type="text" name="keterangan" id="ep-ket" class="form-control">
        </div>
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;">
          <input type="checkbox" name="aktif" id="ep-aktif" value="1"> Aktif
        </label>
      </div>
      <div style="padding:12px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" onclick="closeModal('m-edit-ptkp')" class="btn btn-default">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Perbarui</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL HAPUS PTKP -->
<div class="modal-ov" id="m-hapus-ptkp">
  <div style="background:#fff;width:100%;max-width:360px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mIn .2s ease;">
    <div style="padding:24px;text-align:center;">
      <div style="width:52px;height:52px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
        <i class="fa fa-trash" style="color:#ef4444;font-size:20px;"></i>
      </div>
      <div style="font-size:15px;font-weight:700;margin-bottom:6px;">Hapus Kategori?</div>
      <div style="font-size:13px;color:#6b7280;" id="hapus-ptkp-label"></div>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="hapus_ptkp">
      <input type="hidden" name="id" id="hapus-ptkp-id">
      <div style="padding:0 20px 20px;display:flex;gap:8px;justify-content:center;">
        <button type="button" onclick="closeModal('m-hapus-ptkp')" class="btn btn-default">Batal</button>
        <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Ya, Hapus</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL TAMBAH TER -->
<div class="modal-ov" id="m-tambah-ter">
  <div style="background:#fff;width:100%;max-width:440px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mIn .2s ease;overflow:hidden;">
    <div style="padding:15px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
      <div style="font-size:14px;font-weight:700;"><i class="fa fa-plus" style="color:#7c3aed;"></i> Tambah Baris Tarif TER</div>
      <button onclick="closeModal('m-tambah-ter')" class="btn btn-sm btn-default"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="tambah_ter">
      <input type="hidden" name="ptkp_id" id="tt-ptkp-id">
      <input type="hidden" name="back_ptkp" id="tt-back">
      <div style="padding:20px;display:flex;flex-direction:column;gap:12px;">
        <div style="background:#f5f3ff;border-radius:8px;padding:10px 14px;font-size:12px;color:#5b21b6;">
          <i class="fa fa-layer-group"></i> Kategori: <strong id="tt-ptkp-nama"></strong>
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Penghasilan Bruto Dari (Rp) *</label>
          <input type="number" name="penghasilan_dari" placeholder="0" min="0" step="50000" required class="form-control">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Sampai Dengan (Rp) <span style="color:#9ca3af;">— kosongkan jika tak terbatas</span></label>
          <input type="number" name="penghasilan_sd" placeholder="Kosong = tak terbatas" min="0" step="50000" class="form-control">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Tarif TER (%)</label>
          <input type="number" name="tarif_persen" placeholder="0" min="0" max="100" step="0.01" required class="form-control">
        </div>
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;">
          <input type="checkbox" name="aktif" value="1" checked> Aktif
        </label>
      </div>
      <div style="padding:12px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" onclick="closeModal('m-tambah-ter')" class="btn btn-default">Batal</button>
        <button type="submit" style="background:#7c3aed;color:#fff;" class="btn"><i class="fa fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDIT TER -->
<div class="modal-ov" id="m-edit-ter">
  <div style="background:#fff;width:100%;max-width:440px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mIn .2s ease;overflow:hidden;">
    <div style="padding:15px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
      <div style="font-size:14px;font-weight:700;"><i class="fa fa-pen" style="color:#3b82f6;"></i> Edit Baris Tarif TER</div>
      <button onclick="closeModal('m-edit-ter')" class="btn btn-sm btn-default"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="edit_ter">
      <input type="hidden" name="id" id="et-id">
      <input type="hidden" name="ptkp_id" id="et-ptkp-id">
      <input type="hidden" name="back_ptkp" id="et-back">
      <div style="padding:20px;display:flex;flex-direction:column;gap:12px;">
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Penghasilan Bruto Dari (Rp) *</label>
          <input type="number" name="penghasilan_dari" id="et-dari" min="0" step="50000" required class="form-control">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Sampai Dengan (Rp) <span style="color:#9ca3af;">— kosongkan jika tak terbatas</span></label>
          <input type="number" name="penghasilan_sd" id="et-sd" min="0" step="50000" class="form-control">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Tarif TER (%)</label>
          <input type="number" name="tarif_persen" id="et-tarif" min="0" max="100" step="0.01" required class="form-control">
        </div>
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;">
          <input type="checkbox" name="aktif" id="et-aktif" value="1"> Aktif
        </label>
      </div>
      <div style="padding:12px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" onclick="closeModal('m-edit-ter')" class="btn btn-default">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Perbarui</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL HAPUS TER -->
<div class="modal-ov" id="m-hapus-ter">
  <div style="background:#fff;width:100%;max-width:360px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mIn .2s ease;">
    <div style="padding:24px;text-align:center;">
      <div style="width:52px;height:52px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
        <i class="fa fa-trash" style="color:#ef4444;font-size:20px;"></i>
      </div>
      <div style="font-size:15px;font-weight:700;margin-bottom:6px;">Hapus Baris Tarif?</div>
      <div style="font-size:13px;color:#6b7280;">Baris tarif ini akan dihapus permanen.</div>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="hapus_ter">
      <input type="hidden" name="id" id="hapus-ter-id">
      <input type="hidden" name="back_ptkp" id="hapus-ter-back">
      <div style="padding:0 20px 20px;display:flex;gap:8px;justify-content:center;">
        <button type="button" onclick="closeModal('m-hapus-ter')" class="btn btn-default">Batal</button>
        <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Ya, Hapus</button>
      </div>
    </form>
  </div>
</div>

<script>
// Lookup nama PTKP dari data PHP
const ptkpMap = <?= json_encode(array_column($ptkp_list, 'nama', 'id')) ?>;

function editPtkp(p) {
  document.getElementById('ep-id').value     = p.id;
  document.getElementById('ep-kode').value   = p.kode;
  document.getElementById('ep-nama').value   = p.nama;
  document.getElementById('ep-status').value = p.status_kawin;
  document.getElementById('ep-ptkp').value   = p.ptkp_setahun;
  document.getElementById('ep-ket').value    = p.keterangan || '';
  document.getElementById('ep-urutan').value = p.urutan;
  document.getElementById('ep-aktif').checked = p.aktif == 1;
  openModal('m-edit-ptkp');
}
function hapusPtkp(id, nama) {
  document.getElementById('hapus-ptkp-id').value = id;
  document.getElementById('hapus-ptkp-label').textContent = 'Hapus kategori "' + nama + '"? Pastikan tidak ada baris tarif.';
  openModal('m-hapus-ptkp');
}

function openTambahTer(ptkpId) {
  document.getElementById('tt-ptkp-id').value = ptkpId;
  document.getElementById('tt-back').value    = ptkpId;
  document.getElementById('tt-ptkp-nama').textContent = ptkpMap[ptkpId] || '';
  openModal('m-tambah-ter');
}
function editTer(t, ptkpId) {
  document.getElementById('et-id').value      = t.id;
  document.getElementById('et-ptkp-id').value = t.ptkp_id;
  document.getElementById('et-back').value    = ptkpId;
  document.getElementById('et-dari').value    = t.penghasilan_dari;
  document.getElementById('et-sd').value      = t.penghasilan_sd ?? '';
  document.getElementById('et-tarif').value   = t.tarif_persen;
  document.getElementById('et-aktif').checked = t.aktif == 1;
  openModal('m-edit-ter');
}
function hapusTer(id, ptkpId) {
  document.getElementById('hapus-ter-id').value   = id;
  document.getElementById('hapus-ter-back').value = ptkpId;
  openModal('m-hapus-ter');
}
</script>

<?php include '../includes/footer.php'; ?>