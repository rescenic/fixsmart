<?php
// pages/data_gaji.php
ob_start();
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'keuangan'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Data Gaji';
$active_menu = 'data_gaji';

// ── Auto-create / migrate tabel ───────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `data_gaji` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT UNSIGNED NOT NULL UNIQUE COMMENT 'FK ke users.id',
        `gaji_pokok` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `ptkp_id`    INT UNSIGNED  DEFAULT NULL COMMENT 'FK ke master_pph21_ptkp.id',
        `pph21`      DECIMAL(15,2) NOT NULL DEFAULT 0,
        `catatan`    TEXT DEFAULT NULL,
        `created_by` INT UNSIGNED  DEFAULT NULL,
        `created_at` DATETIME      DEFAULT NULL,
        `updated_by` INT UNSIGNED  DEFAULT NULL,
        `updated_at` DATETIME      DEFAULT NULL,
        KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `data_gaji_detail` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `data_gaji_id`  INT UNSIGNED NOT NULL,
        `tipe`          ENUM('penerimaan','potongan') NOT NULL,
        `penerimaan_id` INT UNSIGNED DEFAULT NULL,
        `potongan_id`   INT UNSIGNED DEFAULT NULL,
        `nilai`         DECIMAL(15,2) NOT NULL DEFAULT 0,
        KEY `idx_dgid` (`data_gaji_id`),
        KEY `idx_pid`  (`penerimaan_id`),
        KEY `idx_tid`  (`potongan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Migrate kolom lama jika perlu
try {
    $cols = $pdo->query("SHOW COLUMNS FROM data_gaji")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('ptkp_id', $cols))
        $pdo->exec("ALTER TABLE data_gaji ADD COLUMN `ptkp_id` INT UNSIGNED DEFAULT NULL AFTER `gaji_pokok`");
    if (!in_array('pph21', $cols))
        $pdo->exec("ALTER TABLE data_gaji ADD COLUMN `pph21` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `ptkp_id`");
    foreach (['bank_nama','bank_rekening','bank_atas_nama'] as $lama) {
        if (in_array($lama, $cols))
            $pdo->exec("ALTER TABLE data_gaji DROP COLUMN `$lama`");
    }
} catch (Exception $e) {}

// ── POST HANDLER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'simpan_gaji') {
        $user_id    = (int)($_POST['user_id']    ?? 0);
        $gaji_pokok = (float)($_POST['gaji_pokok'] ?? 0);
        $ptkp_id    = (int)($_POST['ptkp_id']    ?? 0) ?: null;
        $pph21      = (float)($_POST['pph21']     ?? 0);
        $catatan    = trim($_POST['catatan'] ?? '');

        if (!$user_id) {
            setFlash('danger', 'Pilih karyawan terlebih dahulu.');
            redirect(APP_URL . '/pages/data_gaji.php');
        }

        try {
            $exist = $pdo->prepare("SELECT id FROM data_gaji WHERE user_id=?");
            $exist->execute([$user_id]);
            $row_exist = $exist->fetch();

            if ($row_exist) {
                $dg_id = $row_exist['id'];
                $pdo->prepare("UPDATE data_gaji
                    SET gaji_pokok=?,ptkp_id=?,pph21=?,catatan=?,updated_by=?,updated_at=NOW()
                    WHERE id=?")
                    ->execute([$gaji_pokok,$ptkp_id,$pph21,$catatan,$_SESSION['user_id'],$dg_id]);
            } else {
                $pdo->prepare("INSERT INTO data_gaji
                    (user_id,gaji_pokok,ptkp_id,pph21,catatan,created_by,created_at)
                    VALUES (?,?,?,?,?,?,NOW())")
                    ->execute([$user_id,$gaji_pokok,$ptkp_id,$pph21,$catatan,$_SESSION['user_id']]);
                $dg_id = (int)$pdo->lastInsertId();
            }

            // Detail penerimaan
            $pdo->prepare("DELETE FROM data_gaji_detail WHERE data_gaji_id=? AND tipe='penerimaan'")->execute([$dg_id]);
            foreach (($_POST['penerimaan_id'] ?? []) as $idx => $pid) {
                $pid = (int)$pid;
                $val = (float)($_POST['penerimaan_val'][$idx] ?? 0);
                if ($pid > 0 && $val > 0)
                    $pdo->prepare("INSERT INTO data_gaji_detail (data_gaji_id,tipe,penerimaan_id,nilai) VALUES (?,?,?,?)")
                        ->execute([$dg_id,'penerimaan',$pid,$val]);
            }

            // Detail potongan
            $pdo->prepare("DELETE FROM data_gaji_detail WHERE data_gaji_id=? AND tipe='potongan'")->execute([$dg_id]);
            foreach (($_POST['potongan_id'] ?? []) as $idx => $pid) {
                $pid = (int)$pid;
                $val = (float)($_POST['potongan_val'][$idx] ?? 0);
                if ($pid > 0 && $val > 0)
                    $pdo->prepare("INSERT INTO data_gaji_detail (data_gaji_id,tipe,potongan_id,nilai) VALUES (?,?,?,?)")
                        ->execute([$dg_id,'potongan',$pid,$val]);
            }

            setFlash('success', 'Data gaji karyawan berhasil disimpan.');
        } catch (Exception $e) {
            setFlash('danger', 'Gagal menyimpan: ' . htmlspecialchars($e->getMessage()));
        }
        redirect(APP_URL . '/pages/data_gaji.php');
    }

    if ($act === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM data_gaji_detail WHERE data_gaji_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM data_gaji WHERE id=?")->execute([$id]);
        setFlash('success', 'Data gaji berhasil dihapus.');
        redirect(APP_URL . '/pages/data_gaji.php');
    }
}

// ── FETCH DATA ────────────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$where  = $search ? "WHERE (u.nama LIKE ? OR sk.divisi LIKE ? OR sk.nik_rs LIKE ?)" : '';
$params = $search ? ["%$search%","%$search%","%$search%"] : [];

try {
    $stmt = $pdo->prepare("
        SELECT dg.*,
               u.nama,
               sk.divisi, sk.status_kepegawaian, sk.nik_rs,
               sk.bank, sk.no_rekening, sk.atas_nama_rek,
               pp.nama AS ptkp_nama, pp.kode AS ptkp_kode
        FROM data_gaji dg
        JOIN  users            u  ON u.id       = dg.user_id
        LEFT JOIN sdm_karyawan sk ON sk.user_id = dg.user_id
        LEFT JOIN master_pph21_ptkp pp ON pp.id = dg.ptkp_id
        $where
        ORDER BY u.nama ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
    setFlash('danger', 'Query error: ' . htmlspecialchars($e->getMessage()));
}

// Semua karyawan aktif untuk dropdown (join sdm_karyawan untuk data bank)
try {
    $all_karyawan = $pdo->query("
        SELECT u.id, u.nama,
               sk.divisi, sk.nik_rs, sk.status_kepegawaian,
               sk.bank, sk.no_rekening, sk.atas_nama_rek
        FROM users u
        LEFT JOIN sdm_karyawan sk ON sk.user_id = u.id
        WHERE u.status = 'aktif'
        ORDER BY u.nama ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_karyawan = [];
}

// Master komponen (safe)
$list_penerimaan = $list_potongan = $list_ptkp = $list_ter = [];
try { $list_penerimaan = $pdo->query("SELECT * FROM master_penerimaan WHERE aktif=1 ORDER BY kode")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
try { $list_potongan   = $pdo->query("SELECT * FROM master_potongan   WHERE aktif=1 ORDER BY kode")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
try { $list_ptkp       = $pdo->query("SELECT * FROM master_pph21_ptkp WHERE aktif=1 ORDER BY urutan,id")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
try { $list_ter        = $pdo->query("SELECT * FROM master_pph21_ter  WHERE aktif=1 ORDER BY ptkp_id,penghasilan_dari")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

// Stats
$total_karyawan = count($all_karyawan);
$sudah_setup    = count($rows);
$belum_setup    = $total_karyawan - $sudah_setup;
$total_gp       = array_sum(array_column($rows, 'gaji_pokok'));

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-table-list" style="color:#3b82f6;"></i> &nbsp;Data Gaji Karyawan</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Data Gaji</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Stats -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(165px,1fr));gap:10px;margin-bottom:18px;">
    <?php foreach ([
      ['fa-users',       'Total Karyawan',   $total_karyawan,                          '#1d4ed8','#dbeafe'],
      ['fa-check-circle','Sudah Setup',       $sudah_setup,                             '#065f46','#d1fae5'],
      ['fa-clock',       'Belum Setup',       $belum_setup,                             '#92400e','#fef3c7'],
      ['fa-wallet',      'Total Gaji Pokok', 'Rp '.number_format($total_gp,0,',','.'), '#5b21b6','#ede9fe'],
    ] as [$ic,$lbl,$val,$tc,$bg]): ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:13px 15px;">
      <div style="width:32px;height:32px;background:<?=$bg?>;border-radius:7px;display:flex;align-items:center;justify-content:center;margin-bottom:8px;">
        <i class="fa <?=$ic?>" style="color:<?=$tc?>;font-size:14px;"></i>
      </div>
      <div style="font-size:11px;color:#6b7280;font-weight:500;"><?=$lbl?></div>
      <div style="font-size:16px;font-weight:800;color:#111827;margin-top:2px;"><?=$val?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Toolbar -->
  <div class="panel" style="margin-bottom:14px;">
    <div class="panel-bd">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                 placeholder="Cari nama / divisi / NIK…" class="form-control" style="width:220px;height:34px;">
          <button type="submit" class="btn btn-default" style="height:34px;"><i class="fa fa-search"></i> Cari</button>
          <?php if ($search): ?>
          <a href="data_gaji.php" class="btn btn-default" style="height:34px;">Reset</a>
          <?php endif; ?>
        </form>
        <button onclick="bukaFormBaru()" class="btn btn-primary">
          <i class="fa fa-plus"></i> Set Data Gaji
        </button>
      </div>
    </div>
  </div>

  <!-- Tabel -->
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-table-list" style="color:#3b82f6;"></i> &nbsp;Data Gaji Per Karyawan
        <span style="color:#aaa;font-weight:400;">(<?= count($rows) ?>)</span>
      </h5>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:36px;">#</th>
            <th>Karyawan</th>
            <th>Divisi / Status Kepegawaian</th>
            <th style="text-align:right;">Gaji Pokok</th>
            <th style="text-align:center;">TER</th>
            <th style="text-align:right;">PPh 21/bln</th>
            <th>Bank</th>
            <th style="text-align:center;">Komp.</th>
            <th style="text-align:center;width:100px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="9" class="td-empty"><i class="fa fa-table-list"></i> Belum ada data gaji yang di-setup</td></tr>
          <?php else: foreach ($rows as $i => $r):
            $det = $pdo->prepare("SELECT tipe, COUNT(*) cnt FROM data_gaji_detail WHERE data_gaji_id=? GROUP BY tipe");
            $det->execute([$r['id']]);
            $det_arr = [];
            foreach ($det->fetchAll(PDO::FETCH_ASSOC) as $d) $det_arr[$d['tipe']] = $d['cnt'];
            $words   = array_filter(explode(' ', trim($r['nama'])));
            $inisial = strtoupper(implode('', array_map(fn($w) => mb_substr($w,0,1), array_slice(array_values($words),0,2))));
          ?>
          <tr>
            <td style="color:#bbb;font-size:12px;"><?= $i+1 ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:9px;">
                <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <?= htmlspecialchars($inisial ?: '?') ?>
                </div>
                <div>
                  <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($r['nama']) ?></div>
                  <div style="font-size:11px;color:#9ca3af;"><?= htmlspecialchars($r['nik_rs'] ?? '—') ?></div>
                </div>
              </div>
            </td>
            <td>
              <div style="font-size:12px;font-weight:500;"><?= htmlspecialchars($r['divisi'] ?? '—') ?></div>
              <?php if (!empty($r['status_kepegawaian'])): ?>
              <span style="background:#f0f9ff;color:#0369a1;font-size:10px;font-weight:600;padding:1px 7px;border-radius:10px;">
                <?= htmlspecialchars($r['status_kepegawaian']) ?>
              </span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;font-weight:700;color:#166534;font-size:13px;">
              Rp <?= number_format($r['gaji_pokok'],0,',','.') ?>
            </td>
            <td style="text-align:center;">
              <?php if (!empty($r['ptkp_kode'])): ?>
              <span style="background:#f5f3ff;color:#5b21b6;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;border:1px solid #c4b5fd;">
                <?= htmlspecialchars($r['ptkp_kode']) ?>
              </span>
              <?php else: ?>
              <span style="color:#d1d5db;font-size:11px;">—</span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;font-weight:600;color:#dc2626;font-size:13px;">
              <?= $r['pph21'] > 0 ? 'Rp '.number_format($r['pph21'],0,',','.') : '<span style="color:#d1d5db;">—</span>' ?>
            </td>
            <td style="font-size:12px;">
              <?php if (!empty($r['bank'])): ?>
              <div style="font-weight:600;"><?= htmlspecialchars($r['bank']) ?></div>
              <code style="background:#f3f4f6;padding:1px 5px;border-radius:4px;font-size:11px;"><?= htmlspecialchars($r['no_rekening'] ?? '') ?></code>
              <?php else: ?>
              <span style="color:#d1d5db;font-size:11px;">— belum diisi di SDM —</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;">
              <div style="display:inline-flex;gap:4px;">
                <span title="Penerimaan" style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;font-size:11px;font-weight:600;padding:2px 7px;border-radius:20px;">+<?= $det_arr['penerimaan'] ?? 0 ?></span>
                <span title="Potongan"   style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;font-size:11px;font-weight:600;padding:2px 7px;border-radius:20px;">-<?= $det_arr['potongan']   ?? 0 ?></span>
              </div>
            </td>
            <td style="text-align:center;white-space:nowrap;">
              <button onclick="loadEditGaji(<?= $r['id'] ?>,<?= $r['user_id'] ?>)" class="btn btn-primary btn-sm" title="Edit"><i class="fa fa-pen"></i></button>
              <button onclick="hapusGaji(<?= $r['id'] ?>,'<?= addslashes(htmlspecialchars($r['nama'])) ?>')" class="btn btn-danger btn-sm" title="Hapus"><i class="fa fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ══════════════════ MODAL FORM ══════════════════ -->
<div class="modal-ov" id="m-form">
  <div style="background:#fff;width:100%;max-width:700px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.22);animation:mIn .2s ease;overflow:hidden;max-height:93vh;display:flex;flex-direction:column;">

    <div style="padding:15px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
      <div style="font-size:14px;font-weight:700;">
        <i class="fa fa-table-list" style="color:#3b82f6;"></i>
        <span id="form-title">Set Data Gaji Karyawan</span>
      </div>
      <button onclick="closeModal('m-form')" class="btn btn-sm btn-default"><i class="fa fa-times"></i></button>
    </div>

    <form method="POST" style="overflow-y:auto;flex:1;display:flex;flex-direction:column;">
      <input type="hidden" name="act" value="simpan_gaji">
      <input type="hidden" name="user_id" id="form-user-id">

      <div style="padding:18px 20px;display:flex;flex-direction:column;gap:14px;">

        <!-- Pilih Karyawan -->
        <div>
          <label class="flabel">Karyawan *</label>
          <select id="sel-karyawan" class="form-control" style="height:38px;" onchange="pilihKaryawan(this)">
            <option value="">— Pilih Karyawan —</option>
            <?php foreach ($all_karyawan as $k): ?>
            <option value="<?= $k['id'] ?>"
              data-divisi="<?= htmlspecialchars($k['divisi'] ?? '') ?>"
              data-status="<?= htmlspecialchars($k['status_kepegawaian'] ?? '') ?>"
              data-bank="<?= htmlspecialchars($k['bank'] ?? '') ?>"
              data-rek="<?= htmlspecialchars($k['no_rekening'] ?? '') ?>"
              data-atas="<?= htmlspecialchars($k['atas_nama_rek'] ?? '') ?>">
              <?= htmlspecialchars($k['nama']) ?><?= $k['divisi'] ? ' — '.$k['divisi'] : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Info SDM -->
        <div id="info-karyawan" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:11px 14px;">
          <div style="font-size:10px;font-weight:700;color:#6b7280;margin-bottom:7px;letter-spacing:.5px;">INFO DARI DATA SDM KARYAWAN</div>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:7px;font-size:12px;">
            <div><span style="color:#9ca3af;">Divisi:</span><br><strong id="inf-divisi">—</strong></div>
            <div><span style="color:#9ca3af;">Status Kepegawaian:</span><br><strong id="inf-status">—</strong></div>
            <div><span style="color:#9ca3af;">Bank:</span><br><strong id="inf-bank">—</strong></div>
            <div><span style="color:#9ca3af;">No. Rekening:</span><br><strong id="inf-rek">—</strong></div>
            <div><span style="color:#9ca3af;">Atas Nama:</span><br><strong id="inf-atas">—</strong></div>
          </div>
          <div style="font-size:10px;color:#9ca3af;margin-top:7px;"><i class="fa fa-circle-info"></i> Data bank diambil otomatis dari profil SDM. Untuk mengubah, edit di modul SDM Karyawan.</div>
        </div>

        <!-- Gaji Pokok + TER -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label class="flabel">Gaji Pokok (Rp)</label>
            <select name="gaji_pokok" id="f-gapok" class="form-control" style="height:38px;" onchange="hitungSemua()">
              <option value="0">— Pilih Gaji Pokok —</option>
              <?php for ($g=500000;  $g<=10000000; $g+=500000): ?>
              <option value="<?=$g?>">Rp <?= number_format($g,0,',','.') ?></option>
              <?php endfor; ?>
              <?php for ($g=11000000; $g<=50000000; $g+=1000000): ?>
              <option value="<?=$g?>">Rp <?= number_format($g,0,',','.') ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div>
            <label class="flabel"><i class="fa fa-percent" style="color:#7c3aed;font-size:10px;"></i> Kategori PTKP / TER PPh 21</label>
            <select name="ptkp_id" id="f-ptkp" class="form-control" style="height:38px;" onchange="hitungSemua()">
              <option value="">— Pilih Kategori TER —</option>
              <?php foreach ($list_ptkp as $p): ?>
              <option value="<?= $p['id'] ?>">
                <?= htmlspecialchars($p['nama']) ?> — <?= htmlspecialchars($p['status_kawin']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Penerimaan -->
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <div style="font-size:12px;font-weight:700;color:#065f46;"><i class="fa fa-circle-plus"></i> Komponen Penerimaan</div>
            <button type="button" onclick="addBaris('penerimaan')" class="btn btn-sm btn-success" style="font-size:11px;"><i class="fa fa-plus"></i> Tambah</button>
          </div>
          <div id="penerimaan-rows"><div class="ph-msg" style="font-size:12px;color:#9ca3af;text-align:center;padding:5px 0;">Klik Tambah untuk menambah komponen penerimaan</div></div>
        </div>

        <!-- Potongan -->
        <div style="background:#fff5f5;border:1px solid #fecaca;border-radius:10px;padding:14px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <div style="font-size:12px;font-weight:700;color:#991b1b;"><i class="fa fa-circle-minus"></i> Komponen Potongan</div>
            <button type="button" onclick="addBaris('potongan')" class="btn btn-sm btn-danger" style="font-size:11px;"><i class="fa fa-plus"></i> Tambah</button>
          </div>
          <div id="potongan-rows"><div class="ph-msg" style="font-size:12px;color:#9ca3af;text-align:center;padding:5px 0;">Klik Tambah untuk menambah komponen potongan</div></div>
          <div style="font-size:10.5px;color:#9ca3af;margin-top:6px;"><i class="fa fa-circle-info"></i> PPh 21 diisi tersendiri di bawah — tidak perlu ditambah di sini.</div>
        </div>

        <!-- Kalkulasi -->
        <div style="background:linear-gradient(135deg,#f8fafc,#f1f5f9);border:1.5px solid #e2e8f0;border-radius:12px;padding:16px;">
          <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:12px;">
            <i class="fa fa-calculator" style="color:#3b82f6;"></i> Ringkasan Kalkulasi Gaji
          </div>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;">
            <?php foreach ([
              ['disp-gapok',      'Gaji Pokok',            '#166534','#fff','#e5e7eb'],
              ['disp-penerimaan', '+ Total Penerimaan',     '#166534','#fff','#e5e7eb'],
              ['disp-bruto',      '= Penghasilan Bruto',    '#1d4ed8','#dbeafe','#93c5fd'],
              ['disp-potongan',   '− Potongan (non-PPh)',   '#dc2626','#fff','#e5e7eb'],
              ['disp-pph-val',    '− PPh 21 Dikenakan',     '#dc2626','#fff','#e5e7eb'],
              ['disp-pph-est',    'Estimasi TER (referensi)','#7c3aed','#f5f3ff','#c4b5fd'],
            ] as [$id,$lbl,$tc,$bg,$bc]): ?>
            <div style="background:<?=$bg?>;border-radius:8px;padding:10px 12px;border:1px solid <?=$bc?>;">
              <div style="font-size:10px;color:#6b7280;"><?=$lbl?></div>
              <div style="font-size:14px;font-weight:800;color:<?=$tc?>;" id="<?=$id?>">Rp 0</div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="font-size:10px;color:#7c3aed;margin-bottom:10px;text-align:center;" id="disp-ter-info">— pilih kategori TER untuk melihat estimasi —</div>

          <!-- PPh21 manual -->
          <div style="background:#f5f3ff;border:1px solid #c4b5fd;border-radius:9px;padding:12px;margin-bottom:10px;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <div style="flex:1;min-width:180px;">
                <label class="flabel" style="color:#5b21b6;">PPh 21 yang Dikenakan (Rp/bulan)</label>
                <select name="pph21" id="f-pph21" class="form-control" style="height:36px;" onchange="hitungSemua()">
                  <option value="0">Rp 0 (Dibebaskan)</option>
                  <?php for ($v=50000;   $v<=1000000;  $v+=50000):  ?>
                  <option value="<?=$v?>">Rp <?= number_format($v,0,',','.') ?></option>
                  <?php endfor; ?>
                  <?php for ($v=1100000; $v<=3000000;  $v+=100000): ?>
                  <option value="<?=$v?>">Rp <?= number_format($v,0,',','.') ?></option>
                  <?php endfor; ?>
                  <?php for ($v=3500000; $v<=10000000; $v+=500000): ?>
                  <option value="<?=$v?>">Rp <?= number_format($v,0,',','.') ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div style="padding-top:16px;">
                <button type="button" onclick="pakaiEstimasi()" style="background:#7c3aed;color:#fff;border:none;padding:7px 14px;border-radius:7px;font-size:11px;cursor:pointer;">
                  <i class="fa fa-wand-magic-sparkles"></i> Pakai Estimasi TER
                </button>
              </div>
            </div>
          </div>

          <!-- Gaji Bersih -->
          <div style="background:linear-gradient(135deg,#1d4ed8,#3b82f6);border-radius:10px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;">
            <div>
              <div style="font-size:12px;color:rgba(255,255,255,.8);font-weight:600;">Gaji Bersih (Take Home Pay)</div>
              <div style="font-size:10px;color:rgba(255,255,255,.5);margin-top:2px;">Bruto − Potongan − PPh 21</div>
            </div>
            <div style="font-size:22px;font-weight:900;color:#fff;" id="disp-bersih">Rp 0</div>
          </div>
        </div>

        <!-- Catatan -->
        <div>
          <label class="flabel">Catatan</label>
          <select name="catatan" id="f-catatan" class="form-control" style="height:38px;">
            <option value="">— Tidak ada catatan —</option>
            <option value="Gaji tetap">Gaji tetap</option>
            <option value="Gaji disesuaikan">Gaji disesuaikan</option>
            <option value="Karyawan baru">Karyawan baru</option>
            <option value="Masa percobaan">Masa percobaan</option>
            <option value="Gaji naik berkala">Gaji naik berkala</option>
          </select>
        </div>

      </div>

      <div style="padding:12px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;flex-shrink:0;">
        <button type="button" onclick="closeModal('m-form')" class="btn btn-default">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Data Gaji</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Hapus -->
<div class="modal-ov" id="m-hapus">
  <div style="background:#fff;width:100%;max-width:380px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mIn .2s ease;">
    <div style="padding:24px;text-align:center;">
      <div style="width:52px;height:52px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
        <i class="fa fa-trash" style="color:#ef4444;font-size:20px;"></i>
      </div>
      <div style="font-size:15px;font-weight:700;margin-bottom:6px;">Hapus Data Gaji?</div>
      <div style="font-size:13px;color:#6b7280;" id="hapus-label"></div>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="hapus">
      <input type="hidden" name="id" id="hapus-id">
      <div style="padding:0 20px 20px;display:flex;gap:8px;justify-content:center;">
        <button type="button" onclick="closeModal('m-hapus')" class="btn btn-default">Batal</button>
        <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Ya, Hapus</button>
      </div>
    </form>
  </div>
</div>

<style>
.flabel { font-size:11px; font-weight:600; color:#374151; display:block; margin-bottom:4px; }
</style>

<script>
var masterPenerimaan = <?= json_encode(array_values($list_penerimaan)) ?>;
var masterPotongan   = <?= json_encode(array_values($list_potongan)) ?>;
var masterPtkp       = <?= json_encode(array_values($list_ptkp)) ?>;
var masterTer        = <?= json_encode(array_values($list_ter)) ?>;

function rp(n) {
    return 'Rp ' + Math.round(parseFloat(n)||0).toLocaleString('id-ID');
}

// Saat pilih karyawan → tampilkan info SDM
function pilihKaryawan(sel) {
    var uid = sel.value;
    document.getElementById('form-user-id').value = uid;
    if (!uid) { document.getElementById('info-karyawan').style.display='none'; return; }
    var o = sel.options[sel.selectedIndex];
    document.getElementById('inf-divisi').textContent = o.dataset.divisi  || '—';
    document.getElementById('inf-status').textContent = o.dataset.status  || '—';
    document.getElementById('inf-bank').textContent   = o.dataset.bank    || '— (belum diisi di SDM)';
    document.getElementById('inf-rek').textContent    = o.dataset.rek     || '—';
    document.getElementById('inf-atas').textContent   = o.dataset.atas    || '—';
    document.getElementById('info-karyawan').style.display = '';
}

// Generate opsi nilai
function opsiNilai(defVal, selVal) {
    var pool = new Set([50000,100000,150000,200000,250000,300000,350000,400000,500000,
        600000,700000,750000,800000,900000,1000000,1250000,1500000,1750000,2000000,
        2500000,3000000,3500000,4000000,5000000,6000000,7000000,8000000,10000000,
        12000000,15000000,20000000]);
    if (defVal > 0) pool.add(parseFloat(defVal));
    if (selVal  > 0) pool.add(parseFloat(selVal));
    return Array.from(pool).sort(function(a,b){return a-b;}).map(function(v){
        return '<option value="'+v+'"'+(v==selVal?' selected':'')+'>'
             +'Rp '+Math.round(v).toLocaleString('id-ID')+'</option>';
    }).join('');
}

// Tambah baris komponen
function addBaris(tipe, selId, val) {
    selId = parseInt(selId)||0;
    val   = parseFloat(val)||0;
    var list = tipe==='penerimaan' ? masterPenerimaan : masterPotongan;
    var wrap = document.getElementById(tipe+'-rows');
    if (wrap.querySelector('.ph-msg')) wrap.innerHTML = '';

    var optsK = list.map(function(p){
        return '<option value="'+p.id+'" data-default="'+p.nilai_default+'"'
              +(p.id==selId?' selected':'')+'>'+p.kode+' — '+p.nama+'</option>';
    }).join('');

    var defVal = 0;
    list.forEach(function(p){ if(p.id==selId) defVal=parseFloat(p.nilai_default)||0; });
    var useVal = val>0 ? val : defVal;

    var div = document.createElement('div');
    div.style.cssText = 'display:grid;grid-template-columns:1fr 155px 34px;gap:7px;align-items:center;margin-bottom:7px;';
    div.innerHTML =
        '<select name="'+tipe+'_id[]" class="form-control" style="height:34px;" onchange="gantiKomponen(this,\''+tipe+'\')">'
      +   '<option value="">— Pilih Komponen —</option>'+optsK
      + '</select>'
      + '<select name="'+tipe+'_val[]" class="form-control" style="height:34px;" onchange="hitungSemua()">'
      +   '<option value="0">— Nilai —</option>'+opsiNilai(defVal,useVal)
      + '</select>'
      + '<button type="button" onclick="this.parentNode.remove();hitungSemua();" class="btn btn-danger btn-sm" style="height:34px;padding:0 9px;"><i class="fa fa-times"></i></button>';
    wrap.appendChild(div);
    hitungSemua();
}

function gantiKomponen(sel, tipe) {
    var defVal = parseFloat(sel.options[sel.selectedIndex].getAttribute('data-default'))||0;
    var vs = sel.parentNode.querySelector('select[name="'+tipe+'_val[]"]');
    vs.innerHTML = '<option value="0">— Nilai —</option>'+opsiNilai(defVal,defVal);
    hitungSemua();
}

// Cari tarif TER
function getTarif(ptkpId, bruto) {
    ptkpId = parseInt(ptkpId); bruto = parseFloat(bruto);
    var found = null;
    masterTer.forEach(function(t){
        if (parseInt(t.ptkp_id)!==ptkpId) return;
        var dari = parseFloat(t.penghasilan_dari);
        var sd   = t.penghasilan_sd!==null ? parseFloat(t.penghasilan_sd) : Infinity;
        if (bruto>=dari && bruto<sd) found=t;
    });
    return found;
}

var _estimasiPph = 0;

function hitungSemua() {
    var gapok = parseFloat(document.getElementById('f-gapok').value)||0;
    var totalP=0;
    document.querySelectorAll('#penerimaan-rows select[name="penerimaan_val[]"]').forEach(function(s){ totalP+=parseFloat(s.value)||0; });
    var totalT=0;
    document.querySelectorAll('#potongan-rows select[name="potongan_val[]"]').forEach(function(s){ totalT+=parseFloat(s.value)||0; });
    var bruto  = gapok+totalP;
    var pph21  = parseFloat(document.getElementById('f-pph21').value)||0;
    var ptkpId = document.getElementById('f-ptkp').value;
    var pphEst = 0, terInfo = '— pilih kategori TER untuk melihat estimasi —';

    if (ptkpId && bruto>0) {
        var ter = getTarif(ptkpId, bruto);
        if (ter) {
            var tarif = parseFloat(ter.tarif_persen);
            pphEst = Math.round(bruto*tarif/100);
            var nm=''; masterPtkp.forEach(function(p){ if(p.id==ptkpId) nm=p.nama; });
            terInfo = nm+' | Tarif '+tarif.toFixed(2)+'% × Bruto '+rp(bruto)+' = '+rp(pphEst);
        } else {
            terInfo = '⚠ Rentang tarif tidak ditemukan untuk bruto ini';
        }
    }
    _estimasiPph = pphEst;

    document.getElementById('disp-gapok').textContent      = rp(gapok);
    document.getElementById('disp-penerimaan').textContent = rp(totalP);
    document.getElementById('disp-bruto').textContent      = rp(bruto);
    document.getElementById('disp-potongan').textContent   = rp(totalT);
    document.getElementById('disp-pph-val').textContent    = rp(pph21);
    document.getElementById('disp-pph-est').textContent    = rp(pphEst);
    document.getElementById('disp-ter-info').textContent   = terInfo;
    document.getElementById('disp-bersih').textContent     = rp(Math.max(0, bruto-totalT-pph21));
}

function pakaiEstimasi() {
    var sel=document.getElementById('f-pph21'), est=Math.round(_estimasiPph);
    var bi=0, bd=Infinity;
    for (var i=0;i<sel.options.length;i++) {
        var d=Math.abs(parseFloat(sel.options[i].value)-est);
        if (d<bd){ bd=d; bi=i; }
    }
    sel.selectedIndex=bi;
    hitungSemua();
}

function resetForm() {
    document.getElementById('form-user-id').value='';
    document.getElementById('sel-karyawan').value='';
    document.getElementById('f-gapok').value='0';
    document.getElementById('f-ptkp').value='';
    document.getElementById('f-pph21').value='0';
    document.getElementById('f-catatan').value='';
    document.getElementById('penerimaan-rows').innerHTML='<div class="ph-msg" style="font-size:12px;color:#9ca3af;text-align:center;padding:5px 0;">Klik Tambah untuk menambah komponen penerimaan</div>';
    document.getElementById('potongan-rows').innerHTML='<div class="ph-msg" style="font-size:12px;color:#9ca3af;text-align:center;padding:5px 0;">Klik Tambah untuk menambah komponen potongan</div>';
    document.getElementById('info-karyawan').style.display='none';
    hitungSemua();
}

function bukaFormBaru() {
    resetForm();
    document.getElementById('form-title').textContent='Set Data Gaji Karyawan';
    openModal('m-form');
}

function loadEditGaji(dgId, userId) {
    resetForm();
    document.getElementById('form-user-id').value=userId;
    var sel=document.getElementById('sel-karyawan');
    sel.value=userId;
    pilihKaryawan(sel);
    document.getElementById('form-title').textContent='Edit Data Gaji Karyawan';

    fetch('<?= APP_URL ?>/ajax/get_data_gaji.php?id='+dgId, {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){
            // Gaji pokok
            var gs=document.getElementById('f-gapok');
            for (var i=0;i<gs.options.length;i++){
                if (Math.abs(parseFloat(gs.options[i].value)-parseFloat(d.gaji_pokok))<1){ gs.selectedIndex=i; break; }
            }
            // PTKP
            if (d.ptkp_id) document.getElementById('f-ptkp').value=d.ptkp_id;
            // PPh21
            var ps=document.getElementById('f-pph21'), pv=parseFloat(d.pph21)||0, pf=false;
            for (var i=0;i<ps.options.length;i++){
                if (Math.abs(parseFloat(ps.options[i].value)-pv)<1){ ps.selectedIndex=i; pf=true; break; }
            }
            if (!pf && pv>0) {
                var o=new Option('Rp '+Math.round(pv).toLocaleString('id-ID'), pv, true, true);
                ps.appendChild(o);
            }
            // Catatan
            if (d.catatan) document.getElementById('f-catatan').value=d.catatan;
            // Komponen
            (d.penerimaan||[]).forEach(function(p){ addBaris('penerimaan',p.penerimaan_id,p.nilai); });
            (d.potongan  ||[]).forEach(function(p){ addBaris('potongan',  p.potongan_id,  p.nilai); });
            hitungSemua();
        })
        .catch(function(){ hitungSemua(); });
    openModal('m-form');
}

function hapusGaji(id, nama) {
    document.getElementById('hapus-id').value=id;
    document.getElementById('hapus-label').textContent='Hapus data gaji: '+nama+'?';
    openModal('m-hapus');
}
</script>

<?php include '../includes/footer.php'; ?>