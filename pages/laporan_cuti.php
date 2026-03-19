<?php
// pages/laporan_cuti.php
session_start();
require_once '../config.php';
requireLogin();

// Hanya HRD dan Admin
if (!in_array($_SESSION['user_role'], ['admin','hrd'])) {
    setFlash('danger','Akses ditolak.');
    redirect(APP_URL.'/dashboard.php');
}

$page_title  = 'Laporan Cuti';
$active_menu = 'laporan_cuti';

// ── Filter ────────────────────────────────────────────────
$f_tahun   = (int)($_GET['tahun']   ?? date('Y'));
$f_status  = $_GET['status']  ?? '';
$f_divisi  = $_GET['divisi']  ?? '';
$f_jenis   = (int)($_GET['jenis']   ?? 0);
$f_user    = (int)($_GET['user_id'] ?? 0);
$f_bulan   = (int)($_GET['bulan']   ?? 0);

// ── Dropdown data ─────────────────────────────────────────
$divisi_list = $pdo->query("SELECT DISTINCT divisi FROM users WHERE status='aktif' AND divisi!='' ORDER BY divisi")->fetchAll(PDO::FETCH_COLUMN);
$jenis_list  = $pdo->query("SELECT id,kode,nama FROM master_jenis_cuti WHERE status='aktif' ORDER BY urutan")->fetchAll(PDO::FETCH_ASSOC);
$user_list   = $pdo->query("SELECT id,nama,divisi FROM users WHERE status='aktif' ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$tahun_list  = [];
for ($y = date('Y'); $y >= date('Y')-4; $y--) $tahun_list[] = $y;

// ── Query utama ───────────────────────────────────────────
$where  = ["YEAR(pc.tgl_mulai) = ?"];
$params = [$f_tahun];

if ($f_status)  { $where[] = "pc.status = ?";         $params[] = $f_status; }
if ($f_divisi)  { $where[] = "u.divisi = ?";           $params[] = $f_divisi; }
if ($f_jenis)   { $where[] = "pc.jenis_cuti_id = ?";   $params[] = $f_jenis; }
if ($f_user)    { $where[] = "pc.user_id = ?";          $params[] = $f_user; }
if ($f_bulan)   { $where[] = "MONTH(pc.tgl_mulai) = ?"; $params[] = $f_bulan; }

$wsql = implode(' AND ', $where);

$data = $pdo->prepare("
    SELECT pc.*,
           jc.kode jenis_kode, jc.nama jenis_nama,
           u.nama  pemohon, u.divisi,
           ua1.nama approver1_nama,
           ua2.nama approver2_nama,
           ud.nama  delegasi_nama,
           GROUP_CONCAT(ct.tanggal ORDER BY ct.tanggal SEPARATOR ',') tgl_list
    FROM pengajuan_cuti pc
    JOIN users u ON u.id = pc.user_id
    LEFT JOIN master_jenis_cuti jc ON jc.id = pc.jenis_cuti_id
    LEFT JOIN users ua1 ON ua1.id = pc.approver1_id
    LEFT JOIN users ua2 ON ua2.id = pc.approver2_id
    LEFT JOIN users ud  ON ud.id  = pc.delegasi_id
    LEFT JOIN cuti_tanggal ct ON ct.pengajuan_id = pc.id
    WHERE $wsql
    GROUP BY pc.id
    ORDER BY pc.tgl_mulai DESC
");
$data->execute($params);
$rows = $data->fetchAll(PDO::FETCH_ASSOC);

// ── Statistik ─────────────────────────────────────────────
$total       = count($rows);
$tot_disetujui = 0; $tot_menunggu = 0; $tot_ditolak = 0; $tot_hari = 0;
$by_divisi   = []; $by_jenis = [];
foreach ($rows as $r) {
    if ($r['status']==='disetujui') { $tot_disetujui++; $tot_hari += (int)$r['jumlah_hari']; }
    if ($r['status']==='menunggu')  $tot_menunggu++;
    if ($r['status']==='ditolak')   $tot_ditolak++;
    $div = $r['divisi'] ?: 'Tanpa Divisi';
    $by_divisi[$div] = ($by_divisi[$div] ?? 0) + 1;
    $jn = $r['jenis_kode'] ?: '-';
    $by_jenis[$jn]  = ($by_jenis[$jn]  ?? 0) + (int)$r['jumlah_hari'];
}
arsort($by_divisi); arsort($by_jenis);

// ── Jatah rekap per karyawan ──────────────────────────────
$rekap = $pdo->prepare("
    SELECT u.id, u.nama, u.divisi,
           COALESCE(SUM(jt.kuota),0)    total_kuota,
           COALESCE(SUM(jt.terpakai),0) total_terpakai,
           COALESCE(SUM(jt.sisa),0)     total_sisa
    FROM users u
    LEFT JOIN jatah_cuti jt ON jt.user_id=u.id AND jt.tahun=?
    WHERE u.status='aktif'
    " . ($f_divisi ? "AND u.divisi=?" : "") . "
    GROUP BY u.id
    ORDER BY u.divisi, u.nama
");
$rekap_params = [$f_tahun];
if ($f_divisi) $rekap_params[] = $f_divisi;
$rekap->execute($rekap_params);
$rekap_rows = $rekap->fetchAll(PDO::FETCH_ASSOC);

$bln_id = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];

include '../includes/header.php';
?>
<style>
.stat-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px; margin-bottom:0; }
.sc { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; position:relative; overflow:hidden; }
.sc::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:var(--c,#00c896); border-radius:10px 10px 0 0; }
.sc-lbl { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; margin-bottom:5px; }
.sc-val { font-size:24px; font-weight:800; color:var(--c,#0f172a); line-height:1; }
.sc-sub { font-size:10.5px; color:#64748b; margin-top:4px; }
.filter-row { display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; }
.filter-row .form-group { margin-bottom:0; flex:1; min-width:120px; }
.filter-row label { font-size:10.5px; font-weight:700; color:#64748b; margin-bottom:3px; display:block; }
.sts-badge { display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;padding:2px 9px;border-radius:20px; }
.sts-menunggu  { background:#fef3c7;color:#92400e; }
.sts-disetujui { background:#d1fae5;color:#065f46; }
.sts-ditolak   { background:#fee2e2;color:#991b1b; }
.sts-dibatalkan{ background:#f1f5f9;color:#64748b; }
.bar-bg { background:#e5e7eb; border-radius:99px; height:6px; overflow:hidden; }
.bar-fg { height:6px; border-radius:99px; }
.tbl-sm td, .tbl-sm th { font-size:11.5px; padding:7px 10px; }
</style>

<div class="page-header">
  <h4><i class="fa fa-chart-bar text-primary"></i> &nbsp;Laporan Cuti Karyawan</h4>
  <div class="breadcrumb">
    <a href="<?=APP_URL?>/dashboard.php">Dashboard</a><span class="sep">/</span>
    <span class="cur">Laporan Cuti</span>
  </div>
</div>

<div class="content">

  <!-- ── FILTER ── -->
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-filter" style="color:#00c896;"></i> Filter Laporan</h5>
      <a href="<?=APP_URL?>/pages/laporan_cuti.php" class="btn btn-default btn-sm">
        <i class="fa fa-rotate-left"></i> Reset
      </a>
    </div>
    <div class="panel-bd">
      <form method="GET" action="">
        <div class="filter-row">
          <div class="form-group">
            <label>Tahun</label>
            <select name="tahun" class="form-control" onchange="this.form.submit()">
              <?php foreach ($tahun_list as $y): ?>
              <option value="<?=$y?>" <?=$y===$f_tahun?'selected':''?>><?=$y?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Bulan</label>
            <select name="bulan" class="form-control" onchange="this.form.submit()">
              <option value="">Semua Bulan</option>
              <?php for ($b=1;$b<=12;$b++): ?>
              <option value="<?=$b?>" <?=$b===$f_bulan?'selected':''?>><?=$bln_id[$b]?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Divisi</label>
            <select name="divisi" class="form-control" onchange="this.form.submit()">
              <option value="">Semua Divisi</option>
              <?php foreach ($divisi_list as $d): ?>
              <option value="<?=htmlspecialchars($d)?>" <?=$d===$f_divisi?'selected':''?>><?=htmlspecialchars($d)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Jenis Cuti</label>
            <select name="jenis" class="form-control" onchange="this.form.submit()">
              <option value="">Semua Jenis</option>
              <?php foreach ($jenis_list as $j): ?>
              <option value="<?=$j['id']?>" <?=$j['id']==$f_jenis?'selected':''?>><?=htmlspecialchars($j['kode'])?> — <?=htmlspecialchars($j['nama'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control" onchange="this.form.submit()">
              <option value="">Semua Status</option>
              <option value="menunggu"  <?=$f_status==='menunggu'?'selected':''?>>Menunggu</option>
              <option value="disetujui" <?=$f_status==='disetujui'?'selected':''?>>Disetujui</option>
              <option value="ditolak"   <?=$f_status==='ditolak'?'selected':''?>>Ditolak</option>
              <option value="dibatalkan"<?=$f_status==='dibatalkan'?'selected':''?>>Dibatalkan</option>
            </select>
          </div>
          <div class="form-group">
            <label>Karyawan</label>
            <select name="user_id" class="form-control" onchange="this.form.submit()">
              <option value="">Semua Karyawan</option>
              <?php foreach ($user_list as $ul): ?>
              <option value="<?=$ul['id']?>" <?=$ul['id']==$f_user?'selected':''?>><?=htmlspecialchars($ul['nama'])?> <?=$ul['divisi']?"({$ul['divisi']})":''?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="flex:0;">
            <label>&nbsp;</label>
            <a href="<?=APP_URL?>/pages/cetak_laporan_cuti.php?<?=http_build_query($_GET)?>"
               target="_blank" class="btn btn-primary btn-sm" style="white-space:nowrap;">
              <i class="fa fa-print"></i> Cetak PDF
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- ── STATISTIK ── -->
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-chart-pie" style="color:#00c896;"></i> Ringkasan
        <span style="font-size:11px;font-weight:400;color:#94a3b8;margin-left:6px;">Tahun <?=$f_tahun?><?=$f_bulan?' — '.$bln_id[$f_bulan]:''?><?=$f_divisi?' — '.htmlspecialchars($f_divisi):''?></span>
      </h5>
    </div>
    <div class="panel-bd">
      <div class="stat-cards">
        <div class="sc" style="--c:#1d4ed8">
          <div class="sc-lbl">Total Pengajuan</div>
          <div class="sc-val"><?=$total?></div>
          <div class="sc-sub">semua status</div>
        </div>
        <div class="sc" style="--c:#10b981">
          <div class="sc-lbl">Disetujui</div>
          <div class="sc-val"><?=$tot_disetujui?></div>
          <div class="sc-sub"><?=$total>0?round($tot_disetujui/$total*100):0?>% dari total</div>
        </div>
        <div class="sc" style="--c:#f59e0b">
          <div class="sc-lbl">Menunggu</div>
          <div class="sc-val"><?=$tot_menunggu?></div>
          <div class="sc-sub">perlu diproses</div>
        </div>
        <div class="sc" style="--c:#ef4444">
          <div class="sc-lbl">Ditolak</div>
          <div class="sc-val"><?=$tot_ditolak?></div>
          <div class="sc-sub"><?=$total>0?round($tot_ditolak/$total*100):0?>% dari total</div>
        </div>
        <div class="sc" style="--c:#6366f1">
          <div class="sc-lbl">Total Hari ACC</div>
          <div class="sc-val"><?=$tot_hari?></div>
          <div class="sc-sub">hari kerja disetujui</div>
        </div>
        <div class="sc" style="--c:#0891b2">
          <div class="sc-lbl">Rata-rata / Orang</div>
          <div class="sc-val"><?=$tot_disetujui>0?round($tot_hari/$tot_disetujui,1):0?></div>
          <div class="sc-sub">hari per pengajuan</div>
        </div>
      </div>

      <!-- Mini breakdown -->
      <?php if ($by_divisi || $by_jenis): ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
        <?php if ($by_divisi): ?>
        <div>
          <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
            <i class="fa fa-building" style="color:#00c896;"></i> &nbsp;Per Divisi (jumlah pengajuan)
          </div>
          <?php foreach (array_slice($by_divisi,0,6,true) as $div=>$cnt): ?>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
            <div style="font-size:11.5px;font-weight:600;color:#374151;min-width:100px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=htmlspecialchars($div)?></div>
            <div class="bar-bg" style="flex:1;">
              <div class="bar-fg" style="width:<?=$total>0?round($cnt/$total*100):0?>%;background:#1d4ed8;"></div>
            </div>
            <span style="font-size:11px;font-weight:700;color:#1d4ed8;min-width:22px;text-align:right;"><?=$cnt?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($by_jenis): ?>
        <div>
          <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
            <i class="fa fa-calendar-minus" style="color:#00c896;"></i> &nbsp;Per Jenis (total hari)
          </div>
          <?php $max_jenis = max($by_jenis) ?: 1; foreach ($by_jenis as $kode=>$hari): ?>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
            <span style="font-family:monospace;font-size:10px;font-weight:700;background:#f1f5f9;padding:1px 6px;border-radius:3px;min-width:62px;text-align:center;"><?=htmlspecialchars($kode)?></span>
            <div class="bar-bg" style="flex:1;">
              <div class="bar-fg" style="width:<?=round($hari/$max_jenis*100)?>%;background:#6366f1;"></div>
            </div>
            <span style="font-size:11px;font-weight:700;color:#6366f1;min-width:36px;text-align:right;"><?=$hari?> hr</span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── TABEL DETAIL PENGAJUAN ── -->
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-table-list" style="color:#00c896;"></i> Detail Pengajuan
        <span style="font-size:11px;font-weight:400;color:#94a3b8;margin-left:6px;"><?=$total?> data</span>
      </h5>
      <a href="<?=APP_URL?>/pages/cetak_laporan_cuti.php?<?=http_build_query($_GET)?>"
         target="_blank" class="btn btn-primary btn-sm">
        <i class="fa fa-print"></i> Cetak PDF
      </a>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table class="tbl-sm">
        <thead>
          <tr>
            <th>#</th>
            <th>Karyawan</th>
            <th>Divisi</th>
            <th>Jenis</th>
            <th>Tanggal Cuti</th>
            <th style="text-align:center;">Hari</th>
            <th>Keperluan</th>
            <th>Approval</th>
            <th style="text-align:center;">Status</th>
            <th style="text-align:center;">Slip</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
          <tr><td colspan="10" style="text-align:center;padding:30px;color:#94a3b8;">
            <i class="fa fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;"></i>
            Tidak ada data pengajuan untuk filter ini.
          </td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $i=>$r):
            $tgls = $r['tgl_list'] ? explode(',',$r['tgl_list']) : [];
          ?>
          <tr>
            <td style="color:#94a3b8;font-size:11px;"><?=$i+1?></td>
            <td>
              <div style="font-weight:600;color:#0f172a;"><?=clean($r['pemohon'])?></div>
              <div style="font-size:10px;color:#94a3b8;"><?=date('d M Y',strtotime($r['created_at']))?></div>
            </td>
            <td style="font-size:11px;color:#64748b;"><?=clean($r['divisi']??'-')?></td>
            <td>
              <code style="font-size:10px;background:#f1f5f9;padding:1px 5px;border-radius:3px;"><?=clean($r['jenis_kode']??'')?></code>
              <div style="font-size:10px;color:#94a3b8;margin-top:1px;"><?=clean($r['jenis_nama']??'')?></div>
            </td>
            <td style="white-space:nowrap;font-size:11.5px;">
              <?=date('d M',strtotime($r['tgl_mulai']))?><?=$r['tgl_mulai']!==$r['tgl_selesai']?' &ndash; '.date('d M Y',strtotime($r['tgl_selesai'])):' '.date('Y',strtotime($r['tgl_mulai']))?>
              <?php if (count($tgls)>1): ?>
              <div style="display:flex;flex-wrap:wrap;gap:2px;margin-top:3px;">
                <?php foreach(array_slice($tgls,0,5) as $t): ?>
                <span style="background:#dbeafe;color:#1d4ed8;font-size:9.5px;font-weight:700;padding:1px 5px;border-radius:3px;"><?=date('d/m',strtotime($t))?></span>
                <?php endforeach; ?>
                <?php if(count($tgls)>5): ?><span style="font-size:9.5px;color:#94a3b8;">+<?=count($tgls)-5?></span><?php endif; ?>
              </div>
              <?php endif; ?>
            </td>
            <td style="font-weight:800;font-size:15px;text-align:center;color:#1d4ed8;">
              <?=$r['jumlah_hari']?><span style="font-size:9px;font-weight:400;color:#94a3b8;">h</span>
            </td>
            <td style="font-size:11px;color:#475569;max-width:140px;">
              <?=clean(mb_strimwidth($r['keperluan']??'-',0,60,'...'))?>
              <?php if ($r['delegasi_nama']): ?>
              <div style="font-size:10px;color:#94a3b8;margin-top:1px;"><i class="fa fa-user-clock" style="font-size:9px;"></i> <?=clean($r['delegasi_nama'])?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:10.5px;">
              <div style="display:flex;flex-direction:column;gap:2px;">
                <?php
                $a1s = $r['status_approver1'];
                $a2s = $r['status_approver2'];
                $a1col = $a1s==='disetujui'?'#10b981':($a1s==='ditolak'?'#ef4444':'#f59e0b');
                $a2col = $a2s==='disetujui'?'#10b981':($a2s==='ditolak'?'#ef4444':'#94a3b8');
                $a1ico = $a1s==='disetujui'?'fa-check':($a1s==='ditolak'?'fa-times':'fa-clock');
                $a2ico = $a2s==='disetujui'?'fa-check':($a2s==='ditolak'?'fa-times':'fa-clock');
                ?>
                <span style="color:<?=$a1col?>;font-weight:600;">
                  <i class="fa <?=$a1ico?>" style="font-size:9px;"></i>
                  <?=clean(explode(' ',$r['approver1_nama']??'Atasan')[0])?>
                </span>
                <span style="color:<?=$a2col?>;font-weight:600;">
                  <i class="fa <?=$a2ico?>" style="font-size:9px;"></i>
                  HRD<?=$r['approver2_nama']?' ('.clean(explode(' ',$r['approver2_nama'])[0]).')':''?>
                </span>
              </div>
            </td>
            <td style="text-align:center;">
              <span class="sts-badge sts-<?=$r['status']?>">
                <?=['menunggu'=>'Menunggu','disetujui'=>'Disetujui','ditolak'=>'Ditolak','dibatalkan'=>'Dibatalkan'][$r['status']]??ucfirst($r['status'])?>
              </span>
            </td>
            <td style="text-align:center;">
              <a href="<?=APP_URL?>/pages/cetak_cuti.php?uid=<?=$r['user_id']?>&pid=<?=$r['id']?>"
                 target="_blank" class="btn btn-default btn-sm" style="font-size:10px;padding:2px 7px;">
                <i class="fa fa-print"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── REKAP JATAH PER KARYAWAN ── -->
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-wallet" style="color:#00c896;"></i> Rekap Saldo Jatah Cuti Karyawan
        <span style="font-size:11px;font-weight:400;color:#94a3b8;margin-left:6px;">Tahun <?=$f_tahun?></span>
      </h5>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table class="tbl-sm">
        <thead>
          <tr>
            <th>#</th>
            <th>Nama Karyawan</th>
            <th>Divisi</th>
            <th style="text-align:center;">Total Kuota</th>
            <th style="text-align:center;">Terpakai</th>
            <th style="text-align:center;">Sisa</th>
            <th>Penggunaan</th>
            <th style="text-align:center;">Laporan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rekap_rows)): ?>
          <tr><td colspan="8" style="text-align:center;padding:20px;color:#94a3b8;">Tidak ada data karyawan.</td></tr>
          <?php endif; ?>
          <?php $prev_div=''; foreach ($rekap_rows as $i=>$rk):
            $pct = $rk['total_kuota']>0 ? round($rk['total_terpakai']/$rk['total_kuota']*100) : 0;
            $bar_col = $pct>=80?'#ef4444':($pct>=50?'#f59e0b':'#10b981');
            $sisa_col = $rk['total_sisa']>3?'#10b981':($rk['total_sisa']>0?'#f59e0b':'#ef4444');
            if ($prev_div !== $rk['divisi']): $prev_div = $rk['divisi']; ?>
          <tr style="background:#f8fafc;">
            <td colspan="8" style="padding:5px 10px;font-size:10px;font-weight:700;color:#6366f1;letter-spacing:.5px;text-transform:uppercase;">
              <i class="fa fa-building" style="font-size:9px;"></i> &nbsp;<?=htmlspecialchars($rk['divisi']?:'Tanpa Divisi')?>
            </td>
          </tr>
          <?php endif; ?>
          <tr>
            <td style="color:#94a3b8;font-size:11px;"><?=$i+1?></td>
            <td style="font-weight:600;color:#0f172a;"><?=clean($rk['nama'])?></td>
            <td style="font-size:11px;color:#64748b;"><?=clean($rk['divisi']??'-')?></td>
            <td style="text-align:center;font-weight:700;"><?=$rk['total_kuota']?></td>
            <td style="text-align:center;font-weight:700;color:<?=$rk['total_terpakai']>0?'#ef4444':'#94a3b8'?>;"><?=$rk['total_terpakai']?></td>
            <td style="text-align:center;">
              <span style="font-weight:800;font-size:14px;color:<?=$sisa_col?>;"><?=$rk['total_sisa']?></span>
            </td>
            <td style="min-width:120px;">
              <?php if ($rk['total_kuota']>0): ?>
              <div style="display:flex;align-items:center;gap:6px;">
                <div class="bar-bg" style="flex:1;">
                  <div class="bar-fg" style="width:<?=$pct?>%;background:<?=$bar_col?>;"></div>
                </div>
                <span style="font-size:10px;font-weight:700;color:<?=$bar_col?>;min-width:26px;text-align:right;"><?=$pct?>%</span>
              </div>
              <?php else: ?>
              <span style="font-size:10.5px;color:#94a3b8;font-style:italic;">Belum diinisialisasi</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;">
              <a href="<?=APP_URL?>/pages/cetak_cuti.php?uid=<?=$rk['id']?>&tahun=<?=$f_tahun?>"
                 target="_blank" class="btn btn-default btn-sm" style="font-size:10px;padding:2px 7px;">
                <i class="fa fa-print"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /.content -->
<?php include '../includes/footer.php'; ?>