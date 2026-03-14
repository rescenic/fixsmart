<?php
// pages/kategori.php
session_start(); require_once '../config.php'; requireLogin();
if (!hasRole(['admin','teknisi'])) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title='Kategori'; $active_menu='kategori';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $act=$_POST['action']??''; $nama=trim($_POST['nama']??''); $desc=trim($_POST['deskripsi']??'');
    $icon=$_POST['icon']??'fa-tag'; $sla=(int)($_POST['sla_jam']??24); $sla_r=(int)($_POST['sla_respon_jam']??4);
    if ($act==='tambah'&&$nama) {
        $pdo->prepare("INSERT INTO kategori (nama,deskripsi,icon,sla_jam,sla_respon_jam) VALUES (?,?,?,?,?)")->execute([$nama,$desc,$icon,$sla,$sla_r]);
        setFlash('success','Kategori berhasil ditambahkan.');
    } elseif ($act==='edit'&&$nama) {
        $pdo->prepare("UPDATE kategori SET nama=?,deskripsi=?,icon=?,sla_jam=?,sla_respon_jam=? WHERE id=?")->execute([$nama,$desc,$icon,$sla,$sla_r,(int)$_POST['id']]);
        setFlash('success','Kategori berhasil diperbarui.');
    } elseif ($act==='hapus') {
        $st=$pdo->prepare("SELECT COUNT(*) FROM tiket WHERE kategori_id=?"); $st->execute([(int)$_POST['id']]);
        if ($st->fetchColumn()>0) setFlash('warning','Kategori tidak dapat dihapus, masih ada tiket.');
        else { $pdo->prepare("DELETE FROM kategori WHERE id=?")->execute([(int)$_POST['id']]); setFlash('success','Kategori dihapus.'); }
    }
    redirect(APP_URL.'/pages/kategori.php');
}

$list=$pdo->query("SELECT k.*,COUNT(t.id) as total FROM kategori k LEFT JOIN tiket t ON t.kategori_id=k.id GROUP BY k.id ORDER BY k.nama")->fetchAll();
$icons=['fa-desktop','fa-laptop-code','fa-network-wired','fa-envelope','fa-print','fa-video','fa-server','fa-tag','fa-question-circle','fa-wifi','fa-hdd','fa-mouse'];

// Stats
$total_kat  = count($list);
$total_tiket = array_sum(array_column($list, 'total'));
$avg_sla    = $total_kat > 0 ? round(array_sum(array_column($list,'sla_jam')) / $total_kat) : 0;
$avg_respon = $total_kat > 0 ? round(array_sum(array_column($list,'sla_respon_jam')) / $total_kat) : 0;

// Warna ikon per kategori (variasi warna untuk tiap baris)
$icon_palettes = [
    ['#fef3c7','#d97706'], // amber
    ['#dbeafe','#1d4ed8'], // blue
    ['#d1fae5','#065f46'], // green
    ['#ede9fe','#5b21b6'], // violet
    ['#fce7f3','#9d174d'], // pink
    ['#f0fdf4','#15803d'], // emerald
    ['#fff7ed','#c2410c'], // orange
    ['#f0f9ff','#0369a1'], // sky
];

include '../includes/header.php';
?>

<style>
/* ── Modal bulletproof (visibility+opacity, tidak bisa di-override tema) ── */
#katModal {
    visibility: hidden !important;
    opacity: 0 !important;
    position: fixed !important;
    top: 0 !important; left: 0 !important;
    width: 100% !important; height: 100% !important;
    background: rgba(0,0,0,.5) !important;
    z-index: 99999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: opacity .22s ease, visibility .22s ease !important;
    pointer-events: none !important;
}
#katModal.kat-open {
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}
#katModal .kat-box {
    background: #fff !important;
    border-radius: 12px !important;
    width: 100% !important;
    max-width: 500px !important;
    margin: 16px !important;
    box-shadow: 0 12px 48px rgba(0,0,0,.22) !important;
    overflow: hidden !important;
    transform: translateY(22px) scale(.98);
    transition: transform .22s ease !important;
}
#katModal.kat-open .kat-box {
    transform: translateY(0) scale(1) !important;
}
#katModal .kat-hd {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 16px 22px !important;
    border-bottom: 1px solid #f0f0f0 !important;
    background: #fafafa !important;
}
#katModal .kat-hd h5 {
    margin: 0 !important; font-size: 15px !important;
    font-weight: 700 !important; color: #1e293b !important;
    display: flex !important; align-items: center !important; gap: 8px !important;
}
#katModal .kat-close {
    background: none !important; border: none !important;
    font-size: 22px !important; line-height: 1 !important; color: #94a3b8 !important;
    cursor: pointer !important; padding: 0 !important;
    width: 32px !important; height: 32px !important;
    display: flex !important; align-items: center !important; justify-content: center !important;
    border-radius: 6px !important; transition: background .15s, color .15s !important;
}
#katModal .kat-close:hover { background: #fee2e2 !important; color: #dc2626 !important; }
#katModal .kat-body { padding: 22px !important; }
#katModal .kat-foot {
    padding: 14px 22px !important;
    border-top: 1px solid #f0f0f0 !important;
    display: flex !important; gap: 8px !important;
    justify-content: flex-end !important; background: #fafafa !important;
}

/* ── SLA badge di form ── */
.sla-hint {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 10px; font-weight: 700; color: #64748b;
    background: #f1f5f9; border-radius: 4px; padding: 2px 7px;
    margin-top: 4px;
}

/* ── Icon picker grid ── */
.icon-picker {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 6px;
    margin-top: 6px;
}
.icon-picker-item {
    width: 100%; aspect-ratio: 1;
    display: flex; align-items: center; justify-content: center;
    border-radius: 7px; border: 2px solid #e5e7eb;
    background: #f8fafc; cursor: pointer;
    transition: border-color .15s, background .15s, transform .12s;
    font-size: 14px; color: #64748b;
}
.icon-picker-item:hover { border-color: var(--primary,#2563eb); background: #eff6ff; color: var(--primary,#2563eb); transform: scale(1.08); }
.icon-picker-item.selected { border-color: var(--primary,#2563eb) !important; background: #dbeafe !important; color: var(--primary,#2563eb) !important; }

/* ── SLA display in table ── */
.sla-bar {
    display: flex; align-items: center; gap: 6px;
    font-size: 11px; font-weight: 600; color: #475569;
}
.sla-bar .sla-dot {
    width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
}
</style>

<div class="page-header">
    <h4><i class="fa fa-tags text-primary"></i> &nbsp;Kategori Tiket</h4>
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
        <span class="sep">/</span>
        <span class="cur">Kategori</span>
    </div>
</div>

<div class="content">
    <?= showFlash() ?>

    <!-- ── Stats Row (mengikuti pola jabatan.php) ── -->
    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
        <?php foreach([
            [$total_kat,   'Total Kategori',  'fa-tags',          '#f0fdf4','#00c896'],
            [$total_tiket, 'Total Tiket',      'fa-ticket',        '#dbeafe','#1d4ed8'],
            [$avg_sla,     'Rata-rata SLA',    'fa-clock',         '#fef3c7','#d97706'],
            [$avg_respon,  'Rata-rata Respon', 'fa-bolt',          '#ede9fe','#7c3aed'],
        ] as [$val, $lbl, $ico, $bg, $clr]): ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 18px;display:flex;align-items:center;gap:12px;min-width:140px;">
            <div style="width:38px;height:38px;border-radius:9px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa <?= $ico ?>" style="color:<?= $clr ?>;font-size:15px;"></i>
            </div>
            <div>
                <div style="font-size:22px;font-weight:800;color:#111827;line-height:1;">
                    <?= $val ?><?= in_array($lbl, ['Rata-rata SLA','Rata-rata Respon']) ? '<span style="font-size:12px;font-weight:600;color:#9ca3af;">j</span>' : '' ?>
                </div>
                <div style="font-size:11px;color:#9ca3af;margin-top:2px;"><?= $lbl ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Panel Tabel ── -->
    <div class="panel">
        <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;">
            <h5>
                <i class="fa fa-list text-primary"></i> &nbsp;Daftar Kategori
                <span style="color:#aaa;font-weight:400;">(<?= $total_kat ?>)</span>
            </h5>
            <button class="btn btn-primary btn-sm" onclick="katOpen()">
                <i class="fa fa-plus"></i> Tambah Kategori
            </button>
        </div>
        <div class="panel-bd np tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:50px;text-align:center;">Ikon</th>
                        <th>Nama Kategori</th>
                        <th style="width:130px;">Target SLA</th>
                        <th style="width:130px;">Target Respon</th>
                        <th style="width:80px;text-align:center;">Tiket</th>
                        <th style="width:110px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($list)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:#aaa;padding:40px 20px;">
                            <div style="width:52px;height:52px;background:#f1f5f9;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                                <i class="fa fa-tags" style="font-size:22px;color:#cbd5e1;"></i>
                            </div>
                            <div style="font-weight:600;color:#64748b;margin-bottom:4px;">Belum ada kategori</div>
                            <small style="color:#94a3b8;">Klik <strong>+ Tambah Kategori</strong> untuk memulai.</small>
                        </td>
                    </tr>
                    <?php else: foreach ($list as $i => $k):
                        [$pal_bg, $pal_tc] = $icon_palettes[$i % count($icon_palettes)];
                        $has_tiket = (int)$k['total'] > 0;
                        // Warna SLA: hijau <24j, kuning 24-48j, merah >48j
                        $sla_color = $k['sla_jam'] <= 24 ? '#22c55e' : ($k['sla_jam'] <= 48 ? '#f59e0b' : '#ef4444');
                        $res_color = $k['sla_respon_jam'] <= 4 ? '#22c55e' : ($k['sla_respon_jam'] <= 8 ? '#f59e0b' : '#ef4444');
                    ?>
                    <tr>
                        <!-- Ikon -->
                        <td style="text-align:center;">
                            <div style="width:36px;height:36px;background:<?= $pal_bg ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;margin:0 auto;">
                                <i class="fa <?= clean($k['icon']) ?>" style="color:<?= $pal_tc ?>;font-size:15px;"></i>
                            </div>
                        </td>

                        <!-- Nama + deskripsi -->
                        <td>
                            <div style="font-weight:600;font-size:13px;color:#111827;"><?= clean($k['nama']) ?></div>
                            <?php if (!empty($k['deskripsi'])): ?>
                            <div style="font-size:11px;color:#9ca3af;margin-top:2px;"><?= clean($k['deskripsi']) ?></div>
                            <?php endif; ?>
                        </td>

                        <!-- SLA -->
                        <td>
                            <div class="sla-bar">
                                <span class="sla-dot" style="background:<?= $sla_color ?>;"></span>
                                <span style="font-weight:700;color:#1e293b;"><?= $k['sla_jam'] ?></span>
                                <span style="color:#94a3b8;">jam</span>
                            </div>
                        </td>

                        <!-- Respon -->
                        <td>
                            <div class="sla-bar">
                                <span class="sla-dot" style="background:<?= $res_color ?>;"></span>
                                <span style="font-weight:700;color:#1e293b;"><?= $k['sla_respon_jam'] ?></span>
                                <span style="color:#94a3b8;">jam</span>
                            </div>
                        </td>

                        <!-- Jumlah tiket -->
                        <td style="text-align:center;">
                            <?php if ($has_tiket): ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;color:#00c896;">
                                <i class="fa fa-ticket" style="font-size:10px;"></i> <?= $k['total'] ?>
                            </span>
                            <?php else: ?>
                            <span style="color:#e2e8f0;font-size:12px;">0</span>
                            <?php endif; ?>
                        </td>

                        <!-- Aksi -->
                        <td style="text-align:center;white-space:nowrap;">
                            <button class="btn btn-warning btn-sm" onclick='katOpen(<?= json_encode($k) ?>)' title="Edit">
                                <i class="fa fa-edit"></i>
                            </button>

                            <?php if (!$has_tiket): ?>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Hapus kategori <?= addslashes(clean($k['nama'])) ?>?')">
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <button class="btn btn-sm btn-default" disabled
                                title="Tidak dapat dihapus — masih ada <?= $k['total'] ?> tiket">
                                <i class="fa fa-lock" style="color:#d1d5db;"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Legend SLA ── -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 18px;margin-top:10px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
        <span style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px;">
            <i class="fa fa-info-circle" style="color:#64748b;"></i> Keterangan SLA:
        </span>
        <?php foreach([
            ['#22c55e','≤ 24 jam','Cepat'],
            ['#f59e0b','25–48 jam','Normal'],
            ['#ef4444','> 48 jam','Lambat'],
        ] as [$c,$r,$l]): ?>
        <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#475569;">
            <span style="width:8px;height:8px;border-radius:50%;background:<?= $c ?>;"></span>
            <strong><?= $r ?></strong> — <?= $l ?>
        </span>
        <?php endforeach; ?>
    </div>

</div><!-- /.content -->


<!-- ══════════════════════════════════════════════
     MODAL TAMBAH / EDIT KATEGORI
══════════════════════════════════════════════ -->
<div id="katModal">
    <div class="kat-box">
        <div class="kat-hd">
            <h5>
                <i class="fa fa-tags" id="kat-modal-ico" style="color:var(--primary,#2563eb);"></i>
                <span id="katTitle">Tambah Kategori</span>
            </h5>
            <button class="kat-close" onclick="katClose()" title="Tutup">&times;</button>
        </div>

        <div class="kat-body">
            <form method="POST" id="kf">
                <input type="hidden" name="action" value="tambah" id="fa">
                <input type="hidden" name="id" id="fid">
                <input type="hidden" name="icon" id="fi_hidden" value="fa-tag">

                <!-- Nama -->
                <div class="form-group">
                    <label>Nama Kategori <span class="req">*</span></label>
                    <input type="text" name="nama" id="fn" class="form-control" required
                           placeholder="Contoh: Hardware, Jaringan, Email…">
                </div>

                <!-- Deskripsi -->
                <div class="form-group">
                    <label>Deskripsi <span style="font-size:10px;color:#aaa;font-weight:400;">(opsional)</span></label>
                    <textarea name="deskripsi" id="fd" class="form-control" rows="2"
                              placeholder="Uraian singkat kategori…" style="resize:vertical;"></textarea>
                </div>

                <!-- SLA -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Target SLA (jam)</label>
                        <input type="number" name="sla_jam" id="fs" class="form-control" value="24" min="1">
                        <div class="sla-hint"><i class="fa fa-clock"></i> Waktu penyelesaian tiket</div>
                    </div>
                    <div class="form-group">
                        <label>Target Respon (jam)</label>
                        <input type="number" name="sla_respon_jam" id="fr" class="form-control" value="4" min="1">
                        <div class="sla-hint"><i class="fa fa-bolt"></i> Waktu respons pertama</div>
                    </div>
                </div>

                <!-- Icon Picker -->
                <div class="form-group">
                    <label>Pilih Ikon</label>
                    <div class="icon-picker" id="iconPicker">
                        <?php foreach ($icons as $ic): ?>
                        <div class="icon-picker-item <?= $ic==='fa-tag'?'selected':'' ?>"
                             data-icon="<?= $ic ?>" onclick="pickIcon(this)"
                             title="<?= $ic ?>">
                            <i class="fa <?= $ic ?>"></i>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </form>
        </div>

        <div class="kat-foot">
            <button type="button" class="btn btn-default" onclick="katClose()">
                <i class="fa fa-times"></i> Batal
            </button>
            <button type="submit" form="kf" class="btn btn-primary" id="kat-btn">
                <i class="fa fa-save"></i> Simpan
            </button>
        </div>
    </div>
</div>

<script>
var _kat = document.getElementById('katModal');

/* ── Buka modal ── */
function katOpen(k) {
    if (k) {
        // Mode edit
        document.getElementById('katTitle').textContent  = 'Edit: ' + k.nama;
        document.getElementById('kat-modal-ico').style.color = 'var(--orange,#f59e0b)';
        document.getElementById('fa').value   = 'edit';
        document.getElementById('fid').value  = k.id;
        document.getElementById('fn').value   = k.nama;
        document.getElementById('fd').value   = k.deskripsi || '';
        document.getElementById('fs').value   = k.sla_jam;
        document.getElementById('fr').value   = k.sla_respon_jam;
        document.getElementById('kat-btn').innerHTML = '<i class="fa fa-save"></i> Update';
        selectIcon(k.icon || 'fa-tag');
    } else {
        // Mode tambah
        document.getElementById('katTitle').textContent  = 'Tambah Kategori';
        document.getElementById('kat-modal-ico').style.color = 'var(--primary,#2563eb)';
        document.getElementById('fa').value   = 'tambah';
        document.getElementById('fid').value  = '';
        document.getElementById('fn').value   = '';
        document.getElementById('fd').value   = '';
        document.getElementById('fs').value   = 24;
        document.getElementById('fr').value   = 4;
        document.getElementById('kat-btn').innerHTML = '<i class="fa fa-save"></i> Simpan';
        selectIcon('fa-tag');
    }
    _kat.classList.add('kat-open');
    setTimeout(function(){ document.getElementById('fn').focus(); }, 80);
}

/* ── Tutup modal ── */
function katClose() {
    _kat.classList.remove('kat-open');
}

/* ── Pilih ikon dari grid ── */
function pickIcon(el) {
    document.querySelectorAll('.icon-picker-item').forEach(function(i){ i.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('fi_hidden').value = el.dataset.icon;
}
function selectIcon(iconVal) {
    document.querySelectorAll('.icon-picker-item').forEach(function(i){
        i.classList.toggle('selected', i.dataset.icon === iconVal);
    });
    document.getElementById('fi_hidden').value = iconVal;
}

/* ── Backdrop & Escape ── */
_kat.addEventListener('click', function(e) { if (e.target === _kat) katClose(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') katClose(); });
</script>

<?php include '../includes/footer.php'; ?>