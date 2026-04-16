<?php
// pages/transaksi_gaji.php
ob_start();
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'keuangan'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title  = 'Transaksi Gaji';
$active_menu = 'transaksi_gaji';

$nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// ── Auto-create tabel transaksi ───────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `transaksi_gaji` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`          INT UNSIGNED NOT NULL,
        `periode`          CHAR(7)      NOT NULL COMMENT 'Format: YYYY-MM',
        `gaji_pokok`       DECIMAL(15,2) NOT NULL DEFAULT 0,
        `total_penerimaan` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `total_potongan`   DECIMAL(15,2) NOT NULL DEFAULT 0,
        `pph21`            DECIMAL(15,2) NOT NULL DEFAULT 0,
        `gaji_bersih`      DECIMAL(15,2) NOT NULL DEFAULT 0,
        `ptkp_id`          INT UNSIGNED  DEFAULT NULL,
        `ptkp_kode`        VARCHAR(20)   DEFAULT NULL,
        `bank`             VARCHAR(50)   DEFAULT NULL,
        `no_rekening`      VARCHAR(50)   DEFAULT NULL,
        `atas_nama_rek`    VARCHAR(100)  DEFAULT NULL,
        `status`           ENUM('draft','dibayar') NOT NULL DEFAULT 'draft',
        `tgl_bayar`        DATE          DEFAULT NULL,
        `approved_by`      INT UNSIGNED  DEFAULT NULL,
        `approved_at`      DATETIME      DEFAULT NULL,
        `created_by`       INT UNSIGNED  DEFAULT NULL,
        `created_at`       DATETIME      DEFAULT NULL,
        KEY `idx_user_id` (`user_id`),
        KEY `idx_periode` (`periode`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `transaksi_gaji_detail` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `transaksi_id`  INT UNSIGNED NOT NULL,
        `tipe`          ENUM('penerimaan','potongan') NOT NULL,
        `penerimaan_id` INT UNSIGNED DEFAULT NULL,
        `potongan_id`   INT UNSIGNED DEFAULT NULL,
        `nama_komponen` VARCHAR(100) DEFAULT NULL,
        `nilai`         DECIMAL(15,2) NOT NULL DEFAULT 0,
        KEY `idx_tid` (`transaksi_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// ── Migrate transaksi_gaji ───────────────────────────────────────────────────
try {
    $cols_tg = $pdo->query("SHOW COLUMNS FROM transaksi_gaji")->fetchAll(PDO::FETCH_COLUMN);
    foreach ([
        'pph21'         => "ALTER TABLE transaksi_gaji ADD COLUMN `pph21` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `total_potongan`",
        'ptkp_id'       => "ALTER TABLE transaksi_gaji ADD COLUMN `ptkp_id` INT UNSIGNED DEFAULT NULL AFTER `pph21`",
        'ptkp_kode'     => "ALTER TABLE transaksi_gaji ADD COLUMN `ptkp_kode` VARCHAR(20) DEFAULT NULL AFTER `ptkp_id`",
        'bank'          => "ALTER TABLE transaksi_gaji ADD COLUMN `bank` VARCHAR(50) DEFAULT NULL AFTER `ptkp_kode`",
        'no_rekening'   => "ALTER TABLE transaksi_gaji ADD COLUMN `no_rekening` VARCHAR(50) DEFAULT NULL AFTER `bank`",
        'atas_nama_rek' => "ALTER TABLE transaksi_gaji ADD COLUMN `atas_nama_rek` VARCHAR(100) DEFAULT NULL AFTER `no_rekening`",
    ] as $col => $sql) {
        if (!in_array($col, $cols_tg)) $pdo->exec($sql);
    }
    foreach (['bank_nama','bank_rekening','bank_atas_nama'] as $lama) {
        if (in_array($lama, $cols_tg)) $pdo->exec("ALTER TABLE transaksi_gaji DROP COLUMN `$lama`");
    }
} catch (Exception $e) {}

// ── Migrate data_gaji: pastikan pph21 & ptkp_id ada (agar generate aman) ─────
try {
    $cols_dg = $pdo->query("SHOW COLUMNS FROM data_gaji")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('ptkp_id', $cols_dg))
        $pdo->exec("ALTER TABLE data_gaji ADD COLUMN `ptkp_id` INT UNSIGNED DEFAULT NULL AFTER `gaji_pokok`");
    if (!in_array('pph21', $cols_dg))
        $pdo->exec("ALTER TABLE data_gaji ADD COLUMN `pph21` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `ptkp_id`");
} catch (Exception $e) {}

// ── Migrate transaksi_gaji_detail: kolom nama_komponen ───────────────────────
try {
    $cols_tgd = $pdo->query("SHOW COLUMNS FROM transaksi_gaji_detail")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('nama_komponen', $cols_tgd))
        $pdo->exec("ALTER TABLE transaksi_gaji_detail ADD COLUMN `nama_komponen` VARCHAR(100) DEFAULT NULL AFTER `potongan_id`");
} catch (Exception $e) {}

// ── POST HANDLER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    // ── Generate ──────────────────────────────────────────────────────────────
    if ($act === 'generate') {
        $bulan   = (int)($_POST['bulan'] ?? date('n'));
        $tahun   = (int)($_POST['tahun'] ?? date('Y'));
        $periode = sprintf('%04d-%02d', $tahun, $bulan);

        $cek = $pdo->prepare("SELECT COUNT(*) FROM transaksi_gaji WHERE periode=?");
        $cek->execute([$periode]);
        if ($cek->fetchColumn() > 0) {
            setFlash('danger', "Transaksi gaji periode <strong>$periode</strong> sudah pernah di-generate.");
        } else {
            // Ambil karyawan aktif yang sudah punya data gaji
            $karys = $pdo->query("
                SELECT dg.id, dg.user_id, dg.gaji_pokok,
                       dg.ptkp_id,
                       COALESCE(dg.pph21, 0) AS pph21,
                       u.nama,
                       sk.divisi, sk.status_kepegawaian, sk.nik_rs,
                       sk.bank, sk.no_rekening, sk.atas_nama_rek,
                       pp.kode AS ptkp_kode
                FROM data_gaji dg
                JOIN users u               ON u.id       = dg.user_id
                LEFT JOIN sdm_karyawan sk  ON sk.user_id = dg.user_id
                LEFT JOIN master_pph21_ptkp pp ON pp.id  = dg.ptkp_id
                WHERE u.status = 'aktif'
            ")->fetchAll(PDO::FETCH_ASSOC);

            if (!$karys) {
                setFlash('danger', 'Belum ada data gaji. Isi Data Gaji karyawan terlebih dahulu.');
            } else {
                $cnt = 0;
                foreach ($karys as $k) {
                    // Ambil detail komponen
                    $det = $pdo->prepare("
                        SELECT dgd.*,
                               mp.nama  AS nama_penerimaan,
                               mpt.nama AS nama_potongan
                        FROM data_gaji_detail dgd
                        LEFT JOIN master_penerimaan mp  ON mp.id  = dgd.penerimaan_id
                        LEFT JOIN master_potongan   mpt ON mpt.id = dgd.potongan_id
                        WHERE dgd.data_gaji_id = ?
                    ");
                    $det->execute([$k['id']]);
                    $detail_rows = $det->fetchAll(PDO::FETCH_ASSOC);

                    $total_p = 0;
                    $total_t = 0;
                    foreach ($detail_rows as $d) {
                        if ($d['tipe'] === 'penerimaan') $total_p += $d['nilai'];
                        else                             $total_t += $d['nilai'];
                    }

                    // Gaji bersih = gaji pokok + penerimaan - potongan - pph21
                    $pph21   = (float)($k['pph21'] ?? 0); // sudah COALESCE dari query
                    $bersih  = $k['gaji_pokok'] + $total_p - $total_t - $pph21;

                    $pdo->prepare("
                        INSERT INTO transaksi_gaji
                        (user_id,periode,gaji_pokok,total_penerimaan,total_potongan,pph21,gaji_bersih,
                         ptkp_id,ptkp_kode,bank,no_rekening,atas_nama_rek,status,created_by,created_at)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'draft',?,NOW())
                    ")->execute([
                        $k['user_id'], $periode,
                        $k['gaji_pokok'], $total_p, $total_t, $pph21, $bersih,
                        $k['ptkp_id'] ?? null,
                        $k['ptkp_kode'] ?? null,
                        $k['bank'] ?? null,
                        $k['no_rekening'] ?? null,
                        $k['atas_nama_rek'] ?? null,
                        $_SESSION['user_id'],
                    ]);
                    $tg_id = (int)$pdo->lastInsertId();

                    // Salin detail komponen + simpan nama komponen untuk snapshot
                    foreach ($detail_rows as $d) {
                        $nama_k = $d['tipe'] === 'penerimaan'
                            ? ($d['nama_penerimaan'] ?? null)
                            : ($d['nama_potongan']   ?? null);
                        $pdo->prepare("
                            INSERT INTO transaksi_gaji_detail
                            (transaksi_id,tipe,penerimaan_id,potongan_id,nama_komponen,nilai)
                            VALUES (?,?,?,?,?,?)
                        ")->execute([
                            $tg_id, $d['tipe'],
                            $d['penerimaan_id'] ?? null,
                            $d['potongan_id']   ?? null,
                            $nama_k, $d['nilai'],
                        ]);
                    }
                    $cnt++;
                }
                setFlash('success', "Berhasil generate gaji periode <strong>{$nama_bulan[$bulan]} $tahun</strong> untuk <strong>$cnt</strong> karyawan.");
            }
        }
        redirect(APP_URL . '/pages/transaksi_gaji.php');
    }

    // ── Approve ───────────────────────────────────────────────────────────────
    if ($act === 'approve') {
        $periode   = trim($_POST['periode'] ?? '');
        $tgl_bayar = trim($_POST['tgl_bayar'] ?? date('Y-m-d'));
        if ($periode) {
            $pdo->prepare("UPDATE transaksi_gaji
                SET status='dibayar',tgl_bayar=?,approved_by=?,approved_at=NOW()
                WHERE periode=? AND status='draft'")
                ->execute([$tgl_bayar, $_SESSION['user_id'], $periode]);
            $pt = explode('-', $periode);
            setFlash('success', "Gaji periode <strong>{$nama_bulan[(int)$pt[1]]} {$pt[0]}</strong> berhasil disetujui &amp; ditandai DIBAYAR.");
        }
        redirect(APP_URL . '/pages/transaksi_gaji.php');
    }

    // ── Hapus Periode ─────────────────────────────────────────────────────────
    if ($act === 'hapus_periode') {
        $periode = trim($_POST['periode'] ?? '');
        if ($periode) {
            $ids = $pdo->prepare("SELECT id FROM transaksi_gaji WHERE periode=?");
            $ids->execute([$periode]);
            foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $tid) {
                $pdo->prepare("DELETE FROM transaksi_gaji_detail WHERE transaksi_id=?")->execute([$tid]);
            }
            $pdo->prepare("DELETE FROM transaksi_gaji WHERE periode=?")->execute([$periode]);
            $pt = explode('-', $periode);
            setFlash('success', "Transaksi gaji periode <strong>{$nama_bulan[(int)$pt[1]]} {$pt[0]}</strong> berhasil dihapus.");
        }
        redirect(APP_URL . '/pages/transaksi_gaji.php');
    }
}

// ── FETCH DATA ────────────────────────────────────────────────────────────────
$f_periode = trim($_GET['periode'] ?? '');
$f_status  = trim($_GET['status']  ?? '');
$f_q       = trim($_GET['q']       ?? '');

$all_periode = $pdo->query("SELECT DISTINCT periode FROM transaksi_gaji ORDER BY periode DESC")->fetchAll(PDO::FETCH_COLUMN);

// Ringkasan semua periode
$all_rows_sum = $pdo->query("SELECT tg.periode,tg.status,tg.gaji_bersih,tg.tgl_bayar FROM transaksi_gaji tg ORDER BY tg.periode DESC")->fetchAll(PDO::FETCH_ASSOC);
$summary = [];
foreach ($all_rows_sum as $r) {
    $p = $r['periode'];
    if (!isset($summary[$p])) $summary[$p] = ['total'=>0,'dibayar'=>0,'draft'=>0,'sum_bersih'=>0,'tgl_bayar'=>null];
    $summary[$p]['total']++;
    $summary[$p]['sum_bersih'] += $r['gaji_bersih'];
    if ($r['status'] === 'dibayar') { $summary[$p]['dibayar']++; if (!$summary[$p]['tgl_bayar']) $summary[$p]['tgl_bayar'] = $r['tgl_bayar']; }
    else $summary[$p]['draft']++;
}

// Detail (hanya jika ada filter)
$rows = [];
if ($f_periode || $f_status || $f_q) {
    $where_p = []; $params_p = [];
    if ($f_periode) { $where_p[] = 'tg.periode=?'; $params_p[] = $f_periode; }
    if ($f_status)  { $where_p[] = 'tg.status=?';  $params_p[] = $f_status; }
    if ($f_q)       { $where_p[] = 'u.nama LIKE ?'; $params_p[] = "%$f_q%"; }
    $wsql = $where_p ? 'WHERE '.implode(' AND ',$where_p) : '';

    $rs = $pdo->prepare("
        SELECT tg.*, u.nama, sk.divisi, sk.nik_rs
        FROM transaksi_gaji tg
        JOIN users u              ON u.id       = tg.user_id
        LEFT JOIN sdm_karyawan sk ON sk.user_id = tg.user_id
        $wsql
        ORDER BY tg.periode DESC, u.nama ASC
    ");
    $rs->execute($params_p);
    $rows = $rs->fetchAll(PDO::FETCH_ASSOC);
}

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-receipt" style="color:#d97706;"></i> &nbsp;Transaksi Gaji</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Transaksi Gaji</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Tombol Generate -->
  <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
    <button onclick="openModal('m-generate')" class="btn btn-warning">
      <i class="fa fa-bolt"></i> Generate Gaji Bulanan
    </button>
  </div>

  <!-- Ringkasan per Periode -->
  <?php if ($summary): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(255px,1fr));gap:14px;margin-bottom:20px;">
    <?php foreach ($summary as $per => $s):
      [$yr,$mn] = explode('-', $per);
      $isDraft  = $s['draft'] > 0;
    ?>
    <div style="background:#fff;border:1px solid <?= $isDraft?'#fde68a':'#6ee7b7' ?>;border-radius:10px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <div>
          <div style="font-size:14px;font-weight:800;color:#111827;"><?= $nama_bulan[(int)$mn] ?> <?= $yr ?></div>
          <div style="font-size:11px;color:#9ca3af;margin-top:1px;"><?= $s['total'] ?> karyawan</div>
        </div>
        <?php if ($isDraft): ?>
        <span style="background:#fef9c3;color:#854d0e;border:1px solid #fde68a;font-size:11px;font-weight:700;padding:2px 10px;border-radius:20px;">Draft</span>
        <?php else: ?>
        <span style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;font-size:11px;font-weight:700;padding:2px 10px;border-radius:20px;"><i class="fa fa-check"></i> Dibayar</span>
        <?php endif; ?>
      </div>
      <div style="font-size:18px;font-weight:800;color:#111827;margin-bottom:2px;">Rp <?= number_format($s['sum_bersih'],0,',','.') ?></div>
      <div style="font-size:11px;color:#9ca3af;margin-bottom:4px;">Total gaji bersih</div>
      <?php if ($s['tgl_bayar']): ?>
      <div style="font-size:11px;color:#6b7280;margin-bottom:10px;"><i class="fa fa-calendar-check" style="color:#22c55e;"></i> Dibayar: <?= date('d M Y', strtotime($s['tgl_bayar'])) ?></div>
      <?php else: ?>
      <div style="margin-bottom:10px;"></div>
      <?php endif; ?>
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <a href="?periode=<?= $per ?>" class="btn btn-default btn-sm"><i class="fa fa-eye"></i> Detail</a>
        <?php if ($isDraft): ?>
        <button onclick="approveGaji('<?= $per ?>')" class="btn btn-success btn-sm"><i class="fa fa-check"></i> Approve</button>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/pages/slip_gaji.php?periode=<?= $per ?>" target="_blank" class="btn btn-sm" style="background:#7c3aed;color:#fff;border:none;" title="Cetak semua slip periode ini">
          <i class="fa fa-print"></i> Cetak
        </a>
        <button onclick="hapusPeriode('<?= $per ?>')" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="panel">
    <div class="panel-bd" style="text-align:center;padding:50px;color:#9ca3af;">
      <i class="fa fa-receipt" style="font-size:36px;display:block;margin-bottom:12px;opacity:.2;"></i>
      Belum ada transaksi gaji.<br>Klik <strong>Generate Gaji Bulanan</strong> untuk memulai.
    </div>
  </div>
  <?php endif; ?>

  <!-- Filter bar (selalu tampil jika ada periode) -->
  <?php if ($all_periode): ?>
  <div class="panel" style="margin-bottom:14px;">
    <div class="panel-bd">
      <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <select name="periode" class="form-control" style="height:34px;min-width:160px;">
          <option value="">— Pilih Periode —</option>
          <?php foreach ($all_periode as $ap):
            [$ay,$am] = explode('-', $ap); ?>
          <option value="<?=$ap?>" <?=$f_periode===$ap?'selected':''?>><?= $nama_bulan[(int)$am].' '.$ay ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="form-control" style="height:34px;min-width:120px;">
          <option value="">— Semua Status —</option>
          <option value="draft"   <?=$f_status==='draft'  ?'selected':''?>>Draft</option>
          <option value="dibayar" <?=$f_status==='dibayar'?'selected':''?>>Dibayar</option>
        </select>
        <input type="text" name="q" value="<?= htmlspecialchars($f_q) ?>"
               placeholder="Cari nama…" class="form-control" style="width:170px;height:34px;">
        <button type="submit" class="btn btn-primary" style="height:34px;"><i class="fa fa-search"></i> Filter</button>
        <?php if ($f_periode||$f_status||$f_q): ?>
        <a href="transaksi_gaji.php" class="btn btn-default" style="height:34px;">Reset</a>
        <?php endif; ?>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tabel Detail (hanya jika ada filter aktif) -->
  <?php if ($rows): ?>
  <?php
    $grand_bersih = array_sum(array_column($rows, 'gaji_bersih'));
    $grand_pph    = array_sum(array_column($rows, 'pph21'));
    $pt_label     = '';
    if ($f_periode) { [$ly,$lm] = explode('-',$f_periode); $pt_label = $nama_bulan[(int)$lm].' '.$ly; }
  ?>
  <div class="panel">
    <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;">
      <h5><i class="fa fa-list"></i> &nbsp;Detail Transaksi <?= $pt_label ? "— $pt_label" : '' ?>
        <span style="color:#aaa;font-weight:400;">(<?= count($rows) ?> karyawan)</span>
      </h5>
      <?php if ($f_periode): ?>
      <a href="<?= APP_URL ?>/pages/slip_gaji.php?periode=<?= $f_periode ?>" target="_blank"
         class="btn btn-sm" style="background:#7c3aed;color:#fff;border:none;font-size:12px;">
        <i class="fa fa-print"></i> Cetak Semua Slip
      </a>
      <?php endif; ?>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:36px;">#</th>
            <th>Karyawan</th>
            <th>Divisi</th>
            <th style="text-align:right;">Gaji Pokok</th>
            <th style="text-align:right;">+ Penerimaan</th>
            <th style="text-align:right;">− Potongan</th>
            <th style="text-align:right;">− PPh 21</th>
            <th style="text-align:right;">Gaji Bersih</th>
            <th style="text-align:center;">Status</th>
            <th style="text-align:center;width:60px;">Slip</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $i => $r):
            $words   = array_filter(explode(' ', trim($r['nama'])));
            $inisial = strtoupper(implode('', array_map(fn($w) => mb_substr($w,0,1), array_slice(array_values($words),0,2))));
          ?>
          <tr>
            <td style="color:#bbb;font-size:12px;"><?= $i+1 ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <?= htmlspecialchars($inisial ?: '?') ?>
                </div>
                <div>
                  <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($r['nama']) ?></div>
                  <div style="font-size:10px;color:#9ca3af;"><?= htmlspecialchars($r['nik_rs'] ?? '—') ?></div>
                </div>
              </div>
            </td>
            <td style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($r['divisi'] ?? '—') ?></td>
            <td style="text-align:right;font-size:12px;">Rp <?= number_format($r['gaji_pokok'],0,',','.') ?></td>
            <td style="text-align:right;color:#065f46;font-size:12px;">+ Rp <?= number_format($r['total_penerimaan'],0,',','.') ?></td>
            <td style="text-align:right;color:#991b1b;font-size:12px;">− Rp <?= number_format($r['total_potongan'],0,',','.') ?></td>
            <td style="text-align:right;color:#7c3aed;font-size:12px;">− Rp <?= number_format($r['pph21'],0,',','.') ?></td>
            <td style="text-align:right;font-weight:800;font-size:14px;color:#111827;">Rp <?= number_format($r['gaji_bersih'],0,',','.') ?></td>
            <td style="text-align:center;">
              <?php if ($r['status'] === 'dibayar'): ?>
              <span style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;"><i class="fa fa-check"></i> Dibayar</span>
              <?php else: ?>
              <span style="background:#fef9c3;color:#854d0e;border:1px solid #fde68a;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;">Draft</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;">
              <a href="<?= APP_URL ?>/pages/slip_gaji.php?id=<?= $r['id'] ?>" target="_blank"
                 class="btn btn-sm btn-default" title="Cetak Slip">
                <i class="fa fa-print"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#f8fafc;">
            <td colspan="6" style="text-align:right;font-weight:700;padding:10px 14px;font-size:13px;">TOTAL PPh 21:</td>
            <td style="text-align:right;font-weight:800;font-size:13px;color:#7c3aed;padding:10px 14px;">− Rp <?= number_format($grand_pph,0,',','.') ?></td>
            <td colspan="3"></td>
          </tr>
          <tr style="background:#f0f9ff;border-top:2px solid #bfdbfe;">
            <td colspan="7" style="text-align:right;font-weight:700;padding:10px 14px;font-size:13px;">TOTAL GAJI BERSIH:</td>
            <td style="text-align:right;font-weight:900;font-size:16px;color:#065f46;padding:10px 14px;">Rp <?= number_format($grand_bersih,0,',','.') ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <?php elseif ($f_periode || $f_status || $f_q): ?>
  <div class="panel"><div class="panel-bd" style="text-align:center;padding:30px;color:#9ca3af;">Tidak ada data untuk filter yang dipilih.</div></div>
  <?php endif; ?>

</div>

<!-- MODAL GENERATE -->
<div class="modal-ov" id="m-generate">
  <div style="background:#fff;width:100%;max-width:430px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mIn .2s ease;overflow:hidden;">
    <div style="padding:15px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
      <div style="font-size:14px;font-weight:700;"><i class="fa fa-bolt" style="color:#d97706;"></i> Generate Gaji Bulanan</div>
      <button onclick="closeModal('m-generate')" class="btn btn-sm btn-default"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="generate">
      <div style="padding:20px;display:flex;flex-direction:column;gap:14px;">
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:11px 14px;font-size:12px;color:#92400e;display:flex;gap:8px;">
          <i class="fa fa-triangle-exclamation" style="color:#f59e0b;margin-top:1px;flex-shrink:0;"></i>
          <div>Setiap periode hanya bisa di-generate <strong>sekali</strong>. Sistem akan membuat transaksi untuk semua karyawan aktif yang sudah memiliki Data Gaji. Komponen gaji (termasuk PPh 21) di-snapshot dari data saat ini.</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Bulan</label>
            <select name="bulan" class="form-control">
              <?php foreach (range(1,12) as $m): ?>
              <option value="<?=$m?>" <?=$m==(int)date('n')?'selected':''?>><?= $nama_bulan[$m] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Tahun</label>
            <select name="tahun" class="form-control">
              <?php for ($y=date('Y'); $y>=date('Y')-3; $y--): ?>
              <option value="<?=$y?>" <?=$y==(int)date('Y')?'selected':''?>><?=$y?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
      </div>
      <div style="padding:12px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" onclick="closeModal('m-generate')" class="btn btn-default">Batal</button>
        <button type="submit" class="btn btn-warning"><i class="fa fa-bolt"></i> Generate Sekarang</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL APPROVE -->
<div class="modal-ov" id="m-approve">
  <div style="background:#fff;width:100%;max-width:400px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mIn .2s ease;overflow:hidden;">
    <div style="padding:15px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
      <div style="font-size:14px;font-weight:700;"><i class="fa fa-check-circle" style="color:#22c55e;"></i> Approve &amp; Tandai Dibayar</div>
      <button onclick="closeModal('m-approve')" class="btn btn-sm btn-default"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="approve">
      <input type="hidden" name="periode" id="approve-periode">
      <div style="padding:20px;display:flex;flex-direction:column;gap:12px;">
        <div style="font-size:13px;color:#374151;">Approve gaji periode: <strong id="approve-label"></strong></div>
        <div>
          <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Tanggal Bayar</label>
          <input type="date" name="tgl_bayar" value="<?= date('Y-m-d') ?>" required class="form-control">
        </div>
      </div>
      <div style="padding:12px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" onclick="closeModal('m-approve')" class="btn btn-default">Batal</button>
        <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Konfirmasi Bayar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL HAPUS PERIODE -->
<div class="modal-ov" id="m-hapus-per">
  <div style="background:#fff;width:100%;max-width:380px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mIn .2s ease;">
    <div style="padding:24px;text-align:center;">
      <div style="width:52px;height:52px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
        <i class="fa fa-trash" style="color:#ef4444;font-size:20px;"></i>
      </div>
      <div style="font-size:15px;font-weight:700;margin-bottom:6px;">Hapus Periode Gaji?</div>
      <div style="font-size:13px;color:#6b7280;">Semua transaksi periode <strong id="hapus-per-label"></strong> akan dihapus permanen.</div>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="hapus_periode">
      <input type="hidden" name="periode" id="hapus-per-val">
      <div style="padding:0 20px 20px;display:flex;gap:8px;justify-content:center;">
        <button type="button" onclick="closeModal('m-hapus-per')" class="btn btn-default">Batal</button>
        <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Ya, Hapus</button>
      </div>
    </form>
  </div>
</div>

<script>
var namabulan = <?= json_encode($nama_bulan) ?>;
function approveGaji(periode) {
    var p = periode.split('-');
    document.getElementById('approve-periode').value = periode;
    document.getElementById('approve-label').textContent = (namabulan[parseInt(p[1])] || p[1]) + ' ' + p[0];
    openModal('m-approve');
}
function hapusPeriode(periode) {
    var p = periode.split('-');
    document.getElementById('hapus-per-val').value = periode;
    document.getElementById('hapus-per-label').textContent = (namabulan[parseInt(p[1])] || p[1]) + ' ' + p[0];
    openModal('m-hapus-per');
}
</script>

<?php include '../includes/footer.php'; ?>