<?php
session_start();
require_once '../config.php';
requireLogin();

$id = (int)($_GET['id']??0);
if (!$id) { setFlash('danger','ID tidak valid.'); redirect(APP_URL.(hasRole('user')?'/pages/tiket_saya.php':'/pages/antrian.php')); }

$st = $pdo->prepare("SELECT t.*,k.nama as kat_nama,k.sla_jam,k.sla_respon_jam,k.icon as kat_icon,
    u.nama as req_nama,u.email as req_email,u.divisi as req_divisi,u.no_hp as req_hp,
    tek.nama as tek_nama,tek.email as tek_email
    FROM tiket t LEFT JOIN kategori k ON k.id=t.kategori_id
    LEFT JOIN users u ON u.id=t.user_id LEFT JOIN users tek ON tek.id=t.teknisi_id
    WHERE t.id=?");
$st->execute([$id]); $t = $st->fetch();
if (!$t) { setFlash('danger','Tiket tidak ditemukan.'); redirect(APP_URL.'/dashboard.php'); }
// User hanya bisa lihat tiket miliknya
if (hasRole('user') && $t['user_id'] != $_SESSION['user_id']) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/pages/tiket_saya.php'); }

$logs = $pdo->prepare("SELECT l.*,u.nama,u.role FROM tiket_log l LEFT JOIN users u ON u.id=l.user_id WHERE l.tiket_id=? ORDER BY l.created_at ASC");
$logs->execute([$id]); $logs = $logs->fetchAll();

$komentar = $pdo->prepare("SELECT k.*,u.nama,u.role FROM komentar k LEFT JOIN users u ON u.id=k.user_id WHERE k.tiket_id=? ORDER BY k.created_at ASC");
$komentar->execute([$id]); $komentar = $komentar->fetchAll();

$teknisi_list = hasRole(['admin','teknisi']) ? $pdo->query("SELECT * FROM users WHERE role='teknisi' AND status='aktif' ORDER BY nama")->fetchAll() : [];

// ── HELPER: Kirim notif Telegram tiket ───────────────────────────────────────
function notifTelegram($pdo, $t, $event_key, $extra = []) {
    $cfg = getSettings($pdo);
    if (($cfg['telegram_enabled'] ?? '0') !== '1') return;
    if (($cfg[$event_key] ?? '0') !== '1') return;

    $emoji_prio = ['Rendah' => '🟢', 'Sedang' => '🟡', 'Tinggi' => '🔴'];
    $ep = $emoji_prio[$t['prioritas']] ?? '⚪';

    $info = "📋 <b>No Tiket :</b> {$t['nomor']}\n"
          . "📌 <b>Judul    :</b> " . htmlspecialchars($t['judul'], ENT_QUOTES) . "\n"
          . "🏷️ <b>Kategori :</b> " . htmlspecialchars($t['kat_nama'] ?? '-', ENT_QUOTES) . "\n"
          . "{$ep} <b>Prioritas:</b> {$t['prioritas']}\n"
          . "📍 <b>Lokasi   :</b> " . htmlspecialchars($t['lokasi'] ?? '-', ENT_QUOTES) . "\n"
          . "👤 <b>Pemohon  :</b> " . htmlspecialchars($t['req_nama'] ?? '-', ENT_QUOTES) . "\n";

    switch ($event_key) {

        case 'telegram_notif_diproses':
            $msg = "⚙️ <b>Tiket Mulai Diproses — FixSmart Helpdesk</b>\n\n"
                 . $info
                 . "🔧 <b>Teknisi  :</b> " . htmlspecialchars($extra['teknisi'] ?? '-', ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n\n"
                 . "<i>Tiket sedang dalam penanganan teknisi.</i>";
            break;

        case 'telegram_notif_selesai':
            $catatan = $extra['catatan'] ?? '';
            $msg = "✅ <b>Tiket Selesai Ditangani — FixSmart Helpdesk</b>\n\n"
                 . $info
                 . "🔧 <b>Teknisi  :</b> " . htmlspecialchars($extra['teknisi'] ?? '-', ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n"
                 . ($catatan ? "📝 <b>Catatan  :</b> " . htmlspecialchars(mb_substr($catatan, 0, 200, 'UTF-8'), ENT_QUOTES) . "\n" : '')
                 . "\n<i>Tiket telah berhasil diselesaikan.</i>";
            break;

        case 'telegram_notif_ditolak':
            $status_label = $extra['status_label'] ?? 'Ditolak';
            $catatan      = $extra['catatan']       ?? '';
            $msg = "❌ <b>Tiket {$status_label} — FixSmart Helpdesk</b>\n\n"
                 . $info
                 . "🔧 <b>Diproses :</b> " . htmlspecialchars($extra['teknisi'] ?? '-', ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n"
                 . ($catatan ? "📝 <b>Alasan   :</b> " . htmlspecialchars(mb_substr($catatan, 0, 200, 'UTF-8'), ENT_QUOTES) . "\n" : '')
                 . "\n<i>Tiket tidak dapat dilanjutkan.</i>";
            break;

        case 'telegram_notif_komentar':
            $isi_km = $extra['komentar'] ?? '';
            $msg = "💬 <b>Komentar Baru di Tiket — FixSmart Helpdesk</b>\n\n"
                 . $info
                 . "✍️ <b>Komentar dari:</b> " . htmlspecialchars($extra['pengirim'] ?? '-', ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n\n"
                 . "💬 <b>Isi Komentar:</b>\n"
                 . htmlspecialchars(mb_substr($isi_km, 0, 300, 'UTF-8') . (mb_strlen($isi_km, 'UTF-8') > 300 ? '...' : ''), ENT_QUOTES) . "\n\n"
                 . "<i>Cek dashboard untuk membalas komentar.</i>";
            break;

        default:
            return;
    }

    sendTelegram($pdo, $msg);
}
// ── END HELPER ───────────────────────────────────────────────────────────────

// ── POST ACTIONS ──
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $act = $_POST['action']??'';
    $is_final = in_array($t['status'],['selesai','ditolak','tidak_bisa']);

    // IT: Ambil/Proses tiket
    if ($act==='ambil' && hasRole(['admin','teknisi']) && $t['status']==='menunggu') {
        $tek_id = $_SESSION['user_id'];
        $pdo->prepare("UPDATE tiket SET status='diproses',teknisi_id=?,waktu_diproses=NOW(),
            durasi_respon_menit=TIMESTAMPDIFF(MINUTE,waktu_submit,NOW()) WHERE id=?")->execute([$tek_id,$id]);
        $pdo->prepare("INSERT INTO tiket_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,'menunggu','diproses',?)")
            ->execute([$id,$_SESSION['user_id'],'Tiket diambil dan mulai diproses oleh '.$_SESSION['user_nama']]);

        // ── NOTIF: diproses ──
        $t['req_nama']  = $t['req_nama']  ?? '-';
        $t['kat_nama']  = $t['kat_nama']  ?? '-';
        notifTelegram($pdo, $t, 'telegram_notif_diproses', [
            'teknisi' => $_SESSION['user_nama'],
        ]);

        setFlash('success','Tiket berhasil diambil. Selamat mengerjakan!');
        redirect(APP_URL.'/pages/detail_tiket.php?id='.$id);
    }

    // IT: Update status ke selesai/ditolak/tidak_bisa
    if ($act==='update_status' && hasRole(['admin','teknisi']) && !$is_final) {
        $new_status = $_POST['status_baru']??'';
        $catatan    = trim($_POST['catatan_penolakan']??'');
        $valid = ['selesai','ditolak','tidak_bisa'];
        if (in_array($new_status,$valid)) {
            $dur_selesai = "TIMESTAMPDIFF(MINUTE,waktu_submit,NOW())";
            // Jika teknisi_id belum terisi (langsung selesai tanpa Ambil dulu), isi otomatis
            $pdo->prepare("UPDATE tiket SET
                    status=?,
                    catatan_penolakan=?,
                    waktu_selesai=NOW(),
                    durasi_selesai_menit=($dur_selesai),
                    teknisi_id=COALESCE(teknisi_id,?),
                    waktu_diproses=COALESCE(waktu_diproses,NOW()),
                    durasi_respon_menit=COALESCE(durasi_respon_menit,TIMESTAMPDIFF(MINUTE,waktu_submit,NOW()))
                WHERE id=?")
                ->execute([$new_status, $catatan ?: null, $_SESSION['user_id'], $id]);
            $ket = match($new_status) {
                'selesai'    => 'Tiket selesai ditangani.',
                'ditolak'    => 'Tiket ditolak. Alasan: '.$catatan,
                'tidak_bisa' => 'Tidak dapat ditangani. Keterangan: '.$catatan,
            };
            $pdo->prepare("INSERT INTO tiket_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,?,?,?)")
                ->execute([$id,$_SESSION['user_id'],$t['status'],$new_status,$ket]);

            // ── NOTIF: selesai / ditolak / tidak_bisa ──
            $tek_nama = $t['tek_nama'] ?: $_SESSION['user_nama'];
            if ($new_status === 'selesai') {
                notifTelegram($pdo, $t, 'telegram_notif_selesai', [
                    'teknisi' => $tek_nama,
                    'catatan' => $catatan,
                ]);
            } else {
                $status_label = $new_status === 'ditolak' ? 'Ditolak' : 'Tidak Bisa Ditangani';
                notifTelegram($pdo, $t, 'telegram_notif_ditolak', [
                    'teknisi'      => $tek_nama,
                    'catatan'      => $catatan,
                    'status_label' => $status_label,
                ]);
            }

            setFlash('success','Status tiket berhasil diperbarui.');
            redirect(APP_URL.'/pages/detail_tiket.php?id='.$id);
        }
    }

    // IT: Assign teknisi
    if ($act==='assign' && hasRole(['admin','teknisi'])) {
        $tek_id = (int)($_POST['teknisi_id']??0) ?: null;
        $pdo->prepare("UPDATE tiket SET teknisi_id=? WHERE id=?")->execute([$tek_id,$id]);
        $st2 = $pdo->prepare("SELECT nama FROM users WHERE id=?"); $st2->execute([$tek_id??0]);
        $tn = $tek_id ? ($st2->fetchColumn() ?: 'Teknisi') : 'Tidak ada';
        $pdo->prepare("INSERT INTO tiket_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,?,?,?)")
            ->execute([$id,$_SESSION['user_id'],$t['status'],$t['status'],'Tiket di-assign ke: '.$tn]);
        setFlash('success','Teknisi berhasil di-assign.');
        redirect(APP_URL.'/pages/detail_tiket.php?id='.$id);
    }

    // Komentar (semua bisa)
    if ($act==='komentar') {
        $isi = trim($_POST['isi']??'');
        if ($isi) {
            $pdo->prepare("INSERT INTO komentar (tiket_id,user_id,isi) VALUES (?,?,?)")->execute([$id,$_SESSION['user_id'],$isi]);
            $pdo->prepare("INSERT INTO tiket_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,?,?,?)")
                ->execute([$id,$_SESSION['user_id'],$t['status'],$t['status'],'Komentar: '.substr($isi,0,80)]);

            // ── NOTIF: komentar ──
            notifTelegram($pdo, $t, 'telegram_notif_komentar', [
                'pengirim' => $_SESSION['user_nama'],
                'komentar' => $isi,
            ]);

            setFlash('success','Komentar berhasil dikirim.');
        }
        redirect(APP_URL.'/pages/detail_tiket.php?id='.$id.'#diskusi');
    }
}

// Refresh tiket setelah post
$st->execute([$id]); $t = $st->fetch();
$is_final = in_array($t['status'],['selesai','ditolak','tidak_bisa']);
$dur_respon  = $t['durasi_respon_menit'];
$dur_selesai = $is_final ? $t['durasi_selesai_menit'] : durasiSekarang($t['waktu_submit']);
$sla_selesai = $t['sla_jam']       ? slaStatus($dur_selesai, $t['sla_jam']) : null;
$sla_respon  = $t['sla_respon_jam'] ? slaStatus($dur_respon,  $t['sla_respon_jam']) : null;

$dot_class = ['menunggu'=>'d-menunggu','diproses'=>'d-diproses','selesai'=>'d-selesai','ditolak'=>'d-ditolak','tidak_bisa'=>'d-tidakbisa'];
$border_color = ['menunggu'=>'var(--yellow)','diproses'=>'var(--blue)','selesai'=>'var(--green)','ditolak'=>'var(--red)','tidak_bisa'=>'var(--purple)'];

$page_title = 'Detail Tiket '.$t['nomor'];
$active_menu = hasRole('user') ? 'tiket_saya' : 'semua_tiket';
$back_url = hasRole('user') ? APP_URL.'/pages/tiket_saya.php' : APP_URL.'/pages/semua_tiket.php';

include '../includes/header.php';
?>
<div class="page-header">
  <h4><i class="fa fa-ticket-alt text-primary"></i> &nbsp;Detail Tiket</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Beranda</a><span class="sep">/</span>
    <a href="<?= $back_url ?>">Tiket</a><span class="sep">/</span>
    <span class="cur"><?= clean($t['nomor']) ?></span>
  </div>
</div>
<div class="content">
  <?= showFlash() ?>

  <!-- Tiket Header Card -->
  <div class="panel" style="border-left:5px solid <?= $border_color[$t['status']]??'var(--primary)' ?>;">
    <div class="panel-bd">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;">
        <div style="flex:1;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:7px;flex-wrap:wrap;">
            <span style="font-size:13px;font-weight:700;color:var(--primary);"><?= clean($t['nomor']) ?></span>
            <?= badgeStatus($t['status']) ?>
            <?= badgePrioritas($t['prioritas']) ?>
          </div>
          <h3 style="font-size:16px;color:#333;font-weight:700;margin-bottom:6px;"><?= clean($t['judul']) ?></h3>
          <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:#aaa;">
            <span><i class="fa fa-tag"></i> <?= clean($t['kat_nama']??'-') ?></span>
            <span><i class="fa fa-map-marker-alt"></i> <?= clean($t['lokasi']??'-') ?></span>
            <span><i class="fa fa-clock"></i> <?= formatTanggal($t['waktu_submit'],true) ?></span>
          </div>
        </div>
        <div style="display:flex;gap:7px;flex-shrink:0;">
          <a href="<?= $back_url ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Kembali</a>
          <?php if (!$is_final && hasRole(['admin','teknisi']) && $t['status']==='menunggu'): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="ambil">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-hand-pointer"></i> Ambil & Proses</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="g3">
    <!-- Left: Detail + Aksi -->
    <div>
      <div class="panel">
        <div class="panel-hd" style="padding:0 15px;">
          <div class="tabs" style="margin:0;">
            <button class="tab-btn active" onclick="switchTab(this,'t-info')">Detail</button>
            <button class="tab-btn" onclick="switchTab(this,'t-diskusi')">Diskusi (<?= count($komentar) ?>)</button>
            <?php if (hasRole(['admin','teknisi']) && !$is_final): ?>
            <button class="tab-btn" onclick="switchTab(this,'t-aksi')">Tindakan IT</button>
            <?php endif; ?>
          </div>
        </div>
        <div class="panel-bd">

          <!-- TAB INFO -->
          <div id="t-info" class="tab-c active">
            <div class="form-row" style="margin-bottom:14px;">
              <div>
                <div style="font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;margin-bottom:5px;">Pemohon</div>
                <div class="d-flex ai-c gap10">
                  <div class="av av-md"><?= getInitials($t['req_nama']) ?></div>
                  <div>
                    <div style="font-size:13px;font-weight:600;"><?= clean($t['req_nama']) ?></div>
                    <div style="font-size:11px;color:#aaa;"><?= clean($t['req_divisi']??'') ?></div>
                    <?php if ($t['req_email']): ?><div style="font-size:11px;color:#aaa;"><?= clean($t['req_email']) ?></div><?php endif; ?>
                  </div>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;margin-bottom:5px;">Teknisi</div>
                <?php if ($t['tek_nama']): ?>
                <div class="d-flex ai-c gap10">
                  <div class="av av-md av-blue"><?= getInitials($t['tek_nama']) ?></div>
                  <div>
                    <div style="font-size:13px;font-weight:600;"><?= clean($t['tek_nama']) ?></div>
                    <div style="font-size:11px;color:#aaa;"><?= clean($t['tek_email']??'') ?></div>
                  </div>
                </div>
                <?php else: ?>
                <span style="font-size:12px;color:#aaa;font-style:italic;">Belum ditugaskan</span>
                <?php if (hasRole(['admin','teknisi']) && !$is_final): ?>
                <br><button class="btn btn-default btn-sm mt10" onclick="switchTab(document.querySelector('[onclick*=t-aksi]'),'t-aksi')" style="margin-top:6px;"><i class="fa fa-user-plus"></i> Assign Teknisi</button>
                <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
            <hr class="divider">
            <div style="font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;margin-bottom:6px;">Deskripsi Masalah</div>
            <p style="font-size:13px;color:#444;line-height:1.8;white-space:pre-line;"><?= clean($t['deskripsi']) ?></p>

            <?php if ($t['catatan_penolakan']): ?>
            <hr class="divider">
            <div style="background:#fff8f8;border:1px solid #fca5a5;border-radius:4px;padding:10px 12px;margin-top:4px;">
              <div style="font-size:11px;font-weight:700;color:#991b1b;margin-bottom:4px;"><i class="fa fa-info-circle"></i> Keterangan IT</div>
              <p style="font-size:12px;color:#555;line-height:1.7;"><?= clean($t['catatan_penolakan']) ?></p>
            </div>
            <?php endif; ?>
          </div>

          <!-- TAB DISKUSI -->
          <div id="t-diskusi" class="tab-c" id="diskusi">
            <?php if (empty($komentar)): ?>
            <p style="text-align:center;color:#bbb;padding:20px 0;font-size:12px;"><i class="fa fa-comments" style="font-size:20px;display:block;margin-bottom:7px;"></i>Belum ada komentar</p>
            <?php else: foreach ($komentar as $km): ?>
            <div style="display:flex;gap:9px;margin-bottom:13px;padding-bottom:13px;border-bottom:1px solid #f5f5f5;">
              <div class="av av-sm <?= $km['role']==='teknisi'||$km['role']==='admin'?'av-blue':'' ?>"><?= getInitials($km['nama']) ?></div>
              <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:7px;margin-bottom:3px;">
                  <strong style="font-size:12px;"><?= clean($km['nama']) ?></strong>
                  <span style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:3px;background:<?= $km['role']==='admin'?'#ede9fe':($km['role']==='teknisi'?'#dbeafe':'#f3f4f6') ?>;color:<?= $km['role']==='admin'?'#7c3aed':($km['role']==='teknisi'?'#1d4ed8':'#374151') ?>;"><?= ucfirst($km['role']) ?></span>
                  <span style="font-size:11px;color:#bbb;margin-left:auto;"><?= timeAgo($km['created_at']) ?></span>
                </div>
                <p style="font-size:12px;color:#555;line-height:1.6;"><?= nl2br(clean($km['isi'])) ?></p>
              </div>
            </div>
            <?php endforeach; endif; ?>
            <form method="POST" style="border-top:1px solid #f0f0f0;padding-top:12px;margin-top:4px;">
              <input type="hidden" name="action" value="komentar">
              <textarea name="isi" class="form-control" placeholder="Tulis pertanyaan atau update..." rows="3" required></textarea>
              <button type="submit" class="btn btn-primary btn-sm" style="margin-top:7px;"><i class="fa fa-paper-plane"></i> Kirim</button>
            </form>
          </div>

          <!-- TAB AKSI IT -->
          <?php if (hasRole(['admin','teknisi']) && !$is_final): ?>
          <div id="t-aksi" class="tab-c">

            <!-- Assign Teknisi — hanya admin yang bisa pilih teknisi lain -->
            <?php if (hasRole('admin')): ?>
            <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #f0f0f0;">
              <p style="font-size:12px;font-weight:700;color:#333;margin-bottom:4px;">Assign Teknisi</p>
              <p style="font-size:11px;color:#aaa;margin-bottom:8px;">Pilih teknisi yang akan menangani tiket ini.</p>
              <form method="POST" style="display:flex;gap:8px;align-items:flex-end;">
                <input type="hidden" name="action" value="assign">
                <div style="flex:1;">
                  <select name="teknisi_id" class="form-control">
                    <option value="">-- Tidak Ditugaskan --</option>
                    <?php foreach ($teknisi_list as $tek): ?>
                    <option value="<?= $tek['id'] ?>" <?= $t['teknisi_id']==$tek['id']?'selected':'' ?>><?= clean($tek['nama']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button type="submit" class="btn btn-info"><i class="fa fa-user-check"></i> Assign</button>
              </form>
            </div>
            <?php elseif (hasRole('teknisi')): ?>
            <!-- Untuk teknisi: tampilkan siapa yang ditugaskan, tidak perlu dropdown -->
            <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #f0f0f0;">
              <p style="font-size:12px;font-weight:700;color:#333;margin-bottom:6px;">Teknisi Penanganan</p>
              <?php if ($t['teknisi_id']): ?>
              <div style="display:flex;align-items:center;gap:9px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:5px;padding:9px 12px;">
                <div class="av av-sm av-blue"><?= getInitials($t['tek_nama']) ?></div>
                <div>
                  <div style="font-size:12px;font-weight:700;color:#0369a1;"><?= clean($t['tek_nama']) ?></div>
                  <div style="font-size:11px;color:#64748b;"><?= $t['teknisi_id'] == $_SESSION['user_id'] ? 'Tiket ini ditangani oleh Anda' : 'Ditugaskan oleh admin' ?></div>
                </div>
              </div>
              <?php else: ?>
              <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:5px;padding:9px 12px;font-size:12px;color:#92400e;">
                <i class="fa fa-info-circle"></i>&nbsp;
                Tiket belum ada teknisi. Klik <strong>"Ambil &amp; Proses"</strong> di atas untuk mengambil tiket ini.
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <!-- Update Status Final -->
            <p style="font-size:12px;font-weight:700;color:#333;margin-bottom:8px;">Update Status Tiket</p>
            <form method="POST">
              <input type="hidden" name="action" value="update_status">
              <div class="form-group">
                <label>Status Baru</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                  <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;padding:7px 12px;border:1px solid #ddd;border-radius:3px;background:#d1fae5;color:#065f46;">
                    <input type="radio" name="status_baru" value="selesai" required> <i class="fa fa-check-circle"></i> Selesai
                  </label>
                  <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;padding:7px 12px;border:1px solid #ddd;border-radius:3px;background:#fee2e2;color:#991b1b;">
                    <input type="radio" name="status_baru" value="ditolak"> <i class="fa fa-times-circle"></i> Ditolak
                  </label>
                  <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;padding:7px 12px;border:1px solid #ddd;border-radius:3px;background:#ede9fe;color:#5b21b6;">
                    <input type="radio" name="status_baru" value="tidak_bisa"> <i class="fa fa-ban"></i> Tidak Bisa Ditangani
                  </label>
                </div>
              </div>
              <div class="form-group">
                <label>Catatan / Alasan <span id="req-note"></span></label>
                <textarea name="catatan_penolakan" id="note-input" class="form-control" placeholder="Tuliskan hasil penanganan, alasan penolakan, atau keterangan tidak bisa ditangani..."></textarea>
              </div>
              <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Perubahan</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Right: Timeline + SLA + Info -->
    <div>
      <!-- SLA Card -->
      <?php if ($t['sla_jam']): ?>
      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-stopwatch text-primary"></i> &nbsp;SLA & Waktu</h5></div>
        <div class="panel-bd">
          <?php
          $info_sla = [
            ['Waktu Submit',    formatTanggal($t['waktu_submit'],true)],
            ['Waktu Respon',    $t['waktu_diproses'] ? formatTanggal($t['waktu_diproses'],true) : '—'],
            ['Waktu Selesai',   $t['waktu_selesai']  ? formatTanggal($t['waktu_selesai'],true)  : '—'],
            ['Durasi Respon',   formatDurasi($dur_respon) . ' / target ' . $t['sla_respon_jam'].'j'],
            ['Durasi Total',    formatDurasi($dur_selesai) . ' / target ' . $t['sla_jam'].'j'],
          ];
          foreach ($info_sla as [$l,$v]):
          ?>
          <div class="d-flex ai-c" style="justify-content:space-between;margin-bottom:8px;font-size:12px;">
            <span style="color:#888;"><?= $l ?></span>
            <strong style="color:#333;text-align:right;font-size:11px;"><?= $v ?></strong>
          </div>
          <?php endforeach; ?>
          <?php if ($sla_selesai): ?>
          <hr class="divider">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;font-size:12px;">
            <span>Pencapaian SLA</span>
            <span class="sla-badge" style="background:<?= $sla_selesai['status']==='aman'?'#d1fae5':($sla_selesai['status']==='warning'?'#fef3c7':'#fee2e2') ?>;color:<?= $sla_selesai['status']==='aman'?'#065f46':($sla_selesai['status']==='warning'?'#92400e':'#991b1b') ?>;">
              <?= $sla_selesai['label'] ?>
            </span>
          </div>
          <div class="progress"><div class="progress-fill <?= $sla_selesai['status']==='aman'?'pg-green':($sla_selesai['status']==='warning'?'pg-orange':'pg-red') ?>" style="width:<?= min($sla_selesai['persen'],100) ?>%"></div></div>
          <div style="font-size:10px;color:#aaa;margin-top:3px;"><?= $sla_selesai['persen'] ?>% dari target <?= $t['sla_jam'] ?> jam</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Timeline -->
      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-history text-primary"></i> &nbsp;Riwayat Aktivitas</h5></div>
        <div class="panel-bd">
          <ul class="timeline">
            <?php foreach ($logs as $log):
              $dc = $dot_class[$log['status_ke']] ?? 'd-default';
            ?>
            <li class="tl-item">
              <div class="tl-dot <?= $dc ?>"><i class="fa fa-<?= $log['status_ke']==='selesai'?'check':($log['status_ke']==='ditolak'?'times':($log['status_ke']==='tidak_bisa'?'ban':($log['status_ke']==='diproses'?'cog':'clock'))) ?>"></i></div>
              <div>
                <span class="tl-title"><?= $log['status_ke'] ? strtoupper($log['status_ke']) : 'DIBUAT' ?></span>
                <span class="tl-time"><?= formatTanggal($log['created_at'],true) ?></span>
                <div class="tl-by">oleh <?= clean($log['nama']??'-') ?></div>
                <?php if ($log['keterangan']): ?><div class="tl-desc"><?= clean($log['keterangan']) ?></div><?php endif; ?>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
// Require note for ditolak/tidak_bisa
document.querySelectorAll('[name=status_baru]').forEach(r => {
  r.addEventListener('change', function() {
    const req = document.getElementById('req-note');
    if (this.value !== 'selesai') {
      req.innerHTML = '<span style="color:red;">*</span>';
      document.getElementById('note-input').required = true;
    } else {
      req.innerHTML = '';
      document.getElementById('note-input').required = false;
    }
  });
});
</script>
<?php include '../includes/footer.php'; ?>