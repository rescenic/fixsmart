<?php
// pages/pengajuan_cuti.php
session_start();
require_once '../config.php';
requireLogin();

$page_title  = 'Pengajuan Cuti';
$active_menu = 'pengajuan_cuti';
$uid = (int)$_SESSION['user_id'];

// ── Info karyawan + atasan ────────────────────────────────
$me = $pdo->prepare("
    SELECT u.*, COALESCE(s.atasan_id,0) atasan_id,
           COALESCE(ua.nama,'') atasan_nama,
           COALESCE(ua.role,'') atasan_role,
           COALESCE(u.divisi,'') divisi_ku
    FROM users u
    LEFT JOIN sdm_karyawan s ON s.user_id=u.id
    LEFT JOIN users ua ON ua.id=s.atasan_id
    WHERE u.id=?
");
$me->execute([$uid]);
$me = $me->fetch(PDO::FETCH_ASSOC);

// Apakah saya atasan langsung seseorang?
$cek_atasan = $pdo->prepare("SELECT COUNT(*) FROM sdm_karyawan WHERE atasan_id=?");
$cek_atasan->execute([$uid]);
$is_atasan = (int)$cek_atasan->fetchColumn() > 0;

// Direktur
$direktur = $pdo->query("SELECT id,nama FROM users WHERE role='admin' AND status='aktif' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Jenis cuti aktif
$jenis_cuti = $pdo->query("SELECT * FROM master_jenis_cuti WHERE status='aktif' ORDER BY urutan,nama")->fetchAll(PDO::FETCH_ASSOC);

// Delegasi
$delegasi_list = [];
if ($me['divisi_ku']) {
    $dq = $pdo->prepare("SELECT u.id, u.nama FROM users u WHERE u.status='aktif' AND u.divisi=? AND u.id!=? ORDER BY u.nama");
    $dq->execute([$me['divisi_ku'], $uid]);
    $delegasi_list = $dq->fetchAll(PDO::FETCH_ASSOC);
}
if ($is_atasan) {
    $dq2 = $pdo->prepare("SELECT id,nama,divisi FROM users WHERE status='aktif' AND id!=? ORDER BY divisi,nama");
    $dq2->execute([$uid]);
    $delegasi_list = $dq2->fetchAll(PDO::FETCH_ASSOC);
}

// ── AJAX: submit pengajuan ────────────────────────────────
if (isset($_POST['ajax_ajukan'])) {
    header('Content-Type: application/json; charset=utf-8');
    $jenis_id    = (int)($_POST['jenis_cuti_id'] ?? 0);
    $tanggal_raw = $_POST['tanggal_list'] ?? '';
    $keperluan   = trim($_POST['keperluan'] ?? '');
    $delegasi_id = (int)($_POST['delegasi_id'] ?? 0) ?: null;
    $cat_del     = trim($_POST['catatan_delegasi'] ?? '') ?: null;

    $tanggal_arr = [];
    try { $tanggal_arr = json_decode($tanggal_raw, true) ?? []; } catch(Exception $e) {}
    $tanggal_arr = array_filter($tanggal_arr, fn($t)=>preg_match('/^\d{4}-\d{2}-\d{2}$/',$t));
    $tanggal_arr = array_unique(array_values($tanggal_arr));
    sort($tanggal_arr);

    if (!$jenis_id) { echo json_encode(['ok'=>false,'msg'=>'Pilih jenis cuti.']); exit; }
    if (empty($tanggal_arr)) { echo json_encode(['ok'=>false,'msg'=>'Pilih minimal 1 tanggal.']); exit; }
    if (!$keperluan) { echo json_encode(['ok'=>false,'msg'=>'Isi keperluan/alasan.']); exit; }

    $tahun = (int)date('Y', strtotime($tanggal_arr[0]));
    $jq = $pdo->prepare("SELECT kuota,sisa FROM jatah_cuti WHERE user_id=? AND jenis_cuti_id=? AND tahun=?");
    $jq->execute([$uid, $jenis_id, $tahun]);
    $jatah = $jq->fetch(PDO::FETCH_ASSOC);
    $jns = $pdo->prepare("SELECT kuota_default FROM master_jenis_cuti WHERE id=?"); $jns->execute([$jenis_id]);
    $jns = $jns->fetch(PDO::FETCH_ASSOC);
    if ($jatah && $jns['kuota_default'] > 0 && count($tanggal_arr) > (int)$jatah['sisa']) {
        echo json_encode(['ok'=>false,'msg'=>'Sisa jatah tidak cukup. Sisa: '.$jatah['sisa'].' hari, diajukan: '.count($tanggal_arr).' hari.']);
        exit;
    }

    $approver1_id = null;
    if ($is_atasan) {
        $approver1_id = $direktur['id'] ?? null;
    } else {
        $approver1_id = $me['atasan_id'] ?: null;
    }

    $jumlah_hari = count($tanggal_arr);
    $tgl_mulai   = $tanggal_arr[0];
    $tgl_selesai = end($tanggal_arr);

    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO pengajuan_cuti (user_id,jenis_cuti_id,jumlah_hari,tgl_mulai,tgl_selesai,keperluan,delegasi_id,catatan_delegasi,approver1_id,status) VALUES (?,?,?,?,?,?,?,?,?,'menunggu')")
            ->execute([$uid,$jenis_id,$jumlah_hari,$tgl_mulai,$tgl_selesai,$keperluan,$delegasi_id,$cat_del,$approver1_id]);
        $pid = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO cuti_tanggal (pengajuan_id, tanggal) VALUES (?,?)");
        foreach ($tanggal_arr as $t) $ins->execute([$pid, $t]);
        $pdo->commit();
        echo json_encode(['ok'=>true,'msg'=>'Pengajuan cuti berhasil dikirim.','id'=>$pid]);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Riwayat pengajuan saya ────────────────────────────────
$riwayat = $pdo->prepare("
    SELECT pc.*, jc.nama jenis_nama, jc.kode jenis_kode,
           u2.nama delegasi_nama,
           ua1.nama approver1_nama, ua2.nama approver2_nama
    FROM pengajuan_cuti pc
    LEFT JOIN master_jenis_cuti jc ON jc.id = pc.jenis_cuti_id
    LEFT JOIN users u2  ON u2.id  = pc.delegasi_id
    LEFT JOIN users ua1 ON ua1.id = pc.approver1_id
    LEFT JOIN users ua2 ON ua2.id = pc.approver2_id
    WHERE pc.user_id = ?
    ORDER BY pc.created_at DESC LIMIT 20
");
$riwayat->execute([$uid]);
$riwayat = $riwayat->fetchAll(PDO::FETCH_ASSOC);

// Jatah cuti tahun ini (semua jenis aktif, meski belum ada data jatah)
$jatah_saya = $pdo->prepare("
    SELECT jc.id jenis_cuti_id, jc.kode, jc.nama jenis_nama, jc.kuota_default,
           COALESCE(jt.kuota, jc.kuota_default)  kuota,
           COALESCE(jt.terpakai, 0)               terpakai,
           COALESCE(jt.sisa, jc.kuota_default)    sisa
    FROM master_jenis_cuti jc
    LEFT JOIN jatah_cuti jt ON jt.jenis_cuti_id=jc.id AND jt.user_id=? AND jt.tahun=?
    WHERE jc.status='aktif'
    ORDER BY jc.urutan
");
$jatah_saya->execute([$uid, date('Y')]);
$jatah_saya = $jatah_saya->fetchAll(PDO::FETCH_ASSOC);

// Statistik ringkas pengajuan tahun ini
$stat = $pdo->prepare("
    SELECT
        COUNT(*) total,
        SUM(CASE WHEN status='menunggu'  THEN 1 ELSE 0 END) menunggu,
        SUM(CASE WHEN status='disetujui' THEN 1 ELSE 0 END) disetujui,
        SUM(CASE WHEN status='ditolak'   THEN 1 ELSE 0 END) ditolak,
        SUM(CASE WHEN status='disetujui' THEN jumlah_hari ELSE 0 END) hari_acc
    FROM pengajuan_cuti
    WHERE user_id=? AND YEAR(tgl_mulai)=?
");
$stat->execute([$uid, date('Y')]);
$stat = $stat->fetch(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
<style>
/* ── Info Cuti Cards ── */
.cuti-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 0;
}
.cuti-info-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 14px 16px;
    position: relative;
    overflow: hidden;
}
.cuti-info-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--card-color, #00c896);
    border-radius: 10px 10px 0 0;
}
.cic-label { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
.cic-val   { font-size: 26px; font-weight: 800; line-height: 1; color: var(--card-color, #0f172a); }
.cic-sub   { font-size: 10.5px; color: #64748b; margin-top: 4px; line-height: 1.4; }
.cic-bar-wrap { height: 4px; background: #e5e7eb; border-radius: 99px; margin-top: 8px; overflow: hidden; }
.cic-bar-fill { height: 4px; border-radius: 99px; background: var(--card-color, #00c896); transition: width .5s ease; }

/* ── Jatah detail tabel ── */
.jatah-tbl { width: 100%; border-collapse: collapse; }
.jatah-tbl th { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; padding: 8px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
.jatah-tbl td { padding: 10px 12px; border-bottom: 1px solid #f8fafc; vertical-align: middle; font-size: 12.5px; }
.jatah-tbl tr:last-child td { border-bottom: none; }
.jatah-tbl tr:hover td { background: #fafbfc; }
.jt-kode  { font-family: monospace; font-size: 11px; font-weight: 700; padding: 2px 7px; background: #f1f5f9; border-radius: 4px; }
.jt-bar-wrap { height: 6px; background: #e5e7eb; border-radius: 99px; overflow: hidden; width: 100%; min-width: 60px; }
.jt-bar-fill { height: 6px; border-radius: 99px; }

/* ── Stat summary row ── */
.stat-row { display: flex; gap: 0; border-top: 1px solid #f0f2f7; }
.stat-row-item { flex: 1; padding: 10px 14px; text-align: center; border-right: 1px solid #f0f2f7; }
.stat-row-item:last-child { border-right: none; }
.stat-row-num { font-size: 18px; font-weight: 800; line-height: 1; }
.stat-row-lbl { font-size: 10px; color: #94a3b8; margin-top: 2px; font-weight: 600; }

/* ── Tanggal chips ── */
.tgl-selected-wrap { display:flex;flex-wrap:wrap;gap:5px;margin-top:8px;min-height:28px; }
.tgl-chip { display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:20px;background:#dbeafe;color:#1d4ed8;font-size:11px;font-weight:700; }
.tgl-chip button { background:none;border:none;cursor:pointer;color:#1d4ed8;font-size:10px;padding:0;line-height:1; }
.tgl-chip button:hover { color:#ef4444; }
.cnt-badge { display:inline-flex;align-items:center;gap:4px;background:#0d1b2e;color:#00e5b0;font-size:12px;font-weight:700;padding:4px 12px;border-radius:7px;margin-top:8px; }

/* ── Status badges ── */
.sts-menunggu  { background:#fef3c7;color:#92400e; }
.sts-disetujui { background:#d1fae5;color:#065f46; }
.sts-ditolak   { background:#fee2e2;color:#991b1b; }
.sts-dibatalkan{ background:#f1f5f9;color:#64748b; }
.sts-badge { display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;padding:2px 9px;border-radius:20px; }

/* ── Approval steps ── */
.ap-steps { display:flex;align-items:center;gap:0; }
.ap-step  { display:flex;flex-direction:column;align-items:center;gap:3px;flex:1; }
.ap-dot   { width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;border:2px solid #e5e7eb; }
.ap-dot.ok   { background:#d1fae5;border-color:#00c896;color:#065f46; }
.ap-dot.wait { background:#fef3c7;border-color:#f59e0b;color:#92400e; }
.ap-dot.no   { background:#fee2e2;border-color:#ef4444;color:#991b1b; }
.ap-dot.idle { background:#f1f5f9;border-color:#e5e7eb;color:#cbd5e1; }
.ap-line    { flex:1;height:2px;background:#e5e7eb; }
.ap-line.ok { background:#00c896; }
.ap-lbl     { font-size:9px;color:#94a3b8;font-weight:600;text-align:center;max-width:60px;line-height:1.3; }
</style>

<div class="page-header">
  <h4><i class="fa fa-calendar-minus text-primary"></i> &nbsp;Pengajuan Cuti</h4>
  <div class="breadcrumb">
    <a href="<?=APP_URL?>/dashboard.php">Dashboard</a><span class="sep">/</span>
    <span class="cur">Pengajuan Cuti</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- ══ INFO CUTI SAYA ══ -->
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-circle-info" style="color:#00c896;"></i> Informasi Cuti Saya — Tahun <?= date('Y') ?></h5>
      <a href="<?= APP_URL ?>/pages/cetak_cuti.php?uid=<?= $uid ?>&tahun=<?= date('Y') ?>"
         target="_blank" class="btn btn-default btn-sm">
        <i class="fa fa-print"></i> Cetak Laporan <?= date('Y') ?>
      </a>
    </div>

    <!-- Stat row pengajuan -->
    <div class="stat-row">
      <div class="stat-row-item">
        <div class="stat-row-num" style="color:#0f172a;"><?= (int)($stat['total']??0) ?></div>
        <div class="stat-row-lbl">Total Pengajuan</div>
      </div>
      <div class="stat-row-item">
        <div class="stat-row-num" style="color:#f59e0b;"><?= (int)($stat['menunggu']??0) ?></div>
        <div class="stat-row-lbl">Menunggu</div>
      </div>
      <div class="stat-row-item">
        <div class="stat-row-num" style="color:#10b981;"><?= (int)($stat['disetujui']??0) ?></div>
        <div class="stat-row-lbl">Disetujui</div>
      </div>
      <div class="stat-row-item">
        <div class="stat-row-num" style="color:#ef4444;"><?= (int)($stat['ditolak']??0) ?></div>
        <div class="stat-row-lbl">Ditolak</div>
      </div>
      <div class="stat-row-item">
        <div class="stat-row-num" style="color:#6366f1;"><?= (int)($stat['hari_acc']??0) ?></div>
        <div class="stat-row-lbl">Hari Diambil</div>
      </div>
    </div>

    <!-- Jatah per jenis -->
    <?php if ($jatah_saya): ?>
    <div style="padding: 0 16px 14px;">
      <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;padding:12px 0 8px;">
        <i class="fa fa-wallet" style="color:#00c896;"></i> &nbsp;Saldo Jatah Cuti per Jenis
      </div>
      <table class="jatah-tbl">
        <thead>
          <tr>
            <th>Jenis Cuti</th>
            <th style="text-align:center;width:60px;">Kuota</th>
            <th style="text-align:center;width:70px;">Terpakai</th>
            <th style="text-align:center;width:60px;">Sisa</th>
            <th style="min-width:120px;">Penggunaan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jatah_saya as $jt):
            $pct      = $jt['kuota'] > 0 ? round($jt['terpakai'] / $jt['kuota'] * 100) : 0;
            $sisa_col = $jt['sisa'] > 3 ? '#10b981' : ($jt['sisa'] > 0 ? '#f59e0b' : '#ef4444');
            $bar_col  = $pct >= 80 ? '#ef4444' : ($pct >= 50 ? '#f59e0b' : '#10b981');
            $is_unlimited = $jt['kuota_default'] == 0;
          ?>
          <tr>
            <td>
              <span class="jt-kode"><?= clean($jt['kode']) ?></span>
              <span style="margin-left:7px;font-weight:600;color:#0f172a;"><?= clean($jt['jenis_nama']) ?></span>
            </td>
            <td style="text-align:center;font-weight:700;color:#0f172a;">
              <?= $is_unlimited ? '<span style="color:#94a3b8;font-size:11px;">Bebas</span>' : $jt['kuota'] ?>
            </td>
            <td style="text-align:center;">
              <span style="font-weight:700;color:<?= $jt['terpakai'] > 0 ? '#ef4444' : '#94a3b8' ?>;">
                <?= $jt['terpakai'] ?>
              </span>
              <span style="font-size:10px;color:#94a3b8;"> hari</span>
            </td>
            <td style="text-align:center;">
              <?php if ($is_unlimited): ?>
              <span style="color:#94a3b8;font-size:11px;">—</span>
              <?php else: ?>
              <span style="font-weight:800;font-size:15px;color:<?= $sisa_col ?>;"><?= $jt['sisa'] ?></span>
              <span style="font-size:10px;color:#94a3b8;"> hari</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($is_unlimited): ?>
              <span style="font-size:10.5px;color:#94a3b8;font-style:italic;">Tidak terbatas</span>
              <?php else: ?>
              <div style="display:flex;align-items:center;gap:8px;">
                <div class="jt-bar-wrap" style="flex:1;">
                  <div class="jt-bar-fill" style="width:<?= $pct ?>%;background:<?= $bar_col ?>;"></div>
                </div>
                <span style="font-size:10.5px;font-weight:700;color:<?= $bar_col ?>;min-width:28px;text-align:right;"><?= $pct ?>%</span>
              </div>
              <div style="font-size:9.5px;color:#94a3b8;margin-top:2px;">
                <?= $jt['terpakai'] ?> dari <?= $jt['kuota'] ?> hari terpakai
                <?php if ($jt['sisa'] <= 2 && $jt['sisa'] > 0): ?>
                <span style="color:#f59e0b;font-weight:700;"> — Hampir habis!</span>
                <?php elseif ($jt['sisa'] == 0 && !$is_unlimited): ?>
                <span style="color:#ef4444;font-weight:700;"> — Habis</span>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div style="padding:20px;text-align:center;color:#94a3b8;font-size:12px;">
      <i class="fa fa-wallet" style="font-size:22px;display:block;margin-bottom:8px;color:#d1d5db;"></i>
      Jatah cuti belum diinisialisasi oleh HRD.<br>
      <a href="<?= APP_URL ?>/pages/master_cuti.php" style="color:#00c896;font-weight:700;">Minta HRD untuk inisialisasi jatah cuti <?= date('Y') ?></a>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══ FORM PENGAJUAN ══ -->
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-paper-plane" style="color:#00c896;"></i> Ajukan Cuti Baru</h5>
    </div>
    <div class="panel-bd">

      <!-- Info alur approval -->
      <div style="background:<?=$is_atasan?'#eff6ff':'#f0fdf9'?>;border:1px solid <?=$is_atasan?'#bfdbfe':'#a7f3d0'?>;border-radius:8px;padding:10px 14px;margin-bottom:18px;font-size:12px;color:<?=$is_atasan?'#1e40af':'#065f46'?>;display:flex;align-items:flex-start;gap:9px;">
        <i class="fa fa-route" style="margin-top:2px;flex-shrink:0;font-size:13px;"></i>
        <div>
          <?php if ($is_atasan): ?>
          Karena Anda adalah <strong>atasan langsung</strong> bagi beberapa karyawan, pengajuan cuti Anda akan diteruskan ke <strong><?=clean($direktur['nama']??'Direktur')?></strong> &rarr; HRD &rarr; Selesai.
          <?php elseif ($me['atasan_id']): ?>
          Alur approval: <strong><?=clean($me['atasan_nama'])?></strong> (Atasan) &rarr; <strong>HRD</strong> &rarr; Selesai.
          <?php else: ?>
          <strong>Atasan langsung belum diset.</strong> Pengajuan akan langsung ke HRD. Silakan isi di <a href="<?=APP_URL?>/pages/profil.php#sdm" style="color:#059669;font-weight:700;">Profil &rarr; Data Kepegawaian</a>.
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Jenis Cuti <span class="req">*</span></label>
          <select id="f-jenis" class="form-control" onchange="cekJatah()">
            <option value="">— Pilih Jenis Cuti —</option>
            <?php foreach ($jenis_cuti as $j): ?>
            <option value="<?=$j['id']?>" data-kuota="<?=$j['kuota_default']?>"><?=clean($j['kode'])?> — <?=clean($j['nama'])?></option>
            <?php endforeach; ?>
          </select>
          <div id="f-jatah-info" style="display:none;margin-top:6px;padding:6px 10px;border-radius:6px;font-size:11.5px;font-weight:600;border:1px solid #e5e7eb;background:#f8fafc;"></div>
        </div>
        <div class="form-group"><!-- spacer --></div>
      </div>

      <div class="form-group">
        <label>Tanggal Cuti <span class="req">*</span> <span style="font-size:10px;color:#94a3b8;font-weight:400;">(bisa pilih lompat tanggal, misal: 11, 12, 15)</span></label>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <input type="text" id="f-tgl-picker" placeholder="Klik untuk pilih tanggal…" class="form-control" style="max-width:240px;" readonly>
          <button type="button" class="btn btn-default btn-sm" onclick="clearTgl()"><i class="fa fa-times"></i> Hapus Semua</button>
        </div>
        <div class="tgl-selected-wrap" id="tgl-chips"></div>
        <div id="tgl-count" class="cnt-badge" style="display:none;">
          <i class="fa fa-calendar-check"></i> <span id="tgl-count-num">0</span> hari dipilih
        </div>
      </div>

      <div class="form-group">
        <label>Keperluan / Alasan <span class="req">*</span></label>
        <textarea id="f-keperluan" class="form-control" rows="3" placeholder="Jelaskan keperluan cuti Anda…"></textarea>
      </div>

      <div class="form-group">
        <label>
          <i class="fa fa-user-clock" style="color:#00c896;font-size:11px;"></i>
          Delegasi Tugas
          <span style="font-size:10px;color:#94a3b8;font-weight:400;">(opsional — penerima tugas selama cuti)</span>
        </label>
        <select id="f-delegasi" class="form-control" onchange="toggleDelegasiNote()">
          <option value="">— Tidak ada delegasi —</option>
          <?php
          $cur_div_d = null;
          foreach ($delegasi_list as $dl):
              if (!$is_atasan) {
                  echo '<option value="'.(int)$dl['id'].'">'.htmlspecialchars($dl['nama']).'</option>';
              } else {
                  if ($dl['divisi'] !== $cur_div_d) {
                      if ($cur_div_d !== null) echo '</optgroup>';
                      echo '<optgroup label="'.htmlspecialchars($dl['divisi']?:'Tanpa Divisi').'">';
                      $cur_div_d = $dl['divisi'];
                  }
                  echo '<option value="'.(int)$dl['id'].'">'.htmlspecialchars($dl['nama']).'</option>';
              }
          endforeach;
          if ($is_atasan && $cur_div_d !== null) echo '</optgroup>';
          ?>
        </select>
        <div id="delegasi-extra" style="display:none;margin-top:8px;">
          <input type="text" id="f-cat-delegasi" class="form-control" placeholder="Catatan untuk penerima delegasi (opsional)">
        </div>
      </div>

      <div id="lampiran-group" style="display:none;" class="form-group">
        <label>Lampiran <span class="req">*</span> <span style="font-size:10px;color:#94a3b8;font-weight:400;">(surat dokter, dsb)</span></label>
        <input type="text" id="f-lampiran-note" class="form-control" placeholder="Nama/nomor surat">
      </div>

      <div style="margin-top:4px;">
        <button class="btn btn-primary" id="btn-kirim" onclick="submitCuti()">
          <i class="fa fa-paper-plane"></i> Kirim Pengajuan
        </button>
      </div>
    </div>
  </div>

  <!-- ══ RIWAYAT ══ -->
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-clock-rotate-left" style="color:#00c896;"></i> Riwayat Pengajuan</h5>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Tgl Ajuan</th>
            <th>Jenis</th>
            <th>Tanggal Cuti</th>
            <th style="text-align:center;">Hari</th>
            <th>Delegasi</th>
            <th>Status</th>
            <th>Approval</th>
            <th style="text-align:center;">Cetak</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($riwayat)): ?>
          <tr><td colspan="8" style="text-align:center;padding:28px;color:#94a3b8;">Belum ada pengajuan.</td></tr>
          <?php endif; ?>
          <?php foreach ($riwayat as $r):
            $s1   = $r['status_approver1'];
            $s2   = $r['status_approver2'];
            $dot1 = $s1==='disetujui'?'ok':($s1==='ditolak'?'no':'wait');
            $dot2 = $s1!=='disetujui'?'idle':($s2==='disetujui'?'ok':($s2==='ditolak'?'no':'wait'));
            $line1= $s1==='disetujui'?'ok':'';
            $icon1= $dot1==='ok'?'<i class="fa fa-check" style="font-size:9px;"></i>':($dot1==='no'?'<i class="fa fa-times" style="font-size:9px;"></i>':'<i class="fa fa-clock" style="font-size:8px;"></i>');
            $icon2= $dot2==='ok'?'<i class="fa fa-check" style="font-size:9px;"></i>':($dot2==='no'?'<i class="fa fa-times" style="font-size:9px;"></i>':($dot2==='wait'?'<i class="fa fa-clock" style="font-size:8px;"></i>':'<i class="fa fa-minus" style="font-size:9px;"></i>'));
          ?>
          <tr>
            <td style="font-size:11px;color:#94a3b8;white-space:nowrap;"><?=date('d M Y',strtotime($r['created_at']))?></td>
            <td>
              <code style="font-size:10.5px;background:#f1f5f9;padding:2px 6px;border-radius:4px;"><?=clean($r['jenis_kode']??'')?></code>
              <div style="font-size:10px;color:#94a3b8;margin-top:2px;"><?=clean($r['jenis_nama']??'')?></div>
            </td>
            <td style="font-size:11.5px;white-space:nowrap;">
              <?=date('d M',strtotime($r['tgl_mulai']))?><?= $r['tgl_mulai']!==$r['tgl_selesai']?' &ndash; '.date('d M Y',strtotime($r['tgl_selesai'])):' '.date('Y',strtotime($r['tgl_mulai'])); ?>
            </td>
            <td style="font-weight:800;font-size:15px;text-align:center;color:#1d4ed8;">
              <?=$r['jumlah_hari']?><span style="font-size:9px;font-weight:400;color:#94a3b8;"> hr</span>
            </td>
            <td style="font-size:11px;color:#64748b;"><?=clean($r['delegasi_nama']??'')?:'-'?></td>
            <td>
              <span class="sts-badge sts-<?=$r['status']?>">
                <?= ['menunggu'=>'Menunggu','disetujui'=>'Disetujui','ditolak'=>'Ditolak','dibatalkan'=>'Dibatalkan'][$r['status']] ?? ucfirst($r['status']) ?>
              </span>
              <?php if (!empty($r['catatan_approver1']) && $r['status_approver1']==='ditolak'): ?>
              <div style="font-size:10px;color:#dc2626;margin-top:3px;font-style:italic;">"<?=clean($r['catatan_approver1'])?>"</div>
              <?php elseif (!empty($r['catatan_approver2']) && $r['status_approver2']==='ditolak'): ?>
              <div style="font-size:10px;color:#dc2626;margin-top:3px;font-style:italic;">"<?=clean($r['catatan_approver2'])?>"</div>
              <?php endif; ?>
            </td>
            <td style="min-width:130px;">
              <div class="ap-steps">
                <div class="ap-step">
                  <div class="ap-dot <?=$dot1?>"><?= $icon1 ?></div>
                  <div class="ap-lbl"><?=clean(explode(' ', $r['approver1_nama']??'Atasan')[0])?></div>
                </div>
                <div class="ap-line <?=$line1?>"></div>
                <div class="ap-step">
                  <div class="ap-dot <?=$dot2?>"><?= $icon2 ?></div>
                  <div class="ap-lbl">HRD</div>
                </div>
              </div>
            </td>
            <td style="text-align:center;">
              <a href="<?= APP_URL ?>/pages/cetak_cuti.php?uid=<?= $uid ?>&pid=<?= $r['id'] ?>"
                 target="_blank"
                 class="btn btn-default btn-sm" style="font-size:10.5px;">
                <i class="fa fa-print"></i> Cetak
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /.content -->

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
const JATAH_MAP      = <?= json_encode(array_column($jatah_saya, null, 'jenis_cuti_id')) ?>;
const JENIS_LAMPIRAN = <?= json_encode(array_values(array_map(fn($j)=>$j['id'], array_filter($jenis_cuti, fn($j)=>$j['perlu_lampiran'])))) ?>;
let selectedDates = [];

const fp = flatpickr('#f-tgl-picker', {
    mode: 'multiple',
    dateFormat: 'Y-m-d',
    minDate: 'today',
    locale: { firstDayOfWeek: 1 },
    disableMobile: false,
    onChange: function(dates) {
        selectedDates = dates.map(d => {
            const y = d.getFullYear();
            const m = String(d.getMonth()+1).padStart(2,'0');
            const dd = String(d.getDate()).padStart(2,'0');
            return `${y}-${m}-${dd}`;
        });
        renderChips();
        cekJatah();
    }
});

function renderChips() {
    const wrap = document.getElementById('tgl-chips');
    const cnt  = document.getElementById('tgl-count');
    const num  = document.getElementById('tgl-count-num');
    wrap.innerHTML = selectedDates.map((t,i) =>
        `<span class="tgl-chip">${formatTgl(t)} <button type="button" onclick="removeDate(${i})"><i class="fa fa-times"></i></button></span>`
    ).join('');
    if (selectedDates.length > 0) { cnt.style.display='inline-flex'; num.textContent=selectedDates.length; }
    else cnt.style.display='none';
}
function formatTgl(t) {
    const [y,m,d] = t.split('-');
    const bln = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    return `${parseInt(d)} ${bln[parseInt(m)]}`;
}
function removeDate(idx) {
    selectedDates.splice(idx,1);
    fp.setDate(selectedDates);
    renderChips(); cekJatah();
}
function clearTgl() { selectedDates=[]; fp.clear(); renderChips(); cekJatah(); }

function cekJatah() {
    const jid     = document.getElementById('f-jenis').value;
    const info    = document.getElementById('f-jatah-info');
    const lampGrp = document.getElementById('lampiran-group');

    if (JENIS_LAMPIRAN.includes(parseInt(jid))) lampGrp.style.display = 'block';
    else lampGrp.style.display = 'none';

    if (!jid) { info.style.display = 'none'; return; }

    const jatah   = JATAH_MAP[jid];
    const dipilih = selectedDates.length;

    if (!jatah) {
        // Jenis tidak terbatas atau belum ada data
        info.style.display = 'block';
        info.style.background = '#f0fdf9';
        info.style.borderColor = '#a7f3d0';
        info.style.color = '#065f46';
        info.innerHTML = '<i class="fa fa-circle-info"></i> Jenis cuti ini tidak memiliki kuota terbatas.'
            + (dipilih > 0 ? ` &nbsp;&mdash;&nbsp; <strong>${dipilih} hari</strong> dipilih.` : '');
        return;
    }

    const sisa  = parseInt(jatah.sisa);
    const kuota = parseInt(jatah.kuota);
    const terp  = parseInt(jatah.terpakai);
    const ok    = dipilih === 0 || dipilih <= sisa;
    const pct   = kuota > 0 ? Math.round(terp / kuota * 100) : 0;

    info.style.display = 'block';
    info.style.background  = ok ? '#f0fdf9' : '#fef2f2';
    info.style.borderColor = ok ? '#a7f3d0' : '#fca5a5';
    info.style.color       = ok ? '#065f46' : '#dc2626';

    let html = `<i class="fa fa-wallet"></i>&nbsp;`
        + `Kuota: <strong>${kuota}</strong> hari &nbsp;|&nbsp; `
        + `Terpakai: <strong style="color:#ef4444;">${terp}</strong> hari &nbsp;|&nbsp; `
        + `Sisa: <strong style="color:${sisa > 3 ? '#10b981' : (sisa > 0 ? '#f59e0b' : '#ef4444')};">${sisa}</strong> hari`;
    if (dipilih > 0) {
        html += `&nbsp;&nbsp;<strong style="color:#1d4ed8;">&#8594; Mengajukan: ${dipilih} hari</strong>`;
        if (!ok) html += `&nbsp; <strong style="color:#dc2626;">&#9888; Melebihi sisa! (kurang ${dipilih - sisa} hari)</strong>`;
        else {
            const sisaSetelah = sisa - dipilih;
            html += `&nbsp; &mdash; sisa setelah ini: <strong>${sisaSetelah}</strong> hari`;
        }
    }
    info.innerHTML = html;
}

function toggleDelegasiNote() {
    const val = document.getElementById('f-delegasi').value;
    document.getElementById('delegasi-extra').style.display = val ? 'block' : 'none';
}

function submitCuti() {
    const jid = document.getElementById('f-jenis').value;
    const kep = document.getElementById('f-keperluan').value.trim();
    if (!jid)                    { alert('Pilih jenis cuti.');           return; }
    if (selectedDates.length===0){ alert('Pilih minimal 1 tanggal.');    return; }
    if (!kep)                    { alert('Isi keperluan / alasan cuti.'); return; }

    const btn = document.getElementById('btn-kirim');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Mengirim...';

    const fd = new FormData();
    fd.append('ajax_ajukan','1');
    fd.append('jenis_cuti_id', jid);
    fd.append('tanggal_list', JSON.stringify(selectedDates));
    fd.append('keperluan', kep);
    fd.append('delegasi_id', document.getElementById('f-delegasi').value || '');
    fd.append('catatan_delegasi', document.getElementById('f-cat-delegasi').value || '');

    fetch(location.href, {method:'POST', body:fd, credentials:'same-origin'})
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-paper-plane"></i> Kirim Pengajuan';
            if (d.ok) { alert(d.msg); location.reload(); }
            else alert(d.msg);
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-paper-plane"></i> Kirim Pengajuan';
            alert('Koneksi bermasalah. Coba lagi.');
        });
}
</script>
<?php include '../includes/footer.php'; ?>