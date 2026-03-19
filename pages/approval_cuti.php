<?php
// pages/approval_cuti.php
session_start();
require_once '../config.php';
requireLogin();

$page_title  = 'Approval Cuti';
$active_menu = 'approval_cuti';
$uid   = (int)$_SESSION['user_id'];
$role  = $_SESSION['user_role'] ?? 'user';

// Siapa bisa approve?
// Level 1: siapapun yang jadi atasan_id di sdm_karyawan, atau admin (direktur)
// Level 2: hrd atau admin
$can_l1 = true; // cek per baris
$can_l2 = in_array($role, ['hrd','admin']);

// ── AJAX approval ─────────────────────────────────────────
if (isset($_POST['ajax_approve'])) {
    header('Content-Type: application/json; charset=utf-8');
    $pid     = (int)($_POST['pid'] ?? 0);
    $level   = (int)($_POST['level'] ?? 0);
    $aksi    = $_POST['aksi'] ?? ''; // disetujui | ditolak
    $catatan = trim($_POST['catatan'] ?? '') ?: null;

    if (!$pid || !in_array($aksi,['disetujui','ditolak'])) {
        echo json_encode(['ok'=>false,'msg'=>'Data tidak valid.']); exit;
    }

    $pc = $pdo->prepare("SELECT * FROM pengajuan_cuti WHERE id=?");
    $pc->execute([$pid]);
    $pc = $pc->fetch(PDO::FETCH_ASSOC);
    if (!$pc) { echo json_encode(['ok'=>false,'msg'=>'Pengajuan tidak ditemukan.']); exit; }

    // Cari HRD untuk approver2
    $hrd_id = (int)$pdo->query("SELECT id FROM users WHERE role IN ('hrd','admin') AND status='aktif' ORDER BY role='hrd' DESC, id LIMIT 1")->fetchColumn();

    try {
        $pdo->beginTransaction();

        if ($level === 1) {
            // Validasi: harus approver1 record ini, atau admin
            if ((int)$pc['approver1_id'] !== $uid && $role !== 'admin') {
                echo json_encode(['ok'=>false,'msg'=>'Anda bukan approver level 1 untuk pengajuan ini.']); exit;
            }
            if ($aksi === 'ditolak') {
                // Tolak langsung final
                $pdo->prepare("UPDATE pengajuan_cuti SET
                    status_approver1='ditolak', catatan_approver1=?, tgl_approver1=NOW(), approver1_id=?,
                    status='ditolak'
                    WHERE id=?")
                    ->execute([$catatan, $uid, $pid]);
            } else {
                // Setujui L1 → assign approver2 = HRD, status tetap 'menunggu'
                $pdo->prepare("UPDATE pengajuan_cuti SET
                    status_approver1='disetujui', catatan_approver1=?, tgl_approver1=NOW(), approver1_id=?,
                    approver2_id=?, status_approver2='menunggu',
                    status='menunggu'
                    WHERE id=?")
                    ->execute([$catatan, $uid, $hrd_id ?: null, $pid]);
            }

        } elseif ($level === 2) {
            if (!$can_l2) { echo json_encode(['ok'=>false,'msg'=>'Tidak berwenang.']); exit; }
            if ($pc['status_approver1'] !== 'disetujui') {
                echo json_encode(['ok'=>false,'msg'=>'Harus disetujui atasan dulu.']); exit;
            }
            $final_status = ($aksi === 'disetujui') ? 'disetujui' : 'ditolak';
            $pdo->prepare("UPDATE pengajuan_cuti SET
                status_approver2=?, catatan_approver2=?, tgl_approver2=NOW(), approver2_id=?,
                status=?
                WHERE id=?")
                ->execute([$aksi, $catatan, $uid, $final_status, $pid]);

            // ── Kurangi jatah jika disetujui final ──
            if ($aksi === 'disetujui') {
                $tahun = (int)date('Y', strtotime($pc['tgl_mulai']));
                $jml   = (int)$pc['jumlah_hari'];
                // Coba UPDATE dulu
                $upd = $pdo->prepare("UPDATE jatah_cuti
                    SET terpakai = terpakai + ?,
                        sisa     = GREATEST(0, sisa - ?)
                    WHERE user_id=? AND jenis_cuti_id=? AND tahun=?");
                $upd->execute([$jml, $jml, $pc['user_id'], $pc['jenis_cuti_id'], $tahun]);

                // Kalau baris tidak ada (jatah belum diinisialisasi), buat dulu lalu update
                if ($upd->rowCount() === 0) {
                    $kuota_default = (int)$pdo->prepare("SELECT kuota_default FROM master_jenis_cuti WHERE id=?")
                        ->execute([$pc['jenis_cuti_id']]) ?
                        $pdo->query("SELECT kuota_default FROM master_jenis_cuti WHERE id=".(int)$pc['jenis_cuti_id'])->fetchColumn() : 12;
                    $sisa_baru = max(0, (int)$kuota_default - $jml);
                    $pdo->prepare("INSERT INTO jatah_cuti (user_id,jenis_cuti_id,tahun,kuota,terpakai,sisa) VALUES (?,?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE terpakai=terpakai+?, sisa=GREATEST(0,sisa-?)")
                        ->execute([$pc['user_id'], $pc['jenis_cuti_id'], $tahun, $kuota_default, $jml, $sisa_baru, $jml, $jml]);
                }
            }

        } else {
            echo json_encode(['ok'=>false,'msg'=>'Level tidak valid.']); exit;
        }

        $pdo->commit();
        echo json_encode(['ok'=>true,'msg'=>'Berhasil '.$aksi.'.']);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Ambil data ────────────────────────────────────────────
// Pengajuan yang saya perlu approve (level 1: saya adalah approver1)
$antrian_l1 = [];
$antrian_l1q = $pdo->prepare("
    SELECT pc.*, jc.nama jenis_nama, jc.kode jenis_kode,
           u.nama pemohon, u.divisi pemohon_divisi,
           COALESCE(ud.nama,'—') delegasi_nama,
           GROUP_CONCAT(ct.tanggal ORDER BY ct.tanggal SEPARATOR ',') tgl_list
    FROM pengajuan_cuti pc
    JOIN users u ON u.id=pc.user_id
    LEFT JOIN master_jenis_cuti jc ON jc.id=pc.jenis_cuti_id
    LEFT JOIN users ud ON ud.id=pc.delegasi_id
    LEFT JOIN cuti_tanggal ct ON ct.pengajuan_id=pc.id
    WHERE pc.approver1_id=? AND pc.status_approver1='menunggu' AND pc.status='menunggu'
    GROUP BY pc.id ORDER BY pc.created_at ASC
");
$antrian_l1q->execute([$uid]);
$antrian_l1 = $antrian_l1q->fetchAll(PDO::FETCH_ASSOC);

// Level 2: HRD/Admin — semua yang sudah disetujui l1 tapi belum l2
// TERMASUK yang approver1_id NULL (tidak punya atasan → langsung HRD)
$antrian_l2 = [];
if ($can_l2) {
    $antrian_l2q = $pdo->query("
        SELECT pc.*, jc.nama jenis_nama, jc.kode jenis_kode,
               u.nama pemohon, u.divisi pemohon_divisi,
               ua1.nama approver1_nama,
               COALESCE(ud.nama,'-') delegasi_nama,
               GROUP_CONCAT(ct.tanggal ORDER BY ct.tanggal SEPARATOR ',') tgl_list
        FROM pengajuan_cuti pc
        JOIN users u ON u.id=pc.user_id
        LEFT JOIN master_jenis_cuti jc ON jc.id=pc.jenis_cuti_id
        LEFT JOIN users ua1 ON ua1.id=pc.approver1_id
        LEFT JOIN users ud ON ud.id=pc.delegasi_id
        LEFT JOIN cuti_tanggal ct ON ct.pengajuan_id=pc.id
        WHERE pc.status='menunggu'
          AND pc.status_approver2='menunggu'
          AND (
              pc.status_approver1='disetujui'
              OR pc.approver1_id IS NULL
          )
        GROUP BY pc.id ORDER BY pc.created_at ASC
    ");
    $antrian_l2 = $antrian_l2q->fetchAll(PDO::FETCH_ASSOC);
}

// Riwayat yang sudah saya proses
$riwayat = $pdo->prepare("
    SELECT pc.*, jc.kode jenis_kode, u.nama pemohon,
           GROUP_CONCAT(ct.tanggal ORDER BY ct.tanggal SEPARATOR ',') tgl_list
    FROM pengajuan_cuti pc
    JOIN users u ON u.id=pc.user_id
    LEFT JOIN master_jenis_cuti jc ON jc.id=pc.jenis_cuti_id
    LEFT JOIN cuti_tanggal ct ON ct.pengajuan_id=pc.id
    WHERE (pc.approver1_id=? AND pc.status_approver1!='menunggu')
       OR (pc.approver2_id=? AND pc.status_approver2!='menunggu')
    GROUP BY pc.id ORDER BY pc.updated_at DESC LIMIT 30
");
$riwayat->execute([$uid,$uid]);
$riwayat = $riwayat->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>
<style>
.ap-wrap { font-family:'Inter',sans-serif; }
.ap-card { background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 18px;margin-bottom:12px;position:relative; }
.ap-card-hd { display:flex;align-items:flex-start;gap:12px;margin-bottom:12px; }
.ap-av { width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#00e5b0,#00c896);color:#0a0f14;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.ap-card-nama { font-size:13px;font-weight:700;color:#0f172a;line-height:1.3; }
.ap-card-sub  { font-size:10.5px;color:#94a3b8;margin-top:2px; }
.ap-meta { display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px; }
.ap-meta-item { display:flex;flex-direction:column;gap:2px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:7px;padding:7px 11px;min-width:90px; }
.ap-meta-lbl { font-size:9.5px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px; }
.ap-meta-val { font-size:12px;font-weight:700;color:#0f172a; }
.ap-tgl-list { display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px; }
.ap-tgl-chip { background:#dbeafe;color:#1d4ed8;font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:4px; }
.ap-actions { display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap; }
.ap-catatan { flex:1;min-width:180px;border:1px solid #d1d5db;border-radius:7px;padding:5px 9px;font-size:11.5px;font-family:inherit;height:36px;resize:none;transition:border-color .15s; }
.ap-catatan:focus { outline:none;border-color:#00c896; }
.badge-level { display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px; }
.empty-state { text-align:center;padding:40px 20px;color:#94a3b8; }
.empty-state i { font-size:32px;display:block;margin-bottom:10px;color:#d1d5db; }
</style>

<div class="page-header">
  <h4><i class="fa fa-check-circle text-primary"></i> &nbsp;Approval Cuti</h4>
  <div class="breadcrumb">
    <a href="<?=APP_URL?>/dashboard.php">Dashboard</a><span class="sep">/</span>
    <span class="cur">Approval Cuti</span>
  </div>
</div>

<div class="content ap-wrap">
  <?= showFlash() ?>

  <!-- Level 1: Antrian saya -->
  <?php if ($antrian_l1 || true): // selalu tampil ?>
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-user-check" style="color:#00c896;"></i> Menunggu Persetujuan Saya
        <?php if ($antrian_l1): ?><span style="background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px;margin-left:6px;"><?=count($antrian_l1)?></span><?php endif; ?>
      </h5>
    </div>
    <div style="padding:14px;">
      <?php if (empty($antrian_l1)): ?>
      <div class="empty-state"><i class="fa fa-inbox"></i>Tidak ada pengajuan yang menunggu persetujuan Anda.</div>
      <?php endif; ?>
      <?php foreach ($antrian_l1 as $r):
        $tgls = $r['tgl_list'] ? explode(',',$r['tgl_list']) : [];
        $initials = implode('', array_map(fn($w)=>mb_strtoupper(mb_substr($w,0,1)), array_slice(explode(' ',$r['pemohon']),0,2)));
      ?>
      <div class="ap-card" id="card-<?=$r['id']?>">
        <div class="ap-card-hd">
          <div class="ap-av"><?=$initials?></div>
          <div style="flex:1;">
            <div class="ap-card-nama"><?=clean($r['pemohon'])?></div>
            <div class="ap-card-sub"><?=clean($r['pemohon_divisi']??'—')?> &middot; Diajukan <?=date('d M Y H:i',strtotime($r['created_at']))?></div>
          </div>
          <span class="badge-level" style="background:#dbeafe;color:#1d4ed8;flex-shrink:0;">Level 1 — Atasan</span>
        </div>
        <div class="ap-meta">
          <div class="ap-meta-item"><span class="ap-meta-lbl">Jenis</span><span class="ap-meta-val"><?=clean($r['jenis_kode'])?></span></div>
          <div class="ap-meta-item"><span class="ap-meta-lbl">Jumlah</span><span class="ap-meta-val"><?=$r['jumlah_hari']?> hari</span></div>
          <div class="ap-meta-item"><span class="ap-meta-lbl">Delegasi</span><span class="ap-meta-val" style="font-size:11px;"><?=clean($r['delegasi_nama'])?></span></div>
        </div>
        <?php if ($tgls): ?>
        <div class="ap-tgl-list">
          <?php foreach($tgls as $t): ?><span class="ap-tgl-chip"><?=date('d M',strtotime($t))?></span><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($r['keperluan']): ?>
        <div style="font-size:11.5px;color:#475569;background:#f8fafc;border-radius:7px;padding:8px 11px;margin-bottom:12px;border-left:3px solid #00c896;">
          <strong>Keperluan:</strong> <?=nl2br(clean($r['keperluan']))?>
        </div>
        <?php endif; ?>
        <div class="ap-actions">
          <textarea class="ap-catatan" id="cat-l1-<?=$r['id']?>" placeholder="Catatan (opsional)…"></textarea>
          <button class="btn btn-success btn-sm" onclick="doApprove(<?=$r['id']?>,1,'disetujui')">
            <i class="fa fa-check"></i> Setujui
          </button>
          <button class="btn btn-danger btn-sm" onclick="doApprove(<?=$r['id']?>,1,'ditolak')">
            <i class="fa fa-times"></i> Tolak
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Level 2: HRD/Admin -->
  <?php if ($can_l2): ?>
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-shield-check" style="color:#8b5cf6;"></i> Antrian HRD / Final
        <?php if ($antrian_l2): ?><span style="background:#8b5cf6;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px;margin-left:6px;"><?=count($antrian_l2)?></span><?php endif; ?>
      </h5>
    </div>
    <div style="padding:14px;">
      <?php if (empty($antrian_l2)): ?>
      <div class="empty-state"><i class="fa fa-inbox"></i>Tidak ada pengajuan menunggu persetujuan final.</div>
      <?php endif; ?>
      <?php foreach ($antrian_l2 as $r):
        $tgls = $r['tgl_list'] ? explode(',',$r['tgl_list']) : [];
        $initials = implode('', array_map(fn($w)=>mb_strtoupper(mb_substr($w,0,1)), array_slice(explode(' ',$r['pemohon']),0,2)));
      ?>
      <div class="ap-card" id="card2-<?=$r['id']?>">
        <div class="ap-card-hd">
          <div class="ap-av"><?=$initials?></div>
          <div style="flex:1;">
            <div class="ap-card-nama"><?=clean($r['pemohon'])?></div>
            <div class="ap-card-sub"><?=clean($r['pemohon_divisi']??'—')?> &middot; Disetujui atasan: <strong style="color:#00c896;"><?=clean($r['approver1_nama']??'—')?></strong></div>
          </div>
          <span class="badge-level" style="background:#ede9fe;color:#7c3aed;flex-shrink:0;">Level 2 — HRD Final</span>
        </div>
        <div class="ap-meta">
          <div class="ap-meta-item"><span class="ap-meta-lbl">Jenis</span><span class="ap-meta-val"><?=clean($r['jenis_kode'])?></span></div>
          <div class="ap-meta-item"><span class="ap-meta-lbl">Jumlah</span><span class="ap-meta-val"><?=$r['jumlah_hari']?> hari</span></div>
          <div class="ap-meta-item"><span class="ap-meta-lbl">Delegasi</span><span class="ap-meta-val" style="font-size:11px;"><?=clean($r['delegasi_nama'])?></span></div>
        </div>
        <?php if ($tgls): ?>
        <div class="ap-tgl-list">
          <?php foreach($tgls as $t): ?><span class="ap-tgl-chip" style="background:#ede9fe;color:#7c3aed;"><?=date('d M',strtotime($t))?></span><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($r['keperluan']): ?>
        <div style="font-size:11.5px;color:#475569;background:#f8fafc;border-radius:7px;padding:8px 11px;margin-bottom:12px;border-left:3px solid #8b5cf6;">
          <strong>Keperluan:</strong> <?=nl2br(clean($r['keperluan']))?>
        </div>
        <?php endif; ?>
        <div class="ap-actions">
          <textarea class="ap-catatan" id="cat-l2-<?=$r['id']?>" placeholder="Catatan HRD (opsional)…"></textarea>
          <button class="btn btn-success btn-sm" onclick="doApprove(<?=$r['id']?>,2,'disetujui')">
            <i class="fa fa-check"></i> Setujui Final
          </button>
          <button class="btn btn-danger btn-sm" onclick="doApprove(<?=$r['id']?>,2,'ditolak')">
            <i class="fa fa-times"></i> Tolak
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Riwayat -->
  <?php if ($riwayat): ?>
  <div class="panel">
    <div class="panel-hd"><h5><i class="fa fa-history"></i> Riwayat Approval Saya</h5></div>
    <div class="panel-bd np tbl-wrap" style="padding:0;">
      <table>
        <thead><tr><th>Pemohon</th><th>Jenis</th><th>Tanggal</th><th>Hari</th><th>Status Akhir</th></tr></thead>
        <tbody>
          <?php foreach ($riwayat as $r):
            $tgls2 = $r['tgl_list'] ? explode(',',$r['tgl_list']) : [];
          ?>
          <tr>
            <td style="font-weight:600;font-size:12px;"><?=clean($r['pemohon'])?></td>
            <td><code style="font-size:10.5px;background:#f1f5f9;padding:2px 6px;border-radius:4px;"><?=clean($r['jenis_kode']??'')?></code></td>
            <td style="font-size:11px;">
              <?php foreach(array_slice($tgls2,0,3) as $t): ?>
              <span style="background:#f1f5f9;color:#475569;font-size:10px;padding:1px 5px;border-radius:3px;margin-right:2px;"><?=date('d/m',strtotime($t))?></span>
              <?php endforeach; ?>
              <?php if(count($tgls2)>3): ?><span style="font-size:10px;color:#94a3b8;">+<?=count($tgls2)-3?> lagi</span><?php endif; ?>
            </td>
            <td style="font-weight:700;"><?=$r['jumlah_hari']?>h</td>
            <td>
              <span style="display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;padding:2px 9px;border-radius:20px;
                background:<?=$r['status']==='disetujui'?'#d1fae5':($r['status']==='ditolak'?'#fee2e2':'#f1f5f9')?>;
                color:<?=$r['status']==='disetujui'?'#065f46':($r['status']==='ditolak'?'#991b1b':'#64748b')?> ;">
                <?=ucfirst($r['status'])?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function doApprove(pid, level, aksi) {
    const catId = 'cat-l'+level+'-'+pid;
    const catatan = document.getElementById(catId)?.value || '';
    if (aksi === 'ditolak' && !catatan.trim()) {
        alert('Isi alasan penolakan.'); return;
    }
    if (!confirm((aksi==='disetujui'?'Setujui':'Tolak')+' pengajuan ini?')) return;
    const fd=new FormData();
    fd.append('ajax_approve','1'); fd.append('pid',pid);
    fd.append('level',level); fd.append('aksi',aksi);
    fd.append('catatan',catatan);
    fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'})
        .then(r=>r.json()).then(d=>{ alert(d.msg); if(d.ok) location.reload(); });
}
</script>
<?php include '../includes/footer.php'; ?>