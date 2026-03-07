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
if (hasRole('user') && $t['user_id'] != $_SESSION['user_id']) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/pages/tiket_saya.php'); }

$logs = $pdo->prepare("SELECT l.*,u.nama,u.role FROM tiket_log l LEFT JOIN users u ON u.id=l.user_id WHERE l.tiket_id=? ORDER BY l.created_at ASC");
$logs->execute([$id]); $logs = $logs->fetchAll();

$komentar = $pdo->prepare("SELECT k.*,u.nama,u.role FROM komentar k LEFT JOIN users u ON u.id=k.user_id WHERE k.tiket_id=? ORDER BY k.created_at ASC");
$komentar->execute([$id]); $komentar = $komentar->fetchAll();

$teknisi_list = hasRole(['admin','teknisi']) ? $pdo->query("SELECT * FROM users WHERE role='teknisi' AND status='aktif' ORDER BY nama")->fetchAll() : [];

// ── HELPER: Upload foto bukti ─────────────────────────────────────────────────
function uploadFotoBukti($pdo, $tiket_id, $user_id) {
    if (!isset($_FILES['foto_bukti']) || empty($_FILES['foto_bukti']['name'][0])) return 0;
    $root_dir  = dirname(dirname(__FILE__));
    $upload_dir = $root_dir . '/uploads/tiket_foto/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    if (!is_writable($upload_dir)) return 0;
    $allowed_ext  = array('jpg','jpeg','png','gif','webp');
    $allowed_mime = array('image/jpeg','image/png','image/gif','image/webp');
    $max_size     = 5 * 1024 * 1024;
    $uploaded     = 0;
    foreach ($_FILES['foto_bukti']['tmp_name'] as $i => $tmp) {
        if ($_FILES['foto_bukti']['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($_FILES['foto_bukti']['size'][$i] > $max_size) continue;
        $mime = mime_content_type($tmp);
        if (!in_array($mime, $allowed_mime)) continue;
        $ori = $_FILES['foto_bukti']['name'][$i];
        $ext = strtolower(pathinfo($ori, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) continue;
        $safe = 'tiket_' . $tiket_id . '_' . time() . '_' . $i . '.' . $ext;
        $dest = $upload_dir . $safe;
        if (move_uploaded_file($tmp, $dest)) {
            $pdo->prepare("INSERT INTO tiket_foto (tiket_id,user_id,nama_file,path) VALUES (?,?,?,?)")
                ->execute(array($tiket_id, $user_id, $ori, 'uploads/tiket_foto/' . $safe));
            $uploaded++;
        }
    }
    return $uploaded;
}

// ── HELPER: Notif Telegram ───────────────────────────────────────────────────
function notifTelegram($pdo, $t, $event_key, $extra = array()) {
    $cfg = getSettings($pdo);
    if (($cfg['telegram_enabled'] ?? '0') !== '1') return;
    if (($cfg[$event_key]         ?? '0') !== '1') return;
    $emoji_prio = array('Rendah'=>'🟢','Sedang'=>'🟡','Tinggi'=>'🔴');
    $ep   = isset($emoji_prio[$t['prioritas']]) ? $emoji_prio[$t['prioritas']] : '⚪';
    $info = "📋 <b>No Tiket :</b> {$t['nomor']}\n"
          . "📌 <b>Judul    :</b> " . htmlspecialchars($t['judul'], ENT_QUOTES) . "\n"
          . "🏷️ <b>Kategori :</b> " . htmlspecialchars(isset($t['kat_nama'])  ? $t['kat_nama']  : '-', ENT_QUOTES) . "\n"
          . "{$ep} <b>Prioritas:</b> {$t['prioritas']}\n"
          . "📍 <b>Lokasi   :</b> " . htmlspecialchars(isset($t['lokasi'])    ? $t['lokasi']    : '-', ENT_QUOTES) . "\n"
          . "👤 <b>Pemohon  :</b> " . htmlspecialchars(isset($t['req_nama'])  ? $t['req_nama']  : '-', ENT_QUOTES) . "\n";
    $msg = '';
    switch ($event_key) {
        case 'telegram_notif_diproses':
            $msg = "⚙️ <b>Tiket Mulai Diproses — FixSmart Helpdesk</b>\n\n" . $info
                 . "🔧 <b>Teknisi  :</b> " . htmlspecialchars(isset($extra['teknisi']) ? $extra['teknisi'] : '-', ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n\n<i>Tiket sedang dalam penanganan teknisi.</i>";
            break;
        case 'telegram_notif_selesai':
            $catatan = isset($extra['catatan']) ? $extra['catatan'] : '';
            $msg = "✅ <b>Tiket Selesai Ditangani — FixSmart Helpdesk</b>\n\n" . $info
                 . "🔧 <b>Teknisi  :</b> " . htmlspecialchars(isset($extra['teknisi']) ? $extra['teknisi'] : '-', ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n"
                 . ($catatan ? "📝 <b>Catatan  :</b> " . htmlspecialchars(mb_substr($catatan,0,200,'UTF-8'), ENT_QUOTES) . "\n" : '')
                 . "\n<i>Tiket telah berhasil diselesaikan.</i>";
            break;
        case 'telegram_notif_ditolak':
            $status_label = isset($extra['status_label']) ? $extra['status_label'] : 'Ditolak';
            $catatan      = isset($extra['catatan'])       ? $extra['catatan']      : '';
            $msg = "❌ <b>Tiket {$status_label} — FixSmart Helpdesk</b>\n\n" . $info
                 . "🔧 <b>Diproses :</b> " . htmlspecialchars(isset($extra['teknisi']) ? $extra['teknisi'] : '-', ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n"
                 . ($catatan ? "📝 <b>Alasan   :</b> " . htmlspecialchars(mb_substr($catatan,0,200,'UTF-8'), ENT_QUOTES) . "\n" : '')
                 . "\n<i>Tiket tidak dapat dilanjutkan.</i>";
            break;
        case 'telegram_notif_komentar':
            $isi_km = isset($extra['komentar']) ? $extra['komentar'] : '';
            $msg = "💬 <b>Komentar Baru di Tiket — FixSmart Helpdesk</b>\n\n" . $info
                 . "✍️ <b>Komentar dari:</b> " . htmlspecialchars(isset($extra['pengirim']) ? $extra['pengirim'] : '-', ENT_QUOTES) . "\n"
                 . "🕐 <b>Waktu    :</b> " . date('d/m/Y H:i:s') . "\n\n"
                 . "💬 <b>Isi Komentar:</b>\n"
                 . htmlspecialchars(mb_substr($isi_km,0,300,'UTF-8').(mb_strlen($isi_km,'UTF-8')>300?'...':''), ENT_QUOTES)
                 . "\n\n<i>Cek dashboard untuk membalas komentar.</i>";
            break;
        default: return;
    }
    if ($msg) sendTelegram($pdo, $msg);
}

// ── POST ACTIONS ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act      = isset($_POST['action']) ? $_POST['action'] : '';
    $is_final = in_array($t['status'], array('selesai','ditolak','tidak_bisa'));

    // Ambil/Proses tiket
    if ($act === 'ambil' && hasRole(array('admin','teknisi')) && $t['status'] === 'menunggu') {
        $tek_id = $_SESSION['user_id'];
        $pdo->prepare("UPDATE tiket SET status='diproses',teknisi_id=?,waktu_diproses=NOW(),
            durasi_respon_menit=TIMESTAMPDIFF(MINUTE,waktu_submit,NOW()) WHERE id=?")->execute(array($tek_id,$id));
        $pdo->prepare("INSERT INTO tiket_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,'menunggu','diproses',?)")
            ->execute(array($id,$_SESSION['user_id'],'Tiket diambil dan mulai diproses oleh '.$_SESSION['user_nama']));
        notifTelegram($pdo, $t, 'telegram_notif_diproses', array('teknisi' => $_SESSION['user_nama']));
        setFlash('success','Tiket berhasil diambil. Selamat mengerjakan!');
        redirect(APP_URL.'/pages/detail_tiket.php?id='.$id);
    }

    // Update status + upload foto sekaligus
    if ($act === 'update_status' && hasRole(array('admin','teknisi')) && !$is_final) {
        $new_status = isset($_POST['status_baru']) ? $_POST['status_baru'] : '';
        $catatan    = trim(isset($_POST['catatan_penolakan']) ? $_POST['catatan_penolakan'] : '');
        $valid      = array('selesai','ditolak','tidak_bisa');
        if (in_array($new_status, $valid)) {
            $jml_foto = uploadFotoBukti($pdo, $id, $_SESSION['user_id']);
            $pdo->prepare("UPDATE tiket SET
                    status=?,
                    catatan_penolakan=?,
                    waktu_selesai=NOW(),
                    durasi_selesai_menit=TIMESTAMPDIFF(MINUTE,waktu_submit,NOW()),
                    teknisi_id=COALESCE(teknisi_id,?),
                    waktu_diproses=COALESCE(waktu_diproses,NOW()),
                    durasi_respon_menit=COALESCE(durasi_respon_menit,TIMESTAMPDIFF(MINUTE,waktu_submit,NOW()))
                WHERE id=?")
                ->execute(array($new_status, $catatan ? $catatan : null, $_SESSION['user_id'], $id));

            $ket_foto = $jml_foto ? " ($jml_foto foto bukti diupload)" : '';
            if ($new_status === 'selesai')       $ket = 'Tiket selesai ditangani.' . $ket_foto;
            elseif ($new_status === 'ditolak')   $ket = 'Tiket ditolak. Alasan: ' . $catatan . $ket_foto;
            else                                 $ket = 'Tidak dapat ditangani. Keterangan: ' . $catatan . $ket_foto;

            $pdo->prepare("INSERT INTO tiket_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,?,?,?)")
                ->execute(array($id, $_SESSION['user_id'], $t['status'], $new_status, $ket));

            $tek_nama = $t['tek_nama'] ? $t['tek_nama'] : $_SESSION['user_nama'];
            if ($new_status === 'selesai') {
                notifTelegram($pdo, $t, 'telegram_notif_selesai', array('teknisi'=>$tek_nama,'catatan'=>$catatan));
            } else {
                $sl = ($new_status === 'ditolak') ? 'Ditolak' : 'Tidak Bisa Ditangani';
                notifTelegram($pdo, $t, 'telegram_notif_ditolak', array('teknisi'=>$tek_nama,'catatan'=>$catatan,'status_label'=>$sl));
            }
            $msg_foto = $jml_foto ? " $jml_foto foto bukti berhasil diupload." : '';
            setFlash('success', 'Status tiket berhasil diperbarui.' . $msg_foto);
            redirect(APP_URL.'/pages/detail_tiket.php?id='.$id);
        }
    }

    // Upload foto saja
    if ($act === 'upload_foto' && hasRole(array('admin','teknisi')) && !$is_final) {
        $jml_foto = uploadFotoBukti($pdo, $id, $_SESSION['user_id']);
        if ($jml_foto > 0) {
            $pdo->prepare("INSERT INTO tiket_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,?,?,?)")
                ->execute(array($id, $_SESSION['user_id'], $t['status'], $t['status'], "Upload $jml_foto foto bukti pengerjaan."));
            setFlash('success', "$jml_foto foto berhasil diupload.");
        } else {
            setFlash('warning', 'Tidak ada foto berhasil diupload. Pastikan format JPG/PNG/WebP dan ukuran maks 5 MB per file.');
        }
        redirect(APP_URL.'/pages/detail_tiket.php?id='.$id.'#t-aksi');
    }

    // Hapus foto
    if ($act === 'hapus_foto' && hasRole(array('admin','teknisi'))) {
        $foto_id  = (int)(isset($_POST['foto_id']) ? $_POST['foto_id'] : 0);
        $foto_row = $pdo->prepare("SELECT * FROM tiket_foto WHERE id=? AND tiket_id=?");
        $foto_row->execute(array($foto_id, $id));
        $foto_row = $foto_row->fetch();
        if ($foto_row) {
            $root_dir = dirname(dirname(__FILE__));
            $full     = $root_dir . '/' . $foto_row['path'];
            if (file_exists($full)) unlink($full);
            $pdo->prepare("DELETE FROM tiket_foto WHERE id=?")->execute(array($foto_id));
            setFlash('success', 'Foto berhasil dihapus.');
        }
        redirect(APP_URL.'/pages/detail_tiket.php?id='.$id.'#t-aksi');
    }

    // Assign teknisi
    if ($act === 'assign' && hasRole(array('admin','teknisi'))) {
        $tek_id = (int)(isset($_POST['teknisi_id']) ? $_POST['teknisi_id'] : 0);
        $tek_id = $tek_id ? $tek_id : null;
        $pdo->prepare("UPDATE tiket SET teknisi_id=? WHERE id=?")->execute(array($tek_id, $id));
        $st2 = $pdo->prepare("SELECT nama FROM users WHERE id=?");
        $st2->execute(array($tek_id ? $tek_id : 0));
        $tn = $tek_id ? ($st2->fetchColumn() ?: 'Teknisi') : 'Tidak ada';
        $pdo->prepare("INSERT INTO tiket_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,?,?,?)")
            ->execute(array($id, $_SESSION['user_id'], $t['status'], $t['status'], 'Tiket di-assign ke: '.$tn));
        setFlash('success', 'Teknisi berhasil di-assign.');
        redirect(APP_URL.'/pages/detail_tiket.php?id='.$id);
    }

    // Komentar
    if ($act === 'komentar') {
        $isi = trim(isset($_POST['isi']) ? $_POST['isi'] : '');
        if ($isi) {
            $pdo->prepare("INSERT INTO komentar (tiket_id,user_id,isi) VALUES (?,?,?)")->execute(array($id, $_SESSION['user_id'], $isi));
            $pdo->prepare("INSERT INTO tiket_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,?,?,?)")
                ->execute(array($id, $_SESSION['user_id'], $t['status'], $t['status'], 'Komentar: '.substr($isi,0,80)));
            notifTelegram($pdo, $t, 'telegram_notif_komentar', array('pengirim'=>$_SESSION['user_nama'],'komentar'=>$isi));
            setFlash('success', 'Komentar berhasil dikirim.');
        }
        redirect(APP_URL.'/pages/detail_tiket.php?id='.$id.'#diskusi');
    }

    // ── SIMPAN BERITA ACARA ──────────────────────────────────────────────────
    if ($act === 'simpan_ba' && hasRole(array('admin','teknisi')) && $t['status'] === 'tidak_bisa') {
        $nomor_ba = trim($_POST['nomor_ba'] ?? '');
        if (!$nomor_ba) {
            $tahun    = date('Y');
            $last_seq = (int)$pdo->query("SELECT COUNT(*) FROM berita_acara WHERE YEAR(created_at)=$tahun")->fetchColumn();
            $nomor_ba = 'BA-IT-' . $tahun . '-' . str_pad($last_seq + 1, 4, '0', STR_PAD_LEFT);
        }
        $fields = [
            'nomor_ba'            => $nomor_ba,
            'tanggal_ba'          => $_POST['tanggal_ba']          ?: date('Y-m-d'),
            'jenis_tindak'        => $_POST['jenis_tindak']        ?? 'lainnya',
            'uraian_masalah'      => trim($_POST['uraian_masalah'] ?? ''),
            'kesimpulan'          => trim($_POST['kesimpulan']     ?? ''),
            'tindak_lanjut'       => trim($_POST['tindak_lanjut']  ?? ''),
            'nilai_estimasi'      => strlen(trim($_POST['nilai_estimasi'] ?? '')) ? (int)$_POST['nilai_estimasi'] : null,
            'diketahui_nama'      => trim($_POST['diketahui_nama']      ?? ''),
            'diketahui_jabatan'   => trim($_POST['diketahui_jabatan']   ?? ''),
            'mengetahui_nama'     => trim($_POST['mengetahui_nama']     ?? ''),
            'mengetahui_jabatan'  => trim($_POST['mengetahui_jabatan']  ?? ''),
            'catatan_tambahan'    => trim($_POST['catatan_tambahan']    ?? ''),
        ];
        $ba_exists = $pdo->prepare("SELECT id FROM berita_acara WHERE tiket_id=?");
        $ba_exists->execute([$id]);
        $ba_existing_id = $ba_exists->fetchColumn();

        if ($ba_existing_id) {
            $pdo->prepare("UPDATE berita_acara SET nomor_ba=?,tanggal_ba=?,jenis_tindak=?,
                uraian_masalah=?,kesimpulan=?,tindak_lanjut=?,nilai_estimasi=?,
                diketahui_nama=?,diketahui_jabatan=?,mengetahui_nama=?,mengetahui_jabatan=?,
                catatan_tambahan=?,updated_at=NOW() WHERE tiket_id=?")
                ->execute([...array_values($fields), $id]);
            setFlash('success','Berita Acara berhasil diperbarui.');
        } else {
            $pdo->prepare("INSERT INTO berita_acara (tiket_id,nomor_ba,tanggal_ba,jenis_tindak,
                uraian_masalah,kesimpulan,tindak_lanjut,nilai_estimasi,
                diketahui_nama,diketahui_jabatan,mengetahui_nama,mengetahui_jabatan,
                catatan_tambahan,dibuat_oleh,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([$id, ...array_values($fields), $_SESSION['user_id']]);
            $pdo->prepare("INSERT INTO tiket_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,?,?,?)")
                ->execute([$id,$_SESSION['user_id'],'tidak_bisa','tidak_bisa','Berita Acara dibuat: '.$nomor_ba]);
            setFlash('success','Berita Acara <strong>'.htmlspecialchars($nomor_ba).'</strong> berhasil dibuat.');
        }
        redirect(APP_URL.'/pages/detail_tiket.php?id='.$id.'#t-ba');
    }
}

// ── Refresh tiket & foto setelah POST ────────────────────────────────────────
$st->execute(array($id)); $t = $st->fetch();
$fotos_q = $pdo->prepare("SELECT f.*,u.nama as uploader FROM tiket_foto f LEFT JOIN users u ON u.id=f.user_id WHERE f.tiket_id=? ORDER BY f.created_at ASC");
$fotos_q->execute(array($id)); $fotos = $fotos_q->fetchAll();

// ── Berita Acara ─────────────────────────────────────────────────────────────
$ba = null;
if ($t['status'] === 'tidak_bisa') {
    $ba_q = $pdo->prepare("SELECT ba.*,u.nama as dibuat_nama,u.divisi as dibuat_divisi
        FROM berita_acara ba LEFT JOIN users u ON u.id=ba.dibuat_oleh WHERE ba.tiket_id=? LIMIT 1");
    $ba_q->execute([$id]);
    $ba = $ba_q->fetch();
}

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
        'caption' => $f['nama_file'] . ' — ' . (isset($f['uploader']) ? $f['uploader'] : '-') . ' — ' . date('d/m/Y H:i', strtotime($f['created_at'])),
    );
}

$page_title  = 'Detail Tiket ' . $t['nomor'];
$active_menu = hasRole('user') ? 'tiket_saya' : 'semua_tiket';
$back_url    = hasRole('user') ? APP_URL.'/pages/tiket_saya.php' : APP_URL.'/pages/semua_tiket.php';

include '../includes/header.php';
?>
<style>
/* ─── Foto & Upload ─────────────────────────────────────── */
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
.foto-section{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:18px;}
.foto-section-title{font-size:12px;font-weight:700;color:#334155;display:flex;align-items:center;gap:6px;margin-bottom:4px;}
.foto-count-badge{background:#dbeafe;color:#1d4ed8;font-size:10px;font-weight:700;padding:1px 8px;border-radius:20px;}

/* ─── Lightbox ──────────────────────────────────────────── */
#lightbox{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.9);align-items:center;justify-content:center;flex-direction:column;}
#lightbox.open{display:flex;}
#lightbox img{max-width:90vw;max-height:80vh;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.5);}
#lightbox-close{position:absolute;top:14px;right:18px;color:#fff;font-size:30px;cursor:pointer;background:none;border:none;line-height:1;}
#lightbox-caption{color:#ccc;font-size:11px;margin-top:10px;text-align:center;}
#lightbox-nav{display:flex;gap:14px;margin-top:12px;}
#lightbox-nav button{background:rgba(255,255,255,.15);color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:13px;}
#lightbox-nav button:hover{background:rgba(255,255,255,.28);}

/* ─── Tab Berita Acara ──────────────────────────────────── */
.tab-btn-ba-wajib {
    position:relative;
    animation: pulse-tab 2s infinite;
}
@keyframes pulse-tab {
    0%,100%{box-shadow:none;}
    50%{box-shadow:0 0 0 3px rgba(220,38,38,.3);}
}
.ba-wajib-banner {
    background:linear-gradient(135deg,#dc2626,#b91c1c);
    border-radius:8px;padding:12px 15px;margin-bottom:16px;
    display:flex;align-items:center;gap:12px;
}
.ba-wajib-banner .ico {font-size:22px;flex-shrink:0;}
.ba-wajib-banner .txt {flex:1;}
.ba-wajib-banner .txt strong {font-size:12.5px;color:#fff;display:block;margin-bottom:2px;}
.ba-wajib-banner .txt span {font-size:11px;color:rgba(255,255,255,.8);}

.jenis-card {
    display:flex;align-items:center;gap:8px;cursor:pointer;
    padding:9px 13px;border:2px solid #e2e8f0;border-radius:8px;
    background:#fff;font-size:12px;font-weight:500;color:#374151;
    transition:all .15s;flex:1;min-width:140px;
}
.jenis-card:hover{border-color:#94a3b8;background:#f8fafc;}
.jenis-card input{display:none;}
.jenis-card.selected-pembelian_baru      {border-color:#1d4ed8;background:#dbeafe;color:#1e40af;font-weight:700;}
.jenis-card.selected-perbaikan_eksternal {border-color:#d97706;background:#fef3c7;color:#92400e;font-weight:700;}
.jenis-card.selected-penghapusan_aset    {border-color:#dc2626;background:#fee2e2;color:#991b1b;font-weight:700;}
.jenis-card.selected-penggantian_suku_cadang{border-color:#059669;background:#d1fae5;color:#065f46;font-weight:700;}
.jenis-card.selected-lainnya             {border-color:#7c3aed;background:#ede9fe;color:#5b21b6;font-weight:700;}

.ba-summary-card {
    background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;
    overflow:hidden;margin-bottom:14px;
}
.ba-summary-header {
    background:linear-gradient(135deg,#0f172a,#1e293b);
    padding:11px 15px;display:flex;align-items:center;justify-content:space-between;
}
.ba-summary-header .no {font-size:12px;font-weight:700;color:#00e5b0;font-family:monospace;}
.ba-summary-header .tgl {font-size:11px;color:rgba(255,255,255,.5);}
.ba-summary-body {padding:13px 15px;}
.ba-jenis-badge {
    display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;
    padding:5px 13px;border-radius:20px;margin-bottom:10px;
}
.ba-field {margin-bottom:8px;}
.ba-field-label {font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;}
.ba-field-value {font-size:12px;color:#334155;line-height:1.7;}
.ba-estimasi {
    background:#fffbeb;border:1px solid #fde68a;border-radius:6px;
    padding:9px 13px;margin-top:10px;display:flex;align-items:center;gap:10px;
}
.ba-estimasi .lbl {font-size:10px;color:#92400e;font-weight:700;}
.ba-estimasi .val {font-size:14px;font-weight:700;color:#d97706;}
.ttd-row {
    display:flex;gap:10px;margin-top:14px;padding-top:12px;
    border-top:1px solid #f1f5f9;
}
.ttd-box {
    flex:1;text-align:center;background:#f8fafc;border:1px dashed #cbd5e1;
    border-radius:6px;padding:10px 8px;
}
.ttd-box .role {font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:28px;}
.ttd-box .line {border-top:1px solid #334155;margin:0 10px 4px;}
.ttd-box .nama {font-size:11px;font-weight:700;color:#0f172a;}
.ttd-box .jabatan {font-size:10px;color:#64748b;}
</style>

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

  <div class="panel" style="border-left:5px solid <?= isset($border_color[$t['status']]) ? $border_color[$t['status']] : 'var(--primary)' ?>;">
    <div class="panel-bd">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;">
        <div style="flex:1;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:7px;flex-wrap:wrap;">
            <span style="font-size:13px;font-weight:700;color:var(--primary);"><?= clean($t['nomor']) ?></span>
            <?= badgeStatus($t['status']) ?>
            <?= badgePrioritas($t['prioritas']) ?>
            <?php if (count($fotos)): ?>
            <span style="background:#dbeafe;color:#1d4ed8;font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;">
              <i class="fa fa-images"></i> <?= count($fotos) ?> foto
            </span>
            <?php endif; ?>
            <?php if ($t['status']==='tidak_bisa' && $ba): ?>
            <span style="background:#d1fae5;color:#065f46;font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;">
              <i class="fa fa-file-alt"></i> BA: <?= clean($ba['nomor_ba']) ?>
            </span>
            <?php elseif ($t['status']==='tidak_bisa' && !$ba): ?>
            <span style="background:#fee2e2;color:#dc2626;font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;">
              <i class="fa fa-exclamation-triangle"></i> Berita Acara Belum Dibuat
            </span>
            <?php endif; ?>
          </div>
          <h3 style="font-size:16px;color:#333;font-weight:700;margin-bottom:6px;"><?= clean($t['judul']) ?></h3>
          <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:#aaa;">
            <span><i class="fa fa-tag"></i> <?= clean(isset($t['kat_nama']) ? $t['kat_nama'] : '-') ?></span>
            <span><i class="fa fa-map-marker-alt"></i> <?= clean(isset($t['lokasi']) ? $t['lokasi'] : '-') ?></span>
            <span><i class="fa fa-clock"></i> <?= formatTanggal($t['waktu_submit'],true) ?></span>
          </div>
        </div>
        <div style="display:flex;gap:7px;flex-shrink:0;flex-wrap:wrap;">
          <a href="<?= $back_url ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Kembali</a>
          <?php if ($t['status']==='tidak_bisa' && $ba): ?>
          <a href="<?= APP_URL ?>/pages/cetak_berita_acara.php?tiket_id=<?= $id ?>" target="_blank"
             class="btn btn-success btn-sm"><i class="fa fa-file-pdf"></i> Cetak BA</a>
          <?php endif; ?>
          <?php if (!$is_final && hasRole(array('admin','teknisi')) && $t['status']==='menunggu'): ?>
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
    <div>
      <div class="panel">
        <div class="panel-hd" style="padding:0 15px;">
          <div class="tabs" style="margin:0;">
            <button class="tab-btn active" onclick="switchTab(this,'t-info')"><i class="fa fa-info-circle"></i> Detail</button>
            <button class="tab-btn" onclick="switchTab(this,'t-diskusi')"><i class="fa fa-comments"></i> Diskusi (<?= count($komentar) ?>)</button>
            <?php if (hasRole(array('admin','teknisi')) && !$is_final): ?>
            <button class="tab-btn" onclick="switchTab(this,'t-aksi')" id="btn-tab-aksi"><i class="fa fa-tools"></i> Tindakan IT</button>
            <?php elseif ($is_final && count($fotos)): ?>
            <button class="tab-btn" onclick="switchTab(this,'t-foto-view')"><i class="fa fa-images"></i> Foto Bukti (<?= count($fotos) ?>)</button>
            <?php endif; ?>
            <?php if ($t['status']==='tidak_bisa' && hasRole(array('admin','teknisi'))): ?>
            <button class="tab-btn <?= !$ba ? 'tab-btn-ba-wajib' : '' ?>"
                    onclick="switchTab(this,'t-ba')" id="btn-tab-ba"
                    style="<?= !$ba ? 'color:#dc2626;font-weight:800;border-bottom-color:#dc2626;' : 'color:#059669;font-weight:700;' ?>">
              <i class="fa fa-file-contract"></i> Berita Acara
              <?php if (!$ba): ?>
              <span style="background:#dc2626;color:#fff;font-size:9px;font-weight:700;
                    padding:1px 6px;border-radius:8px;margin-left:3px;vertical-align:middle;">WAJIB</span>
              <?php else: ?>
              <span style="background:#d1fae5;color:#059669;font-size:9px;font-weight:700;
                    padding:1px 6px;border-radius:8px;margin-left:3px;vertical-align:middle;">
                <i class="fa fa-check"></i> Selesai</span>
              <?php endif; ?>
            </button>
            <?php endif; ?>
          </div>
        </div>
        <div class="panel-bd">

          <!-- TAB DETAIL -->
          <div id="t-info" class="tab-c active">
            <div class="form-row" style="margin-bottom:14px;">
              <div>
                <div style="font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;margin-bottom:5px;">Pemohon</div>
                <div class="d-flex ai-c gap10">
                  <div class="av av-md"><?= getInitials($t['req_nama']) ?></div>
                  <div>
                    <div style="font-size:13px;font-weight:600;"><?= clean($t['req_nama']) ?></div>
                    <div style="font-size:11px;color:#aaa;"><?= clean(isset($t['req_divisi']) ? $t['req_divisi'] : '') ?></div>
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
                    <div style="font-size:11px;color:#aaa;"><?= clean(isset($t['tek_email']) ? $t['tek_email'] : '') ?></div>
                  </div>
                </div>
                <?php else: ?>
                <span style="font-size:12px;color:#aaa;font-style:italic;">Belum ditugaskan</span>
                <?php if (hasRole(array('admin','teknisi')) && !$is_final): ?>
                <br><button class="btn btn-default btn-sm" onclick="document.getElementById('btn-tab-aksi').click()" style="margin-top:6px;"><i class="fa fa-user-plus"></i> Assign Teknisi</button>
                <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
            <hr class="divider">
            <div style="font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;margin-bottom:6px;">Deskripsi Masalah</div>
            <p style="font-size:13px;color:#444;line-height:1.8;white-space:pre-line;"><?= clean($t['deskripsi']) ?></p>
            <?php if ($t['catatan_penolakan']): ?>
            <hr class="divider">
            <div style="background:#fff8f8;border:1px solid #fca5a5;border-radius:4px;padding:10px 12px;">
              <div style="font-size:11px;font-weight:700;color:#991b1b;margin-bottom:4px;"><i class="fa fa-info-circle"></i> Keterangan IT</div>
              <p style="font-size:12px;color:#555;line-height:1.7;"><?= clean($t['catatan_penolakan']) ?></p>
            </div>
            <?php endif; ?>
            <?php if ($t['status']==='tidak_bisa' && $ba): ?>
            <hr class="divider">
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px 13px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
              <div>
                <div style="font-size:11px;font-weight:700;color:#065f46;margin-bottom:2px;"><i class="fa fa-file-contract"></i> Berita Acara: <?= clean($ba['nomor_ba']) ?></div>
                <div style="font-size:11px;color:#16a34a;"><?= date('d M Y',strtotime($ba['tanggal_ba'])) ?> &bull; <?= clean($ba['dibuat_nama']??'-') ?></div>
              </div>
              <a href="<?= APP_URL ?>/pages/cetak_berita_acara.php?tiket_id=<?= $id ?>" target="_blank"
                 class="btn btn-success btn-sm"><i class="fa fa-file-pdf"></i> Cetak / PDF</a>
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
                <div class="foto-uploader"><?= clean(isset($foto['uploader']) ? $foto['uploader'] : '-') ?><br><?= date('d/m H:i',strtotime($foto['created_at'])) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- TAB DISKUSI -->
          <div id="t-diskusi" class="tab-c">
            <?php if (empty($komentar)): ?>
            <p style="text-align:center;color:#bbb;padding:20px 0;font-size:12px;"><i class="fa fa-comments" style="font-size:20px;display:block;margin-bottom:7px;"></i>Belum ada komentar</p>
            <?php else: foreach ($komentar as $km): ?>
            <div style="display:flex;gap:9px;margin-bottom:13px;padding-bottom:13px;border-bottom:1px solid #f5f5f5;">
              <div class="av av-sm <?= ($km['role']==='teknisi'||$km['role']==='admin') ? 'av-blue' : '' ?>"><?= getInitials($km['nama']) ?></div>
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
            <form method="POST" style="border-top:1px solid #f0f0f0;padding-top:12px;margin-top:4px;" id="diskusi">
              <input type="hidden" name="action" value="komentar">
              <textarea name="isi" class="form-control" placeholder="Tulis pertanyaan atau update..." rows="3" required></textarea>
              <button type="submit" class="btn btn-primary btn-sm" style="margin-top:7px;"><i class="fa fa-paper-plane"></i> Kirim</button>
            </form>
          </div>

          <!-- TAB FOTO VIEW (tiket final) -->
          <?php if ($is_final && count($fotos)): ?>
          <div id="t-foto-view" class="tab-c">
            <p style="font-size:12px;color:#888;margin-bottom:10px;"><?= count($fotos) ?> foto bukti pengerjaan tersimpan.</p>
            <div class="foto-grid">
              <?php foreach ($fotos as $fi => $foto): ?>
              <div>
                <div class="foto-item" onclick="openLightbox(<?= $fi ?>)">
                  <img src="<?= APP_URL.'/'.$foto['path'] ?>" alt="<?= clean($foto['nama_file']) ?>" loading="lazy">
                </div>
                <div class="foto-uploader"><?= clean(isset($foto['uploader']) ? $foto['uploader'] : '-') ?><br><?= date('d/m H:i',strtotime($foto['created_at'])) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- TAB TINDAKAN IT -->
          <?php if (hasRole(array('admin','teknisi')) && !$is_final): ?>
          <div id="t-aksi" class="tab-c">
            <?php if (hasRole('admin')): ?>
            <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #f0f0f0;">
              <p style="font-size:12px;font-weight:700;color:#333;margin-bottom:4px;">Assign Teknisi</p>
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

            <div class="foto-section">
              <div class="foto-section-title">
                <i class="fa fa-camera text-primary"></i> Upload Foto Bukti Pengerjaan
                <?php if (count($fotos)): ?><span class="foto-count-badge"><?= count($fotos) ?> tersimpan</span><?php endif; ?>
              </div>
              <p style="font-size:11px;color:#94a3b8;margin-bottom:12px;">Format JPG / PNG / WebP &mdash; maks 5 MB per file</p>
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
                    <div class="foto-uploader"><?= clean(isset($foto['uploader']) ? $foto['uploader'] : '-') ?><br><?= date('d/m H:i',strtotime($foto['created_at'])) ?></div>
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
                  atau seret &amp; lepas ke sini
                </div>
                <div id="preview-grid"></div>
                <button type="submit" class="btn btn-info btn-sm" id="btn-upload-foto" style="display:none;margin-top:10px;">
                  <i class="fa fa-upload"></i> Upload Foto (<span id="foto-count">0</span>)
                </button>
              </form>
            </div>

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
                <textarea name="catatan_penolakan" id="note-input" class="form-control"
                  placeholder="Tuliskan hasil penanganan, alasan, atau keterangan tidak bisa ditangani..." rows="3"></textarea>
              </div>
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:6px;">
                  <i class="fa fa-images text-primary"></i> Lampirkan Foto Bukti
                  <span style="font-size:11px;color:#aaa;font-weight:400;">(opsional)</span>
                </label>
                <input type="file" name="foto_bukti[]" id="input-foto-status" accept="image/*" multiple style="display:none;" onchange="previewFotoStatus(this)">
                <div class="drop-zone" id="drop-zone-status" onclick="document.getElementById('input-foto-status').click()" style="padding:14px;">
                  <i class="fa fa-images" style="font-size:20px;"></i>
                  Klik atau seret foto bukti
                </div>
                <div id="preview-grid-status"></div>
              </div>
              <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Perubahan</button>
            </form>
          </div>
          <?php endif; ?>

          <!-- ══════════════════════════════════════════════ -->
          <!-- TAB BERITA ACARA                               -->
          <!-- ══════════════════════════════════════════════ -->
          <?php if ($t['status']==='tidak_bisa' && hasRole(array('admin','teknisi'))): ?>
          <div id="t-ba" class="tab-c">

            <?php if (!$ba): ?>
            <!-- BANNER WAJIB -->
            <div class="ba-wajib-banner">
              <div class="ico">&#9888;&#65039;</div>
              <div class="txt">
                <strong>Berita Acara Wajib Dibuat!</strong>
                <span>Tiket berstatus "Tidak Bisa Ditangani". Buat Berita Acara sebagai dokumen resmi
                  untuk tindak lanjut (pembelian baru, perbaikan vendor, penghapusan aset, dll).</span>
              </div>
            </div>
            <?php else: ?>
            <!-- SUMMARY BA YANG SUDAH ADA -->
            <?php
            $jenis_cfg = [
              'pembelian_baru'          => ['&#128722;','Pengajuan Pembelian Perangkat Baru',    '#dbeafe','#1e40af'],
              'perbaikan_eksternal'     => ['&#128295;','Perbaikan oleh Pihak Eksternal/Vendor',  '#fef3c7','#92400e'],
              'penghapusan_aset'        => ['&#128465;','Penghapusan Aset (Write-Off)',            '#fee2e2','#991b1b'],
              'penggantian_suku_cadang' => ['&#128297;','Penggantian Suku Cadang',                 '#d1fae5','#065f46'],
              'lainnya'                 => ['&#128203;','Tindak Lanjut Lainnya',                   '#ede9fe','#5b21b6'],
            ];
            [$j_ico,$j_lbl,$j_bg,$j_col] = $jenis_cfg[$ba['jenis_tindak']] ?? $jenis_cfg['lainnya'];
            ?>
            <div class="ba-summary-card">
              <div class="ba-summary-header">
                <span class="no"><i class="fa fa-file-contract" style="margin-right:5px;color:rgba(255,255,255,.4);"></i><?= clean($ba['nomor_ba']) ?></span>
                <div style="text-align:right;">
                  <div class="tgl"><?= date('d M Y',strtotime($ba['tanggal_ba'])) ?></div>
                  <div class="tgl">Dibuat: <?= clean($ba['dibuat_nama']??'-') ?></div>
                </div>
              </div>
              <div class="ba-summary-body">
                <div class="ba-jenis-badge" style="background:<?= $j_bg ?>;color:<?= $j_col ?>;">
                  <?= $j_ico ?> &nbsp;<?= $j_lbl ?>
                </div>
                <?php if ($ba['uraian_masalah']): ?>
                <div class="ba-field">
                  <div class="ba-field-label">Uraian Permasalahan</div>
                  <div class="ba-field-value"><?= nl2br(clean(mb_strimwidth($ba['uraian_masalah'],0,200,'...'))) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($ba['tindak_lanjut']): ?>
                <div class="ba-field">
                  <div class="ba-field-label">Tindak Lanjut</div>
                  <div class="ba-field-value"><?= nl2br(clean($ba['tindak_lanjut'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($ba['nilai_estimasi']): ?>
                <div class="ba-estimasi">
                  <div>
                    <div class="lbl">ESTIMASI BIAYA</div>
                    <div class="val">Rp <?= number_format($ba['nilai_estimasi'],0,',','.') ?></div>
                  </div>
                </div>
                <?php endif; ?>
                <?php if ($ba['diketahui_nama'] || $ba['mengetahui_nama']): ?>
                <div class="ttd-row">
                  <div class="ttd-box">
                    <div class="role">Dibuat Oleh</div>
                    <div class="line"></div>
                    <div class="nama"><?= clean($ba['dibuat_nama']??'—') ?></div>
                    <div class="jabatan"><?= clean($ba['dibuat_divisi']??'Staff IT') ?></div>
                  </div>
                  <?php if ($ba['diketahui_nama']): ?>
                  <div class="ttd-box">
                    <div class="role">Diketahui</div>
                    <div class="line"></div>
                    <div class="nama"><?= clean($ba['diketahui_nama']) ?></div>
                    <div class="jabatan"><?= clean($ba['diketahui_jabatan']??'—') ?></div>
                  </div>
                  <?php endif; ?>
                  <?php if ($ba['mengetahui_nama']): ?>
                  <div class="ttd-box">
                    <div class="role">Menyetujui</div>
                    <div class="line"></div>
                    <div class="nama"><?= clean($ba['mengetahui_nama']) ?></div>
                    <div class="jabatan"><?= clean($ba['mengetahui_jabatan']??'—') ?></div>
                  </div>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
              <a href="<?= APP_URL ?>/pages/cetak_berita_acara.php?tiket_id=<?= $id ?>" target="_blank"
                 class="btn btn-success"><i class="fa fa-file-pdf"></i> Cetak / Simpan PDF</a>
              <button onclick="document.getElementById('form-ba').style.display='block';
                               document.getElementById('ba-summary-actions').style.display='none';"
                      class="btn btn-warning" id="ba-summary-actions-btn">
                <i class="fa fa-pen"></i> Edit Berita Acara
              </button>
            </div>
            <?php endif; ?>

            <!-- FORM INPUT / EDIT BA -->
            <div id="form-ba" <?= $ba ? 'style="display:none;"' : '' ?>>
              <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:12px;padding-bottom:8px;border-bottom:2px solid #1d4ed8;">
                <i class="fa fa-file-contract" style="color:#1d4ed8;"></i>
                <?= $ba ? 'Edit Berita Acara' : 'Isi Form Berita Acara' ?>
              </div>
              <form method="POST">
                <input type="hidden" name="action" value="simpan_ba">

                <!-- Nomor & Tanggal -->
                <div class="form-row" style="margin-bottom:12px;">
                  <div>
                    <label style="font-size:11px;font-weight:700;color:#374151;display:block;margin-bottom:4px;">
                      No. Berita Acara
                      <span style="font-size:10px;color:#94a3b8;font-weight:400;">(kosong = otomatis)</span>
                    </label>
                    <input type="text" name="nomor_ba" class="form-control"
                      value="<?= clean($ba['nomor_ba']??'') ?>"
                      placeholder="BA-IT-<?= date('Y') ?>-0001 (otomatis jika kosong)"
                      style="font-family:monospace;">
                  </div>
                  <div>
                    <label style="font-size:11px;font-weight:700;color:#374151;display:block;margin-bottom:4px;">
                      Tanggal BA <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="date" name="tanggal_ba" class="form-control" required
                      value="<?= clean($ba['tanggal_ba']??date('Y-m-d')) ?>">
                  </div>
                </div>

                <!-- Jenis Tindak Lanjut -->
                <div class="form-group" style="margin-bottom:14px;">
                  <label style="font-size:11px;font-weight:700;color:#374151;display:block;margin-bottom:7px;">
                    Jenis Tindak Lanjut <span style="color:#ef4444;">*</span>
                  </label>
                  <div style="display:flex;flex-wrap:wrap;gap:8px;" id="jenis-wrap">
                    <?php
                    $jenis_opts = [
                      'pembelian_baru'          => ['&#128722;','Pembelian Baru'],
                      'perbaikan_eksternal'     => ['&#128295;','Perbaikan Vendor'],
                      'penghapusan_aset'        => ['&#128465;','Hapus Aset'],
                      'penggantian_suku_cadang' => ['&#128297;','Ganti Suku Cadang'],
                      'lainnya'                 => ['&#128203;','Lainnya'],
                    ];
                    $cur_jenis = $ba['jenis_tindak'] ?? 'lainnya';
                    foreach ($jenis_opts as $val => [$ico, $lbl]):
                    ?>
                    <label class="jenis-card <?= $cur_jenis===$val ? 'selected-'.$val : '' ?>"
                           data-val="<?= $val ?>" id="jc-<?= $val ?>">
                      <input type="radio" name="jenis_tindak" value="<?= $val ?>"
                             <?= $cur_jenis===$val ? 'checked' : '' ?> required>
                      <span style="font-size:16px;"><?= $ico ?></span>
                      <span><?= $lbl ?></span>
                    </label>
                    <?php endforeach; ?>
                  </div>
                </div>

                <!-- Uraian Masalah -->
                <div class="form-group" style="margin-bottom:12px;">
                  <label style="font-size:11px;font-weight:700;color:#374151;display:block;margin-bottom:4px;">
                    Uraian Permasalahan <span style="color:#ef4444;">*</span>
                  </label>
                  <textarea name="uraian_masalah" class="form-control" rows="3" required
                    placeholder="Jelaskan permasalahan secara teknis dan kronologisnya..."><?= clean($ba['uraian_masalah']??$t['deskripsi']??'') ?></textarea>
                </div>

                <!-- Kesimpulan -->
                <div class="form-group" style="margin-bottom:12px;">
                  <label style="font-size:11px;font-weight:700;color:#374151;display:block;margin-bottom:4px;">
                    Kesimpulan &amp; Analisa Teknis <span style="color:#ef4444;">*</span>
                  </label>
                  <textarea name="kesimpulan" class="form-control" rows="3" required
                    placeholder="Jelaskan mengapa tidak bisa ditangani secara internal (kerusakan, keterbatasan alat, dll)..."><?= clean($ba['kesimpulan']??$t['catatan_penolakan']??'') ?></textarea>
                </div>

                <!-- Tindak Lanjut -->
                <div class="form-group" style="margin-bottom:12px;">
                  <label style="font-size:11px;font-weight:700;color:#374151;display:block;margin-bottom:4px;">
                    Detail Rekomendasi Tindak Lanjut <span style="color:#ef4444;">*</span>
                  </label>
                  <textarea name="tindak_lanjut" class="form-control" rows="3" required
                    id="ta-tindak"
                    placeholder="Contoh: Direkomendasikan pembelian laptop baru karena motherboard mengalami kerusakan permanen dan tidak ekonomis untuk diperbaiki..."><?= clean($ba['tindak_lanjut']??'') ?></textarea>
                </div>

                <!-- Estimasi -->
                <div class="form-group" style="margin-bottom:14px;">
                  <label style="font-size:11px;font-weight:700;color:#374151;display:block;margin-bottom:4px;">
                    Estimasi Biaya <span style="color:#94a3b8;font-weight:400;">(opsional, Rupiah)</span>
                  </label>
                  <div style="position:relative;max-width:260px;">
                    <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;font-weight:700;">Rp</span>
                    <input type="number" name="nilai_estimasi" class="form-control" min="0"
                      value="<?= clean($ba['nilai_estimasi']??'') ?>"
                      placeholder="Contoh: 8500000" style="padding-left:35px;">
                  </div>
                </div>

                <!-- Tanda Tangan -->
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:14px;">
                  <div style="font-size:11px;font-weight:700;color:#475569;margin-bottom:12px;">
                    <i class="fa fa-signature" style="color:#26B99A;"></i> Data Tanda Tangan
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                      <label style="font-size:10.5px;color:#64748b;font-weight:600;display:block;margin-bottom:3px;">Diketahui (Nama)</label>
                      <input type="text" name="diketahui_nama" class="form-control"
                        value="<?= clean($ba['diketahui_nama']??'') ?>" placeholder="Kepala Divisi IT">
                    </div>
                    <div>
                      <label style="font-size:10.5px;color:#64748b;font-weight:600;display:block;margin-bottom:3px;">Jabatan</label>
                      <input type="text" name="diketahui_jabatan" class="form-control"
                        value="<?= clean($ba['diketahui_jabatan']??'') ?>" placeholder="Kepala IT">
                    </div>
                    <div>
                      <label style="font-size:10.5px;color:#64748b;font-weight:600;display:block;margin-bottom:3px;">Menyetujui (Nama)</label>
                      <input type="text" name="mengetahui_nama" class="form-control"
                        value="<?= clean($ba['mengetahui_nama']??'') ?>" placeholder="Manajer / Pimpinan">
                    </div>
                    <div>
                      <label style="font-size:10.5px;color:#64748b;font-weight:600;display:block;margin-bottom:3px;">Jabatan</label>
                      <input type="text" name="mengetahui_jabatan" class="form-control"
                        value="<?= clean($ba['mengetahui_jabatan']??'') ?>" placeholder="Manajer Operasional">
                    </div>
                  </div>
                </div>

                <!-- Catatan -->
                <div class="form-group" style="margin-bottom:16px;">
                  <label style="font-size:11px;font-weight:700;color:#374151;display:block;margin-bottom:4px;">
                    Catatan Tambahan <span style="color:#94a3b8;font-weight:400;">(opsional)</span>
                  </label>
                  <textarea name="catatan_tambahan" class="form-control" rows="2"
                    placeholder="Informasi lain yang perlu dicantumkan dalam berita acara..."><?= clean($ba['catatan_tambahan']??'') ?></textarea>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                  <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> <?= $ba ? 'Perbarui Berita Acara' : 'Buat &amp; Simpan Berita Acara' ?>
                  </button>
                  <?php if ($ba): ?>
                  <button type="button" class="btn btn-default"
                    onclick="document.getElementById('form-ba').style.display='none';">Batal</button>
                  <a href="<?= APP_URL ?>/pages/cetak_berita_acara.php?tiket_id=<?= $id ?>" target="_blank"
                     class="btn btn-success" style="margin-left:auto;">
                    <i class="fa fa-file-pdf"></i> Cetak / PDF
                  </a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
          </div><!-- /t-ba -->
          <?php endif; ?>

        </div>
      </div>
    </div>

    <!-- KOLOM KANAN -->
    <div>
      <?php if ($t['sla_jam']): ?>
      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-stopwatch text-primary"></i> &nbsp;SLA &amp; Waktu</h5></div>
        <div class="panel-bd">
          <?php
          $info_sla = [
            ['Waktu Submit',  formatTanggal($t['waktu_submit'],true)],
            ['Waktu Respon',  $t['waktu_diproses'] ? formatTanggal($t['waktu_diproses'],true) : '—'],
            ['Waktu Selesai', $t['waktu_selesai']  ? formatTanggal($t['waktu_selesai'],true)  : '—'],
            ['Durasi Respon', formatDurasi($dur_respon).' / target '.$t['sla_respon_jam'].'j'],
            ['Durasi Total',  formatDurasi($dur_selesai).' / target '.$t['sla_jam'].'j'],
          ];
          foreach ($info_sla as [$l,$v]): ?>
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
          <div class="progress"><div class="progress-fill <?= $sla_selesai['status']==='aman'?'pg-green':($sla_selesai['status']==='warning'?'pg-orange':'pg-red') ?>" style="width:<?= min($sla_selesai['persen'],100) ?>%"></div></div>
          <div style="font-size:10px;color:#aaa;margin-top:3px;"><?= $sla_selesai['persen'] ?>% dari target <?= $t['sla_jam'] ?> jam</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-history text-primary"></i> &nbsp;Riwayat Aktivitas</h5></div>
        <div class="panel-bd">
          <ul class="timeline">
            <?php foreach ($logs as $log):
              $dc = isset($dot_class[$log['status_ke']]) ? $dot_class[$log['status_ke']] : 'd-default';
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
                <span class="tl-time"><?= formatTanggal($log['created_at'],true) ?></span>
                <div class="tl-by">oleh <?= clean(isset($log['nama']) ? $log['nama'] : '-') ?></div>
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

<!-- LIGHTBOX -->
<div id="lightbox" onclick="if(event.target===this)closeLightbox()">
  <button id="lightbox-close" onclick="closeLightbox()" title="Tutup">&times;</button>
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
function openLightbox(idx) {
  if (!fotoData.length) return;
  lbIndex = idx; renderLightbox();
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
}
function renderLightbox() {
  var f = fotoData[lbIndex];
  document.getElementById('lightbox-img').src = f.src;
  document.getElementById('lightbox-caption').textContent = (lbIndex+1)+' / '+fotoData.length+'  —  '+f.caption;
  document.getElementById('lightbox-nav').style.display = fotoData.length > 1 ? 'flex' : 'none';
}
function lightboxNav(dir) {
  lbIndex = (lbIndex + dir + fotoData.length) % fotoData.length;
  renderLightbox();
}
document.addEventListener('keydown', function(e) {
  var lb = document.getElementById('lightbox');
  if (!lb.classList.contains('open')) return;
  if (e.key==='Escape')     closeLightbox();
  if (e.key==='ArrowLeft')  lightboxNav(-1);
  if (e.key==='ArrowRight') lightboxNav(1);
});

/* ── Jenis Tindak Lanjut Cards ─────────────────────────── */
var jenisClass = {
  pembelian_baru:'selected-pembelian_baru', perbaikan_eksternal:'selected-perbaikan_eksternal',
  penghapusan_aset:'selected-penghapusan_aset', penggantian_suku_cadang:'selected-penggantian_suku_cadang',
  lainnya:'selected-lainnya'
};
var taPlaceholder = {
  pembelian_baru:'Contoh: Direkomendasikan pembelian laptop Dell Latitude 5530, karena motherboard rusak permanen dan biaya perbaikan melebihi 70% harga unit baru.',
  perbaikan_eksternal:'Contoh: Perlu dikirim ke service center resmi Epson karena kerusakan pada print head yang tidak dapat diperbaiki sendiri.',
  penghapusan_aset:'Contoh: Aset sudah melewati umur ekonomis 5 tahun, kondisi rusak total. Direkomendasikan untuk dihapus dari daftar aset dan dimusnahkan/dilelang.',
  penggantian_suku_cadang:'Contoh: Baterai laptop sudah tidak bisa menyimpan daya, perlu penggantian baterai original tipe XYZ.',
  lainnya:'Tuliskan detail rekomendasi tindak lanjut...'
};
document.querySelectorAll('.jenis-card').forEach(function(card) {
  card.addEventListener('click', function() {
    var val = this.getAttribute('data-val');
    document.querySelectorAll('.jenis-card').forEach(function(c) {
      c.className = 'jenis-card';
    });
    this.className = 'jenis-card ' + (jenisClass[val]||'');
    this.querySelector('input').checked = true;
    var ta = document.getElementById('ta-tindak');
    if (ta && !ta.value) ta.placeholder = taPlaceholder[val]||'';
  });
});

/* ── Upload Foto ────────────────────────────────────────── */
var selectedFiles = [], selectedFilesStatus = [];

function previewFoto(input) { selectedFiles = Array.from(input.files); renderPreview(); }
function renderPreview() {
  var grid=document.getElementById('preview-grid'), btn=document.getElementById('btn-upload-foto'), cnt=document.getElementById('foto-count');
  if (!grid) return; grid.innerHTML='';
  if (btn) btn.style.display = selectedFiles.length ? 'inline-flex' : 'none';
  if (cnt) cnt.textContent = selectedFiles.length;
  selectedFiles.forEach(function(f,i) {
    var wrap=document.createElement('div'); wrap.className='prev-thumb';
    var img=document.createElement('img'), del=document.createElement('button');
    del.className='rm-prev'; del.type='button'; del.innerHTML='&times;';
    del.onclick=function(){ selectedFiles.splice(i,1); syncInputFiles(); renderPreview(); };
    var r=new FileReader(); r.onload=function(e){img.src=e.target.result;}; r.readAsDataURL(f);
    wrap.appendChild(img); wrap.appendChild(del); grid.appendChild(wrap);
  });
}
function syncInputFiles() {
  var dt=new DataTransfer();
  selectedFiles.forEach(function(f){dt.items.add(f);});
  document.getElementById('input-foto').files=dt.files;
}
function previewFotoStatus(input) { selectedFilesStatus=Array.from(input.files); renderPreviewStatus(); }
function renderPreviewStatus() {
  var grid=document.getElementById('preview-grid-status'); if (!grid) return; grid.innerHTML='';
  selectedFilesStatus.forEach(function(f,i) {
    var wrap=document.createElement('div'); wrap.className='prev-thumb'; wrap.style.marginTop='8px';
    var img=document.createElement('img'), del=document.createElement('button');
    del.className='rm-prev'; del.type='button'; del.innerHTML='&times;';
    del.onclick=function(){ selectedFilesStatus.splice(i,1); syncInputFilesStatus(); renderPreviewStatus(); };
    var r=new FileReader(); r.onload=function(e){img.src=e.target.result;}; r.readAsDataURL(f);
    wrap.appendChild(img); wrap.appendChild(del); grid.appendChild(wrap);
  });
}
function syncInputFilesStatus() {
  var dt=new DataTransfer();
  selectedFilesStatus.forEach(function(f){dt.items.add(f);});
  var inp=document.getElementById('input-foto-status'); if (inp) inp.files=dt.files;
}
function setupDrop(zoneId, inputId, isStatus) {
  var zone=document.getElementById(zoneId); if (!zone) return;
  ['dragenter','dragover'].forEach(function(ev){zone.addEventListener(ev,function(e){e.preventDefault();zone.classList.add('drag-over');});});
  ['dragleave','drop'].forEach(function(ev){zone.addEventListener(ev,function(){zone.classList.remove('drag-over');});});
  zone.addEventListener('drop',function(e){
    e.preventDefault();
    var inp=document.getElementById(inputId); if (!inp) return;
    var dt2=new DataTransfer();
    Array.from(e.dataTransfer.files).forEach(function(f){dt2.items.add(f);});
    inp.files=dt2.files;
    if (isStatus){selectedFilesStatus=Array.from(inp.files);renderPreviewStatus();}
    else         {selectedFiles=Array.from(inp.files);renderPreview();}
  });
}
setupDrop('drop-zone','input-foto',false);
setupDrop('drop-zone-status','input-foto-status',true);

/* ── Note required on status change ────────────────────── */
document.querySelectorAll('[name=status_baru]').forEach(function(r){
  r.addEventListener('change',function(){
    var req=document.getElementById('req-note');
    if (this.value!=='selesai'){
      req.innerHTML='<span style="color:red;">*</span>';
      document.getElementById('note-input').required=true;
    } else {
      req.innerHTML='';
      document.getElementById('note-input').required=false;
    }
  });
});

/* ── Auto-open tab + reminder BA ───────────────────────── */
var hash = window.location.hash;
if (hash === '#t-ba') {
  var b = document.getElementById('btn-tab-ba'); if (b) b.click();
} else if (hash === '#t-aksi') {
  var b2 = document.getElementById('btn-tab-aksi'); if (b2) b2.click();
}

<?php if ($t['status']==='tidak_bisa' && !$ba && hasRole(array('admin','teknisi'))): ?>
// Tampilkan popup reminder jika BA belum dibuat
window.addEventListener('load', function() {
  setTimeout(function() {
    var toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:24px;right:20px;z-index:9999;'
      + 'background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;'
      + 'padding:14px 18px;border-radius:10px;font-size:13px;font-weight:700;'
      + 'box-shadow:0 8px 24px rgba(220,38,38,.4);max-width:300px;cursor:pointer;'
      + 'animation:slideUp .4s ease;';
    toast.innerHTML = '<div style="margin-bottom:4px;">&#9888;&#65039; Berita Acara Belum Dibuat!</div>'
      + '<div style="font-size:11px;font-weight:400;opacity:.85;">Klik untuk buka form Berita Acara.</div>';
    toast.onclick = function() {
      var btn = document.getElementById('btn-tab-ba');
      if (btn) btn.click();
      toast.remove();
    };
    document.body.appendChild(toast);
    setTimeout(function() { if (toast.parentNode) toast.remove(); }, 10000);
  }, 1000);
});
<?php endif; ?>
</script>
<?php include '../includes/footer.php'; ?>