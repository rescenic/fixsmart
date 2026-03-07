<?php
// pages/detail_tiket_ipsrs.php
session_start();
require_once '../config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('danger','ID tidak valid.');
    redirect(APP_URL.(hasRole('user') ? '/pages/tiket_saya_ipsrs.php' : '/pages/antrian_ipsrs.php'));
}

$st = $pdo->prepare("
    SELECT t.*,
           k.nama AS kat_nama, k.sla_jam, k.sla_respon_jam, k.icon AS kat_icon, k.jenis AS kat_jenis,
           u.nama  AS req_nama,  u.email AS req_email,  u.divisi AS req_divisi, u.no_hp AS req_hp,
           tek.nama AS tek_nama, tek.email AS tek_email,
           a.nama_aset, a.no_inventaris AS aset_inv, a.merek AS aset_merek,
           a.model_aset AS aset_model, a.kondisi AS aset_kondisi, a.serial_number AS aset_serial
    FROM tiket_ipsrs t
    LEFT JOIN kategori_ipsrs k ON k.id = t.kategori_id
    LEFT JOIN users           u ON u.id = t.user_id
    LEFT JOIN users         tek ON tek.id = t.teknisi_id
    LEFT JOIN aset_ipsrs      a ON a.id = t.aset_id
    WHERE t.id = ?
");
$st->execute([$id]);
$t = $st->fetch();

if (!$t) { setFlash('danger','Tiket tidak ditemukan.'); redirect(APP_URL.'/dashboard.php'); }
if (hasRole('user') && $t['user_id'] != $_SESSION['user_id']) {
    setFlash('danger','Akses ditolak.');
    redirect(APP_URL.'/pages/tiket_saya_ipsrs.php');
}

$logs = $pdo->prepare("
    SELECT l.*, u.nama, u.role FROM tiket_ipsrs_log l
    LEFT JOIN users u ON u.id = l.user_id
    WHERE l.tiket_id = ? ORDER BY l.created_at ASC
");
$logs->execute([$id]); $logs = $logs->fetchAll();

$komentar = $pdo->prepare("
    SELECT k.*, u.nama, u.role FROM komentar_ipsrs k
    LEFT JOIN users u ON u.id = k.user_id
    WHERE k.tiket_id = ? ORDER BY k.created_at ASC
");
$komentar->execute([$id]); $komentar = $komentar->fetchAll();

$teknisi_list = hasRole(['admin','teknisi_ipsrs'])
    ? $pdo->query("SELECT * FROM users WHERE role IN ('teknisi_ipsrs','admin') AND status='aktif' ORDER BY nama")->fetchAll()
    : [];

// ── HELPER: Upload foto bukti IPSRS ──────────────────────────────────────────
function uploadFotoBuktiIpsrs($pdo, $tiket_id, $user_id) {
    $log_file = dirname(dirname(__FILE__)) . '/upload_ipsrs_log.txt';
    $log = function($msg) use ($log_file) {
        file_put_contents($log_file, date('H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
    };

    $log("=== uploadFotoBuktiIpsrs dipanggil, tiket_id=$tiket_id ===");
    $log("FILES: " . json_encode($_FILES));

    if (!isset($_FILES['foto_bukti']) || empty($_FILES['foto_bukti']['name'][0])) {
        $log("BERHENTI: FILES foto_bukti tidak ada atau kosong");
        return 0;
    }

    $root_dir   = dirname(dirname(__FILE__));
    $upload_dir = $root_dir . '/uploads/tiket_ipsrs_foto/';
    $log("upload_dir: $upload_dir");
    $log("is_dir: " . (is_dir($upload_dir) ? 'YA' : 'TIDAK'));
    $log("is_writable: " . (is_writable($upload_dir) ? 'YA' : 'TIDAK'));

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    if (!is_writable($upload_dir)) {
        $log("BERHENTI: folder tidak writable");
        return 0;
    }

    $allowed_ext  = array('jpg','jpeg','png','gif','webp');
    $allowed_mime = array('image/jpeg','image/png','image/gif','image/webp');
    $max_size     = 5 * 1024 * 1024;
    $uploaded     = 0;

    foreach ($_FILES['foto_bukti']['tmp_name'] as $i => $tmp) {
        $err  = $_FILES['foto_bukti']['error'][$i];
        $size = $_FILES['foto_bukti']['size'][$i];
        $ori  = $_FILES['foto_bukti']['name'][$i];
        $log("File[$i]: name=$ori, size=$size, error=$err, tmp=$tmp");

        if ($err !== UPLOAD_ERR_OK)  { $log("SKIP[$i]: error code $err"); continue; }
        if ($size > $max_size)       { $log("SKIP[$i]: ukuran $size > 5MB"); continue; }

        $mime = mime_content_type($tmp);
        $log("File[$i] mime: $mime");
        if (!in_array($mime, $allowed_mime)) { $log("SKIP[$i]: mime $mime tidak diizinkan"); continue; }

        $ext = strtolower(pathinfo($ori, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext))   { $log("SKIP[$i]: ext $ext tidak diizinkan"); continue; }

        $safe = 'ipsrs_' . $tiket_id . '_' . time() . '_' . $i . '.' . $ext;
        $dest = $upload_dir . $safe;
        $log("Mencoba move_uploaded_file ke: $dest");

        if (move_uploaded_file($tmp, $dest)) {
            $log("move_uploaded_file BERHASIL");
            $path_db = 'uploads/tiket_ipsrs_foto/' . $safe;
            try {
                $pdo->prepare("INSERT INTO tiket_ipsrs_foto (tiket_id, user_id, nama_file, path) VALUES (?, ?, ?, ?)")
                    ->execute(array($tiket_id, $user_id, $ori, $path_db));
                $log("INSERT DB BERHASIL, last_id=" . $pdo->lastInsertId());
            } catch (Exception $e) {
                $log("INSERT DB GAGAL: " . $e->getMessage());
            }
            $uploaded++;
        } else {
            $log("move_uploaded_file GAGAL untuk: $dest");
        }
    }

    $log("Total uploaded: $uploaded");
    return $uploaded;
}

// ── HELPER: Notif Telegram IPSRS ─────────────────────────────────────────────
// PERUBAHAN: Semua key sekarang pakai prefix 'ipsrs_' dan kirim ke saluran IPSRS
function notifTelegramIpsrs($pdo, $t, $event_key, $extra = array()) {
    $cfg = getSettings($pdo);

    // ▼ PERUBAHAN 1: Cek ipsrs_telegram_enabled (bukan telegram_enabled IT)
    if (($cfg['ipsrs_telegram_enabled'] ?? '0') !== '1') return;

    // ▼ PERUBAHAN 2: Cek key event dengan prefix ipsrs_
    // event_key yang dikirim sudah berformat 'telegram_notif_*'
    // kita tambahkan prefix 'ipsrs_' untuk cek ke settings
    $ipsrs_event_key = 'ipsrs_' . $event_key; // misal: ipsrs_telegram_notif_diproses
    if (($cfg[$ipsrs_event_key] ?? '0') !== '1') return;

    $emoji_prio = array('Rendah'=>'🟢','Sedang'=>'🟡','Tinggi'=>'🔴');
    $ep         = $emoji_prio[$t['prioritas']] ?? '⚪';
    $jenis_icon = ($t['jenis_tiket'] === 'Medis') ? '🏥' : '🔧';

    $info = "📋 <b>No Tiket :</b> {$t['nomor']}\n"
          . "📌 <b>Judul    :</b> " . htmlspecialchars($t['judul'],                        ENT_QUOTES) . "\n"
          . "{$jenis_icon} <b>Jenis    :</b> {$t['jenis_tiket']}\n"
          . "🏷️ <b>Kategori :</b> " . htmlspecialchars($t['kat_nama']  ?? '-',             ENT_QUOTES) . "\n"
          . "{$ep} <b>Prioritas:</b> {$t['prioritas']}\n"
          . "📍 <b>Lokasi   :</b> " . htmlspecialchars($t['lokasi']    ?? '-',             ENT_QUOTES) . "\n"
          . "👤 <b>Pemohon  :</b> " . htmlspecialchars($t['req_nama']  ?? '-',             ENT_QUOTES) . "\n";

    $msg = '';
    switch ($event_key) {
        case 'telegram_notif_diproses':
            $msg = "⚙️ <b>Tiket IPSRS Mulai Diproses — FixSmart</b>\n\n" . $info
                 . "🔧 <b>Teknisi  :</b> " . htmlspecialchars($extra['teknisi'] ?? '-', ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n\n"
                 . "<i>Tiket sedang dalam penanganan teknisi IPSRS.</i>";
            break;

        case 'telegram_notif_selesai':
            $catatan = $extra['catatan'] ?? '';
            $msg = "✅ <b>Tiket IPSRS Selesai — FixSmart</b>\n\n" . $info
                 . "🔧 <b>Teknisi  :</b> " . htmlspecialchars($extra['teknisi'] ?? '-', ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n"
                 . ($catatan ? "📝 <b>Catatan  :</b> " . htmlspecialchars(mb_substr($catatan,0,200,'UTF-8'), ENT_QUOTES) . "\n" : '')
                 . "\n<i>Tiket IPSRS telah berhasil diselesaikan.</i>";
            break;

        case 'telegram_notif_ditolak':
            $sl      = $extra['status_label'] ?? 'Ditolak';
            $catatan = $extra['catatan']       ?? '';
            $msg = "❌ <b>Tiket IPSRS {$sl} — FixSmart</b>\n\n" . $info
                 . "🔧 <b>Diproses :</b> " . htmlspecialchars($extra['teknisi'] ?? '-', ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n"
                 . ($catatan ? "📝 <b>Alasan   :</b> " . htmlspecialchars(mb_substr($catatan,0,200,'UTF-8'), ENT_QUOTES) . "\n" : '')
                 . "\n<i>Tiket IPSRS tidak dapat dilanjutkan.</i>";
            break;

        case 'telegram_notif_komentar':
            $isi_km = $extra['komentar'] ?? '';
            $msg = "💬 <b>Komentar Baru — Tiket IPSRS FixSmart</b>\n\n" . $info
                 . "✍️ <b>Dari     :</b> " . htmlspecialchars($extra['pengirim'] ?? '-', ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n\n"
                 . "💬 <b>Isi Komentar:</b>\n"
                 . htmlspecialchars(mb_substr($isi_km,0,300,'UTF-8').(mb_strlen($isi_km,'UTF-8')>300?'...':''), ENT_QUOTES)
                 . "\n\n<i>Cek dashboard untuk membalas.</i>";
            break;

        default: return;
    }

    // ▼ PERUBAHAN 3: Kirim ke saluran IPSRS (bukan IT)
    if ($msg) sendTelegramIpsrs($pdo, $msg);
}

// ── POST ACTIONS ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act      = $_POST['action'] ?? '';
    $is_final = in_array($t['status'], array('selesai','ditolak','tidak_bisa'));

    // Ambil & Proses
    if ($act === 'ambil' && hasRole(array('admin','teknisi_ipsrs')) && $t['status'] === 'menunggu') {
        $pdo->prepare("UPDATE tiket_ipsrs SET status='diproses', teknisi_id=?, waktu_proses=NOW(),
            durasi_respon_menit=TIMESTAMPDIFF(MINUTE,waktu_submit,NOW()) WHERE id=?")
            ->execute(array($_SESSION['user_id'], $id));
        $pdo->prepare("INSERT INTO tiket_ipsrs_log (tiket_id,user_id,status_dari,status_ke,keterangan)
            VALUES (?,?,'menunggu','diproses',?)")
            ->execute(array($id, $_SESSION['user_id'], 'Tiket diambil dan mulai diproses oleh '.$_SESSION['user_nama']));
        notifTelegramIpsrs($pdo, $t, 'telegram_notif_diproses', array('teknisi' => $_SESSION['user_nama']));
        setFlash('success','Tiket IPSRS berhasil diambil. Selamat mengerjakan!');
        redirect(APP_URL.'/pages/detail_tiket_ipsrs.php?id='.$id);
    }

    // Update Status + Upload Foto
    if ($act === 'update_status' && hasRole(array('admin','teknisi_ipsrs')) && !$is_final) {
        $new_status = $_POST['status_baru'] ?? '';
        $catatan    = trim($_POST['catatan_teknisi'] ?? '');
        $valid      = array('selesai','ditolak','tidak_bisa');
        if (in_array($new_status, $valid)) {
            $jml_foto = uploadFotoBuktiIpsrs($pdo, $id, $_SESSION['user_id']);
            $pdo->prepare("UPDATE tiket_ipsrs SET
                    status=?, catatan_teknisi=?,
                    waktu_selesai=NOW(),
                    durasi_selesai_menit=TIMESTAMPDIFF(MINUTE,waktu_submit,NOW()),
                    teknisi_id=COALESCE(teknisi_id,?),
                    waktu_proses=COALESCE(waktu_proses,NOW()),
                    durasi_respon_menit=COALESCE(durasi_respon_menit,TIMESTAMPDIFF(MINUTE,waktu_submit,NOW()))
                WHERE id=?")
                ->execute(array($new_status, $catatan ?: null, $_SESSION['user_id'], $id));

            $ket_foto = $jml_foto ? " ($jml_foto foto bukti diupload)" : '';
            if ($new_status === 'selesai')       $ket = 'Tiket IPSRS selesai ditangani.' . $ket_foto;
            elseif ($new_status === 'ditolak')   $ket = 'Tiket ditolak. Alasan: ' . $catatan . $ket_foto;
            else                                 $ket = 'Tidak dapat ditangani. Keterangan: ' . $catatan . $ket_foto;

            $pdo->prepare("INSERT INTO tiket_ipsrs_log (tiket_id,user_id,status_dari,status_ke,keterangan)
                VALUES (?,?,?,?,?)")
                ->execute(array($id, $_SESSION['user_id'], $t['status'], $new_status, $ket));

            $tek_nama = $t['tek_nama'] ?: $_SESSION['user_nama'];
            if ($new_status === 'selesai') {
                notifTelegramIpsrs($pdo, $t, 'telegram_notif_selesai', array('teknisi'=>$tek_nama,'catatan'=>$catatan));
            } else {
                $sl = ($new_status === 'ditolak') ? 'Ditolak' : 'Tidak Bisa Ditangani';
                notifTelegramIpsrs($pdo, $t, 'telegram_notif_ditolak', array('teknisi'=>$tek_nama,'catatan'=>$catatan,'status_label'=>$sl));
            }
            $msg_foto = $jml_foto ? " $jml_foto foto bukti berhasil diupload." : '';
            setFlash('success', 'Status tiket berhasil diperbarui.' . $msg_foto);
            redirect(APP_URL.'/pages/detail_tiket_ipsrs.php?id='.$id);
        }
    }

    // Upload Foto Saja
    if ($act === 'upload_foto' && hasRole(array('admin','teknisi_ipsrs')) && !$is_final) {
        $jml_foto = uploadFotoBuktiIpsrs($pdo, $id, $_SESSION['user_id']);
        if ($jml_foto > 0) {
            $pdo->prepare("INSERT INTO tiket_ipsrs_log (tiket_id,user_id,status_dari,status_ke,keterangan)
                VALUES (?,?,?,?,?)")
                ->execute(array($id, $_SESSION['user_id'], $t['status'], $t['status'], "Upload $jml_foto foto bukti pengerjaan."));
            setFlash('success', "$jml_foto foto berhasil diupload.");
        } else {
            setFlash('warning', 'Tidak ada foto berhasil diupload. Pastikan format JPG/PNG/WebP dan ukuran maks 5 MB per file.');
        }
        redirect(APP_URL.'/pages/detail_tiket_ipsrs.php?id='.$id.'#t-aksi');
    }

    // Hapus Foto
    if ($act === 'hapus_foto' && hasRole(array('admin','teknisi_ipsrs'))) {
        $foto_id  = (int)($_POST['foto_id'] ?? 0);
        $foto_row = $pdo->prepare("SELECT * FROM tiket_ipsrs_foto WHERE id=? AND tiket_id=?");
        $foto_row->execute(array($foto_id, $id));
        $foto_row = $foto_row->fetch();
        if ($foto_row) {
            $root_dir = dirname(dirname(__FILE__));
            $full     = $root_dir . '/' . $foto_row['path'];
            if (file_exists($full)) unlink($full);
            $pdo->prepare("DELETE FROM tiket_ipsrs_foto WHERE id=?")->execute(array($foto_id));
            setFlash('success', 'Foto berhasil dihapus.');
        }
        redirect(APP_URL.'/pages/detail_tiket_ipsrs.php?id='.$id.'#t-aksi');
    }

    // Assign Teknisi
    if ($act === 'assign' && hasRole(array('admin','teknisi_ipsrs'))) {
        $tek_id = (int)($_POST['teknisi_id'] ?? 0) ?: null;
        $pdo->prepare("UPDATE tiket_ipsrs SET teknisi_id=? WHERE id=?")->execute(array($tek_id, $id));
        $st2 = $pdo->prepare("SELECT nama FROM users WHERE id=?");
        $st2->execute(array($tek_id ?? 0));
        $tn = $tek_id ? ($st2->fetchColumn() ?: 'Teknisi') : 'Tidak ada';
        $pdo->prepare("INSERT INTO tiket_ipsrs_log (tiket_id,user_id,status_dari,status_ke,keterangan)
            VALUES (?,?,?,?,?)")
            ->execute(array($id, $_SESSION['user_id'], $t['status'], $t['status'], 'Tiket di-assign ke: '.$tn));
        setFlash('success', 'Teknisi berhasil di-assign.');
        redirect(APP_URL.'/pages/detail_tiket_ipsrs.php?id='.$id);
    }

    // Komentar
    if ($act === 'komentar') {
        $isi = trim($_POST['isi'] ?? '');
        if ($isi) {
            $pdo->prepare("INSERT INTO komentar_ipsrs (tiket_id,user_id,isi) VALUES (?,?,?)")
                ->execute(array($id, $_SESSION['user_id'], $isi));
            $pdo->prepare("INSERT INTO tiket_ipsrs_log (tiket_id,user_id,status_dari,status_ke,keterangan)
                VALUES (?,?,?,?,?)")
                ->execute(array($id, $_SESSION['user_id'], $t['status'], $t['status'], 'Komentar: '.substr($isi,0,80)));
            notifTelegramIpsrs($pdo, $t, 'telegram_notif_komentar', array('pengirim'=>$_SESSION['user_nama'],'komentar'=>$isi));
            setFlash('success', 'Komentar berhasil dikirim.');
        }
        redirect(APP_URL.'/pages/detail_tiket_ipsrs.php?id='.$id.'#diskusi');
    }
}

// ── Refresh data setelah POST ─────────────────────────────────────────────────
$st->execute(array($id)); $t = $st->fetch();

$fotos_q = $pdo->prepare("
    SELECT f.*, u.nama AS uploader FROM tiket_ipsrs_foto f
    LEFT JOIN users u ON u.id = f.user_id
    WHERE f.tiket_id = ? ORDER BY f.created_at ASC
");
$fotos_q->execute(array($id)); $fotos = $fotos_q->fetchAll();

$is_final    = in_array($t['status'], array('selesai','ditolak','tidak_bisa'));
$dur_respon  = $t['durasi_respon_menit'];
$dur_selesai = $is_final ? $t['durasi_selesai_menit'] : durasiSekarang($t['waktu_submit']);
$sla_selesai = $t['sla_jam']        ? slaStatus($dur_selesai, $t['sla_jam'])        : null;
$sla_respon  = $t['sla_respon_jam'] ? slaStatus($dur_respon,  $t['sla_respon_jam']) : null;

$dot_class    = array('menunggu'=>'d-menunggu','diproses'=>'d-diproses','selesai'=>'d-selesai','ditolak'=>'d-ditolak','tidak_bisa'=>'d-tidakbisa');
$border_color = array('menunggu'=>'var(--yellow)','diproses'=>'var(--blue)','selesai'=>'var(--green)','ditolak'=>'var(--red)','tidak_bisa'=>'var(--purple)');

$foto_js_data = array();
foreach ($fotos as $f) {
    $foto_js_data[] = array(
        'src'     => APP_URL . '/' . $f['path'],
        'caption' => $f['nama_file'] . ' — ' . ($f['uploader'] ?? '-') . ' — ' . date('d/m/Y H:i', strtotime($f['created_at'])),
    );
}

$is_medis    = ($t['jenis_tiket'] === 'Medis');
$page_title  = 'Detail Tiket IPSRS ' . $t['nomor'];
$active_menu = hasRole('user') ? 'tiket_saya_ipsrs' : 'semua_tiket_ipsrs';
$back_url    = hasRole('user') ? APP_URL.'/pages/tiket_saya_ipsrs.php' : APP_URL.'/pages/semua_tiket_ipsrs.php';

include '../includes/header.php';
?>
<style>
/* ── Foto grid & lightbox ──────────────────────────────── */
.foto-grid{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;}
.foto-item{position:relative;width:90px;height:90px;border-radius:8px;overflow:hidden;border:2px solid #e5e7eb;cursor:pointer;transition:transform .15s,border-color .15s,box-shadow .15s;}
.foto-item:hover{transform:scale(1.04);border-color:var(--primary,#3b82f6);box-shadow:0 4px 12px rgba(59,130,246,.2);}
.foto-item img{width:100%;height:100%;object-fit:cover;display:block;}
.foto-item .foto-del{position:absolute;top:3px;right:3px;background:rgba(220,38,38,.85);color:#fff;border:none;border-radius:50%;width:20px;height:20px;font-size:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .15s;}
.foto-item:hover .foto-del{opacity:1;}
.foto-uploader{font-size:10px;color:#888;margin-top:3px;text-align:center;line-height:1.3;max-width:90px;}
.drop-zone{border:2px dashed #cbd5e1;border-radius:10px;padding:22px 16px;text-align:center;cursor:pointer;transition:background .2s,border-color .2s;background:#f8fafc;color:#94a3b8;font-size:12px;}
.drop-zone:hover,.drop-zone.drag-over{background:#eff6ff;border-color:var(--primary,#3b82f6);color:var(--primary,#3b82f6);}
.drop-zone i{font-size:26px;display:block;margin-bottom:8px;}
.drop-zone strong{display:block;font-size:13px;margin-bottom:4px;}
#preview-grid,#preview-grid-status{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;}
.prev-thumb{position:relative;width:75px;height:75px;border-radius:6px;overflow:hidden;border:2px solid #e5e7eb;}
.prev-thumb img{width:100%;height:100%;object-fit:cover;}
.prev-thumb .rm-prev{position:absolute;top:2px;right:2px;background:rgba(220,38,38,.85);color:#fff;border:none;border-radius:50%;width:18px;height:18px;font-size:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;}
#lightbox{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.9);align-items:center;justify-content:center;flex-direction:column;}
#lightbox.open{display:flex;}
#lightbox img{max-width:90vw;max-height:80vh;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.5);}
#lightbox-close{position:absolute;top:14px;right:18px;color:#fff;font-size:30px;cursor:pointer;background:none;border:none;line-height:1;}
#lightbox-caption{color:#ccc;font-size:11px;margin-top:10px;text-align:center;}
#lightbox-nav{display:flex;gap:14px;margin-top:12px;}
#lightbox-nav button{background:rgba(255,255,255,.15);color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:13px;}
#lightbox-nav button:hover{background:rgba(255,255,255,.28);}
.foto-section{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:18px;}
.foto-section-title{font-size:12px;font-weight:700;color:#334155;display:flex;align-items:center;gap:6px;margin-bottom:4px;}
.foto-count-badge{background:#dbeafe;color:#1d4ed8;font-size:10px;font-weight:700;padding:1px 8px;border-radius:20px;}
/* ── Kartu Aset IPSRS ──────────────────────────────────── */
.aset-card{background:linear-gradient(135deg,#f0fdf9,#ecfdf5);border:1.5px solid #86efac;border-radius:9px;padding:12px 14px;display:flex;align-items:flex-start;gap:11px;margin-top:8px;}
.aset-card-ic{width:38px;height:38px;border-radius:8px;background:#15803d;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.aset-card-body{flex:1;min-width:0;}
.aset-card-nama{font-size:13px;font-weight:700;color:#1e293b;}
.aset-card-inv{display:inline-block;font-family:'Courier New',monospace;font-size:10.5px;font-weight:700;background:#dcfce7;color:#15803d;border:1px solid #86efac;padding:1px 7px;border-radius:4px;margin-top:3px;}
.aset-card-meta{display:flex;flex-wrap:wrap;gap:10px;margin-top:7px;font-size:11px;color:#64748b;}
.aset-card-meta i{color:#26B99A;font-size:10px;}
/* ── Jenis badge ───────────────────────────────────────── */
.badge-medis   {display:inline-flex;align-items:center;gap:3px;padding:2px 9px;border-radius:10px;font-size:10.5px;font-weight:700;background:#fce7f3;color:#9d174d;}
.badge-nonmedis{display:inline-flex;align-items:center;gap:3px;padding:2px 9px;border-radius:10px;font-size:10.5px;font-weight:700;background:#dbeafe;color:#1e40af;}
</style>

<div class="page-header">
  <h4><i class="fa fa-ticket-alt" style="color:var(--primary);"></i> &nbsp;Detail Tiket IPSRS</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Beranda</a><span class="sep">/</span>
    <a href="<?= $back_url ?>">Tiket IPSRS</a><span class="sep">/</span>
    <span class="cur"><?= clean($t['nomor']) ?></span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Header panel tiket -->
  <div class="panel" style="border-left:5px solid <?= $border_color[$t['status']] ?? 'var(--primary)' ?>;">
    <div class="panel-bd">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;">
        <div style="flex:1;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:7px;flex-wrap:wrap;">
            <span style="font-size:13px;font-weight:700;color:var(--primary);font-family:monospace;"><?= clean($t['nomor']) ?></span>
            <?= badgeStatus($t['status']) ?>
            <?= badgePrioritas($t['prioritas']) ?>
            <?php if ($is_medis): ?>
            <span class="badge-medis"><i class="fa fa-kit-medical" style="font-size:9px;"></i> Medis</span>
            <?php else: ?>
            <span class="badge-nonmedis"><i class="fa fa-screwdriver-wrench" style="font-size:9px;"></i> Non-Medis</span>
            <?php endif; ?>
            <?php if (count($fotos)): ?>
            <span style="background:#dbeafe;color:#1d4ed8;font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;">
              <i class="fa fa-images"></i> <?= count($fotos) ?> foto
            </span>
            <?php endif; ?>
          </div>
          <h3 style="font-size:16px;color:#333;font-weight:700;margin-bottom:6px;"><?= clean($t['judul']) ?></h3>
          <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:#aaa;">
            <span><i class="fa fa-tag"></i> <?= clean($t['kat_nama'] ?? '-') ?></span>
            <span><i class="fa fa-map-marker-alt"></i> <?= clean($t['lokasi'] ?? '-') ?></span>
            <span><i class="fa fa-clock"></i> <?= formatTanggal($t['waktu_submit'], true) ?></span>
            <?php if ($t['nama_aset']): ?>
            <span style="color:#26B99A;"><i class="fa fa-toolbox"></i> <?= clean($t['nama_aset']) ?> (<?= clean($t['aset_inv']) ?>)</span>
            <?php endif; ?>
          </div>
        </div>
        <div style="display:flex;gap:7px;flex-shrink:0;">
          <a href="<?= $back_url ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Kembali</a>
          <?php if (!$is_final && hasRole(array('admin','teknisi_ipsrs')) && $t['status'] === 'menunggu'): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="ambil">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-hand-pointer"></i> Ambil &amp; Proses</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="g3">
    <!-- ══ Kolom Kiri (tab) ══ -->
    <div>
      <div class="panel">
        <div class="panel-hd" style="padding:0 15px;">
          <div class="tabs" style="margin:0;">
            <button class="tab-btn active" onclick="switchTab(this,'t-info')">Detail</button>
            <button class="tab-btn" onclick="switchTab(this,'t-diskusi')">Diskusi (<?= count($komentar) ?>)</button>
            <?php if (hasRole(array('admin','teknisi_ipsrs')) && !$is_final): ?>
            <button class="tab-btn" onclick="switchTab(this,'t-aksi')" id="btn-tab-aksi">Tindakan IPSRS</button>
            <?php elseif ($is_final && count($fotos)): ?>
            <button class="tab-btn" onclick="switchTab(this,'t-foto-view')">Foto Bukti (<?= count($fotos) ?>)</button>
            <?php endif; ?>
          </div>
        </div>
        <div class="panel-bd">

          <!-- ── TAB DETAIL ── -->
          <div id="t-info" class="tab-c active">

            <div class="form-row" style="margin-bottom:14px;">
              <div>
                <div style="font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;margin-bottom:5px;">Pemohon</div>
                <div class="d-flex ai-c gap10">
                  <div class="av av-md"><?= getInitials($t['req_nama']) ?></div>
                  <div>
                    <div style="font-size:13px;font-weight:600;"><?= clean($t['req_nama']) ?></div>
                    <div style="font-size:11px;color:#aaa;"><?= clean($t['req_divisi'] ?? '') ?></div>
                    <?php if ($t['req_email']): ?><div style="font-size:11px;color:#aaa;"><?= clean($t['req_email']) ?></div><?php endif; ?>
                  </div>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;margin-bottom:5px;">Teknisi IPSRS</div>
                <?php if ($t['tek_nama']): ?>
                <div class="d-flex ai-c gap10">
                  <div class="av av-md av-blue"><?= getInitials($t['tek_nama']) ?></div>
                  <div>
                    <div style="font-size:13px;font-weight:600;"><?= clean($t['tek_nama']) ?></div>
                    <div style="font-size:11px;color:#aaa;"><?= clean($t['tek_email'] ?? '') ?></div>
                  </div>
                </div>
                <?php else: ?>
                <span style="font-size:12px;color:#aaa;font-style:italic;">Belum ditugaskan</span>
                <?php if (hasRole(array('admin','teknisi_ipsrs')) && !$is_final): ?>
                <br><button class="btn btn-default btn-sm" onclick="document.getElementById('btn-tab-aksi').click()" style="margin-top:6px;"><i class="fa fa-user-plus"></i> Assign Teknisi</button>
                <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>

            <hr class="divider">

            <!-- Aset IPSRS terkait -->
            <?php if ($t['nama_aset']): ?>
            <div style="font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;margin-bottom:6px;">
              <i class="fa fa-toolbox" style="color:var(--primary);"></i> Aset / Peralatan Terkait
            </div>
            <div class="aset-card">
              <div class="aset-card-ic">
                <i class="fa fa-toolbox" style="color:#fff;font-size:15px;"></i>
              </div>
              <div class="aset-card-body">
                <div class="aset-card-nama"><?= clean($t['nama_aset']) ?></div>
                <div class="aset-card-inv"><?= clean($t['aset_inv']) ?></div>
                <div class="aset-card-meta">
                  <?php if ($t['aset_merek'] || $t['aset_model']): ?>
                  <span><i class="fa fa-tag"></i> <?= clean(implode(' · ', array_filter([$t['aset_merek'],$t['aset_model']]))) ?></span>
                  <?php endif; ?>
                  <?php if ($t['aset_serial']): ?>
                  <span><i class="fa fa-barcode"></i> <span style="font-family:monospace;"><?= clean($t['aset_serial']) ?></span></span>
                  <?php endif; ?>
                  <?php if ($t['aset_kondisi']): ?>
                  <span><i class="fa fa-circle-half-stroke"></i>
                    <span style="font-weight:700;color:<?= $t['aset_kondisi']==='Baik'?'#15803d':'#92400e' ?>;"><?= clean($t['aset_kondisi']) ?></span>
                  </span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <hr class="divider">
            <?php endif; ?>

            <div style="font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;margin-bottom:6px;">Deskripsi Masalah / Permintaan</div>
            <p style="font-size:13px;color:#444;line-height:1.8;white-space:pre-line;"><?= clean($t['deskripsi']) ?></p>

            <?php if ($t['catatan_teknisi']): ?>
            <hr class="divider">
            <div style="background:#fff8f8;border:1px solid #fca5a5;border-radius:4px;padding:10px 12px;">
              <div style="font-size:11px;font-weight:700;color:#991b1b;margin-bottom:4px;"><i class="fa fa-info-circle"></i> Keterangan IPSRS</div>
              <p style="font-size:12px;color:#555;line-height:1.7;"><?= clean($t['catatan_teknisi']) ?></p>
            </div>
            <?php endif; ?>

            <?php if (count($fotos)): ?>
            <hr class="divider">
            <div style="font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;margin-bottom:8px;">
              <i class="fa fa-images" style="color:var(--primary);"></i> Foto Bukti (<?= count($fotos) ?>)
            </div>
            <div class="foto-grid">
              <?php foreach ($fotos as $fi => $foto): ?>
              <div>
                <div class="foto-item" onclick="openLightbox(<?= $fi ?>)">
                  <img src="<?= APP_URL.'/'.$foto['path'] ?>" alt="<?= clean($foto['nama_file']) ?>" loading="lazy">
                </div>
                <div class="foto-uploader"><?= clean($foto['uploader'] ?? '-') ?><br><?= date('d/m H:i',strtotime($foto['created_at'])) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- ── TAB DISKUSI ── -->
          <div id="t-diskusi" class="tab-c">
            <?php if (empty($komentar)): ?>
            <p style="text-align:center;color:#bbb;padding:20px 0;font-size:12px;">
              <i class="fa fa-comments" style="font-size:20px;display:block;margin-bottom:7px;"></i>Belum ada komentar
            </p>
            <?php else: foreach ($komentar as $km): ?>
            <div style="display:flex;gap:9px;margin-bottom:13px;padding-bottom:13px;border-bottom:1px solid #f5f5f5;">
              <div class="av av-sm <?= ($km['role']==='teknisi_ipsrs'||$km['role']==='admin') ? 'av-blue' : '' ?>"><?= getInitials($km['nama']) ?></div>
              <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:7px;margin-bottom:3px;">
                  <strong style="font-size:12px;"><?= clean($km['nama']) ?></strong>
                  <span style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:3px;
                    background:<?= $km['role']==='admin'?'#ede9fe':($km['role']==='teknisi_ipsrs'?'#dbeafe':'#f3f4f6') ?>;
                    color:<?= $km['role']==='admin'?'#7c3aed':($km['role']==='teknisi_ipsrs'?'#1d4ed8':'#374151') ?>;">
                    <?= ucfirst($km['role']) ?>
                  </span>
                  <span style="font-size:11px;color:#bbb;margin-left:auto;"><?= timeAgo($km['created_at']) ?></span>
                </div>
                <p style="font-size:12px;color:#555;line-height:1.6;"><?= nl2br(clean($km['isi'])) ?></p>
              </div>
            </div>
            <?php endforeach; endif; ?>
            <form method="POST" style="border-top:1px solid #f0f0f0;padding-top:12px;margin-top:4px;" id="diskusi">
              <input type="hidden" name="action" value="komentar">
              <textarea name="isi" class="form-control" placeholder="Tulis pertanyaan atau update..." rows="3" required></textarea>
              <button type="submit" class="btn btn-primary btn-sm" style="margin-top:7px;"><i class="fa fa-paper-plane"></i> Kirim</button>
            </form>
          </div>

          <!-- ── TAB FOTO VIEW (tiket final) ── -->
          <?php if ($is_final && count($fotos)): ?>
          <div id="t-foto-view" class="tab-c">
            <p style="font-size:12px;color:#888;margin-bottom:10px;"><?= count($fotos) ?> foto bukti pengerjaan tersimpan.</p>
            <div class="foto-grid">
              <?php foreach ($fotos as $fi => $foto): ?>
              <div>
                <div class="foto-item" onclick="openLightbox(<?= $fi ?>)">
                  <img src="<?= APP_URL.'/'.$foto['path'] ?>" alt="<?= clean($foto['nama_file']) ?>" loading="lazy">
                </div>
                <div class="foto-uploader"><?= clean($foto['uploader'] ?? '-') ?><br><?= date('d/m H:i',strtotime($foto['created_at'])) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- ── TAB TINDAKAN IPSRS ── -->
          <?php if (hasRole(array('admin','teknisi_ipsrs')) && !$is_final): ?>
          <div id="t-aksi" class="tab-c">

            <?php if (hasRole('admin')): ?>
            <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #f0f0f0;">
              <p style="font-size:12px;font-weight:700;color:#333;margin-bottom:4px;">Assign Teknisi IPSRS</p>
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
            <?php elseif (hasRole('teknisi_ipsrs')): ?>
            <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #f0f0f0;">
              <p style="font-size:12px;font-weight:700;color:#333;margin-bottom:6px;">Teknisi Penanganan</p>
              <?php if ($t['teknisi_id']): ?>
              <div style="display:flex;align-items:center;gap:9px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:5px;padding:9px 12px;">
                <div class="av av-sm av-blue"><?= getInitials($t['tek_nama']) ?></div>
                <div>
                  <div style="font-size:12px;font-weight:700;color:#0369a1;"><?= clean($t['tek_nama']) ?></div>
                  <div style="font-size:11px;color:#64748b;"><?= ($t['teknisi_id']==$_SESSION['user_id']) ? 'Tiket ini ditangani oleh Anda' : 'Ditugaskan oleh admin' ?></div>
                </div>
              </div>
              <?php else: ?>
              <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:5px;padding:9px 12px;font-size:12px;color:#92400e;">
                <i class="fa fa-info-circle"></i>&nbsp;Klik <strong>"Ambil &amp; Proses"</strong> di atas untuk mengambil tiket ini.
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Upload Foto Bukti -->
            <div class="foto-section">
              <div class="foto-section-title">
                <i class="fa fa-camera text-primary"></i>
                Upload Foto Bukti Pengerjaan
                <?php if (count($fotos)): ?>
                <span class="foto-count-badge"><?= count($fotos) ?> tersimpan</span>
                <?php endif; ?>
              </div>
              <p style="font-size:11px;color:#94a3b8;margin-bottom:12px;">Format JPG / PNG / WebP &mdash; maks 5 MB per file &mdash; bisa lebih dari satu</p>

              <?php if (count($fotos)): ?>
              <div style="margin-bottom:14px;">
                <div style="font-size:11px;color:#64748b;font-weight:600;margin-bottom:7px;"><i class="fa fa-check-circle" style="color:#10b981;"></i> Foto tersimpan:</div>
                <div class="foto-grid">
                  <?php foreach ($fotos as $fi => $foto): ?>
                  <div>
                    <div class="foto-item" onclick="openLightbox(<?= $fi ?>)">
                      <img src="<?= APP_URL.'/'.$foto['path'] ?>" alt="<?= clean($foto['nama_file']) ?>" loading="lazy">
                      <form method="POST" onsubmit="return confirm('Hapus foto ini?')">
                        <input type="hidden" name="action" value="hapus_foto">
                        <input type="hidden" name="foto_id" value="<?= $foto['id'] ?>">
                        <button class="foto-del" type="submit" title="Hapus"><i class="fa fa-times"></i></button>
                      </form>
                    </div>
                    <div class="foto-uploader"><?= clean($foto['uploader'] ?? '-') ?><br><?= date('d/m H:i',strtotime($foto['created_at'])) ?></div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>

              <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_foto">
                <input type="file" name="foto_bukti[]" id="input-foto" accept="image/*" multiple style="display:none;" onchange="previewFoto(this)">
                <div class="drop-zone" id="drop-zone" onclick="document.getElementById('input-foto').click()">
                  <i class="fa fa-cloud-upload-alt"></i>
                  <strong>Klik untuk pilih foto</strong>
                  atau seret &amp; lepas ke sini<br>
                  <span style="font-size:11px;">JPG &middot; PNG &middot; WebP &middot; GIF &mdash; maks 5 MB/file</span>
                </div>
                <div id="preview-grid"></div>
                <button type="submit" class="btn btn-info btn-sm" id="btn-upload-foto" style="display:none;margin-top:10px;">
                  <i class="fa fa-upload"></i> Upload Foto (<span id="foto-count">0</span>)
                </button>
              </form>
            </div>

            <!-- Update Status -->
            <p style="font-size:12px;font-weight:700;color:#333;margin-bottom:4px;"><i class="fa fa-edit text-primary"></i> Update Status Tiket</p>
            <p style="font-size:11px;color:#aaa;margin-bottom:12px;">Anda juga bisa melampirkan foto bukti sekaligus saat update status.</p>
            <form method="POST" enctype="multipart/form-data">
              <input type="hidden" name="action" value="update_status">
              <div class="form-group">
                <label>Status Baru</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                  <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;padding:7px 12px;border:1px solid #ddd;border-radius:5px;background:#d1fae5;color:#065f46;">
                    <input type="radio" name="status_baru" value="selesai" required> <i class="fa fa-check-circle"></i> Selesai
                  </label>
                  <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;padding:7px 12px;border:1px solid #ddd;border-radius:5px;background:#fee2e2;color:#991b1b;">
                    <input type="radio" name="status_baru" value="ditolak"> <i class="fa fa-times-circle"></i> Ditolak
                  </label>
                  <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;padding:7px 12px;border:1px solid #ddd;border-radius:5px;background:#ede9fe;color:#5b21b6;">
                    <input type="radio" name="status_baru" value="tidak_bisa"> <i class="fa fa-ban"></i> Tidak Bisa Ditangani
                  </label>
                </div>
              </div>
              <div class="form-group">
                <label>Catatan / Alasan <span id="req-note"></span></label>
                <textarea name="catatan_teknisi" id="note-input" class="form-control"
                          placeholder="Tuliskan hasil penanganan, alasan penolakan, atau keterangan tidak bisa ditangani..." rows="3"></textarea>
              </div>
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:6px;">
                  <i class="fa fa-images text-primary"></i> Lampirkan Foto Bukti
                  <span style="font-size:11px;color:#aaa;font-weight:400;">(opsional)</span>
                </label>
                <input type="file" name="foto_bukti[]" id="input-foto-status" accept="image/*" multiple style="display:none;" onchange="previewFotoStatus(this)">
                <div class="drop-zone" id="drop-zone-status" onclick="document.getElementById('input-foto-status').click()" style="padding:14px;">
                  <i class="fa fa-images" style="font-size:20px;"></i>
                  Klik atau seret foto bukti<br>
                  <span style="font-size:11px;">JPG &middot; PNG &middot; WebP &mdash; maks 5 MB/file</span>
                </div>
                <div id="preview-grid-status"></div>
              </div>
              <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Perubahan</button>
            </form>

          </div><!-- /t-aksi -->
          <?php endif; ?>

        </div>
      </div>
    </div>

    <!-- ══ Kolom Kanan: SLA + Timeline ══ -->
    <div>
      <?php if ($t['sla_jam']): ?>
      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-stopwatch text-primary"></i> &nbsp;SLA &amp; Waktu</h5></div>
        <div class="panel-bd">
          <?php foreach ([
            ['Waktu Submit',  formatTanggal($t['waktu_submit'],  true)],
            ['Waktu Respon',  $t['waktu_proses']  ? formatTanggal($t['waktu_proses'],  true) : '—'],
            ['Waktu Selesai', $t['waktu_selesai'] ? formatTanggal($t['waktu_selesai'], true) : '—'],
            ['Durasi Respon', formatDurasi($dur_respon)  . ' / target ' . $t['sla_respon_jam'] . 'j'],
            ['Durasi Total',  formatDurasi($dur_selesai) . ' / target ' . $t['sla_jam']        . 'j'],
          ] as [$l, $v]): ?>
          <div class="d-flex ai-c" style="justify-content:space-between;margin-bottom:8px;font-size:12px;">
            <span style="color:#888;"><?= $l ?></span>
            <strong style="color:#333;text-align:right;font-size:11px;"><?= $v ?></strong>
          </div>
          <?php endforeach; ?>
          <?php if ($sla_selesai): ?>
          <hr class="divider">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;font-size:12px;">
            <span>Pencapaian SLA</span>
            <span class="sla-badge" style="background:<?= $sla_selesai['status']==='aman'?'#d1fae5':($sla_selesai['status']==='warning'?'#fef3c7':'#fee2e2') ?>;color:<?= $sla_selesai['status']==='aman'?'#065f46':($sla_selesai['status']==='warning'?'#92400e':'#991b1b') ?>;"><?= $sla_selesai['label'] ?></span>
          </div>
          <div class="progress">
            <div class="progress-fill <?= $sla_selesai['status']==='aman'?'pg-green':($sla_selesai['status']==='warning'?'pg-orange':'pg-red') ?>"
                 style="width:<?= min($sla_selesai['persen'],100) ?>%"></div>
          </div>
          <div style="font-size:10px;color:#aaa;margin-top:3px;"><?= $sla_selesai['persen'] ?>% dari target <?= $t['sla_jam'] ?> jam</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Riwayat Aktivitas -->
      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-history text-primary"></i> &nbsp;Riwayat Aktivitas</h5></div>
        <div class="panel-bd">
          <ul class="timeline">
            <?php foreach ($logs as $log):
              $dc = $dot_class[$log['status_ke']] ?? 'd-default';
              if ($log['status_ke']==='selesai')        $ic='check';
              elseif ($log['status_ke']==='ditolak')    $ic='times';
              elseif ($log['status_ke']==='tidak_bisa') $ic='ban';
              elseif ($log['status_ke']==='diproses')   $ic='cog';
              else                                       $ic='clock';
            ?>
            <li class="tl-item">
              <div class="tl-dot <?= $dc ?>"><i class="fa fa-<?= $ic ?>"></i></div>
              <div>
                <span class="tl-title"><?= $log['status_ke'] ? strtoupper($log['status_ke']) : 'DIBUAT' ?></span>
                <span class="tl-time"><?= formatTanggal($log['created_at'], true) ?></span>
                <div class="tl-by">oleh <?= clean($log['nama'] ?? '-') ?></div>
                <?php if ($log['keterangan']): ?><div class="tl-desc"><?= clean($log['keterangan']) ?></div><?php endif; ?>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>

  </div><!-- /g3 -->
</div><!-- /content -->

<!-- LIGHTBOX -->
<div id="lightbox" onclick="if(event.target===this)closeLightbox()">
  <button id="lightbox-close" onclick="closeLightbox()" title="Tutup (Esc)">&times;</button>
  <img id="lightbox-img" src="" alt="">
  <div id="lightbox-caption"></div>
  <div id="lightbox-nav">
    <button onclick="lightboxNav(-1)"><i class="fa fa-chevron-left"></i> Sebelumnya</button>
    <button onclick="lightboxNav(1)">Berikutnya <i class="fa fa-chevron-right"></i></button>
  </div>
</div>

<script>
var fotoData = <?= json_encode($foto_js_data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
var lbIndex  = 0;

function openLightbox(idx) { if (!fotoData.length) return; lbIndex=idx; renderLightbox(); document.getElementById('lightbox').classList.add('open'); document.body.style.overflow='hidden'; }
function closeLightbox()   { document.getElementById('lightbox').classList.remove('open'); document.body.style.overflow=''; }
function renderLightbox()  { var f=fotoData[lbIndex]; document.getElementById('lightbox-img').src=f.src; document.getElementById('lightbox-caption').textContent=(lbIndex+1)+' / '+fotoData.length+'  —  '+f.caption; document.getElementById('lightbox-nav').style.display=fotoData.length>1?'flex':'none'; }
function lightboxNav(dir)  { lbIndex=(lbIndex+dir+fotoData.length)%fotoData.length; renderLightbox(); }
document.addEventListener('keydown',function(e){ var lb=document.getElementById('lightbox'); if (!lb.classList.contains('open')) return; if (e.key==='Escape') closeLightbox(); if (e.key==='ArrowLeft') lightboxNav(-1); if (e.key==='ArrowRight') lightboxNav(1); });

var selectedFiles=[], selectedFilesStatus=[];
function previewFoto(input) { selectedFiles=Array.from(input.files); renderPreview(); }
function renderPreview() {
  var grid=document.getElementById('preview-grid'), btn=document.getElementById('btn-upload-foto'), cnt=document.getElementById('foto-count');
  if (!grid) return; grid.innerHTML='';
  if (btn) btn.style.display=selectedFiles.length?'inline-flex':'none';
  if (cnt) cnt.textContent=selectedFiles.length;
  selectedFiles.forEach(function(f,i){ var wrap=document.createElement('div'); wrap.className='prev-thumb'; var img=document.createElement('img'), del=document.createElement('button'); del.className='rm-prev'; del.type='button'; del.innerHTML='&times;'; del.onclick=function(){ selectedFiles.splice(i,1); syncInputFiles(); renderPreview(); }; var r=new FileReader(); r.onload=function(e){img.src=e.target.result;}; r.readAsDataURL(f); wrap.appendChild(img); wrap.appendChild(del); grid.appendChild(wrap); });
}
function syncInputFiles() { var dt=new DataTransfer(); selectedFiles.forEach(function(f){dt.items.add(f);}); document.getElementById('input-foto').files=dt.files; }
function previewFotoStatus(input) { selectedFilesStatus=Array.from(input.files); renderPreviewStatus(); }
function renderPreviewStatus() {
  var grid=document.getElementById('preview-grid-status'); if (!grid) return; grid.innerHTML='';
  selectedFilesStatus.forEach(function(f,i){ var wrap=document.createElement('div'); wrap.className='prev-thumb'; wrap.style.marginTop='8px'; var img=document.createElement('img'), del=document.createElement('button'); del.className='rm-prev'; del.type='button'; del.innerHTML='&times;'; del.onclick=function(){ selectedFilesStatus.splice(i,1); syncInputFilesStatus(); renderPreviewStatus(); }; var r=new FileReader(); r.onload=function(e){img.src=e.target.result;}; r.readAsDataURL(f); wrap.appendChild(img); wrap.appendChild(del); grid.appendChild(wrap); });
}
function syncInputFilesStatus() { var dt=new DataTransfer(); selectedFilesStatus.forEach(function(f){dt.items.add(f);}); var inp=document.getElementById('input-foto-status'); if (inp) inp.files=dt.files; }

function setupDrop(zoneId, inputId, isStatus) {
  var zone=document.getElementById(zoneId); if (!zone) return;
  ['dragenter','dragover'].forEach(function(ev){ zone.addEventListener(ev,function(e){ e.preventDefault(); zone.classList.add('drag-over'); }); });
  ['dragleave','drop'].forEach(function(ev){ zone.addEventListener(ev,function(){ zone.classList.remove('drag-over'); }); });
  zone.addEventListener('drop',function(e){ e.preventDefault(); var inp=document.getElementById(inputId); if (!inp) return; var dt2=new DataTransfer(); Array.from(e.dataTransfer.files).forEach(function(f){dt2.items.add(f);}); inp.files=dt2.files; if (isStatus){selectedFilesStatus=Array.from(inp.files);renderPreviewStatus();}else{selectedFiles=Array.from(inp.files);renderPreview();} });
}
setupDrop('drop-zone','input-foto',false);
setupDrop('drop-zone-status','input-foto-status',true);

document.querySelectorAll('[name=status_baru]').forEach(function(r){ r.addEventListener('change',function(){ var req=document.getElementById('req-note'); if (this.value!=='selesai'){ req.innerHTML='<span style="color:red;">*</span>'; document.getElementById('note-input').required=true; }else{ req.innerHTML=''; document.getElementById('note-input').required=false; } }); });

if (window.location.hash==='#t-aksi'){ var btn=document.getElementById('btn-tab-aksi'); if (btn) btn.click(); }
</script>

<?php include '../includes/footer.php'; ?>