<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title = 'Semua Tiket';
$active_menu = 'semua_tiket';

$page=max(1,(int)($_GET['page']??1)); $per_page=15;
$fs=$_GET['status']??''; $fp=$_GET['prioritas']??''; $fk=$_GET['kat']??''; $search=$_GET['q']??'';

$where=['1=1']; $params=[];
if ($fs) { $where[]='t.status=?'; $params[]=$fs; }
if ($fp) { $where[]='t.prioritas=?'; $params[]=$fp; }
if ($fk) { $where[]='t.kategori_id=?'; $params[]=$fk; }
if ($search) { $where[]='(t.nomor LIKE ? OR t.judul LIKE ? OR u.nama LIKE ?)'; $params=array_merge($params,["%$search%","%$search%","%$search%"]); }
$wsql=implode(' AND ',$where);

$st=$pdo->prepare("SELECT COUNT(*) FROM tiket t LEFT JOIN users u ON u.id=t.user_id WHERE $wsql"); $st->execute($params); $total=(int)$st->fetchColumn();
$pages=max(1,ceil($total/$per_page)); $page=min($page,$pages); $offset=($page-1)*$per_page;

$st=$pdo->prepare("SELECT t.*,k.nama as kat_nama,u.nama as req_nama,u.divisi,tek.nama as tek_nama
    FROM tiket t LEFT JOIN kategori k ON k.id=t.kategori_id
    LEFT JOIN users u ON u.id=t.user_id LEFT JOIN users tek ON tek.id=t.teknisi_id
    WHERE $wsql ORDER BY t.created_at DESC LIMIT $per_page OFFSET $offset");
$st->execute($params); $tikets=$st->fetchAll();

$kat_list=$pdo->query("SELECT * FROM kategori ORDER BY nama")->fetchAll();

// Stats
$stats=[];
$st2=$pdo->query("SELECT status,COUNT(*) n FROM tiket GROUP BY status");
foreach ($st2->fetchAll() as $r) $stats[$r['status']]=$r['n'];

include '../includes/header.php';
?>

<style>
/* ── Modal Cetak Tiket ── */
.mc-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;align-items:center;justify-content:center;}
.mc-overlay.open{display:flex;}
.mc-box{background:#fff;width:100%;max-width:520px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;}
@keyframes mIn{from{opacity:0;transform:scale(.95) translateY(-10px);}to{opacity:1;transform:scale(1) translateY(0);}}

.mc-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:linear-gradient(135deg,#1a2e3f,#2a3f54);}
.mc-head-icon{width:30px;height:30px;background:rgba(38,185,154,.25);border:1px solid rgba(38,185,154,.5);border-radius:6px;display:flex;align-items:center;justify-content:center;}
.mc-head-title{color:#fff;font-size:13.5px;font-weight:700;margin-left:10px;}
.mc-head-sub{color:rgba(255,255,255,.4);font-size:10px;margin-left:10px;}
.mc-close{width:25px;height:25px;border-radius:50%;background:rgba(255,255,255,.1);border:none;cursor:pointer;color:#ccc;font-size:12px;display:flex;align-items:center;justify-content:center;transition:background .15s;}
.mc-close:hover{background:#ef4444;color:#fff;}

.mc-body{padding:18px 20px;}
.mc-section{margin-bottom:16px;}
.mc-section-label{font-size:11px;font-weight:700;color:#374151;margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.mc-section-label i{color:#26B99A;}
.mc-grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.mc-grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;}
.mc-label{font-size:11px;font-weight:600;color:#475569;display:block;margin-bottom:4px;}
.mc-inp{width:100%;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;box-sizing:border-box;font-family:inherit;transition:border .15s;}
.mc-inp:focus{outline:none;border-color:#26B99A;box-shadow:0 0 0 3px rgba(38,185,154,.1);}

/* Pilihan cepat periode */
.period-chips{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:10px;}
.period-chip{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;cursor:pointer;transition:all .14s;white-space:nowrap;}
.period-chip:hover,.period-chip.active{background:#26B99A;color:#fff;border-color:#26B99A;}

/* Pilihan jenis laporan */
.report-opts{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.report-opt{border:2px solid #e2e8f0;border-radius:8px;padding:10px 12px;cursor:pointer;transition:all .15s;background:#fff;}
.report-opt:hover{border-color:#26B99A;background:#f0fdf9;}
.report-opt.selected{border-color:#26B99A;background:#f0fdf9;}
.report-opt-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:14px;margin-bottom:6px;}
.report-opt-title{font-size:12px;font-weight:700;color:#1e293b;}
.report-opt-sub{font-size:10px;color:#94a3b8;margin-top:2px;}

.mc-foot{padding:12px 18px;border-top:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;}
.mc-preview-bar{font-size:10.5px;color:#64748b;background:#eff6ff;border:1px solid #bfdbfe;border-radius:5px;padding:5px 10px;margin:0 18px 12px;display:none;}
.mc-preview-bar.show{display:block;}
</style>

<div class="page-header">
  <h4><i class="fa fa-ticket-alt text-primary"></i> &nbsp;Semua Tiket</h4>
  <div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span class="cur">Semua Tiket</span></div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Status quick filter -->
  <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;">
    <?php foreach ([''=>'Semua','menunggu'=>'Menunggu','diproses'=>'Diproses','selesai'=>'Selesai','ditolak'=>'Ditolak','tidak_bisa'=>'Tidak Bisa'] as $v=>$l):
      $cnt=$v===''?array_sum($stats):($stats[$v]??0); ?>
    <a href="?status=<?= $v ?>" class="btn <?= $fs===$v?'btn-primary':'btn-default' ?>" style="font-size:12px;">
      <?= $l ?> <span style="background:<?= $fs===$v?'rgba(255,255,255,.3)':'#ddd' ?>;border-radius:9px;padding:0 6px;font-size:10px;"><?= $cnt ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <div class="tbl-tools">
      <div class="tbl-tools-l">
        <form method="GET" id="sf" style="display:flex;gap:7px;flex-wrap:wrap;">
          <?php if ($fs): ?><input type="hidden" name="status" value="<?= clean($fs) ?>"><?php endif; ?>
          <input type="text" name="q" value="<?= clean($search) ?>" class="inp-search" placeholder="Cari..." onchange="document.getElementById('sf').submit()">
          <select name="prioritas" class="sel-filter" onchange="document.getElementById('sf').submit()">
            <option value="">Prioritas</option>
            <option value="Tinggi" <?= $fp==='Tinggi'?'selected':'' ?>>Tinggi</option>
            <option value="Sedang" <?= $fp==='Sedang'?'selected':'' ?>>Sedang</option>
            <option value="Rendah" <?= $fp==='Rendah'?'selected':'' ?>>Rendah</option>
          </select>
          <select name="kat" class="sel-filter" onchange="document.getElementById('sf').submit()">
            <option value="">Kategori</option>
            <?php foreach ($kat_list as $k): ?><option value="<?= $k['id'] ?>" <?= $fk==$k['id']?'selected':'' ?>><?= clean($k['nama']) ?></option><?php endforeach; ?>
          </select>
          <?php if ($search||$fp||$fk): ?><a href="?status=<?= $fs ?>" class="btn btn-default btn-sm"><i class="fa fa-times"></i></a><?php endif; ?>
        </form>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="tbl-info"><?= $total ?> tiket</span>

        <!-- ══ TOMBOL CETAK ══ -->
        <button type="button" onclick="bukaModalCetak()"
          class="btn btn-default btn-sm"
          style="border-color:#26B99A;color:#0f766e;font-weight:600;">
          <i class="fa fa-print"></i> Cetak Laporan
        </button>
        <!-- ══ END TOMBOL CETAK ══ -->
      </div>
    </div>

    <div class="tbl-wrap">
      <table>
        <thead><tr><th>#</th><th>No. Tiket</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Pemohon</th><th>Teknisi</th><th>Status</th><th>Masuk</th><th>Durasi</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if (empty($tikets)): ?><tr><td colspan="11" class="td-empty"><i class="fa fa-inbox"></i> Tidak ada data</td></tr>
          <?php else: $no=$offset+1; foreach ($tikets as $t):
            $is_final=in_array($t['status'],['selesai','ditolak','tidak_bisa']);
            $dur=$is_final?$t['durasi_selesai_menit']:durasiSekarang($t['waktu_submit']);
          ?>
          <tr>
            <td style="color:#bbb;"><?= $no++ ?></td>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
            <td style="max-width:160px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= clean($t['judul']) ?>"><?= clean($t['judul']) ?></span>
              <small style="color:#aaa;"><?= clean($t['divisi']??'') ?></small></td>
            <td style="font-size:11px;"><?= clean($t['kat_nama']??'-') ?></td>
            <td><?= badgePrioritas($t['prioritas']) ?></td>
            <td><div class="d-flex ai-c gap6"><div class="av av-xs"><?= getInitials($t['req_nama']) ?></div><?= clean($t['req_nama']) ?></div></td>
            <td><?= $t['tek_nama']?'<div class="d-flex ai-c gap6"><div class="av av-xs av-blue">'.getInitials($t['tek_nama']).'</div>'.clean($t['tek_nama']).'</div>':'<span class="text-muted">—</span>' ?></td>
            <td><?= badgeStatus($t['status']) ?></td>
            <td style="font-size:11px;color:#aaa;white-space:nowrap;"><?= formatTanggal($t['waktu_submit']) ?></td>
            <td style="font-size:12px;font-weight:700;"><?= formatDurasi($dur) ?></td>
            <td><a href="<?= APP_URL ?>/pages/detail_tiket.php?id=<?= $t['id'] ?>" class="btn btn-info btn-sm"><i class="fa fa-eye"></i></a></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="tbl-footer">
      <span class="tbl-info">Menampilkan <?= min($offset+1,$total) ?>–<?= min($offset+$per_page,$total) ?> dari <?= $total ?></span>
      <?php if ($pages>1): ?><div class="pagination">
        <?php if ($page>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="pag-btn"><i class="fa fa-chevron-left"></i></a><?php endif; ?>
        <?php for($i=1;$i<=$pages;$i++): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="pag-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
        <?php if ($page<$pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="pag-btn"><i class="fa fa-chevron-right"></i></a><?php endif; ?>
      </div><?php endif; ?>
    </div>
  </div>
</div>


<!-- ════════════════════════════════════════════════════
     MODAL CETAK LAPORAN TIKET
════════════════════════════════════════════════════════ -->
<div class="mc-overlay" id="mc-overlay" onclick="if(event.target===this)tutupModalCetak()">
  <div class="mc-box">

    <!-- Header -->
    <div class="mc-head">
      <div style="display:flex;align-items:center;">
        <div class="mc-head-icon"><i class="fa fa-print" style="color:#26B99A;font-size:13px;"></i></div>
        <div>
          <div class="mc-head-title">Cetak Laporan Tiket</div>
          <div class="mc-head-sub">Atur filter dan periode laporan</div>
        </div>
      </div>
      <button class="mc-close" onclick="tutupModalCetak()"><i class="fa fa-times"></i></button>
    </div>

    <!-- Body -->
    <div class="mc-body">

      <!-- Pilihan cepat periode -->
      <div class="mc-section">
        <div class="mc-section-label"><i class="fa fa-calendar-days"></i> Pilihan Cepat Periode</div>
        <div class="period-chips">
          <span class="period-chip" onclick="setPeriod('bulan_ini')">Bulan Ini</span>
          <span class="period-chip" onclick="setPeriod('bulan_lalu')">Bulan Lalu</span>
          <span class="period-chip" onclick="setPeriod('3_bulan')">3 Bulan Terakhir</span>
          <span class="period-chip" onclick="setPeriod('6_bulan')">6 Bulan Terakhir</span>
          <span class="period-chip" onclick="setPeriod('tahun_ini')">Tahun Ini</span>
          <span class="period-chip" onclick="setPeriod('semua')">Semua Waktu</span>
        </div>
      </div>

      <!-- Rentang tanggal manual -->
      <div class="mc-section">
        <div class="mc-section-label"><i class="fa fa-calendar-range"></i> Rentang Tanggal</div>
        <div class="mc-grid2">
          <div>
            <label class="mc-label">Tanggal Mulai</label>
            <input type="date" id="mc-tgl-dari" class="mc-inp" value="<?= date('Y-m-01') ?>" onchange="updatePreview()">
          </div>
          <div>
            <label class="mc-label">Tanggal Sampai</label>
            <input type="date" id="mc-tgl-sampai" class="mc-inp" value="<?= date('Y-m-t') ?>" onchange="updatePreview()">
          </div>
        </div>
      </div>

      <!-- Filter tambahan -->
      <div class="mc-section">
        <div class="mc-section-label"><i class="fa fa-filter"></i> Filter Tambahan</div>
        <div class="mc-grid3">
          <div>
            <label class="mc-label">Kategori</label>
            <select id="mc-kat" class="mc-inp" onchange="updatePreview()">
              <option value="">Semua Kategori</option>
              <?php foreach ($kat_list as $k): ?>
              <option value="<?= $k['id'] ?>"><?= clean($k['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="mc-label">Status</label>
            <select id="mc-status" class="mc-inp" onchange="updatePreview()">
              <option value="">Semua Status</option>
              <option value="menunggu">Menunggu</option>
              <option value="diproses">Diproses</option>
              <option value="selesai">Selesai</option>
              <option value="ditolak">Ditolak</option>
              <option value="tidak_bisa">Tidak Bisa</option>
            </select>
          </div>
          <div>
            <label class="mc-label">Prioritas</label>
            <select id="mc-prioritas" class="mc-inp" onchange="updatePreview()">
              <option value="">Semua Prioritas</option>
              <option value="Tinggi">Tinggi</option>
              <option value="Sedang">Sedang</option>
              <option value="Rendah">Rendah</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Jenis laporan -->
      <div class="mc-section" style="margin-bottom:0;">
        <div class="mc-section-label"><i class="fa fa-file-pdf"></i> Jenis Laporan</div>
        <div class="report-opts">
          <div class="report-opt selected" id="opt-semua" onclick="pilihJenis('semua')">
            <div class="report-opt-icon" style="background:#eff6ff;">
              <i class="fa fa-list" style="color:#1d4ed8;"></i>
            </div>
            <div class="report-opt-title">Semua Kategori</div>
            <div class="report-opt-sub">Laporan lengkap semua kategori tiket</div>
          </div>
          <div class="report-opt" id="opt-kat" onclick="pilihJenis('kat')">
            <div class="report-opt-icon" style="background:#f0fdf9;">
              <i class="fa fa-tag" style="color:#26B99A;"></i>
            </div>
            <div class="report-opt-title">Per Kategori Terpilih</div>
            <div class="report-opt-sub">Hanya kategori yang dipilih di atas</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview URL info -->
    <div class="mc-preview-bar" id="mc-preview-bar">
      <i class="fa fa-circle-info" style="color:#1d4ed8;"></i>
      <span id="mc-preview-text">Laporan akan memuat data tiket...</span>
    </div>

    <!-- Footer -->
    <div class="mc-foot">
      <div style="font-size:10.5px;color:#94a3b8;">
        <i class="fa fa-file-pdf" style="color:#ef4444;"></i>
        Laporan terbuka sebagai PDF di tab baru
      </div>
      <div style="display:flex;gap:8px;">
        <button type="button" onclick="tutupModalCetak()"
          style="padding:7px 15px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;cursor:pointer;color:#64748b;font-family:inherit;">
          Batal
        </button>
        <button type="button" onclick="cetakLaporan()"
          style="padding:7px 18px;background:linear-gradient(135deg,#26B99A,#1a7a5e);border:none;border-radius:5px;font-size:12px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;">
          <i class="fa fa-print"></i> Cetak PDF
        </button>
      </div>
    </div>

  </div>
</div>
<!-- ════ END MODAL CETAK ════ -->


<script>
const APP_URL = '<?= APP_URL ?>';
let jenisLaporan = 'semua';

/* ── Buka / Tutup Modal ── */
function bukaModalCetak() {
    document.getElementById('mc-overlay').classList.add('open');
    updatePreview();
}
function tutupModalCetak() {
    document.getElementById('mc-overlay').classList.remove('open');
}

/* ── Pilih jenis laporan ── */
function pilihJenis(j) {
    jenisLaporan = j;
    document.getElementById('opt-semua').classList.toggle('selected', j === 'semua');
    document.getElementById('opt-kat').classList.toggle('selected', j === 'kat');
    updatePreview();
}

/* ── Pilihan cepat periode ── */
function setPeriod(type) {
    // Reset chip aktif
    document.querySelectorAll('.period-chip').forEach(c => c.classList.remove('active'));
    event.target.classList.add('active');

    const now   = new Date();
    let dari, sampai;

    if (type === 'bulan_ini') {
        dari   = new Date(now.getFullYear(), now.getMonth(), 1);
        sampai = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    } else if (type === 'bulan_lalu') {
        dari   = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        sampai = new Date(now.getFullYear(), now.getMonth(), 0);
    } else if (type === '3_bulan') {
        dari   = new Date(now.getFullYear(), now.getMonth() - 2, 1);
        sampai = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    } else if (type === '6_bulan') {
        dari   = new Date(now.getFullYear(), now.getMonth() - 5, 1);
        sampai = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    } else if (type === 'tahun_ini') {
        dari   = new Date(now.getFullYear(), 0, 1);
        sampai = new Date(now.getFullYear(), 11, 31);
    } else { // semua
        dari   = new Date(2020, 0, 1);
        sampai = new Date(now.getFullYear(), 11, 31);
    }

    document.getElementById('mc-tgl-dari').value   = fmtDate(dari);
    document.getElementById('mc-tgl-sampai').value = fmtDate(sampai);
    updatePreview();
}

function fmtDate(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const dd= String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${dd}`;
}

/* ── Update preview info ── */
function updatePreview() {
    const dari    = document.getElementById('mc-tgl-dari').value;
    const sampai  = document.getElementById('mc-tgl-sampai').value;
    const kat     = document.getElementById('mc-kat');
    const status  = document.getElementById('mc-status');
    const prior   = document.getElementById('mc-prioritas');

    const katLabel    = kat.options[kat.selectedIndex].text;
    const statusLabel = status.options[status.selectedIndex].text;
    const priorLabel  = prior.options[prior.selectedIndex].text;

    const bar  = document.getElementById('mc-preview-bar');
    const text = document.getElementById('mc-preview-text');

    if (dari && sampai) {
        const d1 = new Date(dari), d2 = new Date(sampai);
        const diff = Math.round((d2-d1)/(1000*60*60*24)) + 1;
        text.innerHTML = `<strong>Periode:</strong> ${dari} s.d. ${sampai} (${diff} hari) &nbsp;|&nbsp; `
                       + `<strong>Kategori:</strong> ${katLabel} &nbsp;|&nbsp; `
                       + `<strong>Status:</strong> ${statusLabel} &nbsp;|&nbsp; `
                       + `<strong>Prioritas:</strong> ${priorLabel}`;
        bar.classList.add('show');
    } else {
        bar.classList.remove('show');
    }
}

/* ── Cetak ── */
function cetakLaporan() {
    const dari    = document.getElementById('mc-tgl-dari').value;
    const sampai  = document.getElementById('mc-tgl-sampai').value;
    const kat     = document.getElementById('mc-kat').value;
    const status  = document.getElementById('mc-status').value;
    const prior   = document.getElementById('mc-prioritas').value;

    if (!dari || !sampai) {
        alert('Harap isi tanggal mulai dan tanggal sampai.');
        return;
    }
    if (new Date(dari) > new Date(sampai)) {
        alert('Tanggal mulai tidak boleh lebih besar dari tanggal sampai.');
        return;
    }

    // Jika jenis = 'kat' tapi tidak ada kategori dipilih, tampilkan semua
    let params = `tgl_dari=${encodeURIComponent(dari)}&tgl_sampai=${encodeURIComponent(sampai)}`;
    if (kat)    params += `&kat=${encodeURIComponent(kat)}`;
    if (status) params += `&status=${encodeURIComponent(status)}`;
    if (prior)  params += `&prioritas=${encodeURIComponent(prior)}`;

    const url = `${APP_URL}/pages/cetak_semua_tiket.php?${params}`;
    window.open(url, '_blank');
    tutupModalCetak();
}

/* ── Init preview saat load ── */
document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
});
</script>

<?php include '../includes/footer.php'; ?>