<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }

$search       = trim($_GET['q']      ?? '');
$filter_jenis = $_GET['jenis']       ?? '';
$filter_thn   = (int)($_GET['tahun'] ?? 0);
$page         = max(1,(int)($_GET['page'] ?? 1));
$per_page     = 15;
$offset       = ($page-1)*$per_page;

// ── Hapus BA ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='hapus_ba' && hasRole('admin')) {
    $ba_id = (int)($_POST['ba_id'] ?? 0);
    if ($ba_id) { $pdo->prepare("DELETE FROM berita_acara WHERE id=?")->execute([$ba_id]); setFlash('success','Berita Acara berhasil dihapus.'); }
    redirect(APP_URL.'/pages/berita_acara.php');
}

// ── AJAX: ambil detail satu BA ────────────────────────────────────────────────
if (isset($_GET['ajax_detail'])) {
    $ba_id = (int)($_GET['ba_id'] ?? 0);
    $row = $pdo->prepare("
        SELECT ba.*, t.nomor, t.judul, t.prioritas, t.lokasi, t.deskripsi, t.waktu_submit, t.status,
               k.nama AS kat_nama,
               u.nama  AS req_nama,  u.divisi  AS req_divisi,
               tek.nama AS tek_nama, tek.divisi AS tek_divisi,
               pb.nama  AS dibuat_nama, pb.divisi AS dibuat_divisi
        FROM berita_acara ba
        LEFT JOIN tiket t   ON t.id  = ba.tiket_id
        LEFT JOIN kategori k ON k.id = t.kategori_id
        LEFT JOIN users u   ON u.id  = t.user_id
        LEFT JOIN users tek ON tek.id= t.teknisi_id
        LEFT JOIN users pb  ON pb.id = ba.dibuat_oleh
        WHERE ba.id = ? LIMIT 1
    ");
    $row->execute([$ba_id]);
    $d = $row->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($d ?: null);
    exit;
}

// ── Query list ────────────────────────────────────────────────────────────────
$where = ['1=1']; $params = [];
if ($search) {
    $where[] = '(ba.nomor_ba LIKE ? OR t.nomor LIKE ? OR t.judul LIKE ? OR u.nama LIKE ?)';
    $s = "%$search%"; $params = array_merge($params,[$s,$s,$s,$s]);
}
if ($filter_jenis) { $where[] = 'ba.jenis_tindak = ?'; $params[] = $filter_jenis; }
if ($filter_thn)   { $where[] = 'YEAR(ba.tanggal_ba) = ?'; $params[] = $filter_thn; }
$wsql = implode(' AND ', $where);

$total_q = $pdo->prepare("SELECT COUNT(*) FROM berita_acara ba LEFT JOIN tiket t ON t.id=ba.tiket_id LEFT JOIN users u ON u.id=t.user_id WHERE $wsql");
$total_q->execute($params); $total = (int)$total_q->fetchColumn();
$total_pages = max(1,ceil($total/$per_page));

$data_q = $pdo->prepare("
    SELECT ba.*, t.nomor, t.judul, t.prioritas, t.lokasi,
           u.nama AS req_nama, u.divisi AS req_divisi,
           tek.nama AS tek_nama, pb.nama AS dibuat_nama
    FROM berita_acara ba
    LEFT JOIN tiket t   ON t.id  = ba.tiket_id
    LEFT JOIN users u   ON u.id  = t.user_id
    LEFT JOIN users tek ON tek.id= t.teknisi_id
    LEFT JOIN users pb  ON pb.id = ba.dibuat_oleh
    WHERE $wsql ORDER BY ba.created_at DESC LIMIT $per_page OFFSET $offset
");
$data_q->execute($params); $rows = $data_q->fetchAll(PDO::FETCH_ASSOC);

$tahun_list = $pdo->query("SELECT DISTINCT YEAR(tanggal_ba) y FROM berita_acara ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);

$stats = $pdo->query("
    SELECT COUNT(*) total,
        SUM(jenis_tindak='pembelian_baru') pembelian,
        SUM(jenis_tindak='perbaikan_eksternal') perbaikan,
        SUM(jenis_tindak='penghapusan_aset') penghapusan,
        SUM(jenis_tindak='penggantian_suku_cadang') suku_cadang,
        SUM(jenis_tindak='lainnya') lainnya,
        SUM(COALESCE(nilai_estimasi,0)) total_estimasi
    FROM berita_acara
")->fetch(PDO::FETCH_ASSOC);

function jenisBadge(string $j, bool $big=false): string {
    $sz = $big ? '11px' : '10px'; $py = $big ? '3px 11px' : '2px 9px';
    return match($j) {
        'pembelian_baru'          => "<span style='background:#dbeafe;color:#1e40af;font-size:{$sz};font-weight:700;padding:{$py};border-radius:20px;white-space:nowrap;'>Pembelian Baru</span>",
        'perbaikan_eksternal'     => "<span style='background:#fef3c7;color:#92400e;font-size:{$sz};font-weight:700;padding:{$py};border-radius:20px;white-space:nowrap;'>Perbaikan Vendor</span>",
        'penghapusan_aset'        => "<span style='background:#fee2e2;color:#991b1b;font-size:{$sz};font-weight:700;padding:{$py};border-radius:20px;white-space:nowrap;'>Hapus Aset</span>",
        'penggantian_suku_cadang' => "<span style='background:#d1fae5;color:#065f46;font-size:{$sz};font-weight:700;padding:{$py};border-radius:20px;white-space:nowrap;'>Suku Cadang</span>",
        default                   => "<span style='background:#ede9fe;color:#5b21b6;font-size:{$sz};font-weight:700;padding:{$py};border-radius:20px;white-space:nowrap;'>Lainnya</span>",
    };
}

$page_title  = 'Daftar Berita Acara';
$active_menu = 'berita_acara';
include '../includes/header.php';
?>
<style>
.ba-stat-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;display:flex;align-items:center;gap:12px;}
.ba-stat-ico{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.ba-stat-val{font-size:20px;font-weight:700;color:#0f172a;line-height:1;}
.ba-stat-lbl{font-size:11px;color:#94a3b8;margin-top:3px;}
.ba-row:hover td{background:#f8fafc !important;}
.ba-row td{vertical-align:middle;padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:12px;}
.btn-icon{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;border:1px solid #e2e8f0;background:#fff;cursor:pointer;color:#64748b;font-size:12px;transition:all .15s;text-decoration:none;}
.btn-icon:hover{border-color:#00e5b0;color:#00e5b0;background:#f0fdf9;}
.btn-icon.red:hover{border-color:#ef4444;color:#ef4444;background:#fff1f2;}

/* ── Modal Preview ── */
.ba-modal-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1100;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(4px);}
.ba-modal-ov.open{display:flex;}
@keyframes baIn{from{opacity:0;transform:translateY(14px) scale(.97);}to{opacity:1;transform:translateY(0) scale(1);}}
.ba-modal-box{background:#fff;width:100%;max-width:720px;border-radius:14px;box-shadow:0 24px 64px rgba(0,0,0,.22);overflow:hidden;display:flex;flex-direction:column;max-height:90vh;animation:baIn .22s cubic-bezier(.4,0,.2,1);}
.ba-modal-hd{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #f1f5f9;flex-shrink:0;background:#0f172a;}
.ba-modal-body{flex:1;overflow-y:auto;padding:20px 22px;}
.ba-modal-ft{padding:12px 18px;border-top:1px solid #f1f5f9;display:flex;gap:8px;justify-content:flex-end;flex-shrink:0;}

/* Detail rows */
.dtbl{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:14px;}
.dtbl td{padding:6px 10px;border:1px solid #f1f5f9;vertical-align:top;}
.dtbl .dl{background:#f8fafc;color:#64748b;font-weight:600;width:35%;font-size:11.5px;}
.dtbl .dv{color:#1e293b;}
.sec-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#94a3b8;margin:16px 0 7px;padding-bottom:5px;border-bottom:2px solid #f1f5f9;}
.sec-label:first-child{margin-top:0;}
.content-field{background:#f8fafc;border:1px solid #f1f5f9;border-radius:6px;padding:10px 12px;font-size:12px;color:#1e293b;line-height:1.7;min-height:38px;}
.ba-ttd-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:4px;}
.ba-ttd-card{border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;text-align:center;}
.ba-ttd-role{font-size:10px;color:#94a3b8;margin-bottom:32px;}
.ba-ttd-line{border-top:1px solid #334155;margin:0 8px 5px;}
.ba-ttd-name{font-size:11px;font-weight:700;color:#0f172a;}
.ba-ttd-jab{font-size:10px;color:#64748b;margin-top:2px;}

/* Spinner */
.ba-spinner{display:flex;align-items:center;justify-content:center;height:180px;color:#94a3b8;}
.ba-spinner i{font-size:28px;animation:spin .8s linear infinite;}
@keyframes spin{from{transform:rotate(0deg);}to{transform:rotate(360deg);}}
</style>

<div class="page-header">
  <h4><i class="fa fa-file-contract text-primary"></i> &nbsp;Daftar Berita Acara</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Beranda</a><span class="sep">/</span>
    <span class="cur">Berita Acara</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- STATS -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px;">
    <div class="ba-stat-card">
      <div class="ba-stat-ico" style="background:#f0f9ff;"><i class="fa fa-file-contract" style="color:#0ea5e9;"></i></div>
      <div><div class="ba-stat-val"><?= number_format((int)$stats['total']) ?></div><div class="ba-stat-lbl">Total BA</div></div>
    </div>
    <div class="ba-stat-card">
      <div class="ba-stat-ico" style="background:#eff6ff;"><i class="fa fa-cart-plus" style="color:#2563eb;"></i></div>
      <div><div class="ba-stat-val"><?= (int)$stats['pembelian'] ?></div><div class="ba-stat-lbl">Pembelian Baru</div></div>
    </div>
    <div class="ba-stat-card">
      <div class="ba-stat-ico" style="background:#fffbeb;"><i class="fa fa-tools" style="color:#d97706;"></i></div>
      <div><div class="ba-stat-val"><?= (int)$stats['perbaikan'] ?></div><div class="ba-stat-lbl">Perbaikan Vendor</div></div>
    </div>
    <div class="ba-stat-card">
      <div class="ba-stat-ico" style="background:#fff1f2;"><i class="fa fa-trash-alt" style="color:#ef4444;"></i></div>
      <div><div class="ba-stat-val"><?= (int)$stats['penghapusan'] ?></div><div class="ba-stat-lbl">Hapus Aset</div></div>
    </div>
    <div class="ba-stat-card" style="border-color:#fde68a;background:#fffbeb;">
      <div class="ba-stat-ico" style="background:#fef3c7;"><i class="fa fa-coins" style="color:#d97706;"></i></div>
      <div>
        <div class="ba-stat-val" style="font-size:14px;">Rp <?= number_format((int)$stats['total_estimasi'],0,',','.') ?></div>
        <div class="ba-stat-lbl">Total Estimasi</div>
      </div>
    </div>
  </div>

  <!-- FILTER -->
  <div class="panel">
    <div class="panel-bd" style="padding:12px 16px;">
      <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:180px;">
          <label style="font-size:11px;color:#94a3b8;font-weight:600;display:block;margin-bottom:4px;">Cari</label>
          <div style="position:relative;">
            <i class="fa fa-search" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:11px;"></i>
            <input type="text" name="q" class="form-control" value="<?= clean($search) ?>" placeholder="No. BA, Tiket, Judul, Nama..." style="padding-left:28px;">
          </div>
        </div>
        <div style="min-width:160px;">
          <label style="font-size:11px;color:#94a3b8;font-weight:600;display:block;margin-bottom:4px;">Jenis Tindak Lanjut</label>
          <select name="jenis" class="form-control">
            <option value="">Semua Jenis</option>
            <option value="pembelian_baru"          <?= $filter_jenis==='pembelian_baru'?'selected':'' ?>>Pembelian Baru</option>
            <option value="perbaikan_eksternal"     <?= $filter_jenis==='perbaikan_eksternal'?'selected':'' ?>>Perbaikan Vendor</option>
            <option value="penghapusan_aset"        <?= $filter_jenis==='penghapusan_aset'?'selected':'' ?>>Hapus Aset</option>
            <option value="penggantian_suku_cadang" <?= $filter_jenis==='penggantian_suku_cadang'?'selected':'' ?>>Suku Cadang</option>
            <option value="lainnya"                 <?= $filter_jenis==='lainnya'?'selected':'' ?>>Lainnya</option>
          </select>
        </div>
        <div>
          <label style="font-size:11px;color:#94a3b8;font-weight:600;display:block;margin-bottom:4px;">Tahun</label>
          <select name="tahun" class="form-control">
            <option value="">Semua Tahun</option>
            <?php foreach ($tahun_list as $thn): ?>
            <option value="<?= $thn ?>" <?= $filter_thn==(int)$thn?'selected':'' ?>><?= $thn ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex;gap:6px;align-items:flex-end;">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Cari</button>
          <?php if ($search||$filter_jenis||$filter_thn): ?>
          <a href="<?= APP_URL ?>/pages/berita_acara.php" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Reset</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- TABEL -->
  <div class="panel">
    <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <h5><i class="fa fa-list text-primary"></i> &nbsp;Daftar Berita Acara
        <span style="font-size:11px;color:#94a3b8;font-weight:400;margin-left:6px;">(<?= $total ?> data)</span>
      </h5>
      <div style="font-size:11px;color:#94a3b8;">Halaman <?= $page ?> / <?= $total_pages ?></div>
    </div>
    <div class="panel-bd" style="padding:0;overflow-x:auto;">
      <?php if (empty($rows)): ?>
      <div style="text-align:center;padding:40px 20px;color:#94a3b8;">
        <i class="fa fa-file-contract" style="font-size:32px;display:block;margin-bottom:10px;opacity:.3;"></i>
        <?= ($search||$filter_jenis||$filter_thn) ? 'Tidak ada hasil pencarian.' : 'Belum ada Berita Acara.' ?>
      </div>
      <?php else: ?>
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
            <th style="padding:10px 12px;font-size:11px;color:#64748b;font-weight:700;text-align:left;white-space:nowrap;">No. BA</th>
            <th style="padding:10px 12px;font-size:11px;color:#64748b;font-weight:700;text-align:left;white-space:nowrap;">Tiket</th>
            <th style="padding:10px 12px;font-size:11px;color:#64748b;font-weight:700;text-align:left;">Judul / Keluhan</th>
            <th style="padding:10px 12px;font-size:11px;color:#64748b;font-weight:700;text-align:left;white-space:nowrap;">Jenis Tindak Lanjut</th>
            <th style="padding:10px 12px;font-size:11px;color:#64748b;font-weight:700;text-align:left;white-space:nowrap;">Estimasi</th>
            <th style="padding:10px 12px;font-size:11px;color:#64748b;font-weight:700;text-align:left;white-space:nowrap;">Pemohon</th>
            <th style="padding:10px 12px;font-size:11px;color:#64748b;font-weight:700;text-align:left;white-space:nowrap;">Dibuat</th>
            <th style="padding:10px 12px;font-size:11px;color:#64748b;font-weight:700;text-align:center;white-space:nowrap;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr class="ba-row">
            <td>
              <div style="font-family:monospace;font-size:11px;font-weight:700;color:#0f172a;"><?= clean($r['nomor_ba']) ?></div>
              <div style="font-size:10px;color:#94a3b8;margin-top:2px;"><?= date('d M Y',strtotime($r['tanggal_ba'])) ?></div>
            </td>
            <td>
              <span style="font-size:11px;font-weight:700;color:#1d4ed8;font-family:monospace;"><?= clean($r['nomor']) ?></span>
              <?php if ($r['prioritas']): ?>
              <div style="margin-top:2px;"><?= badgePrioritas($r['prioritas']) ?></div>
              <?php endif; ?>
            </td>
            <td style="max-width:200px;">
              <div style="font-size:12px;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px;" title="<?= clean($r['judul']) ?>">
                <?= clean($r['judul']) ?>
              </div>
              <?php if ($r['lokasi']): ?>
              <div style="font-size:10px;color:#94a3b8;margin-top:2px;"><i class="fa fa-map-marker-alt"></i> <?= clean($r['lokasi']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= jenisBadge($r['jenis_tindak']) ?></td>
            <td>
              <?php if ($r['nilai_estimasi']): ?>
              <span style="font-size:11px;font-weight:700;color:#d97706;">Rp <?= number_format((int)$r['nilai_estimasi'],0,',','.') ?></span>
              <?php else: ?>
              <span style="color:#cbd5e1;font-size:11px;">&mdash;</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-size:11px;color:#334155;font-weight:600;"><?= clean($r['req_nama']??'-') ?></div>
              <div style="font-size:10px;color:#94a3b8;"><?= clean($r['req_divisi']??'') ?></div>
            </td>
            <td>
              <div style="font-size:11px;color:#334155;"><?= clean($r['dibuat_nama']??'-') ?></div>
              <div style="font-size:10px;color:#94a3b8;"><?= date('d/m/Y',strtotime($r['created_at'])) ?></div>
            </td>
            <td style="text-align:center;">
              <div style="display:flex;gap:5px;justify-content:center;">
                <!-- Tombol lihat → buka modal -->
                <button type="button" class="btn-icon" title="Lihat Detail"
                  onclick="bukaModalBA(<?= $r['id'] ?>)">
                  <i class="fa fa-eye"></i>
                </button>
                <a href="<?= APP_URL ?>/pages/cetak_berita_acara.php?tiket_id=<?= $r['tiket_id'] ?>"
                   target="_blank" class="btn-icon" title="Cetak / PDF"
                   style="color:#059669;border-color:#bbf7d0;background:#f0fdf4;">
                  <i class="fa fa-file-pdf"></i>
                </a>
                <?php if (hasRole('admin')): ?>
                <form method="POST" onsubmit="return confirm('Hapus Berita Acara ini? Data tidak bisa dikembalikan.');" style="display:inline;">
                  <input type="hidden" name="action" value="hapus_ba">
                  <input type="hidden" name="ba_id" value="<?= $r['id'] ?>">
                  <button type="submit" class="btn-icon red" title="Hapus"><i class="fa fa-trash-alt"></i></button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div style="padding:12px 16px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <div style="font-size:11.5px;color:#94a3b8;">
        Menampilkan <?= (($page-1)*$per_page)+1 ?>–<?= min($page*$per_page,$total) ?> dari <?= $total ?> data
      </div>
      <div style="display:flex;gap:4px;">
        <?php
        $qs_base = http_build_query(['q'=>$search,'jenis'=>$filter_jenis,'tahun'=>$filter_thn]);
        for ($p=1;$p<=$total_pages;$p++): $active=($p===$page); ?>
        <a href="?<?= $qs_base ?>&page=<?= $p ?>"
           style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid <?= $active?'#00e5b0':'#e2e8f0' ?>;background:<?= $active?'#00e5b0':'#fff' ?>;color:<?= $active?'#0a0f14':'#374151' ?>;">
          <?= $p ?>
        </a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ════════════════════════════════════════
     MODAL PREVIEW BERITA ACARA
════════════════════════════════════════ -->
<div class="ba-modal-ov" id="ba-modal-ov" onclick="if(event.target===this)tutupModalBA()">
  <div class="ba-modal-box" id="ba-modal-box">

    <!-- Header modal -->
    <div class="ba-modal-hd">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:32px;height:32px;background:rgba(0,229,176,.15);border:1px solid rgba(0,229,176,.3);border-radius:8px;display:flex;align-items:center;justify-content:center;">
          <i class="fa fa-file-contract" style="color:#00e5b0;font-size:14px;"></i>
        </div>
        <div>
          <div id="ba-modal-nomor" style="font-size:13px;font-weight:700;color:#e8f0f8;font-family:monospace;"></div>
          <div id="ba-modal-tgl" style="font-size:10px;color:rgba(255,255,255,.35);margin-top:1px;"></div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <div id="ba-modal-jenis-hd"></div>
        <button onclick="tutupModalBA()" title="Tutup"
          style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);cursor:pointer;color:rgba(255,255,255,.5);font-size:13px;display:flex;align-items:center;justify-content:center;transition:all .15s;"
          onmouseover="this.style.background='#ff4d6d';this.style.color='#fff';this.style.borderColor='#ff4d6d';"
          onmouseout="this.style.background='rgba(255,255,255,.08)';this.style.color='rgba(255,255,255,.5)';this.style.borderColor='rgba(255,255,255,.12)';">
          <i class="fa fa-times"></i>
        </button>
      </div>
    </div>

    <!-- Body modal -->
    <div class="ba-modal-body" id="ba-modal-body">
      <div class="ba-spinner" id="ba-spinner"><i class="fa fa-circle-notch"></i></div>
      <div id="ba-modal-content" style="display:none;"></div>
    </div>

    <!-- Footer modal -->
    <div class="ba-modal-ft" id="ba-modal-ft" style="display:none;">
      <a id="ba-btn-tiket" href="#" class="btn btn-default btn-sm" target="_blank">
        <i class="fa fa-ticket-alt"></i> Buka Tiket
      </a>
      <a id="ba-btn-cetak" href="#" class="btn btn-success btn-sm" target="_blank">
        <i class="fa fa-file-pdf"></i> Cetak / PDF
      </a>
      <button onclick="tutupModalBA()" class="btn btn-default btn-sm">
        <i class="fa fa-times"></i> Tutup
      </button>
    </div>

  </div>
</div>

<script>
var _appUrl = '<?= APP_URL ?>';

function bukaModalBA(baId) {
  var ov = document.getElementById('ba-modal-ov');
  ov.classList.add('open');
  document.body.style.overflow = 'hidden';

  // reset
  document.getElementById('ba-modal-nomor').textContent = 'Memuat...';
  document.getElementById('ba-modal-tgl').textContent   = '';
  document.getElementById('ba-modal-jenis-hd').innerHTML = '';
  document.getElementById('ba-spinner').style.display   = 'flex';
  document.getElementById('ba-modal-content').style.display = 'none';
  document.getElementById('ba-modal-ft').style.display  = 'none';

  fetch(_appUrl + '/pages/berita_acara.php?ajax_detail=1&ba_id=' + baId)
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d) { alert('Data tidak ditemukan.'); tutupModalBA(); return; }
      renderModalBA(d);
    })
    .catch(function(){ alert('Gagal memuat data.'); tutupModalBA(); });
}

function tutupModalBA() {
  var ov = document.getElementById('ba-modal-ov');
  ov.classList.remove('open');
  document.body.style.overflow = '';
}

function esc(s) {
  if (!s) return '&mdash;';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function nl2br(s) { return esc(s).replace(/\n/g,'<br>'); }
function rupiah(n) {
  if (!n) return '&mdash;';
  return 'Rp ' + parseInt(n).toLocaleString('id-ID');
}
function tgl(s) {
  if (!s) return '&mdash;';
  var d = new Date(s);
  var bln = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
  return d.getDate() + ' ' + bln[d.getMonth()] + ' ' + d.getFullYear();
}
function jenisLabel(j) {
  var map = {
    pembelian_baru:'Pembelian Baru', perbaikan_eksternal:'Perbaikan Vendor',
    penghapusan_aset:'Hapus Aset', penggantian_suku_cadang:'Suku Cadang', lainnya:'Lainnya'
  };
  return map[j] || j;
}
function jenisBadgeJs(j) {
  var colors = {
    pembelian_baru:     {bg:'#dbeafe',c:'#1e40af'},
    perbaikan_eksternal:{bg:'#fef3c7',c:'#92400e'},
    penghapusan_aset:   {bg:'#fee2e2',c:'#991b1b'},
    penggantian_suku_cadang:{bg:'#d1fae5',c:'#065f46'},
    lainnya:            {bg:'#ede9fe',c:'#5b21b6'}
  };
  var cl = colors[j] || colors.lainnya;
  return '<span style="background:'+cl.bg+';color:'+cl.c+';font-size:11px;font-weight:700;padding:3px 12px;border-radius:20px;">'+jenisLabel(j)+'</span>';
}

function renderModalBA(d) {
  // Header
  document.getElementById('ba-modal-nomor').textContent = d.nomor_ba || '-';
  document.getElementById('ba-modal-tgl').textContent   = 'Tanggal: ' + tgl(d.tanggal_ba);
  document.getElementById('ba-modal-jenis-hd').innerHTML = jenisBadgeJs(d.jenis_tindak);

  var html = '';

  // ── I. Informasi Tiket ────────────────────────────────────────────
  html += '<div class="sec-label"><i class="fa fa-ticket-alt" style="color:#0ea5e9;margin-right:5px;"></i> Informasi Tiket</div>';
  html += '<table class="dtbl"><tbody>';
  html += '<tr><td class="dl">Nomor Tiket</td><td class="dv"><strong style="font-family:monospace;color:#1d4ed8;">'+esc(d.nomor)+'</strong></td><td class="dl">Tanggal Submit</td><td class="dv">'+esc(d.waktu_submit ? d.waktu_submit.replace('T',' ').slice(0,16) : '-')+'</td></tr>';
  html += '<tr><td class="dl">Judul / Keluhan</td><td class="dv" colspan="3"><strong>'+esc(d.judul)+'</strong></td></tr>';
  html += '<tr><td class="dl">Kategori</td><td class="dv">'+esc(d.kat_nama)+'</td><td class="dl">Prioritas</td><td class="dv">'+esc(d.prioritas)+'</td></tr>';
  html += '<tr><td class="dl">Lokasi</td><td class="dv">'+esc(d.lokasi)+'</td><td class="dl">Teknisi</td><td class="dv">'+esc(d.tek_nama)+'</td></tr>';
  html += '<tr><td class="dl">Pemohon</td><td class="dv">'+esc(d.req_nama)+'</td><td class="dl">Divisi</td><td class="dv">'+esc(d.req_divisi)+'</td></tr>';
  html += '</tbody></table>';

  // ── II. Uraian Masalah ────────────────────────────────────────────
  html += '<div class="sec-label"><i class="fa fa-align-left" style="color:#8b5cf6;margin-right:5px;"></i> Uraian Permasalahan</div>';
  html += '<div class="content-field">'+nl2br(d.uraian_masalah || d.deskripsi || '-')+'</div>';

  // ── III. Kesimpulan ───────────────────────────────────────────────
  html += '<div class="sec-label"><i class="fa fa-lightbulb" style="color:#f59e0b;margin-right:5px;"></i> Kesimpulan &amp; Analisa Teknis</div>';
  html += '<div class="content-field">'+nl2br(d.kesimpulan || '-')+'</div>';

  // ── IV. Tindak Lanjut ─────────────────────────────────────────────
  html += '<div class="sec-label"><i class="fa fa-arrow-right" style="color:#0ea5e9;margin-right:5px;"></i> Rekomendasi &amp; Tindak Lanjut</div>';
  html += '<div style="margin-bottom:8px;">'+jenisBadgeJs(d.jenis_tindak)+'</div>';
  html += '<div class="content-field" style="background:#eef3ff;border-color:#bfdbfe;">'+nl2br(d.tindak_lanjut || '-')+'</div>';

  // ── Estimasi ──────────────────────────────────────────────────────
  if (d.nilai_estimasi) {
    html += '<div style="margin-top:12px;background:#fffbeb;border:1px solid #f59e0b;border-radius:7px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;">';
    html += '<div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.5px;">Estimasi Biaya</div>';
    html += '<div style="font-size:15px;font-weight:700;color:#d97706;">'+rupiah(d.nilai_estimasi)+'</div></div>';
  }

  // ── Catatan Tambahan ──────────────────────────────────────────────
  if (d.catatan_tambahan) {
    html += '<div class="sec-label"><i class="fa fa-sticky-note" style="color:#fbbf24;margin-right:5px;"></i> Catatan Tambahan</div>';
    html += '<div class="content-field" style="background:#fefce8;border-color:#fde68a;color:#713f12;">'+nl2br(d.catatan_tambahan)+'</div>';
  }

  // ── Tanda Tangan ──────────────────────────────────────────────────
  html += '<div class="sec-label"><i class="fa fa-pen-to-square" style="color:#64748b;margin-right:5px;"></i> Tanda Tangan</div>';
  html += '<div class="ba-ttd-grid">';
  var ttd = [
    {role:'Dibuat Oleh',     nama: d.dibuat_nama,     jab: d.dibuat_divisi || 'Staff IT'},
    {role:'Diketahui Oleh',  nama: d.diketahui_nama,  jab: d.diketahui_jabatan || '&mdash;'},
    {role:'Menyetujui',      nama: d.mengetahui_nama, jab: d.mengetahui_jabatan || '&mdash;'}
  ];
  ttd.forEach(function(t){
    html += '<div class="ba-ttd-card">';
    html += '<div class="ba-ttd-role">'+t.role+',</div>';
    html += '<div class="ba-ttd-line"></div>';
    html += '<div class="ba-ttd-name">'+(t.nama ? esc(t.nama) : '<span style="color:#d1d5db;">_______________</span>')+'</div>';
    html += '<div class="ba-ttd-jab">'+(t.jab ? esc(t.jab) : '')+'</div>';
    html += '</div>';
  });
  html += '</div>';

  // Render
  document.getElementById('ba-modal-content').innerHTML = html;
  document.getElementById('ba-spinner').style.display   = 'none';
  document.getElementById('ba-modal-content').style.display = 'block';

  // Footer buttons
  document.getElementById('ba-btn-tiket').href = _appUrl + '/pages/detail_tiket.php?id=' + d.tiket_id + '#t-ba';
  document.getElementById('ba-btn-cetak').href = _appUrl + '/pages/cetak_berita_acara.php?tiket_id=' + d.tiket_id;
  document.getElementById('ba-modal-ft').style.display = 'flex';
}

document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') tutupModalBA();
});
</script>

<?php include '../includes/footer.php'; ?>