<?php
// pages/profil.php
session_start();
require_once '../config.php';
requireLogin();

$page_title  = 'Profil Saya';
$active_menu = 'profil';

$st = $pdo->prepare("SELECT * FROM users WHERE id=?");
$st->execute([$_SESSION['user_id']]);
$user = $st->fetch();

// ── Ambil data SDM ────────────────────────────────────────────────────────────
$sdmSt = $pdo->prepare("SELECT * FROM sdm_karyawan WHERE user_id=? LIMIT 1");
$sdmSt->execute([$_SESSION['user_id']]);
$sdm = $sdmSt->fetch(PDO::FETCH_ASSOC) ?: [];

// ── OPTIONS ───────────────────────────────────────────────────────────────────
$opt_agama      = ['Islam','Kristen Protestan','Kristen Katolik','Hindu','Buddha','Konghucu'];
$opt_gol_darah  = ['A','B','AB','O','A+','A-','B+','B-','AB+','AB-','O+','O-'];
$opt_status_nik = ['Belum Menikah','Menikah','Cerai Hidup','Cerai Mati'];
$opt_pendidikan = ['SD','SMP','SMA/SMK','D1','D3','D4','S1','S2','S3','Profesi','Spesialis','Sub-Spesialis'];
$opt_jenis_kary = ['Medis','Non-Medis','Penunjang Medis'];
$opt_status_kep = ['Tetap','Kontrak','Honorer','Magang','PPPK','Outsourcing'];
$opt_bank       = ['BRI','BNI','BCA','Mandiri','BTN','CIMB Niaga','Danamon','Permata','Syariah Indonesia','Lainnya'];

$jabatan_list = [];
try { $jabatan_list = $pdo->query("SELECT id,nama,kode FROM jabatan WHERE status='aktif' ORDER BY level,urutan,nama")->fetchAll(); } catch (Exception $e) {}

$divs = [];
try { $divs = $pdo->query("SELECT nama,kode FROM bagian WHERE status='aktif' ORDER BY urutan,nama")->fetchAll(); } catch (Exception $e) {}

$atasan_list = [];
try {
    $atasan_list = $pdo->query("
        SELECT u.id, u.nama, u.divisi,
               COALESCE(s.gelar_depan,'') gelar_depan,
               COALESCE(s.gelar_belakang,'') gelar_belakang,
               COALESCE(s.nik_rs,'') nik_rs
        FROM users u
        LEFT JOIN sdm_karyawan s ON s.user_id = u.id
        WHERE u.status = 'aktif' AND u.id != " . (int)$_SESSION['user_id'] . "
        ORDER BY u.divisi, u.nama
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

function sdmVal(array $sdm, string $key, $default = ''): string {
    $v = $sdm[$key] ?? $default;
    return htmlspecialchars((string)($v ?? $default));
}
function sdmSel(array $sdm, string $key, string $val): string {
    return (isset($sdm[$key]) && (string)$sdm[$key] === $val) ? 'selected' : '';
}
function sdmDate(array $sdm, string $key): string {
    $v = $sdm[$key] ?? '';
    if ($v && $v !== '0000-00-00') return date('Y-m-d', strtotime($v));
    return '';
}

// ── Upload dir berkas ─────────────────────────────────────────────────────────
define('PRF_UPLOAD_DIR', dirname(__DIR__) . '/uploads/berkas_karyawan/');
if (!is_dir(PRF_UPLOAD_DIR)) @mkdir(PRF_UPLOAD_DIR, 0755, true);

// ── POST HANDLER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // ── Update profil dasar ───────────────────────────────────────────────────
    if ($act === 'profil') {
        $n = trim($_POST['nama']  ?? '');
        $e = trim($_POST['email'] ?? '');
        if ($n && $e) {
            $pdo->prepare("UPDATE users SET nama=?,email=? WHERE id=?")
                ->execute([$n, $e, $_SESSION['user_id']]);
            $_SESSION['user_nama'] = $n;
            setFlash('success', 'Profil berhasil diperbarui.');
        } else {
            setFlash('danger', 'Nama dan email wajib diisi.');
        }
    }

    // ── Ganti password ────────────────────────────────────────────────────────
    if ($act === 'password') {
        $old = $_POST['old'] ?? '';
        $new = $_POST['new'] ?? '';
        $cnf = $_POST['cnf'] ?? '';
        if (!password_verify($old, $user['password']))
            setFlash('danger', 'Password lama salah.');
        elseif (strlen($new) < 6)
            setFlash('danger', 'Password baru minimal 6 karakter.');
        elseif ($new !== $cnf)
            setFlash('danger', 'Konfirmasi password tidak cocok.');
        else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_BCRYPT), $_SESSION['user_id']]);
            setFlash('success', 'Password berhasil diubah.');
        }
    }

    // ── Simpan data SDM ───────────────────────────────────────────────────────
    if ($act === 'simpan_sdm') {
        $uid = (int)$_SESSION['user_id'];
        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $fields_text  = ['nik_ktp','nik_rs','gelar_depan','gelar_belakang','tempat_lahir','kewarganegaraan','suku','no_hp','no_hp_darurat','kontak_darurat','hubungan_darurat','kelurahan_ktp','kecamatan_ktp','kota_ktp','provinsi_ktp','kode_pos_ktp','kota_domisili','jurusan','universitas','divisi','unit_kerja','jenis_tenaga','spesialisasi','sub_spesialisasi','no_bpjs_kes','no_bpjs_tk','no_npwp','no_rekening','bank','atas_nama_rek','no_str','no_sip','no_sik','alasan_resign'];
            $fields_text2 = ['alamat_ktp','alamat_domisili','kompetensi','catatan'];
            $fields_enum  = ['jenis_kelamin','golongan_darah','agama','status_pernikahan','pendidikan_terakhir','jenis_karyawan','status_kepegawaian'];
            $fields_date  = ['tgl_lahir','tgl_masuk','tgl_kontrak_mulai','tgl_kontrak_selesai','tgl_pengangkatan','tgl_terbit_str','tgl_exp_str','tgl_terbit_sip','tgl_exp_sip','tgl_exp_sik','tgl_resign'];
            $fields_int   = ['jabatan_id','jumlah_anak','tahun_lulus','atasan_id'];

            $d = [];
            foreach ($fields_text  as $f) $d[$f] = trim($_POST[$f] ?? '') ?: null;
            foreach ($fields_text2 as $f) $d[$f] = trim($_POST[$f] ?? '') ?: null;
            foreach ($fields_enum  as $f) { $v = trim($_POST[$f] ?? ''); $d[$f] = $v !== '' ? $v : null; }
            foreach ($fields_date  as $f) { $v = trim($_POST[$f] ?? ''); $d[$f] = $v !== '' ? $v : null; }
            foreach ($fields_int   as $f) { $v = (int)($_POST[$f] ?? 0); $d[$f] = $v > 0 ? $v : null; }
            $d['updated_by'] = $uid;

            $ex = $pdo->prepare("SELECT id FROM sdm_karyawan WHERE user_id=?");
            $ex->execute([$uid]);
            if ($ex->fetchColumn()) {
                $sets = implode(',', array_map(fn($k) => "`$k`=?", array_keys($d)));
                $vals = array_values($d); $vals[] = $uid;
                $pdo->prepare("UPDATE sdm_karyawan SET $sets WHERE user_id=?")->execute($vals);
            } else {
                $d['user_id'] = $uid;
                $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($d)));
                $phs  = implode(',', array_fill(0, count($d), '?'));
                $pdo->prepare("INSERT INTO sdm_karyawan ($cols) VALUES ($phs)")->execute(array_values($d));
            }
            if (!empty($d['no_hp']))  { $pdo->prepare("UPDATE users SET no_hp=? WHERE id=?")->execute([$d['no_hp'],$uid]); $_SESSION['user_no_hp']=$d['no_hp']; }
            if (!empty($d['divisi'])) { $pdo->prepare("UPDATE users SET divisi=? WHERE id=?")->execute([$d['divisi'],$uid]); $_SESSION['user_divisi']=$d['divisi']; }
            setFlash('success', 'Data kepegawaian berhasil disimpan.');
        } catch (Exception $e) {
            setFlash('danger', 'Gagal menyimpan: ' . htmlspecialchars($e->getMessage()));
        }
    }

    // ── Upload berkas oleh user sendiri ───────────────────────────────────────
    if ($act === 'upload_berkas_profil') {
        $uid      = (int)$_SESSION['user_id'];
        $jenis_id = (int)($_POST['jenis_berkas_id'] ?? 0);
        $ket      = trim($_POST['keterangan']  ?? '');
        $tgl_dok  = trim($_POST['tgl_dokumen'] ?? '') ?: null;
        $tgl_exp  = trim($_POST['tgl_exp']     ?? '') ?: null;

        if (!$jenis_id) { setFlash('danger','Pilih jenis berkas.'); redirect(APP_URL.'/pages/profil.php#berkas'); }

        $jenis = $pdo->prepare("SELECT j.*,k.id AS kid FROM master_jenis_berkas j LEFT JOIN master_kategori_berkas k ON k.id=j.kategori_id WHERE j.id=? AND j.status='aktif'");
        $jenis->execute([$jenis_id]);
        $jenis = $jenis->fetch(PDO::FETCH_ASSOC);
        if (!$jenis) { setFlash('danger','Jenis berkas tidak valid.'); redirect(APP_URL.'/pages/profil.php#berkas'); }

        if (empty($_FILES['file_berkas']['name'])) { setFlash('danger','Pilih file terlebih dahulu.'); redirect(APP_URL.'/pages/profil.php#berkas'); }

        $file    = $_FILES['file_berkas'];
        $allowed = array_map('trim', explode(',', $jenis['format_file'] ?: 'pdf,jpg,jpeg,png'));
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) { setFlash('danger','Format tidak diizinkan. Gunakan: '.strtoupper(implode(', ',$allowed))); redirect(APP_URL.'/pages/profil.php#berkas'); }
        if ($file['size'] > 10*1024*1024) { setFlash('danger','File melebihi 10 MB.'); redirect(APP_URL.'/pages/profil.php#berkas'); }

        $up_dir = PRF_UPLOAD_DIR.$uid.'/';
        if (!is_dir($up_dir)) @mkdir($up_dir, 0755, true);
        $nama_file = uniqid('bk_',true).'.'.$ext;

        if (move_uploaded_file($file['tmp_name'], $up_dir.$nama_file)) {
            try {
                $old = $pdo->prepare("SELECT nama_file FROM berkas_karyawan WHERE user_id=? AND jenis_berkas_id=? LIMIT 1");
                $old->execute([$uid,$jenis_id]);
                if ($r=$old->fetch()) {
                    @unlink($up_dir.$r['nama_file']);
                    $pdo->prepare("DELETE FROM berkas_karyawan WHERE user_id=? AND jenis_berkas_id=?")->execute([$uid,$jenis_id]);
                }
                $pdo->prepare("INSERT INTO berkas_karyawan (user_id,jenis_berkas_id,kategori_id,nama_file,nama_asli,ukuran,mime_type,keterangan,tgl_dokumen,tgl_exp,status_verif,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,'pending',?)")
                    ->execute([$uid,$jenis_id,$jenis['kid'],$nama_file,$file['name'],$file['size'],$file['type']?:null,$ket?:null,$tgl_dok,$tgl_exp,$uid]);
                setFlash('success','Berkas <strong>'.htmlspecialchars($jenis['nama']).'</strong> berhasil diunggah. Menunggu verifikasi HRD.');
            } catch (Exception $e) {
                @unlink($up_dir.$nama_file);
                setFlash('danger','Gagal: '.htmlspecialchars($e->getMessage()));
            }
        } else {
            setFlash('danger','Gagal mengunggah file.');
        }
        redirect(APP_URL.'/pages/profil.php#berkas');
    }

    // ── Hapus berkas milik sendiri ─────────────────────────────────────────────
    if ($act === 'hapus_berkas_profil') {
        $bid = (int)($_POST['berkas_id'] ?? 0);
        $uid = (int)$_SESSION['user_id'];
        if ($bid) {
            $r = $pdo->prepare("SELECT * FROM berkas_karyawan WHERE id=? AND user_id=?");
            $r->execute([$bid,$uid]);
            $b = $r->fetch();
            if ($b) {
                @unlink(PRF_UPLOAD_DIR.$uid.'/'.$b['nama_file']);
                $pdo->prepare("DELETE FROM berkas_karyawan WHERE id=?")->execute([$bid]);
                setFlash('success','Berkas dihapus.');
            }
        }
        redirect(APP_URL.'/pages/profil.php#berkas');
    }

    redirect(APP_URL . '/pages/profil.php');
}

// ── Reload setelah redirect ───────────────────────────────────────────────────
$st->execute([$_SESSION['user_id']]);
$user = $st->fetch();
$sdmSt->execute([$_SESSION['user_id']]);
$sdm = $sdmSt->fetch(PDO::FETCH_ASSOC) ?: [];

// ── Statistik tiket ───────────────────────────────────────────────────────────
$my = $pdo->prepare("SELECT status, COUNT(*) n FROM tiket WHERE user_id=? GROUP BY status");
$my->execute([$_SESSION['user_id']]);
$ms = [];
foreach ($my->fetchAll() as $r) $ms[$r['status']] = $r['n'];

// ── Info atasan langsung ──────────────────────────────────────────────────────
$atasan_info = null;
if (!empty($sdm['atasan_id'])) {
    try {
        $aq = $pdo->prepare("SELECT u.nama, u.divisi, COALESCE(s.gelar_depan,'') gelar_depan, COALESCE(s.gelar_belakang,'') gelar_belakang FROM users u LEFT JOIN sdm_karyawan s ON s.user_id=u.id WHERE u.id=?");
        $aq->execute([$sdm['atasan_id']]);
        $atasan_info = $aq->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ── Kelengkapan SDM ───────────────────────────────────────────────────────────
$sdm_fields_check = ['nik_rs','no_hp','tgl_lahir','tgl_masuk','jenis_karyawan','status_kepegawaian','no_bpjs_kes'];
$sdm_filled = count(array_filter($sdm_fields_check, fn($f) => !empty($sdm[$f])));
$sdm_pct    = (int)round($sdm_filled / count($sdm_fields_check) * 100);

// ── Data master berkas & berkas milik user ────────────────────────────────────
$master_berkas_profil = [];
try {
    $mb = $pdo->query("
        SELECT j.id, j.nama, j.icon, j.keterangan, j.wajib, j.has_exp, j.has_tgl_terbit,
               j.format_file, j.urutan,
               k.id AS kategori_id, k.nama AS nama_kategori,
               k.icon AS kat_icon, k.warna AS kat_warna
        FROM master_jenis_berkas j
        LEFT JOIN master_kategori_berkas k ON k.id=j.kategori_id
        WHERE j.status='aktif' AND (k.status='aktif' OR k.id IS NULL)
        ORDER BY k.urutan, k.nama, j.urutan, j.nama
    ");
    $master_berkas_profil = $mb->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Grouping per kategori
$berkas_kat_profil = [];
foreach ($master_berkas_profil as $b) {
    $key = ($b['kategori_id']??0).'|'.($b['nama_kategori']??'Umum');
    if (!isset($berkas_kat_profil[$key])) {
        $berkas_kat_profil[$key] = [
            'id'    => $b['kategori_id'],
            'nama'  => $b['nama_kategori'] ?? 'Umum',
            'icon'  => $b['kat_icon']      ?? 'fa-folder',
            'warna' => $b['kat_warna']     ?? '#6366f1',
            'items' => [],
        ];
    }
    $berkas_kat_profil[$key]['items'][] = $b;
}
$total_req_profil = array_sum(array_column($master_berkas_profil,'wajib'));

// Berkas yang sudah diupload user ini
$berkas_uploaded = [];
try {
    $bu = $pdo->prepare("SELECT * FROM berkas_karyawan WHERE user_id=?");
    $bu->execute([$_SESSION['user_id']]);
    foreach ($bu->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $berkas_uploaded[$row['jenis_berkas_id']] = $row;
    }
} catch (Exception $e) {}

// Persentase berkas wajib sudah diupload
$berkas_wajib_done = count(array_filter(
    $master_berkas_profil,
    fn($j) => $j['wajib'] && isset($berkas_uploaded[$j['id']])
));
$pct_berkas_profil = $total_req_profil > 0
    ? min(100, (int)round($berkas_wajib_done / $total_req_profil * 100))
    : 0;

// Cek berkas akan expired < 30 hari
$berkas_exp_cnt = count(array_filter($berkas_uploaded, function($b){
    return $b['tgl_exp'] && strtotime($b['tgl_exp']) < strtotime('+30 days') && strtotime($b['tgl_exp']) >= time();
}));

// Role label
$rs_map = [
    'admin'         => ['Admin','fa-shield-alt','#5b21b6'],
    'teknisi'       => ['Teknisi IT','fa-wrench','#1d4ed8'],
    'teknisi_ipsrs' => ['Teknisi IPSRS','fa-screwdriver-wrench','#c2410c'],
    'hrd'           => ['HRD','fa-people-group','#9d174d'],
    'akreditasi'    => ['Tim Akreditasi','fa-medal','#854d0e'],
    'user'          => ['User','fa-user','#374151'],
];
[$rs_label, $rs_icon, $rs_color] = $rs_map[$user['role']] ?? ['User','fa-user','#374151'];

include '../includes/header.php';
?>

<style>
* { box-sizing: border-box; }
.prf-panel { background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; }
.prf-top-bar { display:flex; align-items:center; gap:16px; padding:18px 24px 0; border-bottom:1px solid #f0f2f7; flex-wrap:wrap; }
.prf-top-av { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,#00e5b0,#00c896); color:#0a0f14; font-size:18px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; border:2px solid #e5e7eb; }
.prf-top-info { flex:1; min-width:0; }
.prf-top-nama { font-size:15px; font-weight:800; color:#0f172a; line-height:1.3; }
.prf-top-meta { font-size:11.5px; color:#6b7280; margin-top:2px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.prf-top-meta span { display:inline-flex; align-items:center; gap:4px; }
.prf-top-badge { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; background:#f3f4f6; color:#374151; }
.prf-top-right { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
.prf-stat-row { display:flex; gap:0; border-top:1px solid #f0f2f7; }
.prf-stat-item { flex:1; padding:10px 14px; text-align:center; border-right:1px solid #f0f2f7; }
.prf-stat-item:last-child { border-right:none; }
.prf-stat-num { font-size:18px; font-weight:800; line-height:1; }
.prf-stat-lbl { font-size:10px; color:#9ca3af; font-weight:500; margin-top:2px; }
.prf-tabs { display:flex; border-bottom:2px solid #e5e7eb; background:#fafafa; overflow-x:auto; flex-shrink:0; }
.prf-tab { padding:11px 16px; font-size:12px; font-weight:600; color:#6b7280; border:none; background:none; cursor:pointer; white-space:nowrap; border-bottom:2px solid transparent; margin-bottom:-2px; transition:all .15s; display:flex; align-items:center; gap:6px; }
.prf-tab.active { color:#00c896; border-bottom-color:#00c896; }
.prf-tab:hover:not(.active) { color:#374151; background:#f3f4f6; }
.prf-tab-pane { display:none; padding:22px 24px; }
.prf-tab-pane.active { display:block; }
.prf-sec { font-size:10px; font-weight:700; color:#00c896; letter-spacing:1.2px; text-transform:uppercase; margin:18px 0 10px; padding-bottom:6px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:6px; }
.prf-sec:first-child { margin-top:0; }
.prf-g2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.prf-g3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
.prf-g4 { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
.prf-fg { display:flex; flex-direction:column; gap:4px; margin-bottom:10px; }
.prf-fg label { font-size:11px; font-weight:600; color:#374151; }
.prf-fg label .opt { font-size:9.5px; color:#9ca3af; font-weight:400; }
.prf-fg .req { color:#ef4444; }
.prf-fg input, .prf-fg select, .prf-fg textarea { border:1px solid #d1d5db; border-radius:8px; padding:0 11px; font-size:12.5px; font-family:inherit; background:#f9fafb; color:#111827; transition:border-color .15s,background .15s,box-shadow .15s; box-sizing:border-box; width:100%; }
.prf-fg input, .prf-fg select { height:36px; }
.prf-fg textarea { padding:8px 11px; resize:vertical; min-height:60px; }
.prf-fg input:focus, .prf-fg select:focus, .prf-fg textarea:focus { outline:none; border-color:#00c896; background:#fff; box-shadow:0 0 0 3px rgba(0,200,150,.1); }
.prf-fg input[readonly] { background:#f3f4f6; color:#9ca3af; cursor:not-allowed; }
.prf-info-box { background:linear-gradient(135deg,#f0fdf4,#ecfdf5); border:1px solid #bbf7d0; border-radius:10px; padding:12px 16px; margin-bottom:18px; font-size:12px; color:#065f46; display:flex; align-items:flex-start; gap:10px; }
.prf-save-row { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:16px 24px; border-top:1px solid #e5e7eb; background:#fafafa; }
.sdm-subtab { padding:8px 14px; font-size:11.5px; font-weight:600; color:#6b7280; border:none; background:none; cursor:pointer; white-space:nowrap; border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .15s; display:inline-flex; align-items:center; gap:5px; }
.sdm-subtab.active { color:#00c896; border-bottom-color:#00c896; background:#f0fdf4; border-radius:6px 6px 0 0; }
.sdm-subtab:hover:not(.active) { color:#374151; background:#f9fafb; }
.sdm-subpane { display:none; }
.sdm-subpane.active { display:block; }
.atasan-preview { display:flex; align-items:center; gap:10px; padding:10px 14px; border:1.5px solid #a7f3d0; border-radius:9px; background:#f0fdf9; margin-top:6px; font-size:12px; }
.atasan-av { width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,#00e5b0,#00c896); color:#0a0f14; font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

/* ── Tab Berkas ── */
.bk-kat-card { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; margin-bottom:10px; }
.bk-kat-hd { background:#f8fafc; padding:11px 16px; display:flex; align-items:center; gap:10px; cursor:pointer; user-select:none; transition:background .15s; }
.bk-kat-hd:hover { background:#f1f5f9; }
.bk-kat-arrow { font-size:10px; color:#94a3b8; transition:transform .2s; }
.bk-kat-body { display:none; }
.bk-kat-card.open .bk-kat-body { display:block; }
.bk-kat-card.open .bk-kat-arrow { transform:rotate(180deg); }
.bk-item-row { display:flex; align-items:center; gap:12px; padding:10px 16px 10px 24px; border-top:1px solid #f8fafc; transition:background .12s; }
.bk-item-row:hover { background:#fafbff; }
.bk-item-icon { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:11px; flex-shrink:0; }
.bk-chip { font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap; display:inline-flex; align-items:center; gap:4px; }
.bk-chip-ok      { background:#dcfce7; color:#16a34a; }
.bk-chip-pending  { background:#fef3c7; color:#d97706; }
.bk-chip-ditolak  { background:#fee2e2; color:#dc2626; }
.bk-chip-kosong   { background:#f1f5f9; color:#94a3b8; }
.bk-btn-up { font-size:11px; font-weight:600; padding:5px 11px; border-radius:8px; border:1.5px dashed #94a3b8; background:#f8fafc; color:#64748b; cursor:pointer; transition:all .15s; display:inline-flex; align-items:center; gap:5px; white-space:nowrap; }
.bk-btn-up:hover { border-color:#6366f1; color:#6366f1; background:#f5f3ff; }
.bk-req { font-size:9px; font-weight:700; padding:1px 5px; border-radius:4px; background:#fee2e2; color:#dc2626; }
.bk-exp-soon { font-size:9.5px; font-weight:700; padding:1px 6px; border-radius:4px; background:#fff7ed; color:#ea580c; }
.bk-expired  { font-size:9.5px; font-weight:700; padding:1px 6px; border-radius:4px; background:#fee2e2; color:#dc2626; }

/* Modal upload */
.prf-up-ov { display:none; position:fixed; inset:0; background:rgba(15,23,42,.65); z-index:99999; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(4px); }
.prf-up-ov.open { display:flex; }
.prf-up-box { background:#fff; border-radius:14px; box-shadow:0 24px 80px rgba(0,0,0,.3); width:100%; max-width:460px; overflow:hidden; animation:prfIn .2s ease; }
@keyframes prfIn { from{opacity:0;transform:translateY(14px) scale(.97);}to{opacity:1;transform:none;} }
.prf-up-hd { background:linear-gradient(135deg,#4f46e5,#6366f1); padding:14px 18px; display:flex; align-items:center; gap:10px; color:#fff; }
.prf-up-title { font-size:13px; font-weight:700; flex:1; }
.prf-up-close { width:28px; height:28px; border-radius:7px; border:none; background:rgba(255,255,255,.15); color:#fff; cursor:pointer; font-size:12px; display:flex; align-items:center; justify-content:center; transition:background .15s; }
.prf-up-close:hover { background:#dc2626; }
.prf-up-body { padding:18px; }
.prf-up-drop { border:2px dashed #c7d2fe; border-radius:12px; padding:22px; text-align:center; background:#f5f3ff; cursor:pointer; margin-bottom:14px; transition:all .15s; }
.prf-up-drop:hover, .prf-up-drop.drag { border-color:#6366f1; background:#ede9fe; }
.prf-up-drop i { font-size:24px; color:#6366f1; margin-bottom:8px; display:block; }
.prf-up-drop p { font-size:12.5px; color:#6b7280; margin:0; }
.prf-up-drop small { font-size:10.5px; color:#9ca3af; }
.prf-up-fg { margin-bottom:10px; }
.prf-up-fg label { font-size:11px; font-weight:600; color:#374151; display:block; margin-bottom:4px; }
.prf-up-fg input, .prf-up-fg textarea { width:100%; border:1px solid #d1d5db; border-radius:8px; padding:7px 11px; font-size:12.5px; font-family:inherit; background:#f9fafb; color:#111827; box-sizing:border-box; transition:border-color .15s; }
.prf-up-fg input:focus, .prf-up-fg textarea:focus { outline:none; border-color:#6366f1; background:#fff; box-shadow:0 0 0 3px rgba(99,102,241,.1); }
.prf-up-ft { padding:12px 18px; border-top:1px solid #e5e7eb; display:flex; gap:8px; justify-content:flex-end; background:#f8fafc; }

@media(max-width:900px) { .prf-g2,.prf-g3,.prf-g4 { grid-template-columns:1fr 1fr; } }
@media(max-width:560px) { .prf-g2,.prf-g3,.prf-g4 { grid-template-columns:1fr; } .prf-tab-pane { padding:16px; } .prf-top-right { display:none; } }
</style>

<div class="page-header">
  <h4><i class="fa fa-user-circle text-primary"></i> &nbsp;Profil Saya</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Profil</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <div class="prf-panel">

    <!-- ══ HEADER ══ -->
    <div class="prf-top-bar">
      <div class="prf-top-av"><?= getInitials($user['nama']) ?></div>
      <div class="prf-top-info">
        <div class="prf-top-nama">
          <?= htmlspecialchars((!empty($sdm['gelar_depan'])?$sdm['gelar_depan'].' ':'').clean($user['nama']).(!empty($sdm['gelar_belakang'])?', '.$sdm['gelar_belakang']:'')) ?>
        </div>
        <div class="prf-top-meta">
          <?php if(!empty($sdm['nik_rs'])): ?><span><i class="fa fa-id-badge" style="color:#00c896;font-size:10px;"></i><?= clean($sdm['nik_rs']) ?></span><?php endif; ?>
          <?php if(!empty($user['divisi'])): ?><span><i class="fa fa-building" style="font-size:10px;"></i><?= clean($user['divisi']) ?></span><?php endif; ?>
          <?php if(!empty($sdm['jenis_karyawan'])): ?><span><i class="fa fa-briefcase" style="font-size:10px;"></i><?= clean($sdm['jenis_karyawan']) ?></span><?php endif; ?>
          <span class="prf-top-badge" style="color:<?= $rs_color ?>;background:<?= $rs_color ?>18;">
            <i class="fa <?= $rs_icon ?>" style="font-size:9px;"></i><?= $rs_label ?>
          </span>
        </div>
      </div>
      <div class="prf-top-right">
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-size:10.5px;color:#6b7280;">SDM</span>
          <div style="width:80px;height:5px;border-radius:99px;background:#e5e7eb;overflow:hidden;">
            <div style="height:100%;background:linear-gradient(90deg,#00e5b0,#00c896);width:<?= $sdm_pct ?>%;"></div>
          </div>
          <span style="font-size:11px;font-weight:700;color:#00c896;"><?= $sdm_pct ?>%</span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-size:10.5px;color:#6b7280;">Berkas</span>
          <div style="width:80px;height:5px;border-radius:99px;background:#e5e7eb;overflow:hidden;">
            <div style="height:100%;background:linear-gradient(90deg,#6366f1,#4f46e5);width:<?= $pct_berkas_profil ?>%;"></div>
          </div>
          <span style="font-size:11px;font-weight:700;color:#6366f1;"><?= $pct_berkas_profil ?>%</span>
        </div>
        <?php if($atasan_info): ?>
        <div style="font-size:10.5px;color:#6b7280;text-align:right;">
          <i class="fa fa-user-tie" style="color:#00c896;"></i>
          Atasan: <strong style="color:#0f172a;"><?= htmlspecialchars(($atasan_info['gelar_depan']?$atasan_info['gelar_depan'].' ':'').$atasan_info['nama']) ?></strong>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stats tiket -->
    <div class="prf-stat-row">
      <?php foreach([['Menunggu',$ms['menunggu']??0,'#f59e0b'],['Diproses',$ms['diproses']??0,'#3b82f6'],['Selesai',$ms['selesai']??0,'#10b981'],['Ditolak',$ms['ditolak']??0,'#ef4444']] as [$lbl,$val,$clr]): ?>
      <div class="prf-stat-item">
        <div class="prf-stat-num" style="color:<?= $clr ?>;"><?= (int)$val ?></div>
        <div class="prf-stat-lbl"><?= $lbl ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ══ TABS ══ -->
    <div class="prf-tabs">
      <button type="button" class="prf-tab active" data-pane="tp-profil">
        <i class="fa fa-user"></i> Edit Profil
      </button>
      <button type="button" class="prf-tab" data-pane="tp-sdm">
        <i class="fa fa-id-card"></i> Data Kepegawaian
        <?php if($sdm_pct < 50): ?><span style="background:#fef3c7;color:#92400e;font-size:9px;font-weight:700;padding:1px 5px;border-radius:8px;">Belum lengkap</span><?php endif; ?>
      </button>
      <button type="button" class="prf-tab" data-pane="tp-berkas" id="tab-berkas-btn">
        <i class="fa fa-folder-open"></i> Berkas Saya
        <?php if($pct_berkas_profil < 100 && !empty($master_berkas_profil)): ?>
        <span style="background:<?= $berkas_exp_cnt>0?'#fee2e2':'#fef3c7' ?>;color:<?= $berkas_exp_cnt>0?'#dc2626':'#92400e' ?>;font-size:9px;font-weight:700;padding:1px 5px;border-radius:8px;">
          <?= $berkas_exp_cnt > 0 ? $berkas_exp_cnt.' exp!' : $pct_berkas_profil.'%' ?>
        </span>
        <?php endif; ?>
      </button>
      <button type="button" class="prf-tab" data-pane="tp-pw">
        <i class="fa fa-lock"></i> Ganti Password
      </button>
    </div>

    <!-- ══ TAB: Edit Profil ══ -->
    <div id="tp-profil" class="prf-tab-pane active">
      <form method="POST">
        <input type="hidden" name="action" value="profil">
        <div class="prf-sec"><i class="fa fa-user"></i> Informasi Akun</div>
        <div class="prf-g2">
          <div class="prf-fg">
            <label>Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="nama" value="<?= clean($user['nama']) ?>" required>
          </div>
          <div class="prf-fg">
            <label>Email <span class="req">*</span></label>
            <input type="email" name="email" value="<?= clean($user['email']) ?>" required>
          </div>
        </div>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;margin-bottom:10px;font-size:12px;color:#1e40af;display:flex;align-items:flex-start;gap:8px;">
          <i class="fa fa-circle-info" style="margin-top:2px;flex-shrink:0;"></i>
          <div>Untuk mengubah <strong>Divisi</strong> dan <strong>No. HP</strong>, gunakan tab <strong>Data Kepegawaian</strong>.</div>
        </div>
        <div class="prf-g2">
          <div class="prf-fg">
            <label>Divisi <span class="opt">(via Data Kepegawaian)</span></label>
            <input type="text" value="<?= clean($user['divisi']??'') ?: '—' ?>" readonly>
          </div>
          <div class="prf-fg">
            <label>No. HP <span class="opt">(via Data Kepegawaian)</span></label>
            <input type="text" value="<?= clean($user['no_hp']??'') ?: '—' ?>" readonly>
          </div>
        </div>
        <div class="prf-fg" style="max-width:300px;">
          <label>Role <span class="opt">(tidak dapat diubah sendiri)</span></label>
          <input type="text" value="<?= $rs_label ?>" readonly>
        </div>
        <div style="margin-top:4px;">
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Profil</button>
        </div>
      </form>
    </div>

    <!-- ══ TAB: Data Kepegawaian ══ -->
    <div id="tp-sdm" class="prf-tab-pane">
      <div class="prf-info-box">
        <i class="fa fa-circle-info"></i>
        <div>Data kepegawaian digunakan oleh HRD/Admin. Perubahan <strong>No. HP</strong> dan <strong>Divisi</strong> di sini otomatis tersinkron ke profil akun.</div>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="simpan_sdm">
        <div style="display:flex;border-bottom:1px solid #e5e7eb;margin-bottom:18px;overflow-x:auto;gap:0;">
          <?php foreach([['sdm-t-id','fa-id-card','Identitas'],['sdm-t-kt','fa-location-dot','Kontak'],['sdm-t-kp','fa-briefcase','Kepegawaian'],['sdm-t-ku','fa-credit-card','Keuangan'],['sdm-t-ls','fa-stethoscope','Lisensi']] as $i=>[$pid,$ico,$lbl]): ?>
          <button type="button" class="sdm-subtab <?= $i===0?'active':'' ?>" data-spane="<?= $pid ?>">
            <i class="fa <?= $ico ?>"></i> <?= $lbl ?>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- IDENTITAS -->
        <div class="sdm-subpane active" id="sdm-t-id">
          <div class="prf-sec"><i class="fa fa-id-card"></i> Identitas Pribadi</div>
          <div class="prf-g3">
            <div class="prf-fg"><label>NIK RS</label><input type="text" name="nik_rs" value="<?= sdmVal($sdm,'nik_rs') ?>" placeholder="Nomor Induk RS"></div>
            <div class="prf-fg"><label>NIK KTP <span class="opt">(16 digit)</span></label><input type="text" name="nik_ktp" value="<?= sdmVal($sdm,'nik_ktp') ?>" maxlength="16"></div>
            <div class="prf-fg"><label>Jenis Kelamin</label>
              <select name="jenis_kelamin"><option value="">— Pilih —</option>
                <option value="L" <?= sdmSel($sdm,'jenis_kelamin','L') ?>>Laki-laki</option>
                <option value="P" <?= sdmSel($sdm,'jenis_kelamin','P') ?>>Perempuan</option>
              </select></div>
          </div>
          <div class="prf-g3">
            <div class="prf-fg"><label>Gelar Depan</label><input type="text" name="gelar_depan" value="<?= sdmVal($sdm,'gelar_depan') ?>" placeholder="dr. / Ns."></div>
            <div class="prf-fg"><label>Gelar Belakang</label><input type="text" name="gelar_belakang" value="<?= sdmVal($sdm,'gelar_belakang') ?>" placeholder="M.Kes / Sp.A"></div>
            <div class="prf-fg"><label>Golongan Darah</label>
              <select name="golongan_darah"><option value="">— Pilih —</option>
                <?php foreach($opt_gol_darah as $g): ?><option value="<?= $g ?>" <?= sdmSel($sdm,'golongan_darah',$g) ?>><?= $g ?></option><?php endforeach; ?>
              </select></div>
          </div>
          <div class="prf-g3">
            <div class="prf-fg"><label>Tempat Lahir</label><input type="text" name="tempat_lahir" value="<?= sdmVal($sdm,'tempat_lahir') ?>"></div>
            <div class="prf-fg"><label>Tanggal Lahir</label><input type="date" name="tgl_lahir" value="<?= sdmDate($sdm,'tgl_lahir') ?>"></div>
            <div class="prf-fg"><label>Agama</label>
              <select name="agama"><option value="">— Pilih —</option>
                <?php foreach($opt_agama as $a): ?><option value="<?= $a ?>" <?= sdmSel($sdm,'agama',$a) ?>><?= $a ?></option><?php endforeach; ?>
              </select></div>
          </div>
          <div class="prf-g3">
            <div class="prf-fg"><label>Status Pernikahan</label>
              <select name="status_pernikahan"><option value="">— Pilih —</option>
                <?php foreach($opt_status_nik as $sn): ?><option value="<?= $sn ?>" <?= sdmSel($sdm,'status_pernikahan',$sn) ?>><?= $sn ?></option><?php endforeach; ?>
              </select></div>
            <div class="prf-fg"><label>Jumlah Anak</label><input type="number" name="jumlah_anak" value="<?= (int)($sdm['jumlah_anak']??0) ?>" min="0" max="20"></div>
            <div class="prf-fg"><label>Kewarganegaraan</label><input type="text" name="kewarganegaraan" value="<?= sdmVal($sdm,'kewarganegaraan','WNI') ?>"></div>
          </div>
          <div class="prf-g2">
            <div class="prf-fg"><label>Suku / Etnis</label><input type="text" name="suku" value="<?= sdmVal($sdm,'suku') ?>" placeholder="Jawa / Minang…"></div>
          </div>
          <div class="prf-sec"><i class="fa fa-graduation-cap"></i> Pendidikan</div>
          <div class="prf-g4">
            <div class="prf-fg"><label>Pendidikan Terakhir</label>
              <select name="pendidikan_terakhir"><option value="">— Pilih —</option>
                <?php foreach($opt_pendidikan as $p): ?><option value="<?= $p ?>" <?= sdmSel($sdm,'pendidikan_terakhir',$p) ?>><?= $p ?></option><?php endforeach; ?>
              </select></div>
            <div class="prf-fg"><label>Jurusan</label><input type="text" name="jurusan" value="<?= sdmVal($sdm,'jurusan') ?>"></div>
            <div class="prf-fg"><label>Universitas</label><input type="text" name="universitas" value="<?= sdmVal($sdm,'universitas') ?>"></div>
            <div class="prf-fg"><label>Tahun Lulus</label><input type="number" name="tahun_lulus" value="<?= sdmVal($sdm,'tahun_lulus') ?>" min="1970" max="2099"></div>
          </div>
        </div>

        <!-- KONTAK -->
        <div class="sdm-subpane" id="sdm-t-kt">
          <div class="prf-sec"><i class="fa fa-phone"></i> Kontak</div>
          <div class="prf-g3">
            <div class="prf-fg"><label>No. HP / WhatsApp</label><input type="text" name="no_hp" value="<?= sdmVal($sdm,'no_hp',$user['no_hp']??'') ?>" placeholder="08xxxxxxxxxx"></div>
            <div class="prf-fg"><label>No. HP Darurat</label><input type="text" name="no_hp_darurat" value="<?= sdmVal($sdm,'no_hp_darurat') ?>"></div>
            <div class="prf-fg"><label>Nama Kontak Darurat</label><input type="text" name="kontak_darurat" value="<?= sdmVal($sdm,'kontak_darurat') ?>"></div>
          </div>
          <div class="prf-g2">
            <div class="prf-fg"><label>Hubungan Kontak Darurat</label><input type="text" name="hubungan_darurat" value="<?= sdmVal($sdm,'hubungan_darurat') ?>" placeholder="Istri / Suami…"></div>
          </div>
          <div class="prf-sec"><i class="fa fa-location-dot"></i> Alamat KTP</div>
          <div class="prf-fg"><label>Alamat Lengkap</label>
            <textarea name="alamat_ktp"><?= sdmVal($sdm,'alamat_ktp') ?></textarea></div>
          <div class="prf-g4">
            <div class="prf-fg"><label>Kelurahan</label><input type="text" name="kelurahan_ktp" value="<?= sdmVal($sdm,'kelurahan_ktp') ?>"></div>
            <div class="prf-fg"><label>Kecamatan</label><input type="text" name="kecamatan_ktp" value="<?= sdmVal($sdm,'kecamatan_ktp') ?>"></div>
            <div class="prf-fg"><label>Kota</label><input type="text" name="kota_ktp" value="<?= sdmVal($sdm,'kota_ktp') ?>"></div>
            <div class="prf-fg"><label>Provinsi</label><input type="text" name="provinsi_ktp" value="<?= sdmVal($sdm,'provinsi_ktp') ?>"></div>
          </div>
          <div class="prf-g2">
            <div class="prf-fg"><label>Kode Pos</label><input type="text" name="kode_pos_ktp" value="<?= sdmVal($sdm,'kode_pos_ktp') ?>" maxlength="10"></div>
          </div>
          <div class="prf-sec"><i class="fa fa-house"></i> Alamat Domisili <span style="font-size:10px;color:#6b7280;font-weight:400;">(kosongkan jika sama KTP)</span></div>
          <div class="prf-fg"><label>Alamat Domisili</label>
            <textarea name="alamat_domisili" placeholder="Kosongkan jika sama KTP…"><?= sdmVal($sdm,'alamat_domisili') ?></textarea></div>
          <div class="prf-g2">
            <div class="prf-fg"><label>Kota Domisili</label><input type="text" name="kota_domisili" value="<?= sdmVal($sdm,'kota_domisili') ?>"></div>
          </div>
        </div>

        <!-- KEPEGAWAIAN -->
        <div class="sdm-subpane" id="sdm-t-kp">
          <div class="prf-sec"><i class="fa fa-briefcase"></i> Data Kepegawaian</div>
          <div class="prf-g3">
            <div class="prf-fg"><label>Divisi</label>
              <select name="divisi"><option value="">— Pilih —</option>
                <?php foreach($divs as $dv): ?><option value="<?= clean($dv['nama']) ?>" <?= sdmSel($sdm,'divisi',$dv['nama']) ?>><?= clean($dv['nama']) ?></option><?php endforeach; ?>
              </select></div>
            <div class="prf-fg"><label>Unit Kerja</label><input type="text" name="unit_kerja" value="<?= sdmVal($sdm,'unit_kerja') ?>" placeholder="ICU, IGD…"></div>
            <div class="prf-fg"><label>Jenis Karyawan</label>
              <select name="jenis_karyawan"><option value="">— Pilih —</option>
                <?php foreach($opt_jenis_kary as $jk): ?><option value="<?= $jk ?>" <?= sdmSel($sdm,'jenis_karyawan',$jk) ?>><?= $jk ?></option><?php endforeach; ?>
              </select></div>
          </div>
          <div class="prf-g3">
            <div class="prf-fg"><label>Jenis Tenaga</label><input type="text" name="jenis_tenaga" value="<?= sdmVal($sdm,'jenis_tenaga') ?>" placeholder="Dokter / Perawat…"></div>
            <div class="prf-fg"><label>Spesialisasi</label><input type="text" name="spesialisasi" value="<?= sdmVal($sdm,'spesialisasi') ?>"></div>
            <div class="prf-fg"><label>Sub Spesialisasi</label><input type="text" name="sub_spesialisasi" value="<?= sdmVal($sdm,'sub_spesialisasi') ?>"></div>
          </div>
          <div class="prf-g3">
            <div class="prf-fg"><label>Jabatan</label>
              <select name="jabatan_id"><option value="">— Pilih —</option>
                <?php foreach($jabatan_list as $jb): ?><option value="<?= (int)$jb['id'] ?>" <?= (isset($sdm['jabatan_id'])&&(int)$sdm['jabatan_id']===(int)$jb['id'])?'selected':'' ?>><?= htmlspecialchars($jb['nama']) ?></option><?php endforeach; ?>
              </select></div>
            <div class="prf-fg"><label>Status Kepegawaian</label>
              <select name="status_kepegawaian">
                <?php foreach($opt_status_kep as $sk): ?><option value="<?= $sk ?>" <?= sdmSel($sdm,'status_kepegawaian',$sk) ?>><?= $sk ?></option><?php endforeach; ?>
              </select></div>
          </div>
          <div class="prf-g4">
            <div class="prf-fg"><label>Tanggal Masuk</label><input type="date" name="tgl_masuk" value="<?= sdmDate($sdm,'tgl_masuk') ?>"></div>
            <div class="prf-fg"><label>Awal Kontrak</label><input type="date" name="tgl_kontrak_mulai" value="<?= sdmDate($sdm,'tgl_kontrak_mulai') ?>"></div>
            <div class="prf-fg"><label>Akhir Kontrak</label><input type="date" name="tgl_kontrak_selesai" value="<?= sdmDate($sdm,'tgl_kontrak_selesai') ?>"></div>
            <div class="prf-fg"><label>Tgl Pengangkatan</label><input type="date" name="tgl_pengangkatan" value="<?= sdmDate($sdm,'tgl_pengangkatan') ?>"></div>
          </div>
          <div class="prf-sec"><i class="fa fa-user-tie"></i> Atasan Langsung <span style="font-size:10px;color:#6b7280;font-weight:400;text-transform:none;letter-spacing:0;">&nbsp;— untuk approval cuti</span></div>
          <div class="prf-g2">
            <div class="prf-fg">
              <label>Atasan Langsung <span class="opt">(approver level 1)</span></label>
              <select name="atasan_id" id="sel-atasan" onchange="previewAtasan(this)">
                <option value="">— Tidak ada —</option>
                <?php $cur_div=null; foreach($atasan_list as $at):
                  if($at['divisi']!==$cur_div){if($cur_div!==null)echo'</optgroup>';echo'<optgroup label="'.htmlspecialchars($at['divisi']?:'Tanpa Divisi').'">';$cur_div=$at['divisi'];}
                  $sel=(isset($sdm['atasan_id'])&&(int)$sdm['atasan_id']===(int)$at['id'])?'selected':'';
                  $nf=($at['gelar_depan']?$at['gelar_depan'].' ':'').$at['nama'];
                  echo'<option value="'.(int)$at['id'].'" '.$sel.' data-div="'.htmlspecialchars($at['divisi']).'" data-nama="'.htmlspecialchars($nf).'">'.htmlspecialchars($nf).($at['nik_rs']?' — '.$at['nik_rs']:'').'</option>';
                endforeach; if($cur_div!==null)echo'</optgroup>'; ?>
              </select>
              <div class="atasan-preview" id="atasan-preview" style="<?= empty($sdm['atasan_id'])?'display:none;':'' ?>">
                <?php if($atasan_info): ?>
                <div class="atasan-av"><?= getInitials($atasan_info['nama']) ?></div>
                <div>
                  <div style="font-weight:700;color:#065f46;"><?= htmlspecialchars(($atasan_info['gelar_depan']?$atasan_info['gelar_depan'].' ':'').$atasan_info['nama']) ?></div>
                  <div style="font-size:10.5px;color:#6b7280;"><?= clean($atasan_info['divisi']?:'—') ?></div>
                </div>
                <span style="margin-left:auto;font-size:9.5px;background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:4px;font-weight:700;">Level 1</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="prf-fg">
              <label style="color:#94a3b8;">Approver Level 2 <span class="opt">(HRD — otomatis)</span></label>
              <input type="text" value="HRD / Admin HR" readonly>
              <div style="font-size:10.5px;color:#6b7280;margin-top:4px;line-height:1.5;"><i class="fa fa-info-circle" style="color:#00c896;"></i> Approval: <strong>Atasan</strong> → <strong>HRD</strong> → Selesai.</div>
            </div>
          </div>
          <div class="prf-fg"><label>Catatan</label><textarea name="catatan"><?= sdmVal($sdm,'catatan') ?></textarea></div>
        </div>

        <!-- KEUANGAN -->
        <div class="sdm-subpane" id="sdm-t-ku">
          <div class="prf-sec"><i class="fa fa-shield-halved"></i> BPJS &amp; Pajak</div>
          <div class="prf-g3">
            <div class="prf-fg"><label>No. BPJS Kesehatan</label><input type="text" name="no_bpjs_kes" value="<?= sdmVal($sdm,'no_bpjs_kes') ?>"></div>
            <div class="prf-fg"><label>No. BPJS TK</label><input type="text" name="no_bpjs_tk" value="<?= sdmVal($sdm,'no_bpjs_tk') ?>"></div>
            <div class="prf-fg"><label>No. NPWP</label><input type="text" name="no_npwp" value="<?= sdmVal($sdm,'no_npwp') ?>"></div>
          </div>
          <div class="prf-sec"><i class="fa fa-building-columns"></i> Rekening Bank</div>
          <div class="prf-g3">
            <div class="prf-fg"><label>Bank</label>
              <select name="bank"><option value="">— Pilih —</option>
                <?php foreach($opt_bank as $b): ?><option value="<?= $b ?>" <?= sdmSel($sdm,'bank',$b) ?>><?= $b ?></option><?php endforeach; ?>
              </select></div>
            <div class="prf-fg"><label>No. Rekening</label><input type="text" name="no_rekening" value="<?= sdmVal($sdm,'no_rekening') ?>"></div>
            <div class="prf-fg"><label>Atas Nama</label><input type="text" name="atas_nama_rek" value="<?= sdmVal($sdm,'atas_nama_rek') ?>"></div>
          </div>
        </div>

        <!-- LISENSI -->
        <div class="sdm-subpane" id="sdm-t-ls">
          <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#065f46;display:flex;align-items:center;gap:8px;">
            <i class="fa fa-circle-info"></i> Non-Medis: isi <code>-</code> atau kosongkan.
          </div>
          <div class="prf-sec"><i class="fa fa-id-badge"></i> STR</div>
          <div class="prf-g3">
            <div class="prf-fg"><label>Nomor STR</label><input type="text" name="no_str" value="<?= sdmVal($sdm,'no_str') ?>"></div>
            <div class="prf-fg"><label>Tgl Terbit</label><input type="date" name="tgl_terbit_str" value="<?= sdmDate($sdm,'tgl_terbit_str') ?>"></div>
            <div class="prf-fg"><label>Exp. STR</label><input type="date" name="tgl_exp_str" value="<?= sdmDate($sdm,'tgl_exp_str') ?>">
              <?php if(!empty($sdm['tgl_exp_str'])&&strtotime($sdm['tgl_exp_str'])<strtotime('+30 days')): ?><span style="font-size:10px;color:#dc2626;font-weight:700;"><i class="fa fa-triangle-exclamation"></i> Segera expired!</span><?php endif; ?></div>
          </div>
          <div class="prf-sec"><i class="fa fa-file-medical"></i> SIP</div>
          <div class="prf-g3">
            <div class="prf-fg"><label>Nomor SIP</label><input type="text" name="no_sip" value="<?= sdmVal($sdm,'no_sip') ?>"></div>
            <div class="prf-fg"><label>Tgl Terbit</label><input type="date" name="tgl_terbit_sip" value="<?= sdmDate($sdm,'tgl_terbit_sip') ?>"></div>
            <div class="prf-fg"><label>Exp. SIP</label><input type="date" name="tgl_exp_sip" value="<?= sdmDate($sdm,'tgl_exp_sip') ?>">
              <?php if(!empty($sdm['tgl_exp_sip'])&&strtotime($sdm['tgl_exp_sip'])<strtotime('+30 days')): ?><span style="font-size:10px;color:#dc2626;font-weight:700;"><i class="fa fa-triangle-exclamation"></i> Segera expired!</span><?php endif; ?></div>
          </div>
          <div class="prf-sec"><i class="fa fa-file-signature"></i> SIK</div>
          <div class="prf-g3">
            <div class="prf-fg"><label>Nomor SIK</label><input type="text" name="no_sik" value="<?= sdmVal($sdm,'no_sik') ?>"></div>
            <div class="prf-fg"><label>Exp. SIK</label><input type="date" name="tgl_exp_sik" value="<?= sdmDate($sdm,'tgl_exp_sik') ?>"></div>
          </div>
          <div class="prf-sec"><i class="fa fa-certificate"></i> Kompetensi</div>
          <div class="prf-fg"><label>Kompetensi / Sertifikasi</label>
            <textarea name="kompetensi" placeholder="BLS, ACLS, BTCLS… pisahkan koma."><?= sdmVal($sdm,'kompetensi') ?></textarea></div>
        </div>

        <div class="prf-save-row">
          <span style="font-size:11px;color:#9ca3af;"><i class="fa fa-clock"></i> Terakhir diperbarui: <?= !empty($sdm['updated_at'])?date('d M Y H:i',strtotime($sdm['updated_at'])):'—' ?></span>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Data Kepegawaian</button>
        </div>
      </form>
    </div>

    <!-- ══ TAB: BERKAS SAYA ══ -->
    <div id="tp-berkas" class="prf-tab-pane">

      <!-- Progress kelengkapan -->
      <div style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:1px solid #c4b5fd;border-radius:12px;padding:16px 20px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <div style="flex:1;min-width:180px;">
          <div style="font-size:13px;font-weight:700;color:#4c1d95;margin-bottom:6px;">
            <i class="fa fa-folder-open"></i> Kelengkapan Berkas Saya
          </div>
          <div style="height:8px;border-radius:99px;background:#ddd6fe;overflow:hidden;">
            <div style="height:100%;border-radius:99px;background:linear-gradient(90deg,#6366f1,#4f46e5);width:<?= $pct_berkas_profil ?>%;transition:width .5s;"></div>
          </div>
          <div style="font-size:11px;color:#6b7280;margin-top:5px;">
            <?= $berkas_wajib_done ?> dari <?= $total_req_profil ?> berkas wajib sudah diupload
            <?php if($berkas_exp_cnt>0): ?>
            &nbsp;·&nbsp; <span style="color:#dc2626;font-weight:700;"><i class="fa fa-triangle-exclamation"></i> <?= $berkas_exp_cnt ?> berkas mendekati expired!</span>
            <?php endif; ?>
          </div>
        </div>
        <div style="text-align:center;flex-shrink:0;">
          <div style="font-size:32px;font-weight:800;color:#6366f1;line-height:1;"><?= $pct_berkas_profil ?>%</div>
          <div style="font-size:10px;color:#9ca3af;">kelengkapan</div>
        </div>
      </div>

      <!-- Info verifikasi -->
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:11px 14px;margin-bottom:18px;font-size:12px;color:#92400e;display:flex;gap:8px;align-items:flex-start;">
        <i class="fa fa-circle-info" style="margin-top:1px;flex-shrink:0;color:#f59e0b;"></i>
        <div>Berkas yang Anda upload berstatus <strong>Menunggu Verifikasi</strong> sampai disetujui oleh HRD/Admin. Berkas yang ditolak perlu diupload ulang.</div>
      </div>

      <?php if (empty($master_berkas_profil)): ?>
      <div style="text-align:center;padding:40px;color:#9ca3af;">
        <i class="fa fa-folder-tree" style="font-size:32px;margin-bottom:12px;display:block;"></i>
        <div style="font-size:14px;font-weight:600;color:#374151;">Master Berkas Belum Dikonfigurasi</div>
        <div style="font-size:12px;margin-top:4px;">Hubungi HRD/Admin untuk mengatur jenis berkas.</div>
      </div>
      <?php else: ?>

      <!-- Accordion per kategori -->
      <?php foreach ($berkas_kat_profil as $kat):
        $kat_filled = count(array_filter($kat['items'], fn($j) => isset($berkas_uploaded[$j['id']])));
        $kat_total  = count($kat['items']);
        $kat_warna  = $kat['warna'] ?? '#6366f1';
        $kat_bg = $kat_filled === $kat_total
          ? 'background:#dcfce7;color:#16a34a;'
          : ($kat_filled > 0 ? 'background:#fef3c7;color:#d97706;' : 'background:#f1f5f9;color:#9ca3af;');
      ?>
      <div class="bk-kat-card open">
        <div class="bk-kat-hd" onclick="this.closest('.bk-kat-card').classList.toggle('open')">
          <div style="width:26px;height:26px;border-radius:7px;background:<?= $kat_warna ?>18;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fa <?= htmlspecialchars($kat['icon']) ?>" style="color:<?= $kat_warna ?>;font-size:11px;"></i>
          </div>
          <span style="font-size:12.5px;font-weight:700;color:#1e293b;flex:1;"><?= htmlspecialchars($kat['nama']) ?></span>
          <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;<?= $kat_bg ?>"><?= $kat_filled ?>/<?= $kat_total ?></span>
          <i class="fa fa-chevron-down bk-kat-arrow" style="font-size:10px;color:#94a3b8;margin-left:6px;transition:transform .2s;"></i>
        </div>

        <div class="bk-kat-body">
          <?php foreach ($kat['items'] as $j):
            $bk      = $berkas_uploaded[$j['id']] ?? null;
            $bk_url  = $bk ? (APP_URL.'/uploads/berkas_karyawan/'.$_SESSION['user_id'].'/'.$bk['nama_file']) : '';
            $ic_color= $kat_warna;

            // Status chip
            if (!$bk) {
              $chip_cls = 'bk-chip-kosong'; $chip_lbl = '<i class="fa fa-minus"></i> Belum ada';
            } elseif ($bk['status_verif'] === 'terverifikasi') {
              $chip_cls = 'bk-chip-ok';     $chip_lbl = '<i class="fa fa-check"></i> Terverifikasi';
            } elseif ($bk['status_verif'] === 'ditolak') {
              $chip_cls = 'bk-chip-ditolak';$chip_lbl = '<i class="fa fa-times"></i> Ditolak';
            } else {
              $chip_cls = 'bk-chip-pending';$chip_lbl = '<i class="fa fa-clock"></i> Menunggu Verif';
            }

            // Exp tag
            $exp_html = '';
            if ($bk && $bk['tgl_exp']) {
              $diff = (strtotime($bk['tgl_exp']) - time()) / 86400;
              if ($diff < 0)   $exp_html = '<span class="bk-expired"><i class="fa fa-triangle-exclamation"></i> Expired</span>';
              elseif ($diff<30)$exp_html = '<span class="bk-exp-soon"><i class="fa fa-clock"></i> '.ceil($diff).' hari lagi</span>';
            }

            // Catatan ditolak
            $catatan_ditolak = ($bk && $bk['status_verif']==='ditolak' && $bk['catatan_verif'])
              ? '<div style="font-size:10.5px;color:#dc2626;margin-top:2px;"><i class="fa fa-comment-dots"></i> '.htmlspecialchars($bk['catatan_verif']).'</div>'
              : '';
          ?>
          <div class="bk-item-row">
            <!-- Icon -->
            <div class="bk-item-icon" style="background:<?= $ic_color ?>18;">
              <i class="fa <?= htmlspecialchars($j['icon']) ?>" style="color:<?= $ic_color ?>;"></i>
            </div>

            <!-- Info -->
            <div style="flex:1;min-width:0;">
              <div style="font-size:12.5px;font-weight:600;color:#1e293b;display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
                <?= htmlspecialchars($j['nama']) ?>
                <?php if ($j['wajib']): ?><span class="bk-req">WAJIB</span><?php endif; ?>
                <span class="bk-chip <?= $chip_cls ?>"><?= $chip_lbl ?></span>
                <?= $exp_html ?>
              </div>
              <?php if ($bk): ?>
              <div style="font-size:10.5px;color:#9ca3af;margin-top:2px;">
                <i class="fa fa-file" style="font-size:9px;"></i>
                <?= htmlspecialchars($bk['nama_asli']) ?>
                <?php if ($bk['tgl_dokumen']): ?> &nbsp;·&nbsp; <?= date('d M Y',strtotime($bk['tgl_dokumen'])) ?><?php endif; ?>
                <?php if ($bk['keterangan']): ?> &nbsp;·&nbsp; <?= htmlspecialchars($bk['keterangan']) ?><?php endif; ?>
              </div>
              <?= $catatan_ditolak ?>
              <?php else: ?>
              <div style="font-size:10.5px;color:#d1d5db;">
                <?= $j['keterangan'] ? htmlspecialchars($j['keterangan']) : 'Belum ada berkas' ?>
              </div>
              <?php endif; ?>
            </div>

            <!-- Aksi -->
            <div style="display:flex;align-items:center;gap:5px;flex-shrink:0;">
              <?php if ($bk): ?>
              <a href="<?= $bk_url ?>" target="_blank"
                 style="font-size:11px;font-weight:600;padding:5px 10px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;color:#374151;text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:background .15s;"
                 onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">
                <i class="fa fa-eye"></i> Lihat
              </a>
              <?php endif; ?>

              <button type="button" class="bk-btn-up"
                      onclick="prfOpenUpload(<?= $j['id'] ?>, '<?= addslashes(htmlspecialchars($j['nama'])) ?>', <?= (int)$j['has_exp'] ?>, <?= (int)$j['has_tgl_terbit'] ?>, '<?= addslashes(htmlspecialchars($j['format_file']??'pdf,jpg,jpeg,png')) ?>')">
                <i class="fa <?= $bk ? 'fa-arrow-up-from-bracket' : 'fa-plus' ?>"></i>
                <?= $bk ? 'Ganti' : 'Upload' ?>
              </button>

              <?php if ($bk): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus berkas ini?')">
                <input type="hidden" name="action"    value="hapus_berkas_profil">
                <input type="hidden" name="berkas_id" value="<?= $bk['id'] ?>">
                <button type="submit"
                        style="font-size:11px;width:28px;height:28px;border-radius:8px;border:1px solid #fca5a5;background:#fff5f5;color:#dc2626;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;"
                        onmouseover="this.style.background='#dc2626';this.style.color='#fff';"
                        onmouseout="this.style.background='#fff5f5';this.style.color='#dc2626';">
                  <i class="fa fa-trash"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      </div>
      <?php endforeach; ?>

      <?php endif; ?>
    </div><!-- /tp-berkas -->

    <!-- ══ TAB: Ganti Password ══ -->
    <div id="tp-pw" class="prf-tab-pane">
      <div class="prf-sec"><i class="fa fa-lock"></i> Ubah Password</div>
      <form method="POST" style="max-width:420px;">
        <input type="hidden" name="action" value="password">
        <div class="prf-fg"><label>Password Lama <span class="req">*</span></label><input type="password" name="old" required></div>
        <div class="prf-fg"><label>Password Baru <span class="req">*</span></label><input type="password" name="new" id="pw-new" required placeholder="Min. 6 karakter"></div>
        <div class="prf-fg"><label>Konfirmasi Password Baru <span class="req">*</span></label><input type="password" name="cnf" id="pw-cnf" required></div>
        <div id="pw-match-msg" style="font-size:11.5px;margin-bottom:10px;display:none;"></div>
        <button type="submit" class="btn btn-primary"><i class="fa fa-lock"></i> Ganti Password</button>
      </form>
    </div>

  </div><!-- /.prf-panel -->
</div><!-- /.content -->


<!-- ══════════ MODAL UPLOAD BERKAS ══════════ -->
<div id="prf-up-modal" class="prf-up-ov">
  <div class="prf-up-box">
    <div class="prf-up-hd">
      <i class="fa fa-cloud-arrow-up" style="font-size:17px;"></i>
      <span class="prf-up-title" id="prf-up-title">Upload Berkas</span>
      <button type="button" class="prf-up-close" onclick="prfCloseUpload()"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data" id="prf-up-form">
      <input type="hidden" name="action"          value="upload_berkas_profil">
      <input type="hidden" name="jenis_berkas_id" id="prf-up-jenis-id" value="">
      <div class="prf-up-body">
        <div class="prf-up-drop" id="prf-up-drop" onclick="document.getElementById('prf-up-file').click()">
          <i class="fa fa-file-arrow-up"></i>
          <p>Klik atau seret file ke sini</p>
          <small id="prf-up-hint">Format: PDF, JPG, PNG — Maks. 10 MB</small>
        </div>
        <input type="file" name="file_berkas" id="prf-up-file" style="display:none;">
        <div id="prf-up-prev" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:9px 12px;margin-bottom:12px;font-size:12px;align-items:center;gap:8px;">
          <i class="fa fa-file-check" style="color:#16a34a;"></i>
          <span id="prf-up-fname" style="flex:1;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
          <span id="prf-up-fsize" style="color:#9ca3af;flex-shrink:0;"></span>
        </div>
        <div id="prf-up-terbit-wrap" class="prf-up-fg" style="display:none;">
          <label>Tanggal Terbit Dokumen</label>
          <input type="date" name="tgl_dokumen">
        </div>
        <div id="prf-up-exp-wrap" class="prf-up-fg" style="display:none;">
          <label>Tanggal Kadaluarsa</label>
          <input type="date" name="tgl_exp">
        </div>
        <div class="prf-up-fg">
          <label>Keterangan <span style="font-size:10px;color:#9ca3af;">(opsional)</span></label>
          <textarea name="keterangan" rows="2" placeholder="No. dokumen, instansi, catatan…"></textarea>
        </div>
      </div>
      <div class="prf-up-ft">
        <button type="button" class="btn btn-default" onclick="prfCloseUpload()"><i class="fa fa-times"></i> Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-cloud-arrow-up"></i> Upload</button>
      </div>
    </form>
  </div>
</div>


<script>
(function(){
  // ── Tab utama ─────────────────────────────────────────────────────────────
  document.querySelectorAll('.prf-tab').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('.prf-tab').forEach(function(b){ b.classList.remove('active'); });
      document.querySelectorAll('.prf-tab-pane').forEach(function(p){ p.classList.remove('active'); });
      btn.classList.add('active');
      var pane = document.getElementById(btn.getAttribute('data-pane'));
      if (pane) pane.classList.add('active');
    });
  });

  // Sub-tabs SDM
  document.querySelectorAll('.sdm-subtab').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('.sdm-subtab').forEach(function(b){ b.classList.remove('active'); });
      document.querySelectorAll('.sdm-subpane').forEach(function(p){ p.classList.remove('active'); });
      btn.classList.add('active');
      var pane = document.getElementById(btn.getAttribute('data-spane'));
      if (pane) pane.classList.add('active');
    });
  });

  // Auto-buka tab berkas jika hash #berkas
  if (window.location.hash === '#berkas') {
    var btn = document.getElementById('tab-berkas-btn');
    if (btn) btn.click();
  }

  // Validasi password
  var pwNew = document.getElementById('pw-new');
  var pwCnf = document.getElementById('pw-cnf');
  var pwMsg = document.getElementById('pw-match-msg');
  function checkPw(){
    if (!pwCnf.value) { pwMsg.style.display='none'; return; }
    pwMsg.style.display='block';
    pwMsg.innerHTML = pwNew.value===pwCnf.value
      ? '<span style="color:#10b981;"><i class="fa fa-check"></i> Password cocok</span>'
      : '<span style="color:#ef4444;"><i class="fa fa-times"></i> Password tidak cocok</span>';
  }
  if (pwNew) pwNew.addEventListener('input', checkPw);
  if (pwCnf) pwCnf.addEventListener('input', checkPw);

  // Accordion berkas — chevron sync
  document.querySelectorAll('.bk-kat-card').forEach(function(card){
    var hd    = card.querySelector('.bk-kat-hd');
    var arrow = card.querySelector('.bk-kat-arrow');
    if (!hd || !arrow) return;
    // Initial state
    arrow.style.transform = card.classList.contains('open') ? 'rotate(180deg)' : '';
    hd.addEventListener('click', function(){
      card.classList.toggle('open');
      arrow.style.transform = card.classList.contains('open') ? 'rotate(180deg)' : '';
    });
  });
})();

// Preview atasan
function previewAtasan(sel) {
  var opt  = sel.options[sel.selectedIndex];
  var prev = document.getElementById('atasan-preview');
  if (!sel.value) { prev.style.display='none'; prev.innerHTML=''; return; }
  var nama = opt.getAttribute('data-nama') || opt.text;
  var div  = opt.getAttribute('data-div')  || '—';
  var init = nama.trim().split(/\s+/).slice(0,2).map(function(w){ return w[0]||''; }).join('').toUpperCase();
  prev.style.display = 'flex';
  prev.innerHTML =
    '<div class="atasan-av">'+init+'</div>'+
    '<div><div style="font-weight:700;color:#065f46;">'+nama+'</div><div style="font-size:10.5px;color:#6b7280;">'+div+'</div></div>'+
    '<span style="margin-left:auto;font-size:9.5px;background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:4px;font-weight:700;">Level 1</span>';
}

// ── Modal Upload Berkas ────────────────────────────────────────────────────────
var _prfMod  = document.getElementById('prf-up-modal');
var _prfFile = document.getElementById('prf-up-file');
var _prfDrop = document.getElementById('prf-up-drop');
var _prfForm = document.getElementById('prf-up-form');
var _prfPrev = document.getElementById('prf-up-prev');

function prfOpenUpload(jenisId, jenisNama, hasExp, hasTerbit, fmt) {
  _prfForm.reset();
  _prfPrev.style.display = 'none';
  document.getElementById('prf-up-jenis-id').value = jenisId;
  document.getElementById('prf-up-title').textContent = jenisNama;
  document.getElementById('prf-up-exp-wrap').style.display    = hasExp    ? 'block' : 'none';
  document.getElementById('prf-up-terbit-wrap').style.display = hasTerbit ? 'block' : 'none';
  document.getElementById('prf-up-hint').textContent = 'Format: ' + fmt.toUpperCase() + ' — Maks. 10 MB';
  var fmts = fmt.split(',').map(function(f){ return '.'+f.trim(); }).join(',');
  _prfFile.setAttribute('accept', fmts);
  _prfMod.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function prfCloseUpload() {
  _prfMod.classList.remove('open');
  document.body.style.overflow = '';
}

_prfMod.addEventListener('click', function(e){ if(e.target===_prfMod) prfCloseUpload(); });
document.addEventListener('keydown', function(e){ if(e.key==='Escape'&&_prfMod.classList.contains('open')) prfCloseUpload(); });

function showFilePrev(file) {
  document.getElementById('prf-up-fname').textContent = file.name;
  var sz = file.size < 1048576 ? (file.size/1024).toFixed(1)+' KB' : (file.size/1048576).toFixed(1)+' MB';
  document.getElementById('prf-up-fsize').textContent = sz;
  _prfPrev.style.display = 'flex';
}

_prfFile.addEventListener('change', function(){ if(_prfFile.files[0]) showFilePrev(_prfFile.files[0]); });

_prfDrop.addEventListener('dragover',  function(e){ e.preventDefault(); _prfDrop.classList.add('drag'); });
_prfDrop.addEventListener('dragleave', function(){  _prfDrop.classList.remove('drag'); });
_prfDrop.addEventListener('drop', function(e){
  e.preventDefault(); _prfDrop.classList.remove('drag');
  if (e.dataTransfer.files[0]) {
    var dt = new DataTransfer(); dt.items.add(e.dataTransfer.files[0]);
    _prfFile.files = dt.files;
    showFilePrev(e.dataTransfer.files[0]);
  }
});
_prfForm.addEventListener('submit', function(e){
  if (!_prfFile.files[0]) { e.preventDefault(); alert('Pilih file terlebih dahulu!'); }
});
</script>

<?php include '../includes/footer.php'; ?>