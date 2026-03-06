<?php
// pages/import_aset_it.php
// Import Aset IT dari file Excel (.xlsx / .xls / .csv)
// Flow: Upload → Preview → Konfirmasi → Simpan

session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'teknisi'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

// ── Download template ─────────────────────────────────────────────────────────
if (isset($_GET['download_template'])) {
    $tpl = __DIR__ . '/../assets/template_aset_it.xlsx';
    // fallback: generate on-the-fly jika file tidak ada
    if (!file_exists($tpl)) {
        // Kirim template sederhana pure PHP
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="template_aset_it.xlsx"');
        // Baca dari folder yang sama dengan script ini
        $tpl2 = __DIR__ . '/template_aset_it.xlsx';
        if (file_exists($tpl2)) { readfile($tpl2); exit; }
    } else {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="template_aset_it.xlsx"');
        readfile($tpl); exit;
    }
}

$page_title  = 'Import Aset IT';
$active_menu = 'aset_it';

// ── Mapping header Excel → kolom DB ──────────────────────────────────────────
$HEADER_MAP = [
    'no_inventaris'    => ['no. inventaris', 'no inventaris', 'nomor inventaris', 'no_inventaris', 'inventaris'],
    'nama_aset'        => ['nama aset', 'nama_aset', 'nama', 'aset'],
    'kategori'         => ['kategori', 'category'],
    'merek'            => ['merek', 'brand', 'merk'],
    'model_aset'       => ['model', 'model aset', 'tipe', 'model_aset', 'model / tipe'],
    'serial_number'    => ['serial number', 'serial_number', 'sn', 'no seri', 'serial'],
    'kondisi'          => ['kondisi', 'condition'],
    'status_pakai'     => ['status pemakaian', 'status pakai', 'status_pakai', 'status'],
    'lokasi'           => ['lokasi', 'lokasi / bagian', 'bagian', 'ruangan', 'location'],
    'penanggung_jawab' => ['penanggung jawab', 'penanggung_jawab', 'pj', 'pic', 'pengguna'],
    'tanggal_beli'     => ['tanggal beli', 'tgl beli', 'tanggal_beli', 'tgl_beli', 'purchase date'],
    'harga_beli'       => ['harga beli', 'harga', 'harga_beli', 'harga beli (rp)', 'price'],
    'garansi_sampai'   => ['garansi sampai', 'garansi s/d', 'garansi_sampai', 'warranty'],
    'keterangan'       => ['keterangan', 'catatan', 'notes', 'note'],
];

$VALID_KONDISI = ['Baik', 'Dalam Perbaikan', 'Rusak', 'Tidak Aktif'];
$VALID_STATUS  = ['Terpakai', 'Tidak Terpakai', 'Dipinjam'];

// ── Helper: parse nilai kondisi/status (toleran) ──────────────────────────────
function matchKondisi(string $val, array $valid): string {
    $val = trim($val);
    foreach ($valid as $v) {
        if (strtolower($val) === strtolower($v)) return $v;
    }
    // fuzzy: "baik" → "Baik", "perbaikan" → "Dalam Perbaikan"
    $lower = strtolower($val);
    if (str_contains($lower, 'perbaikan')) return 'Dalam Perbaikan';
    if (str_contains($lower, 'rusak'))     return 'Rusak';
    if (str_contains($lower, 'tidak aktif') || str_contains($lower, 'tidak_aktif')) return 'Tidak Aktif';
    if (str_contains($lower, 'dipinjam'))  return 'Dipinjam';
    if (str_contains($lower, 'tidak terpakai') || str_contains($lower, 'tidak_terpakai')) return 'Tidak Terpakai';
    if (str_contains($lower, 'baik'))      return 'Baik';
    return ''; // tidak dikenali
}

// ── Helper: parse tanggal fleksibel ──────────────────────────────────────────
function parseDate($val): ?string {
    if (!$val || trim((string)$val) === '') return null;
    $s = trim((string)$val);
    // Excel serial number
    if (is_numeric($s) && (int)$s > 40000 && (int)$s < 60000) {
        $unix = ((int)$s - 25569) * 86400;
        return date('Y-m-d', $unix);
    }
    // DD/MM/YYYY or DD-MM-YYYY
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $s, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    // YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
    // PHP strtotime fallback
    $t = strtotime($s);
    return $t ? date('Y-m-d', $t) : null;
}

// ── Helper: parse angka harga ─────────────────────────────────────────────────
function parseHarga($val): ?int {
    if ($val === null || $val === '') return null;
    $s = preg_replace('/[^0-9]/', '', (string)$val);
    return $s !== '' ? (int)$s : null;
}

// ══════════════════════════════════════════════════════════════════════════════
// AKSI: KONFIRMASI SIMPAN (dari form preview)
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'simpan_import') {
    $rows_json = $_POST['rows_json'] ?? '[]';
    $rows      = json_decode($rows_json, true) ?: [];
    $mode      = $_POST['mode_duplikat'] ?? 'skip';   // skip | update

    $inserted = 0; $updated = 0; $skipped = 0; $errors = [];

    foreach ($rows as $i => $r) {
        if (!empty($r['_skip'])) { $skipped++; continue; }
        $no_inv   = trim($r['no_inventaris']   ?? '');
        $nama     = trim($r['nama_aset']        ?? '');
        $kondisi  = $r['kondisi']  ?: 'Baik';
        $sp       = $r['status_pakai'] ?: 'Terpakai';
        if (!$no_inv || !$nama) { $errors[] = "Baris ".($i+1).": No. Inventaris / Nama kosong."; continue; }

        // Cek duplikat
        $cek = $pdo->prepare("SELECT id FROM aset_it WHERE no_inventaris = ?");
        $cek->execute([$no_inv]);
        $exist_id = $cek->fetchColumn();

        $tgl_beli    = $r['tanggal_beli']   ?: null;
        $garansi     = $r['garansi_sampai'] ?: null;
        $harga       = isset($r['harga_beli']) && $r['harga_beli'] !== '' ? (int)$r['harga_beli'] : null;
        $keterangan  = $r['keterangan']     ?? '';

        if ($exist_id) {
            if ($mode === 'update') {
                $pdo->prepare("UPDATE aset_it SET
                    nama_aset=?,kategori=?,merek=?,model_aset=?,serial_number=?,kondisi=?,status_pakai=?,
                    lokasi=?,penanggung_jawab=?,tanggal_beli=?,harga_beli=?,garansi_sampai=?,keterangan=?,updated_at=NOW()
                    WHERE id=?")
                    ->execute([$nama,$r['kategori']??'',$r['merek']??'',$r['model_aset']??'',$r['serial_number']??'',
                               $kondisi,$sp,$r['lokasi']??'',$r['penanggung_jawab']??'',
                               $tgl_beli,$harga,$garansi,$keterangan,$exist_id]);
                $updated++;
            } else {
                $skipped++;
            }
        } else {
            $pdo->prepare("INSERT INTO aset_it
                (no_inventaris,nama_aset,kategori,merek,model_aset,serial_number,kondisi,status_pakai,
                 lokasi,penanggung_jawab,tanggal_beli,harga_beli,garansi_sampai,keterangan,created_by,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([$no_inv,$nama,$r['kategori']??'',$r['merek']??'',$r['model_aset']??'',$r['serial_number']??'',
                           $kondisi,$sp,$r['lokasi']??'',$r['penanggung_jawab']??'',
                           $tgl_beli,$harga,$garansi,$keterangan,$_SESSION['user_id']]);
            $inserted++;
        }
    }

    $msg = "Import selesai: <strong>$inserted</strong> ditambah, <strong>$updated</strong> diperbarui, <strong>$skipped</strong> dilewati.";
    if ($errors) $msg .= ' <br><span style="color:#ef4444;">'.count($errors).' error: '.implode('; ', array_slice($errors,0,3)).'</span>';
    setFlash('success', $msg);
    redirect(APP_URL . '/pages/aset_it.php');
}

// ══════════════════════════════════════════════════════════════════════════════
// AKSI: UPLOAD & PARSE FILE
// ══════════════════════════════════════════════════════════════════════════════
$preview_rows   = [];
$parse_errors   = [];
$parse_warnings = [];
$col_map        = [];
$file_uploaded  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'upload') {
    $file = $_FILES['file_import'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $parse_errors[] = 'File gagal diupload. Periksa ukuran / format file.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            $parse_errors[] = 'Format file tidak didukung. Gunakan .xlsx, .xls, atau .csv.';
        } else {
            $tmp = $file['tmp_name'];

            // ── Parse CSV ──
            if ($ext === 'csv') {
                $rows_raw = [];
                if (($fh = fopen($tmp, 'r')) !== false) {
                    while (($line = fgetcsv($fh, 2000, ',')) !== false) $rows_raw[] = $line;
                    fclose($fh);
                }
            }
            // ── Parse XLSX/XLS menggunakan ZipArchive (pure PHP) ──
            else {
                $rows_raw = [];
                try {
                    // Copy tmp ke file dengan ekstensi proper
                    $tmpxlsx = sys_get_temp_dir() . '/aset_import_' . uniqid() . '.xlsx';
                    copy($tmp, $tmpxlsx);

                    $zip = new ZipArchive();
                    if ($zip->open($tmpxlsx) !== true) throw new Exception("Gagal membuka file Excel.");

                    // Ambil shared strings
                    $sst = [];
                    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
                    if ($ssXml) {
                        $sx = simplexml_load_string($ssXml);
                        if ($sx) {
                            foreach ($sx->si as $si) {
                                // Gabungkan semua <t> dalam satu <si>
                                $text = '';
                                if (isset($si->t)) {
                                    $text = (string)$si->t;
                                } elseif (isset($si->r)) {
                                    foreach ($si->r as $r) $text .= (string)($r->t ?? '');
                                }
                                $sst[] = $text;
                            }
                        }
                    }

                    // Ambil sheet 1
                    $sheet1 = null;
                    $wbXml = $zip->getFromName('xl/workbook.xml');
                    if ($wbXml) {
                        $wb2 = simplexml_load_string($wbXml);
                        $ns  = $wb2->getNamespaces(true);
                        // sheet pertama saja
                        $sheets = $wb2->sheets->sheet ?? [];
                        if (count($sheets) > 0) {
                            // Dapatkan r:id
                            $rid = null;
                            foreach ($ns as $prefix => $uri) {
                                if (str_contains($uri, 'relationships')) {
                                    $rid = (string)$sheets[0]->attributes($uri)['id'];
                                    break;
                                }
                            }
                            if (!$rid) $rid = (string)$sheets[0]->attributes('r', true)['id'];
                            // Cari target di workbook.xml.rels
                            $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
                            if ($relsXml && $rid) {
                                $rels = simplexml_load_string($relsXml);
                                foreach ($rels->Relationship as $rel) {
                                    if ((string)$rel['Id'] === $rid) {
                                        $target = 'xl/' . ltrim((string)$rel['Target'], '/');
                                        $sheet1 = $zip->getFromName($target);
                                        break;
                                    }
                                }
                            }
                            // fallback
                            if (!$sheet1) $sheet1 = $zip->getFromName('xl/worksheets/sheet1.xml');
                        }
                    }

                    if ($sheet1) {
                        $sx2 = simplexml_load_string($sheet1);
                        $data_rows = [];
                        foreach ($sx2->sheetData->row as $row) {
                            $r_idx = (int)$row['r'];
                            $data_rows[$r_idx] = [];
                            foreach ($row->c as $c) {
                                $ref  = (string)$c['r'];
                                // Ambil col letter
                                preg_match('/^([A-Z]+)/', $ref, $cm);
                                $cl   = $cm[1] ?? 'A';
                                $ci   = 0;
                                for ($k = 0; $k < strlen($cl); $k++)
                                    $ci = $ci * 26 + (ord($cl[$k]) - 64);

                                $t    = (string)$c['t'];
                                $v    = (string)($c->v ?? '');
                                if ($t === 's') $val = $sst[(int)$v] ?? '';
                                elseif ($t === 'str' || $t === 'inlineStr') $val = (string)($c->is->t ?? $c->v ?? '');
                                else $val = $v;
                                $data_rows[$r_idx][$ci] = $val;
                            }
                        }
                        // Normalise ke array berurutan
                        ksort($data_rows);
                        foreach ($data_rows as $r_idx => $cols) {
                            if (!$cols) continue;
                            $max_col = max(array_keys($cols));
                            $row_arr = [];
                            for ($ci = 1; $ci <= $max_col; $ci++) $row_arr[] = $cols[$ci] ?? '';
                            $rows_raw[] = $row_arr;
                        }
                    }
                    $zip->close();
                    @unlink($tmpxlsx);
                } catch (Exception $e) {
                    $parse_errors[] = 'Gagal parse Excel: ' . $e->getMessage();
                }
            }

            // ── Deteksi baris header ──────────────────────────────────────────
            if (!$parse_errors && $rows_raw) {
                $header_row_idx = -1;
                $header_cols    = [];
                foreach ($rows_raw as $ri => $row) {
                    $matched = 0;
                    $tmp_map = [];
                    foreach ($row as $ci => $cell) {
                        $cell_l = strtolower(trim((string)$cell));
                        foreach ($HEADER_MAP as $dbcol => $aliases) {
                            if (in_array($cell_l, $aliases)) {
                                $tmp_map[$dbcol] = $ci;
                                $matched++;
                                break;
                            }
                        }
                    }
                    if ($matched >= 2) { // minimal 2 kolom dikenali
                        $header_row_idx = $ri;
                        $col_map        = $tmp_map;
                        break;
                    }
                }
                if ($header_row_idx === -1) {
                    $parse_errors[] = 'Header kolom tidak dikenali. Pastikan menggunakan template yang disediakan.';
                } else {
                    // Cek kolom wajib
                    foreach (['no_inventaris', 'nama_aset'] as $req) {
                        if (!isset($col_map[$req])) $parse_errors[] = "Kolom wajib <strong>$req</strong> tidak ditemukan di file.";
                    }
                }

                // ── Parse data rows ──────────────────────────────────────────
                if (!$parse_errors) {
                    $no_inv_seen = [];
                    // Ambil semua no_inventaris yg sudah ada di DB
                    $exist_set = [];
                    foreach ($pdo->query("SELECT no_inventaris FROM aset_it")->fetchAll(PDO::FETCH_COLUMN) as $n)
                        $exist_set[strtolower($n)] = true;

                    for ($ri = $header_row_idx + 1; $ri < count($rows_raw); $ri++) {
                        $row = $rows_raw[$ri];
                        $get = fn($col) => isset($col_map[$col]) ? trim((string)($row[$col_map[$col]] ?? '')) : '';

                        $no_inv  = $get('no_inventaris');
                        $nama    = $get('nama_aset');

                        // Skip baris kosong / baris hint
                        if (!$no_inv && !$nama) continue;
                        // Skip baris yang kemungkinan baris panduan (teks panjang di kolom 1)
                        if (!$no_inv && strlen($nama) > 60) continue;

                        $warn = [];
                        // Kondisi
                        $kondisi_raw = $get('kondisi');
                        $kondisi = matchKondisi($kondisi_raw, $VALID_KONDISI);
                        if (!$kondisi) { $kondisi = 'Baik'; if ($kondisi_raw) $warn[] = "Kondisi '$kondisi_raw' tidak dikenal → default 'Baik'"; }

                        // Status Pakai
                        $sp_raw = $get('status_pakai');
                        $sp = matchKondisi($sp_raw, $VALID_STATUS);
                        if (!$sp) { $sp = 'Terpakai'; if ($sp_raw) $warn[] = "Status '$sp_raw' tidak dikenal → default 'Terpakai'"; }

                        // Duplikat dalam file
                        $dup_in_file = isset($no_inv_seen[strtolower($no_inv)]);
                        if ($no_inv) $no_inv_seen[strtolower($no_inv)] = true;

                        $in_db = isset($exist_set[strtolower($no_inv)]);

                        $parsed = [
                            'no_inventaris'    => $no_inv,
                            'nama_aset'        => $nama,
                            'kategori'         => $get('kategori'),
                            'merek'            => $get('merek'),
                            'model_aset'       => $get('model_aset'),
                            'serial_number'    => $get('serial_number'),
                            'kondisi'          => $kondisi,
                            'status_pakai'     => $sp,
                            'lokasi'           => $get('lokasi'),
                            'penanggung_jawab' => $get('penanggung_jawab'),
                            'tanggal_beli'     => parseDate($get('tanggal_beli')),
                            'harga_beli'       => parseHarga($get('harga_beli')),
                            'garansi_sampai'   => parseDate($get('garansi_sampai')),
                            'keterangan'       => $get('keterangan'),
                            '_row'             => $ri + 1,
                            '_warn'            => $warn,
                            '_in_db'           => $in_db,
                            '_dup_file'        => $dup_in_file,
                            '_invalid'         => (!$no_inv || !$nama),
                            '_skip'            => false,
                        ];
                        $preview_rows[] = $parsed;
                    }
                    $file_uploaded = true;
                    if (!$preview_rows) $parse_errors[] = 'Tidak ada data yang dapat dibaca dari file. Pastikan file tidak kosong.';
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<style>
.imp-card{background:#fff;border-radius:10px;border:1px solid #e8ecf0;padding:24px;margin-bottom:18px;}
.imp-step{display:flex;align-items:center;gap:12px;margin-bottom:20px;}
.imp-step-num{width:28px;height:28px;border-radius:50%;background:#26B99A;color:#fff;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.imp-step-num.done{background:#d1fae5;color:#065f46;}
.imp-step-num.inactive{background:#f1f5f9;color:#94a3b8;}
.imp-step-label{font-weight:700;font-size:13px;color:#1e293b;}
.imp-step-sub{font-size:11px;color:#94a3b8;margin-top:1px;}

.imp-drop{border:2px dashed #d1d5db;border-radius:10px;padding:40px 20px;text-align:center;cursor:pointer;transition:all .2s;background:#fafafa;}
.imp-drop:hover,.imp-drop.drag{border-color:#26B99A;background:#f0fdf9;}
.imp-drop i{font-size:40px;color:#94a3b8;margin-bottom:12px;}
.imp-drop.drag i{color:#26B99A;}

.prev-table th{background:#1e3a5f;color:#fff;font-size:11px;padding:7px 10px;white-space:nowrap;position:sticky;top:0;z-index:2;}
.prev-table td{font-size:11.5px;padding:6px 10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
.prev-table tr:hover td{background:#f8fafc;}
.prev-table tr.row-warn td{background:#fffbeb !important;}
.prev-table tr.row-error td{background:#fff1f2 !important;opacity:.75;}
.prev-table tr.row-indb td{background:#eff6ff !important;}
.prev-table tr.row-dup  td{background:#fdf4ff !important;}

.badge-new   {background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:9px;font-size:10px;font-weight:700;}
.badge-indb  {background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:9px;font-size:10px;font-weight:700;}
.badge-dup   {background:#f3e8ff;color:#6b21a8;padding:2px 8px;border-radius:9px;font-size:10px;font-weight:700;}
.badge-err   {background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:9px;font-size:10px;font-weight:700;}

.mode-opt{border:2px solid #e2e8f0;border-radius:8px;padding:12px 16px;cursor:pointer;transition:all .15s;display:flex;align-items:flex-start;gap:12px;}
.mode-opt:hover{border-color:#26B99A;}
.mode-opt input[type=radio]:checked ~ .mode-content .mode-title{color:#26B99A;}
</style>

<div class="page-header">
  <h4><i class="fa fa-file-import text-primary"></i> &nbsp;Import Aset IT</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <a href="<?= APP_URL ?>/pages/aset_it.php">Aset IT</a>
    <span class="sep">/</span>
    <span class="cur">Import</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <?php if (!$file_uploaded): ?>
  <!-- ══════════════════════════════════════════════
       STEP 1: UPLOAD
  ══════════════════════════════════════════════════ -->
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;align-items:start;">

    <div class="imp-card">
      <div class="imp-step">
        <div class="imp-step-num">1</div>
        <div><div class="imp-step-label">Upload File Excel</div><div class="imp-step-sub">Pilih file .xlsx, .xls, atau .csv berisi data aset</div></div>
      </div>

      <?php if ($parse_errors): ?>
      <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:7px;padding:12px 16px;margin-bottom:16px;">
        <div style="font-weight:700;color:#991b1b;margin-bottom:4px;"><i class="fa fa-circle-exclamation"></i> Gagal memproses file:</div>
        <?php foreach ($parse_errors as $e): ?><div style="font-size:12px;color:#b91c1c;">• <?= $e ?></div><?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" id="form-upload">
        <input type="hidden" name="_action" value="upload">

        <div class="imp-drop" id="drop-zone" onclick="document.getElementById('file-input').click()">
          <div><i class="fa fa-cloud-arrow-up" id="drop-icon"></i></div>
          <div id="drop-label" style="font-weight:700;color:#374151;margin-bottom:6px;">Klik atau seret file ke sini</div>
          <div style="font-size:12px;color:#94a3b8;">Format: <strong>.xlsx</strong>, .xls, .csv &nbsp;·&nbsp; Maks. 5 MB</div>
          <div id="file-chosen" style="margin-top:10px;font-size:12px;color:#26B99A;font-weight:700;display:none;"></div>
        </div>
        <input type="file" name="file_import" id="file-input" accept=".xlsx,.xls,.csv" style="display:none;" onchange="onFileChosen(this)">

        <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;">
          <a href="<?= APP_URL ?>/pages/aset_it.php" class="btn btn-default">
            <i class="fa fa-arrow-left"></i> Kembali
          </a>
          <button type="submit" id="btn-upload" class="btn btn-primary" disabled>
            <i class="fa fa-magnifying-glass"></i> Preview Data
          </button>
        </div>
      </form>
    </div>

    <!-- Sidebar: Download Template + Panduan -->
    <div>
      <div class="imp-card" style="margin-bottom:14px;">
        <div style="font-weight:700;color:#1e293b;margin-bottom:10px;font-size:13px;"><i class="fa fa-file-excel" style="color:#16a34a;"></i> Template Excel</div>
        <p style="font-size:12px;color:#64748b;margin:0 0 12px;">Gunakan template ini agar format kolom sesuai dan proses import berjalan lancar.</p>
        <a href="<?= APP_URL ?>/pages/import_aset_it.php?download_template=1"
           class="btn btn-default btn-sm"
           style="border-color:#16a34a;color:#15803d;font-weight:600;width:100%;justify-content:center;display:flex;align-items:center;gap:6px;">
          <i class="fa fa-download"></i> Download Template .xlsx
        </a>
      </div>
      <div class="imp-card">
        <div style="font-weight:700;color:#1e293b;margin-bottom:10px;font-size:13px;"><i class="fa fa-circle-info" style="color:#26B99A;"></i> Ketentuan Import</div>
        <?php foreach ([
            ['fa-check-circle','#22c55e','Kolom <strong>No. Inventaris</strong> & <strong>Nama Aset</strong> wajib diisi'],
            ['fa-check-circle','#22c55e','Kondisi: Baik / Dalam Perbaikan / Rusak / Tidak Aktif'],
            ['fa-check-circle','#22c55e','Status: Terpakai / Tidak Terpakai / Dipinjam'],
            ['fa-check-circle','#22c55e','Tanggal format: YYYY-MM-DD (contoh: 2023-06-15)'],
            ['fa-info-circle', '#f59e0b','Baris tanpa No. Inventaris & Nama akan dilewati'],
            ['fa-info-circle', '#f59e0b','No. Inventaris duplikat: bisa skip atau update'],
        ] as [$ico,$col,$txt]): ?>
        <div style="display:flex;gap:8px;margin-bottom:6px;font-size:11.5px;color:#374151;">
          <i class="fa <?= $ico ?>" style="color:<?= $col ?>;margin-top:2px;flex-shrink:0;"></i>
          <span><?= $txt ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- ══════════════════════════════════════════════
       STEP 2: PREVIEW & KONFIRMASI
  ══════════════════════════════════════════════════ -->
  <?php
  $cnt_new   = count(array_filter($preview_rows, fn($r)=>!$r['_in_db']&&!$r['_invalid']&&!$r['_dup_file']));
  $cnt_indb  = count(array_filter($preview_rows, fn($r)=>$r['_in_db']));
  $cnt_err   = count(array_filter($preview_rows, fn($r)=>$r['_invalid']));
  $cnt_dup   = count(array_filter($preview_rows, fn($r)=>$r['_dup_file']));
  $cnt_warn  = count(array_filter($preview_rows, fn($r)=>!empty($r['_warn'])));
  ?>

  <div class="imp-card" style="margin-bottom:14px;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
      <div>
        <div style="font-size:14px;font-weight:700;color:#1e293b;margin-bottom:4px;"><i class="fa fa-table-list" style="color:#26B99A;"></i> Preview Data — <?= count($preview_rows) ?> baris ditemukan</div>
        <div style="font-size:12px;color:#64748b;">Periksa data di bawah sebelum mengkonfirmasi import.</div>
      </div>
      <!-- Summary badges -->
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:7px;padding:6px 12px;text-align:center;">
          <div style="font-size:18px;font-weight:800;color:#065f46;"><?= $cnt_new ?></div>
          <div style="font-size:10px;color:#6b7280;">Baru</div>
        </div>
        <div style="background:#dbeafe;border:1px solid #93c5fd;border-radius:7px;padding:6px 12px;text-align:center;">
          <div style="font-size:18px;font-weight:800;color:#1e40af;"><?= $cnt_indb ?></div>
          <div style="font-size:10px;color:#6b7280;">Ada di DB</div>
        </div>
        <?php if ($cnt_err): ?>
        <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:7px;padding:6px 12px;text-align:center;">
          <div style="font-size:18px;font-weight:800;color:#991b1b;"><?= $cnt_err ?></div>
          <div style="font-size:10px;color:#6b7280;">Error</div>
        </div>
        <?php endif; ?>
        <?php if ($cnt_warn): ?>
        <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:7px;padding:6px 12px;text-align:center;">
          <div style="font-size:18px;font-weight:800;color:#92400e;"><?= $cnt_warn ?></div>
          <div style="font-size:10px;color:#6b7280;">Peringatan</div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Opsi Duplikat & Mode -->
  <form method="POST" id="form-konfirmasi">
    <input type="hidden" name="_action" value="simpan_import">
    <input type="hidden" name="rows_json" id="rows-json-input" value="">

    <div class="imp-card" style="margin-bottom:14px;">
      <div style="font-weight:700;color:#1e293b;margin-bottom:12px;font-size:13px;"><i class="fa fa-sliders" style="color:#26B99A;"></i> Opsi Import</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <label class="mode-opt" id="opt-skip" style="border-color:#26B99A;background:#f0fdf9;" onclick="setMode('skip')">
          <div style="width:18px;height:18px;border-radius:50%;border:2px solid #26B99A;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;" id="radio-skip">
            <div style="width:10px;height:10px;border-radius:50%;background:#26B99A;"></div>
          </div>
          <div>
            <div style="font-weight:700;font-size:12.5px;color:#1e293b;">Lewati jika sudah ada</div>
            <div style="font-size:11px;color:#64748b;margin-top:2px;">No. Inventaris yang sudah ada di database tidak akan diubah</div>
          </div>
        </label>
        <label class="mode-opt" id="opt-update" onclick="setMode('update')">
          <div style="width:18px;height:18px;border-radius:50%;border:2px solid #d1d5db;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;" id="radio-update">
          </div>
          <div>
            <div style="font-weight:700;font-size:12.5px;color:#1e293b;">Update jika sudah ada</div>
            <div style="font-size:11px;color:#64748b;margin-top:2px;">Data lama akan ditimpa dengan data dari file Excel</div>
          </div>
        </label>
      </div>
    </div>
    <input type="hidden" name="mode_duplikat" id="mode-duplikat" value="skip">

    <!-- Legend -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;font-size:11px;align-items:center;">
      <span style="color:#64748b;font-weight:700;">Keterangan warna:</span>
      <span><span style="display:inline-block;width:12px;height:12px;background:#d1fae5;border-radius:2px;"></span> Baru (akan ditambah)</span>
      <span><span style="display:inline-block;width:12px;height:12px;background:#dbeafe;border-radius:2px;"></span> Sudah ada di DB (skip/update)</span>
      <span><span style="display:inline-block;width:12px;height:12px;background:#fef3c7;border-radius:2px;"></span> Ada peringatan</span>
      <?php if ($cnt_err): ?><span><span style="display:inline-block;width:12px;height:12px;background:#fee2e2;border-radius:2px;"></span> Error (akan dilewati)</span><?php endif; ?>
    </div>

    <!-- Tabel Preview -->
    <div style="overflow-x:auto;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:16px;">
      <table class="prev-table" style="width:100%;border-collapse:collapse;">
        <thead>
          <tr>
            <th style="width:40px;">#</th>
            <th>Status</th>
            <th>No. Inventaris</th>
            <th>Nama Aset</th>
            <th>Kategori</th>
            <th>Merek</th>
            <th>Model</th>
            <th>Kondisi</th>
            <th>Status Pakai</th>
            <th>Lokasi</th>
            <th>PJ</th>
            <th>Tgl Beli</th>
            <th>Garansi s/d</th>
            <th>Peringatan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($preview_rows as $i => $r):
            $row_class = '';
            if ($r['_invalid'])  $row_class = 'row-error';
            elseif ($r['_dup_file']) $row_class = 'row-dup';
            elseif ($r['_in_db'])    $row_class = 'row-indb';
            elseif (!empty($r['_warn'])) $row_class = 'row-warn';
          ?>
          <tr class="<?= $row_class ?>">
            <td style="color:#94a3b8;text-align:center;"><?= $r['_row'] ?></td>
            <td>
              <?php if ($r['_invalid']): ?>
                <span class="badge-err"><i class="fa fa-xmark"></i> Error</span>
              <?php elseif ($r['_dup_file']): ?>
                <span class="badge-dup"><i class="fa fa-copy"></i> Dup. File</span>
              <?php elseif ($r['_in_db']): ?>
                <span class="badge-indb"><i class="fa fa-database"></i> Ada di DB</span>
              <?php else: ?>
                <span class="badge-new"><i class="fa fa-plus"></i> Baru</span>
              <?php endif; ?>
            </td>
            <td style="font-family:monospace;font-size:11px;">
              <?php if (!$r['no_inventaris']): ?><span style="color:#ef4444;">— kosong —</span>
              <?php else: ?><strong><?= clean($r['no_inventaris']) ?></strong><?php endif; ?>
            </td>
            <td><?php if (!$r['nama_aset']): ?><span style="color:#ef4444;">— kosong —</span>
              <?php else: ?><?= clean($r['nama_aset']) ?><?php endif; ?></td>
            <td style="color:#64748b;"><?= clean($r['kategori']??'') ?></td>
            <td style="color:#64748b;"><?= clean($r['merek']??'') ?></td>
            <td style="color:#64748b;font-size:11px;"><?= clean($r['model_aset']??'') ?></td>
            <td>
              <?php
              $kmap=['Baik'=>'#dcfce7:#166534','Rusak'=>'#fee2e2:#991b1b','Dalam Perbaikan'=>'#fef9c3:#854d0e','Tidak Aktif'=>'#f1f5f9:#64748b'];
              [$kbg,$kfg]=explode(':',$kmap[$r['kondisi']]??'#f1f5f9:#64748b');
              ?>
              <span style="background:<?=$kbg?>;color:<?=$kfg?>;padding:2px 7px;border-radius:9px;font-size:10px;font-weight:700;"><?= clean($r['kondisi']) ?></span>
            </td>
            <td>
              <?php
              $smap=['Terpakai'=>'#dbeafe:#1e40af','Tidak Terpakai'=>'#d1fae5:#065f46','Dipinjam'=>'#fef3c7:#92400e'];
              [$sbg,$sfg]=explode(':',$smap[$r['status_pakai']]??'#dbeafe:#1e40af');
              ?>
              <span style="background:<?=$sbg?>;color:<?=$sfg?>;padding:2px 7px;border-radius:9px;font-size:10px;font-weight:700;"><?= clean($r['status_pakai']) ?></span>
            </td>
            <td style="font-size:11px;color:#64748b;"><?= clean($r['lokasi']??'') ?></td>
            <td style="font-size:11px;"><?= clean($r['penanggung_jawab']??'') ?></td>
            <td style="font-size:11px;color:#94a3b8;white-space:nowrap;"><?= $r['tanggal_beli']??'' ?></td>
            <td style="font-size:11px;color:#94a3b8;white-space:nowrap;"><?= $r['garansi_sampai']??'' ?></td>
            <td style="font-size:10.5px;">
              <?php if ($r['_invalid']): ?>
                <span style="color:#ef4444;">No. Inv / Nama wajib diisi</span>
              <?php elseif ($r['_dup_file']): ?>
                <span style="color:#7c3aed;">Duplikat dalam file</span>
              <?php elseif (!empty($r['_warn'])): ?>
                <span style="color:#d97706;"><?= implode('<br>', array_map('clean', $r['_warn'])) ?></span>
              <?php else: ?>
                <span style="color:#22c55e;">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Footer Aksi -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <a href="<?= APP_URL ?>/pages/import_aset_it.php" class="btn btn-default">
        <i class="fa fa-arrow-left"></i> Upload Ulang
      </a>
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="font-size:12px;color:#64748b;">
          Akan diproses: <strong style="color:#1e293b;"><?= $cnt_new + $cnt_indb ?></strong> baris
          <?php if ($cnt_err+$cnt_dup): ?> (<span style="color:#ef4444;"><?= $cnt_err+$cnt_dup ?> dilewati</span>)<?php endif; ?>
        </div>
        <?php if ($cnt_new + $cnt_indb > 0): ?>
        <button type="button" onclick="konfirmasi()" class="btn btn-primary"
          style="background:linear-gradient(135deg,#26B99A,#1a7a5e);">
          <i class="fa fa-check"></i> Konfirmasi & Import
        </button>
        <?php else: ?>
        <button type="button" disabled class="btn btn-default">Tidak ada data valid</button>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <?php endif; ?>
</div>


<!-- ════════════════════════════════════════════════
     MODAL KONFIRMASI IMPORT
════════════════════════════════════════════════════ -->
<div class="modal-ov" id="m-konfirmasi" style="align-items:center;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:420px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">
    <div style="padding:20px 22px 14px;text-align:center;">
      <div style="width:52px;height:52px;background:#d1fae5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
        <i class="fa fa-file-import" style="color:#16a34a;font-size:22px;"></i>
      </div>
      <div style="font-size:15px;font-weight:700;color:#1e293b;">Konfirmasi Import</div>
      <div style="font-size:12.5px;color:#64748b;margin-top:8px;" id="konfirmasi-text">
        Proses ini tidak dapat dibatalkan setelah berjalan.
      </div>
    </div>
    <div style="padding:12px 20px 16px;display:flex;gap:8px;justify-content:center;border-top:1px solid #f0f0f0;">
      <button type="button" onclick="closeModal('m-konfirmasi')" style="padding:8px 20px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12.5px;cursor:pointer;color:#64748b;font-family:inherit;">Batal</button>
      <button type="button" onclick="submitImport()" style="padding:8px 20px;background:linear-gradient(135deg,#26B99A,#1a7a5e);border:none;border-radius:5px;font-size:12.5px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;"><i class="fa fa-check"></i> Ya, Import Sekarang</button>
    </div>
  </div>
</div>


<script>
const APP_URL = '<?= APP_URL ?>';
<?php if ($file_uploaded): ?>
// Semua baris preview
const ALL_ROWS = <?= json_encode($preview_rows) ?>;
<?php endif; ?>

/* ── Drag & drop ── */
const dz = document.getElementById('drop-zone');
if (dz) {
    ['dragenter','dragover'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.add('drag'); }));
    ['dragleave','drop'].forEach(e => dz.addEventListener(e, ev => { dz.classList.remove('drag'); }));
    dz.addEventListener('drop', ev => {
        ev.preventDefault();
        const f = ev.dataTransfer.files[0];
        if (f) { document.getElementById('file-input').files; onFileChosenDrop(f); }
    });
}
function onFileChosenDrop(f) {
    document.getElementById('drop-label').textContent = f.name;
    document.getElementById('file-chosen').textContent = 'File: ' + f.name + ' (' + (f.size/1024).toFixed(1) + ' KB)';
    document.getElementById('file-chosen').style.display = '';
    document.getElementById('btn-upload').disabled = false;
}
function onFileChosen(inp) {
    if (inp.files.length) {
        const f = inp.files[0];
        document.getElementById('drop-label').textContent = f.name;
        document.getElementById('file-chosen').textContent = f.name + ' (' + (f.size/1024).toFixed(1) + ' KB)';
        document.getElementById('file-chosen').style.display = '';
        document.getElementById('btn-upload').disabled = false;
        document.getElementById('drop-icon').style.color = '#26B99A';
    }
}

/* ── Mode duplikat ── */
let currentMode = 'skip';
function setMode(m) {
    currentMode = m;
    document.getElementById('mode-duplikat').value = m;
    const skip   = document.getElementById('opt-skip');
    const update = document.getElementById('opt-update');
    const rs     = document.getElementById('radio-skip');
    const ru     = document.getElementById('radio-update');
    if (m === 'skip') {
        skip.style.borderColor   = '#26B99A'; skip.style.background = '#f0fdf9';
        update.style.borderColor = '#e2e8f0'; update.style.background = '#fff';
        rs.innerHTML = '<div style="width:10px;height:10px;border-radius:50%;background:#26B99A;"></div>';
        rs.style.borderColor = '#26B99A'; ru.innerHTML = ''; ru.style.borderColor = '#d1d5db';
    } else {
        update.style.borderColor = '#26B99A'; update.style.background = '#f0fdf9';
        skip.style.borderColor   = '#e2e8f0'; skip.style.background = '#fff';
        ru.innerHTML = '<div style="width:10px;height:10px;border-radius:50%;background:#26B99A;"></div>';
        ru.style.borderColor = '#26B99A'; rs.innerHTML = ''; rs.style.borderColor = '#d1d5db';
    }
}

/* ── Konfirmasi ── */
function konfirmasi() {
    <?php if ($file_uploaded): ?>
    // Tandai baris yg skip (_invalid atau _dup_file)
    const rows = ALL_ROWS.map(r => {
        let skip = r._invalid || r._dup_file;
        return {...r, _skip: skip};
    });
    document.getElementById('rows-json-input').value = JSON.stringify(rows);
    const cntNew  = rows.filter(r=>!r._in_db&&!r._skip).length;
    const cntUpd  = rows.filter(r=> r._in_db&&!r._skip).length;
    const cntSkip = rows.filter(r=> r._skip).length;
    const mode = currentMode === 'update' ? 'update (data lama ditimpa)' : 'skip (data lama tidak diubah)';
    document.getElementById('konfirmasi-text').innerHTML =
        `<strong style="color:#16a34a;">${cntNew}</strong> aset baru akan ditambahkan<br>` +
        `<strong style="color:#1d4ed8;">${cntUpd}</strong> aset sudah ada di DB → mode: <strong>${mode}</strong><br>` +
        (cntSkip ? `<strong style="color:#ef4444;">${cntSkip}</strong> baris dilewati (error/duplikat)<br>` : '') +
        `<br><span style="font-size:11px;color:#94a3b8;">Proses tidak dapat dibatalkan.</span>`;
    openModal('m-konfirmasi');
    <?php endif; ?>
}
function submitImport() {
    closeModal('m-konfirmasi');
    document.getElementById('form-konfirmasi').submit();
}
</script>

<?php include '../includes/footer.php'; ?>