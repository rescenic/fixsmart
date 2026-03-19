<?php
// pages/master_cuti.php
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin','hrd'])) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }

$page_title  = 'Master Cuti';
$active_menu = 'master_cuti';

// ── AJAX ─────────────────────────────────────────────────
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $act = $_POST['act'] ?? '';

    // ── Jenis Cuti CRUD ──────────────────────────────────
    if ($act === 'jenis_simpan') {
        $id    = (int)($_POST['id'] ?? 0);
        $kode  = strtoupper(trim($_POST['kode'] ?? ''));
        $nama  = trim($_POST['nama'] ?? '');
        $kuota = max(0, (int)($_POST['kuota'] ?? 0));
        $ket   = trim($_POST['keterangan'] ?? '') ?: null;
        $urutan= (int)($_POST['urutan'] ?? 0);
        $pl    = isset($_POST['perlu_lampiran']) ? 1 : 0;
        $st    = in_array($_POST['status'] ?? '', ['aktif','nonaktif']) ? $_POST['status'] : 'aktif';
        if (!$kode || !$nama) { echo json_encode(['ok'=>false,'msg'=>'Kode dan nama wajib diisi.']); exit; }
        try {
            if ($id) {
                $pdo->prepare("UPDATE master_jenis_cuti SET kode=?,nama=?,kuota_default=?,perlu_lampiran=?,keterangan=?,urutan=?,status=? WHERE id=?")
                    ->execute([$kode,$nama,$kuota,$pl,$ket,$urutan,$st,$id]);
            } else {
                $pdo->prepare("INSERT INTO master_jenis_cuti (kode,nama,kuota_default,perlu_lampiran,keterangan,urutan,status) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$kode,$nama,$kuota,$pl,$ket,$urutan,$st]);
            }
            echo json_encode(['ok'=>true,'msg'=>'Jenis cuti berhasil disimpan.']);
        } catch(Exception $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
        exit;
    }

    if ($act === 'jenis_hapus') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $used = $pdo->prepare("SELECT COUNT(*) FROM jatah_cuti WHERE jenis_cuti_id=?"); $used->execute([$id]);
            if ($used->fetchColumn()) { echo json_encode(['ok'=>false,'msg'=>'Tidak bisa dihapus, sudah ada jatah cuti.']); exit; }
            $pdo->prepare("DELETE FROM master_jenis_cuti WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true,'msg'=>'Jenis cuti dihapus.']);
        } catch(Exception $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
        exit;
    }

    // ── Jatah Cuti ───────────────────────────────────────
    if ($act === 'jatah_simpan') {
        $uid   = (int)($_POST['user_id'] ?? 0);
        $jid   = (int)($_POST['jenis_cuti_id'] ?? 0);
        $tahun = (int)($_POST['tahun'] ?? date('Y'));
        $kuota = max(0, (int)($_POST['kuota'] ?? 0));
        $ket   = trim($_POST['catatan'] ?? '') ?: null;
        if (!$uid || !$jid) { echo json_encode(['ok'=>false,'msg'=>'Data tidak lengkap.']); exit; }
        try {
            $terpakai = 0;
            $ex = $pdo->prepare("SELECT id,terpakai FROM jatah_cuti WHERE user_id=? AND jenis_cuti_id=? AND tahun=?");
            $ex->execute([$uid,$jid,$tahun]);
            $row = $ex->fetch();
            if ($row) {
                $terpakai = (int)$row['terpakai'];
                $sisa = max(0, $kuota - $terpakai);
                $pdo->prepare("UPDATE jatah_cuti SET kuota=?,sisa=?,catatan=?,updated_by=? WHERE id=?")
                    ->execute([$kuota,$sisa,$ket,$_SESSION['user_id'],$row['id']]);
            } else {
                $pdo->prepare("INSERT INTO jatah_cuti (user_id,jenis_cuti_id,tahun,kuota,terpakai,sisa,catatan,updated_by) VALUES (?,?,?,?,0,?,?,?)")
                    ->execute([$uid,$jid,$tahun,$kuota,$kuota,$ket,$_SESSION['user_id']]);
            }
            echo json_encode(['ok'=>true,'msg'=>'Jatah cuti berhasil disimpan.']);
        } catch(Exception $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
        exit;
    }

    // Inisialisasi jatah massal untuk 1 tahun
    if ($act === 'jatah_init') {
        $tahun = (int)($_POST['tahun'] ?? date('Y'));
        try {
            $jenis_list = $pdo->query("SELECT id,kuota_default FROM master_jenis_cuti WHERE status='aktif' AND kuota_default>0")->fetchAll();
            $users_list = $pdo->query("SELECT id FROM users WHERE status='aktif'")->fetchAll();
            $cnt = 0;
            foreach ($users_list as $u) foreach ($jenis_list as $j) {
                $ex=$pdo->prepare("SELECT id FROM jatah_cuti WHERE user_id=? AND jenis_cuti_id=? AND tahun=?");
                $ex->execute([$u['id'],$j['id'],$tahun]);
                if (!$ex->fetchColumn()) {
                    $pdo->prepare("INSERT INTO jatah_cuti (user_id,jenis_cuti_id,tahun,kuota,terpakai,sisa,updated_by) VALUES (?,?,?,?,0,?,?)")
                        ->execute([$u['id'],$j['id'],$tahun,$j['kuota_default'],$j['kuota_default'],$_SESSION['user_id']]);
                    $cnt++;
                }
            }
            echo json_encode(['ok'=>true,'msg'=>"$cnt jatah cuti berhasil diinisialisasi untuk tahun $tahun."]);
        } catch(Exception $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Aksi tidak dikenal.']); exit;
}

// ── Data ─────────────────────────────────────────────────
$jenis_list = $pdo->query("SELECT * FROM master_jenis_cuti ORDER BY urutan,nama")->fetchAll(PDO::FETCH_ASSOC);
$tahun_sel  = (int)($_GET['tahun'] ?? date('Y'));
$divisi_sel = $_GET['divisi'] ?? '';

$where_div = $divisi_sel ? "AND u.divisi = ".($pdo->quote($divisi_sel)) : '';
$jatah_list = $pdo->query("
    SELECT u.id user_id, u.nama, u.divisi,
           COALESCE(s.nik_rs,'') nik_rs,
           jc.id jenis_id, jc.kode jenis_kode, jc.nama jenis_nama,
           COALESCE(jt.kuota, jc.kuota_default) kuota,
           COALESCE(jt.terpakai,0) terpakai,
           COALESCE(jt.sisa, jc.kuota_default) sisa,
           jt.id jatah_id
    FROM users u
    CROSS JOIN master_jenis_cuti jc
    LEFT JOIN sdm_karyawan s ON s.user_id = u.id
    LEFT JOIN jatah_cuti jt ON jt.user_id=u.id AND jt.jenis_cuti_id=jc.id AND jt.tahun=$tahun_sel
    WHERE u.status='aktif' AND jc.status='aktif' AND jc.kuota_default>0 $where_div
    ORDER BY u.divisi, u.nama, jc.urutan
")->fetchAll(PDO::FETCH_ASSOC);

$divisi_opts = $pdo->query("SELECT DISTINCT divisi FROM users WHERE status='aktif' AND divisi!='' ORDER BY divisi")->fetchAll(PDO::FETCH_COLUMN);

include '../includes/header.php';
?>
<style>
.mc-wrap { font-family:'Inter',sans-serif; color:#1e293b; }
.mc-tabs { display:flex;gap:0;border-bottom:2px solid #e5e7eb;margin-bottom:18px; }
.mc-tab { padding:10px 20px;font-size:12.5px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:2.5px solid transparent;margin-bottom:-2px;color:#6b7280;transition:all .15s;font-family:inherit; }
.mc-tab.active { color:#00c896;border-bottom-color:#00c896; }
.mc-pane { display:none; }
.mc-pane.active { display:block; }
/* Jatah table */
.jatah-tbl td,.jatah-tbl th { font-size:11.5px; }
.sisa-bar { height:5px;border-radius:99px;background:#e5e7eb;overflow:hidden;margin-top:3px; }
.sisa-fill { height:100%;border-radius:99px;background:linear-gradient(90deg,#00e5b0,#00c896); }
.sisa-fill.warn { background:linear-gradient(90deg,#f59e0b,#d97706); }
.sisa-fill.danger { background:linear-gradient(90deg,#ef4444,#dc2626); }
.badge-hari { display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:5px; }
</style>

<div class="page-header">
  <h4><i class="fa fa-calendar-minus text-primary"></i> &nbsp;Master Cuti</h4>
  <div class="breadcrumb">
    <a href="<?=APP_URL?>/dashboard.php">Dashboard</a><span class="sep">/</span>
    <a href="<?=APP_URL?>/pages/management.php">Management</a><span class="sep">/</span>
    <span class="cur">Master Cuti</span>
  </div>
</div>

<div class="content mc-wrap">
  <?= showFlash() ?>

  <div class="panel" style="padding:0;overflow:hidden;">
    <div class="mc-tabs" style="padding:0 20px;background:#fafbfc;border-bottom:2px solid #e5e7eb;">
      <button class="mc-tab active" data-pane="tab-jenis"><i class="fa fa-list"></i> Jenis Cuti</button>
      <button class="mc-tab" data-pane="tab-jatah"><i class="fa fa-calendar-days"></i> Jatah Cuti per Karyawan</button>
    </div>

    <!-- TAB 1: Jenis Cuti -->
    <div id="tab-jenis" class="mc-pane active" style="padding:20px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div style="font-size:13px;font-weight:700;color:#0f172a;">Daftar Jenis Cuti</div>
        <button class="btn btn-primary btn-sm" onclick="openJenisFrm(0)"><i class="fa fa-plus"></i> Tambah Jenis</button>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>#</th><th>Kode</th><th>Nama</th><th>Kuota (hari/tahun)</th><th>Lampiran</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody id="jenis-tbody">
            <?php foreach ($jenis_list as $i=>$j): ?>
            <tr id="jr-<?=$j['id']?>">
              <td style="color:#cbd5e1;"><?=$i+1?></td>
              <td><code style="font-size:11px;background:#f1f5f9;padding:2px 7px;border-radius:4px;"><?=clean($j['kode'])?></code></td>
              <td style="font-weight:600;"><?=clean($j['nama'])?></td>
              <td>
                <?php if ($j['kuota_default']==0): ?>
                <span style="font-size:11px;color:#94a3b8;font-style:italic;">Tidak terbatas</span>
                <?php else: ?>
                <span class="badge-hari" style="background:#dbeafe;color:#1d4ed8;"><?=$j['kuota_default']?> hari</span>
                <?php endif; ?>
              </td>
              <td><?=$j['perlu_lampiran']?'<span style="color:#f59e0b;font-size:11px;font-weight:700;"><i class="fa fa-paperclip"></i> Ya</span>':'<span style="color:#cbd5e1;font-size:11px;">—</span>'?></td>
              <td>
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;padding:2px 9px;border-radius:20px;
                  background:<?=$j['status']==='aktif'?'#d1fae5':'#f1f5f9'?>;color:<?=$j['status']==='aktif'?'#065f46':'#64748b'?>;">
                  <?=$j['status']==='aktif'?'Aktif':'Nonaktif'?>
                </span>
              </td>
              <td>
                <button class="btn btn-warning btn-sm" onclick='openJenisFrm(<?=json_encode($j)?>)'><i class="fa fa-edit"></i></button>
                <button class="btn btn-danger btn-sm" onclick="hapusJenis(<?=$j['id']?>,\\'<?=addslashes(clean($j['nama']))?>\\')" style="margin-left:3px;"><i class="fa fa-trash"></i></button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 2: Jatah per Karyawan -->
    <div id="tab-jatah" class="mc-pane" style="padding:20px;">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap;">
        <div style="font-size:13px;font-weight:700;color:#0f172a;">Jatah Cuti</div>
        <!-- Filter -->
        <form method="GET" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <input type="hidden" name="tab" value="jatah">
          <select name="tahun" class="form-control" style="width:100px;height:32px;font-size:12px;" onchange="this.form.submit()">
            <?php for($y=date('Y')+1;$y>=date('Y')-3;$y--): ?>
            <option value="<?=$y?>" <?=$tahun_sel==$y?'selected':''?>><?=$y?></option>
            <?php endfor; ?>
          </select>
          <select name="divisi" class="form-control" style="width:160px;height:32px;font-size:12px;" onchange="this.form.submit()">
            <option value="">Semua Divisi</option>
            <?php foreach ($divisi_opts as $d): ?>
            <option value="<?=htmlspecialchars($d)?>" <?=$divisi_sel===$d?'selected':''?>><?=htmlspecialchars($d)?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <button class="btn btn-default btn-sm" onclick="initJatah(<?=$tahun_sel?>)" style="margin-left:auto;">
          <i class="fa fa-magic"></i> Inisialisasi <?=$tahun_sel?> (semua karyawan)
        </button>
      </div>

      <?php
      // Grup per karyawan
      $by_user = [];
      foreach ($jatah_list as $row) {
          $by_user[$row['user_id']]['nama']   = $row['nama'];
          $by_user[$row['user_id']]['divisi'] = $row['divisi'];
          $by_user[$row['user_id']]['nik_rs'] = $row['nik_rs'];
          $by_user[$row['user_id']]['jenis'][]= $row;
      }
      $cur_div = null;
      ?>
      <div class="tbl-wrap">
        <table class="jatah-tbl">
          <thead><tr><th>Karyawan</th><?php foreach ($jenis_list as $j): if($j['kuota_default']==0||$j['status']!='aktif')continue; ?><th><?=clean($j['kode'])?></th><?php endforeach; ?><th style="text-align:center;">Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($by_user as $uid => $udata):
            if ($udata['divisi'] !== $cur_div) {
                $cur_div = $udata['divisi'];
                echo '<tr><td colspan="99" style="background:#f8fafc;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;padding:6px 14px;"><i class="fa fa-building" style="margin-right:5px;"></i>'.htmlspecialchars($cur_div?:'Tanpa Divisi').'</td></tr>';
            }
            $jatah_by_jenis = [];
            foreach ($udata['jenis'] as $j2) $jatah_by_jenis[$j2['jenis_id']] = $j2;
          ?>
          <tr>
            <td>
              <div style="font-weight:600;font-size:12px;color:#0f172a;"><?=clean($udata['nama'])?></div>
              <?php if($udata['nik_rs']): ?><div style="font-size:10px;color:#6366f1;font-family:monospace;"><?=clean($udata['nik_rs'])?></div><?php endif; ?>
            </td>
            <?php foreach ($jenis_list as $jn): if($jn['kuota_default']==0||$jn['status']!='aktif')continue;
              $jd = $jatah_by_jenis[$jn['id']] ?? null;
              $kuota   = $jd ? $jd['kuota']    : $jn['kuota_default'];
              $terpakai= $jd ? $jd['terpakai'] : 0;
              $sisa    = max(0,$kuota-$terpakai);
              $pct     = $kuota>0 ? round($sisa/$kuota*100) : 100;
              $cls     = $pct>50 ? '' : ($pct>20?'warn':'danger');
            ?>
            <td style="min-width:72px;">
              <div style="font-size:11px;font-weight:700;color:#0f172a;"><?=$sisa?>/<?=$kuota?></div>
              <div class="sisa-bar"><div class="sisa-fill <?=$cls?>" style="width:<?=$pct?>%;"></div></div>
              <div style="font-size:9.5px;color:#94a3b8;margin-top:1px;"><?=$terpakai?> terpakai</div>
            </td>
            <?php endforeach; ?>
            <td style="text-align:center;">
              <button class="btn btn-primary btn-sm" style="font-size:10px;" onclick='openJatahFrm(<?=$uid?>,<?=json_encode($udata["nama"])?>,<?=json_encode($jatah_by_jenis)?>)'>
                <i class="fa fa-edit"></i> Edit
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div><!-- /.panel -->
</div>

<!-- Modal Jenis Cuti -->
<div class="modal-ov" id="m-jenis">
  <div class="modal-box sm">
    <div class="modal-hd"><h5 id="m-jenis-title"><i class="fa fa-list"></i> Tambah Jenis Cuti</h5><button class="mc" onclick="closeModal('m-jenis')"><i class="fa fa-times"></i></button></div>
    <div class="modal-bd">
      <input type="hidden" id="jf-id">
      <div class="form-row">
        <div class="form-group">
          <label>Kode <span class="req">*</span></label>
          <input type="text" id="jf-kode" class="form-control" placeholder="TAHUNAN" style="text-transform:uppercase;">
        </div>
        <div class="form-group">
          <label>Urutan</label>
          <input type="number" id="jf-urutan" class="form-control" value="0" min="0">
        </div>
      </div>
      <div class="form-group">
        <label>Nama Jenis Cuti <span class="req">*</span></label>
        <input type="text" id="jf-nama" class="form-control" placeholder="Cuti Tahunan">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Kuota Default (hari/tahun)</label>
          <input type="number" id="jf-kuota" class="form-control" value="12" min="0">
          <small style="color:#94a3b8;font-size:10.5px;">0 = tidak terbatas</small>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select id="jf-status" class="form-control">
            <option value="aktif">Aktif</option>
            <option value="nonaktif">Nonaktif</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
          <input type="checkbox" id="jf-lampiran" style="width:15px;height:15px;">
          <span>Wajib lampiran (surat dokter, dll)</span>
        </label>
      </div>
      <div class="form-group">
        <label>Keterangan</label>
        <input type="text" id="jf-ket" class="form-control" placeholder="Opsional">
      </div>
    </div>
    <div class="modal-ft">
      <button class="btn btn-default" onclick="closeModal('m-jenis')">Batal</button>
      <button class="btn btn-primary" onclick="simpanJenis()"><i class="fa fa-save"></i> Simpan</button>
    </div>
  </div>
</div>

<!-- Modal Edit Jatah -->
<div class="modal-ov" id="m-jatah">
  <div class="modal-box">
    <div class="modal-hd"><h5><i class="fa fa-calendar-days"></i> Edit Jatah Cuti — <span id="jt-nama" style="color:#00c896;"></span></h5><button class="mc" onclick="closeModal('m-jatah')"><i class="fa fa-times"></i></button></div>
    <div class="modal-bd" id="jt-body"></div>
    <div class="modal-ft">
      <button class="btn btn-default" onclick="closeModal('m-jatah')">Batal</button>
      <button class="btn btn-primary" onclick="simpanJatah()"><i class="fa fa-save"></i> Simpan Semua</button>
    </div>
  </div>
</div>

<script>
const TAHUN = <?= $tahun_sel ?>;
const JENIS_LIST = <?= json_encode(array_values(array_filter($jenis_list, fn($j)=>$j['status']==='aktif'&&$j['kuota_default']>0))) ?>;
let _jatah_uid = 0;

function openJenisFrm(data) {
    document.getElementById('m-jenis-title').innerHTML = data&&data.id ? '<i class="fa fa-edit"></i> Edit Jenis Cuti' : '<i class="fa fa-plus"></i> Tambah Jenis Cuti';
    if (data && data.id) {
        document.getElementById('jf-id').value       = data.id;
        document.getElementById('jf-kode').value     = data.kode;
        document.getElementById('jf-nama').value     = data.nama;
        document.getElementById('jf-kuota').value    = data.kuota_default;
        document.getElementById('jf-urutan').value   = data.urutan;
        document.getElementById('jf-status').value   = data.status;
        document.getElementById('jf-lampiran').checked = data.perlu_lampiran==1;
        document.getElementById('jf-ket').value      = data.keterangan||'';
    } else {
        document.getElementById('jf-id').value=''; document.getElementById('jf-kode').value='';
        document.getElementById('jf-nama').value=''; document.getElementById('jf-kuota').value=12;
        document.getElementById('jf-urutan').value=0; document.getElementById('jf-status').value='aktif';
        document.getElementById('jf-lampiran').checked=false; document.getElementById('jf-ket').value='';
    }
    openModal('m-jenis');
}
function simpanJenis() {
    const fd=new FormData();
    fd.append('ajax','1'); fd.append('act','jenis_simpan');
    fd.append('id',document.getElementById('jf-id').value);
    fd.append('kode',document.getElementById('jf-kode').value);
    fd.append('nama',document.getElementById('jf-nama').value);
    fd.append('kuota',document.getElementById('jf-kuota').value);
    fd.append('urutan',document.getElementById('jf-urutan').value);
    fd.append('status',document.getElementById('jf-status').value);
    fd.append('keterangan',document.getElementById('jf-ket').value);
    if(document.getElementById('jf-lampiran').checked) fd.append('perlu_lampiran','1');
    fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'})
        .then(r=>r.json()).then(d=>{ if(d.ok){closeModal('m-jenis');location.reload();}else alert(d.msg); });
}
function hapusJenis(id,nama) {
    if(!confirm('Hapus jenis cuti "'+nama+'"?')) return;
    const fd=new FormData(); fd.append('ajax','1'); fd.append('act','jenis_hapus'); fd.append('id',id);
    fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'})
        .then(r=>r.json()).then(d=>{ if(d.ok)location.reload();else alert(d.msg); });
}

function openJatahFrm(uid, nama, existing) {
    _jatah_uid = uid;
    document.getElementById('jt-nama').textContent = nama;
    let html = '';
    JENIS_LIST.forEach(j => {
        const e = existing[j.id] || {};
        const kuota   = e.kuota   ?? j.kuota_default;
        const terpakai= e.terpakai ?? 0;
        const sisa    = e.sisa    ?? j.kuota_default;
        html += `<div style="display:grid;grid-template-columns:1fr 80px 80px 80px;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #f0f2f7;">
          <div>
            <div style="font-size:12px;font-weight:700;color:#0f172a;">${j.nama}</div>
            <div style="font-size:10px;color:#94a3b8;">Default: ${j.kuota_default} hari/tahun</div>
          </div>
          <div>
            <div style="font-size:9.5px;color:#64748b;margin-bottom:2px;font-weight:600;">Kuota</div>
            <input type="number" id="jt-kuota-${j.id}" value="${kuota}" min="0" max="365"
              style="width:100%;height:30px;border:1px solid #d1d5db;border-radius:6px;padding:0 7px;font-size:12px;font-family:inherit;text-align:center;"
              oninput="hitungSisa(${j.id})">
          </div>
          <div>
            <div style="font-size:9.5px;color:#64748b;margin-bottom:2px;font-weight:600;">Terpakai</div>
            <input type="number" id="jt-terpakai-${j.id}" value="${terpakai}" min="0"
              style="width:100%;height:30px;border:1px solid #d1d5db;border-radius:6px;padding:0 7px;font-size:12px;font-family:inherit;text-align:center;background:#f9fafb;"
              oninput="hitungSisa(${j.id})">
          </div>
          <div>
            <div style="font-size:9.5px;color:#64748b;margin-bottom:2px;font-weight:600;">Sisa</div>
            <div id="jt-sisa-${j.id}" style="height:30px;border-radius:6px;background:#f0fdf9;border:1px solid #a7f3d0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#059669;">${sisa}</div>
          </div>
        </div>`;
    });
    document.getElementById('jt-body').innerHTML = html;
    openModal('m-jatah');
}
function hitungSisa(jid) {
    const k=parseInt(document.getElementById('jt-kuota-'+jid).value)||0;
    const t=parseInt(document.getElementById('jt-terpakai-'+jid).value)||0;
    document.getElementById('jt-sisa-'+jid).textContent=Math.max(0,k-t);
}
function simpanJatah() {
    const saves = JENIS_LIST.map(j => {
        const kuota   = parseInt(document.getElementById('jt-kuota-'+j.id).value)||0;
        const terpakai= parseInt(document.getElementById('jt-terpakai-'+j.id).value)||0;
        const fd=new FormData();
        fd.append('ajax','1'); fd.append('act','jatah_simpan');
        fd.append('user_id',_jatah_uid); fd.append('jenis_cuti_id',j.id);
        fd.append('tahun',TAHUN); fd.append('kuota',kuota); fd.append('terpakai',terpakai);
        return fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json());
    });
    Promise.all(saves).then(results => {
        const err=results.find(r=>!r.ok);
        if(err) alert(err.msg);
        else { closeModal('m-jatah'); location.reload(); }
    });
}
function initJatah(tahun) {
    if(!confirm('Inisialisasi jatah cuti untuk SEMUA karyawan aktif tahun '+tahun+'?\nSlot yang sudah ada tidak akan ditimpa.')) return;
    const fd=new FormData(); fd.append('ajax','1'); fd.append('act','jatah_init'); fd.append('tahun',tahun);
    fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'})
        .then(r=>r.json()).then(d=>{ alert(d.msg); if(d.ok) location.reload(); });
}

// Tabs
document.querySelectorAll('.mc-tab').forEach(btn=>{
    btn.addEventListener('click',function(){
        document.querySelectorAll('.mc-tab').forEach(b=>b.classList.remove('active'));
        document.querySelectorAll('.mc-pane').forEach(p=>p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.pane).classList.add('active');
    });
});
// Auto-open tab dari URL
const urlP=new URLSearchParams(location.search);
if(urlP.get('tab')==='jatah') document.querySelector('[data-pane=tab-jatah]').click();
</script>
<?php include '../includes/footer.php'; ?>