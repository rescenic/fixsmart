<?php
// pages/export_sla.php
// Export Laporan SLA ke Excel (.xlsx) — Pure PHP, tanpa library tambahan
// Tidak butuh composer, openpyxl, atau install apapun.
// Cukup upload file ini dan langsung jalan.

session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger', 'Akses ditolak.'); redirect(APP_URL . '/dashboard.php'); }

$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));

$nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
               'Juli','Agustus','September','Oktober','November','Desember'];
$bulan_str  = ($nama_bulan[$bulan] ?? '') . ' ' . $tahun;

// ── Query data (sama persis dengan sla.php) ───────────────────────────────────
$sla_kat_st = $pdo->prepare("
    SELECT k.nama, k.sla_jam, k.sla_respon_jam,
        COUNT(t.id)                                                          AS total,
        SUM(t.status IN ('selesai','ditolak','tidak_bisa'))                 AS selesai,
        SUM(t.status='selesai')                                              AS solved,
        AVG(t.durasi_respon_menit)                                           AS avg_respon,
        AVG(t.durasi_selesai_menit)                                          AS avg_selesai,
        SUM(t.status='selesai' AND t.durasi_selesai_menit <= k.sla_jam*60) AS sla_met,
        SUM(t.status IN ('menunggu','diproses'))                             AS aktif
    FROM kategori k
    LEFT JOIN tiket t ON t.kategori_id=k.id
        AND MONTH(t.created_at)=? AND YEAR(t.created_at)=?
    GROUP BY k.id ORDER BY k.nama
");
$sla_kat_st->execute([$bulan, $tahun]);
$sla_kat = $sla_kat_st->fetchAll();

$sla_tek_st = $pdo->prepare("
    SELECT u.nama,
        COUNT(t.id)                                                                       AS total,
        SUM(t.status='selesai')                                                           AS selesai,
        SUM(t.status='ditolak')                                                           AS ditolak,
        SUM(t.status='tidak_bisa')                                                        AS tdk_bisa,
        AVG(t.durasi_respon_menit)                                                        AS avg_respon,
        AVG(t.durasi_selesai_menit)                                                       AS avg_selesai,
        SUM(t.status='selesai' AND t.durasi_selesai_menit <=
            (SELECT k2.sla_jam*60 FROM kategori k2 WHERE k2.id=t.kategori_id))           AS sla_met
    FROM users u
    LEFT JOIN tiket t ON t.teknisi_id = u.id
        AND (
            (t.status='selesai'  AND MONTH(t.waktu_selesai)=? AND YEAR(t.waktu_selesai)=?)
            OR
            (t.status!='selesai' AND MONTH(t.created_at)=?   AND YEAR(t.created_at)=?)
        )
    WHERE u.role='teknisi' AND u.status='aktif'
    GROUP BY u.id ORDER BY selesai DESC
");
$sla_tek_st->execute([$bulan,$tahun,$bulan,$tahun]);
$sla_tek = $sla_tek_st->fetchAll();

$ov_st = $pdo->prepare("
    SELECT COUNT(*)                                                                       AS total,
        SUM(status IN ('selesai','ditolak','tidak_bisa'))                                AS selesai,
        SUM(status='menunggu')                                                            AS menunggu,
        SUM(status='diproses')                                                            AS diproses,
        SUM(status='selesai')                                                             AS solved,
        SUM(status='ditolak')                                                             AS ditolak,
        SUM(status='tidak_bisa')                                                          AS tidak_bisa,
        AVG(durasi_respon_menit)                                                          AS avg_respon,
        AVG(durasi_selesai_menit)                                                         AS avg_selesai,
        SUM(status='selesai' AND durasi_selesai_menit <=
            (SELECT k.sla_jam*60 FROM kategori k WHERE k.id=kategori_id))                AS sla_met
    FROM tiket WHERE MONTH(created_at)=? AND YEAR(created_at)=?
");
$ov_st->execute([$bulan, $tahun]);
$ov      = $ov_st->fetch();
$sla_pct = $ov['solved'] > 0 ? round($ov['sla_met'] / $ov['solved'] * 100) : 0;

// ── Helper functions ──────────────────────────────────────────────────────────
function fmtDur(int $menit): string {
    if ($menit <= 0)   return '-';
    if ($menit < 60)   return $menit . ' mnt';
    if ($menit < 1440) return floor($menit/60) . ' jam ' . ($menit%60) . ' mnt';
    return floor($menit/1440) . ' hr ' . floor(($menit%1440)/60) . ' jam';
}

function slaStatusXls(int $pct): string {
    if ($pct >= 90) return 'Tercapai';
    if ($pct >= 70) return 'Perlu Perhatian';
    return 'Tidak Tercapai';
}

// ══════════════════════════════════════════════════════════════════════════════
// XLSX BUILDER — Pure PHP, zero dependencies
// Format: Office Open XML (.xlsx)
// ══════════════════════════════════════════════════════════════════════════════

// Warna hex (tanpa #)
const C_NAVY   = '1e3a5f';
const C_TEAL   = '26B99A';
const C_SUBHD  = '2a4a6b';
const C_WHITE  = 'FFFFFF';
const C_LIGHT  = 'EBF5F0';
const C_GRAY   = 'F8FAFC';
const C_GREEN  = 'D1FAE5'; const C_GREENTXT  = '065F46';
const C_ORANGE = 'FEF3C7'; const C_ORANGETXT = '92400E';
const C_RED    = 'FEE2E2'; const C_REDTXT    = '991B1B';
const C_NAVY2  = 'DBEAFE'; const C_NAVY2TXT  = '1E40AF';

function slaColor(int $pct): array {
    if ($pct >= 90) return [C_GREEN,  C_GREENTXT];
    if ($pct >= 70) return [C_ORANGE, C_ORANGETXT];
    return [C_RED, C_REDTXT];
}

// ── Shared strings (teks sel) ─────────────────────────────────────────────────
$strings = [];
function si(string $s): int {
    global $strings;
    $k = array_search($s, $strings, true);
    if ($k === false) { $strings[] = $s; $k = array_key_last($strings); }
    return $k;
}

// ── Cell XML builders ─────────────────────────────────────────────────────────
// styleId: index ke $styles array (dibuat di bawah)
function cellS(string $ref, $value, int $styleId): string {
    if ($value === null || $value === '') {
        return "<c r=\"$ref\" s=\"$styleId\"><v></v></c>";
    }
    if (is_numeric($value)) {
        return "<c r=\"$ref\" t=\"n\" s=\"$styleId\"><v>$value</v></c>";
    }
    $si = si((string)$value);
    return "<c r=\"$ref\" t=\"s\" s=\"$styleId\"><v>$si</v></c>";
}

// Konversi nomor kolom (1-based) ke huruf Excel (A,B,...,Z,AA,...)
function col2l(int $n): string {
    $l = '';
    while ($n > 0) {
        $n--; $l = chr(65 + ($n % 26)) . $l; $n = (int)($n / 26);
    }
    return $l;
}

function ref(int $col, int $row): string { return col2l($col) . $row; }

// ── Style registry ────────────────────────────────────────────────────────────
// Setiap kombinasi unik font+fill+border+alignment = 1 style index
// Kita predefine semua style yang dibutuhkan

$styleList = []; // ['font'=>...,'fill'=>...,'border'=>...,'align'=>...]

function addStyle(array $s): int {
    global $styleList;
    $key = json_encode($s);
    foreach ($styleList as $i => $st) {
        if (json_encode($st) === $key) return $i;
    }
    $styleList[] = $s;
    return array_key_last($styleList);
}

// Predefine styles
$S = [];

// Helper untuk buat style array
function makeStyle(
    bool   $bold    = false,
    int    $sz      = 10,
    string $fgColor = '000000',
    string $bgColor = '',        // '' = no fill
    string $halign  = 'left',
    bool   $wrap    = false,
    bool   $italic  = false,
    bool   $border  = true
): array {
    return compact('bold','sz','fgColor','bgColor','halign','wrap','italic','border');
}

// Style indices — define semua yang dibutuhkan
$S['default']    = addStyle(makeStyle());
$S['hd_main']    = addStyle(makeStyle(true,13,C_WHITE,C_NAVY,'center'));
$S['hd_sub']     = addStyle(makeStyle(true,10,C_WHITE,C_TEAL,'center'));
$S['hd_col']     = addStyle(makeStyle(true,9, C_WHITE,C_SUBHD,'center',true));
$S['sub_gray']   = addStyle(makeStyle(false,9,'999999',C_GRAY,'center',false,true));
$S['card_lbl']   = addStyle(makeStyle(true,8,C_WHITE,'1e3a5f','center'));
$S['card_val']   = addStyle(makeStyle(true,14,C_WHITE,'1e3a5f','center'));
$S['card_g_lbl'] = addStyle(makeStyle(true,8,C_WHITE,'16a34a','center'));
$S['card_g_val'] = addStyle(makeStyle(true,14,C_WHITE,'16a34a','center'));
$S['card_r_lbl'] = addStyle(makeStyle(true,8,C_WHITE,'dc2626','center'));
$S['card_r_val'] = addStyle(makeStyle(true,14,C_WHITE,'dc2626','center'));
$S['card_o_lbl'] = addStyle(makeStyle(true,8,C_WHITE,'d97706','center'));
$S['card_o_val'] = addStyle(makeStyle(true,14,C_WHITE,'d97706','center'));
$S['card_b_lbl'] = addStyle(makeStyle(true,8,C_WHITE,'0369a1','center'));
$S['card_b_val'] = addStyle(makeStyle(true,14,C_WHITE,'0369a1','center'));
$S['card_p_lbl'] = addStyle(makeStyle(true,8,C_WHITE,'6d28d9','center'));
$S['card_p_val'] = addStyle(makeStyle(true,14,C_WHITE,'6d28d9','center'));
$S['gauge_ok']   = addStyle(makeStyle(true,15,C_GREENTXT,C_GREEN,'center'));
$S['gauge_warn'] = addStyle(makeStyle(true,15,C_ORANGETXT,C_ORANGE,'center'));
$S['gauge_bad']  = addStyle(makeStyle(true,15,C_REDTXT,C_RED,'center'));
$S['sla_ok']     = addStyle(makeStyle(true,10,C_GREENTXT,C_GREEN,'center'));
$S['sla_warn']   = addStyle(makeStyle(true,10,C_ORANGETXT,C_ORANGE,'center'));
$S['sla_bad']    = addStyle(makeStyle(true,10,C_REDTXT,C_RED,'center'));
$S['tot_navy']   = addStyle(makeStyle(true,10,C_WHITE,C_NAVY,'center'));
$S['tot_navy_l'] = addStyle(makeStyle(true,10,C_WHITE,C_NAVY,'left'));
$S['data_l']     = addStyle(makeStyle(false,10,'1e293b','','left'));
$S['data_c']     = addStyle(makeStyle(false,10,'1e293b','','center'));
$S['data_b']     = addStyle(makeStyle(true,10,'1e293b','','center'));
$S['data_l_alt'] = addStyle(makeStyle(false,10,'1e293b',C_LIGHT,'left'));
$S['data_c_alt'] = addStyle(makeStyle(false,10,'1e293b',C_LIGHT,'center'));
$S['data_b_alt'] = addStyle(makeStyle(true,10,'1e293b',C_LIGHT,'center'));
$S['data_gray']  = addStyle(makeStyle(false,10,'888888','','center',false,true));
$S['data_gray_alt'] = addStyle(makeStyle(false,10,'888888',C_LIGHT,'center',false,true));
$S['ket_ok']     = addStyle(makeStyle(false,9,C_GREENTXT,'','left',false,true));
$S['ket_warn']   = addStyle(makeStyle(false,9,C_ORANGETXT,'','left',false,true));
$S['ket_bad']    = addStyle(makeStyle(false,9,C_REDTXT,'','left',false,true));
$S['ket_ok_alt'] = addStyle(makeStyle(false,9,C_GREENTXT,C_LIGHT,'left',false,true));
$S['ket_warn_alt']= addStyle(makeStyle(false,9,C_ORANGETXT,C_LIGHT,'left',false,true));
$S['ket_bad_alt']= addStyle(makeStyle(false,9,C_REDTXT,C_LIGHT,'left',false,true));
$S['footer']     = addStyle(makeStyle(false,8,'aaaaaa',C_GRAY,'center',false,true));
$S['empty']      = addStyle(makeStyle(false,10,'cccccc','','center',false,true));

function getSlaStyle(int $pct, bool $alt = false, bool $bold = true): int {
    global $S;
    if ($pct >= 90) return $S[$bold ? 'sla_ok'   : ($alt ? 'ket_ok_alt'   : 'ket_ok')];
    if ($pct >= 70) return $S[$bold ? 'sla_warn'  : ($alt ? 'ket_warn_alt' : 'ket_warn')];
    return                  $S[$bold ? 'sla_bad'   : ($alt ? 'ket_bad_alt'  : 'ket_bad')];
}

function getGaugeStyle(int $pct): int {
    global $S;
    if ($pct >= 90) return $S['gauge_ok'];
    if ($pct >= 70) return $S['gauge_warn'];
    return                  $S['gauge_bad'];
}

function altStyle(string $base, bool $alt): int {
    global $S;
    return $S[$alt ? $base . '_alt' : $base] ?? $S[$base];
}

// ── Sheet builder class sederhana ─────────────────────────────────────────────
class Sheet {
    public string $name;
    public array  $rows   = [];   // [rowNum => [colNum => [val, styleId]]]
    public array  $merges = [];   // ["A1:C1", ...]
    public array  $colW   = [];   // [colNum => width]
    public array  $rowH   = [];   // [rowNum => height]
    public string $freeze = '';   // e.g. "B5"

    public function __construct(string $name) { $this->name = $name; }

    public function set(int $row, int $col, $val, int $style): void {
        $this->rows[$row][$col] = [$val, $style];
    }

    public function merge(int $r1, int $c1, int $r2, int $c2): void {
        if ($r1===$r2 && $c1===$c2) return;
        $this->merges[] = ref($c1,$r1) . ':' . ref($c2,$r2);
    }

    public function colW(int $col, float $w): void { $this->colW[$col] = $w; }
    public function rowH(int $row, float $h): void { $this->rowH[$row] = $h; }

    // Isi satu baris header kolom sekaligus
    public function headerRow(int $row, int $startCol, array $cols, int $style, float $height=22): void {
        foreach ($cols as $i => [$text, $width]) {
            $col = $startCol + $i;
            $this->set($row, $col, $text, $style);
            if ($width) $this->colW($col, $width);
        }
        $this->rowH($row, $height);
    }

    // Judul utama (merge + style)
    public function title(int $row, int $c1, int $c2, string $text, int $style, float $h=32): void {
        $this->set($row, $c1, $text, $style);
        $this->merge($row, $c1, $row, $c2);
        $this->rowH($row, $h);
    }

    public function toXml(): string {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $xml .= ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0">';
        if ($this->freeze) {
            // Split at letter/number boundary
            preg_match('/([A-Z]+)(\d+)/', $this->freeze, $m);
            $col = 0; $tmp = $m[1];
            for ($i=0;$i<strlen($tmp);$i++) $col = $col*26 + (ord($tmp[$i])-64);
            $xml .= '<pane xSplit="' . ($col-1) . '" ySplit="' . ($m[2]-1) . '" topLeftCell="' . $this->freeze . '" activePane="bottomRight" state="frozen"/>';
        }
        $xml .= '</sheetView></sheetViews>';

        // Column widths
        if ($this->colW) {
            ksort($this->colW);
            $xml .= '<cols>';
            foreach ($this->colW as $c => $w) {
                $xml .= '<col min="'.$c.'" max="'.$c.'" width="'.$w.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        ksort($this->rows);
        foreach ($this->rows as $rowNum => $cols) {
            $ht = isset($this->rowH[$rowNum]) ? ' ht="'.$this->rowH[$rowNum].'" customHeight="1"' : '';
            $xml .= '<row r="'.$rowNum.'"'.$ht.'>';
            ksort($cols);
            foreach ($cols as $colNum => [$val, $style]) {
                $xml .= cellS(ref($colNum,$rowNum), $val, $style);
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if ($this->merges) {
            $xml .= '<mergeCells count="'.count($this->merges).'">';
            foreach ($this->merges as $m) $xml .= '<mergeCell ref="'.$m.'"/>';
            $xml .= '</mergeCells>';
        }

        // Print settings
        $xml .= '<pageSetup orientation="landscape" paperSize="9" fitToPage="1" fitToWidth="1" fitToHeight="0"/>';
        $xml .= '<pageMargins left="0.5" right="0.5" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>';
        $xml .= '</worksheet>';
        return $xml;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// BANGUN SHEET 1 — RINGKASAN
// ══════════════════════════════════════════════════════════════════════════════
$sh1 = new Sheet('Ringkasan');
$sh1->colW(1, 2);   // gutter kiri
$sh1->colW(2, 22); $sh1->colW(3, 11); $sh1->colW(4, 11);
$sh1->colW(5, 11);  $sh1->colW(6, 11); $sh1->colW(7, 11);
$sh1->colW(8, 11);  $sh1->colW(9, 11); $sh1->colW(10, 13);
$sh1->colW(11, 13); $sh1->colW(12, 11); $sh1->colW(13, 11);

// Judul
$sh1->rowH(1, 6);
$sh1->title(2, 2, 13, 'LAPORAN SLA IT HELPDESK  —  ' . strtoupper($bulan_str), $S['hd_main'], 36);
$sh1->set(3, 2, 'Dicetak: ' . date('d F Y  H:i') . '   |   FixSmart Helpdesk', $S['sub_gray']);
$sh1->merge(3, 2, 3, 13);
$sh1->rowH(3, 16); $sh1->rowH(4, 6);

// ── Kartu statistik (baris 5-7) ──────────────────────────────────────────────
$cards = [
    ['TOTAL TIKET',         (int)($ov['total']??0),                            'card_lbl','card_val'],
    ['SELESAI',             (int)($ov['solved']??0),                           'card_g_lbl','card_g_val'],
    ['DITOLAK / TDK BISA',  (int)($ov['ditolak']??0)+(int)($ov['tidak_bisa']??0), 'card_r_lbl','card_r_val'],
    ['MASIH AKTIF',         (int)($ov['menunggu']??0)+(int)($ov['diproses']??0),   'card_o_lbl','card_o_val'],
    ['AVG. RESPON',         fmtDur((int)round($ov['avg_respon']??0)),          'card_b_lbl','card_b_val'],
    ['AVG. SELESAI',        fmtDur((int)round($ov['avg_selesai']??0)),         'card_p_lbl','card_p_val'],
];
$cardCols = [2,4,6,8,10,12];
$sh1->rowH(5,14); $sh1->rowH(6,28); $sh1->rowH(7,10);
foreach ($cards as $i => [$lbl, $val, $lblSt, $valSt]) {
    $c1 = $cardCols[$i]; $c2 = $c1+1;
    $sh1->set(5, $c1, $lbl, $S[$lblSt]);
    $sh1->merge(5, $c1, 5, $c2);
    $sh1->set(6, $c1, (string)$val, $S[$valSt]);
    $sh1->merge(6, $c1, 6, $c2);
    $sh1->set(7, $c1, '', $S[$lblSt]);
    $sh1->merge(7, $c1, 7, $c2);
}

// ── SLA Gauge (baris 8-10) ───────────────────────────────────────────────────
$sh1->rowH(8, 8);
$sh1->title(9, 2, 13, 'PENCAPAIAN SLA BULAN INI', $S['hd_sub'], 16);
$statusTxt = $sla_pct >= 90 ? 'TERCAPAI v' : ($sla_pct >= 70 ? 'PERLU PERHATIAN !' : 'TIDAK TERCAPAI X');
$gaugeText = $sla_pct . '%  ' . $statusTxt . '  --  '
           . (int)($ov['sla_met']??0) . ' dari ' . (int)($ov['solved']??0)
           . ' tiket selesai dalam target SLA';
$sh1->set(10, 2, $gaugeText, getGaugeStyle($sla_pct));
$sh1->merge(10, 2, 10, 13);
$sh1->rowH(10, 30); $sh1->rowH(11, 8);

// ── Tabel SLA per Kategori ────────────────────────────────────────────────────
$sh1->title(12, 2, 13, 'SLA PER KATEGORI', $S['hd_sub'], 18);
$sh1->headerRow(13, 2, [
    ['Kategori',22], ['Target SLA',11], ['Total',8], ['Selesai',8],
    ['Dlm Target',10], ['% SLA',9], ['Avg Respon',13], ['Avg Selesai',13], ['Aktif',8],
], $S['hd_col']);
$R = 14;
foreach ($sla_kat as $idx => $k) {
    $alt    = ($idx % 2 === 0);
    $k_sla  = (int)($k['solved']) > 0 ? (int)round($k['sla_met']/$k['solved']*100) : 0;
    $dL     = $alt ? $S['data_l_alt'] : $S['data_l'];
    $dC     = $alt ? $S['data_c_alt'] : $S['data_c'];
    $dGray  = $alt ? $S['data_gray_alt'] : $S['data_gray'];
    $dSla   = getSlaStyle($k_sla, false, true);

    $sh1->set($R, 2,  $k['nama'],                        $dL);
    $sh1->set($R, 3,  $k['sla_jam'] . ' jam',            $dGray);
    $sh1->set($R, 4,  (int)($k['total']??0),             $dC);
    $sh1->set($R, 5,  (int)($k['selesai']??0),           $dC);
    $sh1->set($R, 6,  (int)($k['sla_met']??0),           $dC);
    $sh1->set($R, 7,  $k_sla . '%',                      $dSla);
    $sh1->set($R, 8,  fmtDur((int)round($k['avg_respon']??0)),  $dC);
    $sh1->set($R, 9,  fmtDur((int)round($k['avg_selesai']??0)), $dC);
    $sh1->set($R, 10, (int)($k['aktif']??0),             $dC);
    $sh1->rowH($R, 15);
    $R++;
}
$sh1->rowH($R, 8); $R++;

// ── Tabel SLA per Teknisi ─────────────────────────────────────────────────────
$sh1->title($R, 2, 13, 'SLA PER TEKNISI', $S['hd_sub'], 18); $R++;
$sh1->headerRow($R, 2, [
    ['Teknisi',22], ['Total',8], ['Selesai',8], ['Ditolak',8],
    ['Tdk Bisa',9], ['% SLA',9], ['Avg Respon',13], ['Avg Selesai',13],
], $S['hd_col']);
$R++;
$medals = ['[1] ','[2] ','[3] ','',''];
foreach ($sla_tek as $idx => $t) {
    $alt   = ($idx % 2 === 0);
    $t_sla = (int)($t['selesai']) > 0 ? (int)round($t['sla_met']/$t['selesai']*100) : 0;
    $dL    = $alt ? $S['data_l_alt'] : $S['data_l'];
    $dC    = $alt ? $S['data_c_alt'] : $S['data_c'];
    $dSla  = getSlaStyle($t_sla, false, true);

    $sh1->set($R, 2,  ($medals[$idx] ?? '') . $t['nama'], $dL);
    $sh1->set($R, 3,  (int)($t['total']??0),              $dC);
    $sh1->set($R, 4,  (int)($t['selesai']??0),            $dC);
    $sh1->set($R, 5,  (int)($t['ditolak']??0),            $dC);
    $sh1->set($R, 6,  (int)($t['tdk_bisa']??0),           $dC);
    $sh1->set($R, 7,  $t_sla . '%',                       $dSla);
    $sh1->set($R, 8,  fmtDur((int)round($t['avg_respon']??0)),  $dC);
    $sh1->set($R, 9,  fmtDur((int)round($t['avg_selesai']??0)), $dC);
    $sh1->rowH($R, 15);
    $R++;
}
$sh1->rowH($R, 8); $R++;
$sh1->set($R, 2, 'Laporan dibuat otomatis oleh FixSmart Helpdesk  --  ' . date('d/m/Y H:i'), $S['footer']);
$sh1->merge($R, 2, $R, 13);
$sh1->freeze = 'B12';


// ══════════════════════════════════════════════════════════════════════════════
// BANGUN SHEET 2 — DETAIL KATEGORI
// ══════════════════════════════════════════════════════════════════════════════
$sh2 = new Sheet('Detail Kategori');
$sh2->colW(1, 2);
$sh2->rowH(1, 6);
$sh2->title(2, 2, 13, 'DETAIL SLA PER KATEGORI  --  ' . strtoupper($bulan_str), $S['hd_main'], 30);
$sh2->rowH(3, 6);
$sh2->headerRow(4, 2, [
    ['Kategori',26],['Target SLA (Jam)',16],['Target Respon (Jam)',18],
    ['Total Tiket',12],['Selesai',12],['Dlm Target SLA',14],
    ['Di Luar Target',14],['% SLA Met',12],
    ['Avg Respon',14],['Avg Selesai',14],['Masih Aktif',12],['Keterangan',16],
], $S['hd_col'], 28);

$R2 = 5;
$tot_ttl = $tot_sls = $tot_met = $tot_sol = 0;
foreach ($sla_kat as $idx => $k) {
    $alt    = ($idx % 2 === 0);
    $k_sla  = (int)($k['solved']) > 0 ? (int)round($k['sla_met']/$k['solved']*100) : 0;
    $diluar = max(0, (int)($k['solved']??0) - (int)($k['sla_met']??0));
    $ket    = slaStatusXls($k_sla);
    $dL     = $alt ? $S['data_l_alt'] : $S['data_l'];
    $dC     = $alt ? $S['data_c_alt'] : $S['data_c'];
    $dG     = $alt ? $S['data_gray_alt'] : $S['data_gray'];
    $dSla   = getSlaStyle($k_sla, false, true);
    $dKet   = getSlaStyle($k_sla, $alt, false);

    $sh2->set($R2, 2,  $k['nama'],                               $dL);
    $sh2->set($R2, 3,  (int)($k['sla_jam']??0),                 $dC);
    $sh2->set($R2, 4,  (int)($k['sla_respon_jam']??0),          $dC);
    $sh2->set($R2, 5,  (int)($k['total']??0),                   $dC);
    $sh2->set($R2, 6,  (int)($k['selesai']??0),                 $dC);
    $sh2->set($R2, 7,  (int)($k['sla_met']??0),                 $dC);
    $sh2->set($R2, 8,  $diluar,                                  $dC);
    $sh2->set($R2, 9,  $k_sla . '%',                            $dSla);
    $sh2->set($R2, 10, fmtDur((int)round($k['avg_respon']??0)), $dG);
    $sh2->set($R2, 11, fmtDur((int)round($k['avg_selesai']??0)),$dG);
    $sh2->set($R2, 12, (int)($k['aktif']??0),                   $dC);
    $sh2->set($R2, 13, $ket,                                     $dKet);
    $sh2->rowH($R2, 15);

    $tot_ttl += (int)($k['total']??0);
    $tot_sls += (int)($k['selesai']??0);
    $tot_met += (int)($k['sla_met']??0);
    $tot_sol += (int)($k['solved']??0);
    $R2++;
}
// Baris total
$tot_pct = $tot_sol > 0 ? (int)round($tot_met/$tot_sol*100) : 0;
$sh2->set($R2, 2,  'TOTAL / KESELURUHAN', $S['tot_navy_l']);
$sh2->set($R2, 3,  '-', $S['tot_navy']); $sh2->set($R2, 4, '-', $S['tot_navy']);
$sh2->set($R2, 5,  $tot_ttl, $S['tot_navy']); $sh2->set($R2, 6, $tot_sls, $S['tot_navy']);
$sh2->set($R2, 7,  $tot_met, $S['tot_navy']); $sh2->set($R2, 8, $tot_sls-$tot_met, $S['tot_navy']);
$sh2->set($R2, 9,  $tot_pct . '%', getSlaStyle($tot_pct));
$sh2->set($R2, 10, '-', $S['tot_navy']); $sh2->set($R2, 11, '-', $S['tot_navy']);
$sh2->set($R2, 12, '-', $S['tot_navy']); $sh2->set($R2, 13, slaStatusXls($tot_pct), getSlaStyle($tot_pct));
$sh2->rowH($R2, 16);
$sh2->freeze = 'B5';


// ══════════════════════════════════════════════════════════════════════════════
// BANGUN SHEET 3 — DETAIL TEKNISI
// ══════════════════════════════════════════════════════════════════════════════
$sh3 = new Sheet('Detail Teknisi');
$sh3->colW(1, 2);
$sh3->rowH(1, 6);
$sh3->title(2, 2, 14, 'DETAIL SLA PER TEKNISI  --  ' . strtoupper($bulan_str), $S['hd_main'], 30);
$sh3->rowH(3, 6);
$sh3->headerRow(4, 2, [
    ['#',5],['Teknisi',24],['Total',10],['Selesai',10],['Ditolak',10],
    ['Tidak Bisa',11],['% Selesai',11],['SLA Met',10],
    ['% SLA Met',11],['Avg Respon',14],['Avg Selesai',14],['Status Kinerja',16],
], $S['hd_col'], 28);

$R3 = 5;
foreach ($sla_tek as $idx => $t) {
    $alt     = ($idx % 2 === 0);
    $t_sla   = (int)($t['selesai']) > 0 ? (int)round($t['sla_met']/$t['selesai']*100) : 0;
    $t_slsp  = (int)($t['total'])   > 0 ? (int)round($t['selesai']/$t['total']*100)   : 0;
    $prefix  = ($medals[$idx] ?? '') . ' ';
    $ket     = $t_sla >= 90 ? 'Top Performer' : ($t_sla >= 70 ? 'Perlu Peningkatan' : 'Perlu Evaluasi');
    $dL      = $alt ? $S['data_l_alt'] : $S['data_l'];
    $dC      = $alt ? $S['data_c_alt'] : $S['data_c'];
    $dSla    = getSlaStyle($t_sla, false, true);
    $dKet    = getSlaStyle($t_sla, $alt, false);

    $sh3->set($R3, 2,  $idx+1,                                   $dC);
    $sh3->set($R3, 3,  trim($prefix) . ' ' . $t['nama'],         $dL);
    $sh3->set($R3, 4,  (int)($t['total']??0),                    $dC);
    $sh3->set($R3, 5,  (int)($t['selesai']??0),                  $dC);
    $sh3->set($R3, 6,  (int)($t['ditolak']??0),                  $dC);
    $sh3->set($R3, 7,  (int)($t['tdk_bisa']??0),                 $dC);
    $sh3->set($R3, 8,  $t_slsp . '%',                            $dC);
    $sh3->set($R3, 9,  (int)($t['sla_met']??0),                  $dC);
    $sh3->set($R3, 10, $t_sla . '%',                             $dSla);
    $sh3->set($R3, 11, fmtDur((int)round($t['avg_respon']??0)),  $dC);
    $sh3->set($R3, 12, fmtDur((int)round($t['avg_selesai']??0)), $dC);
    $sh3->set($R3, 13, $ket,                                     $dKet);
    $sh3->rowH($R3, 15);
    $R3++;
}
$sh3->freeze = 'B5';


// ══════════════════════════════════════════════════════════════════════════════
// BANGUN XLSX — ZIP semua parts
// ══════════════════════════════════════════════════════════════════════════════

// Styles XML builder
function buildStylesXml(array $styleList): string {
    $fonts = $fills = $borders = $xfs = [];

    // Font default wajib ada di index 0
    $fonts[] = '<font><sz val="10"/><name val="Arial"/></font>';
    // Fill default (none) wajib index 0 & 1
    $fills[] = '<fill><patternFill patternType="none"/></fill>';
    $fills[] = '<fill><patternFill patternType="gray125"/></fill>';
    // Border default
    $borders[] = '<border><left/><right/><top/><bottom/><diagonal/></border>';

    $fontMap = $fillMap = $borderMap = [];
    $thinBorderXml = '<border><left style="thin"><color rgb="D1D5DB"/></left><right style="thin"><color rgb="D1D5DB"/></right><top style="thin"><color rgb="D1D5DB"/></top><bottom style="thin"><color rgb="D1D5DB"/></bottom><diagonal/></border>';

    foreach ($styleList as $st) {
        // Font
        $fXml = '<font>';
        if ($st['bold'])   $fXml .= '<b/>';
        if ($st['italic']) $fXml .= '<i/>';
        $fXml .= '<sz val="'.$st['sz'].'"/>';
        $fXml .= '<color rgb="FF'.strtoupper($st['fgColor']).'"/>';
        $fXml .= '<name val="Arial"/>';
        $fXml .= '</font>';
        if (!isset($fontMap[$fXml])) { $fontMap[$fXml] = count($fonts); $fonts[] = $fXml; }
        $fontId = $fontMap[$fXml];

        // Fill
        if ($st['bgColor']) {
            $fiXml = '<fill><patternFill patternType="solid"><fgColor rgb="FF'.strtoupper($st['bgColor']).'"/></patternFill></fill>';
        } else {
            $fiXml = '<fill><patternFill patternType="none"/></fill>';
        }
        if (!isset($fillMap[$fiXml])) { $fillMap[$fiXml] = count($fills); $fills[] = $fiXml; }
        $fillId = $fillMap[$fiXml];

        // Border — semua pakai thin gray kecuali 'border'=>false
        $bXml = $st['border'] ? $thinBorderXml : '<border><left/><right/><top/><bottom/><diagonal/></border>';
        if (!isset($borderMap[$bXml])) { $borderMap[$bXml] = count($borders); $borders[] = $bXml; }
        $borderId = $borderMap[$bXml];

        // Alignment
        $hA = $st['halign'] === 'center' ? 'center' : ($st['halign'] === 'right' ? 'right' : 'left');
        $wT = $st['wrap'] ? ' wrapText="1"' : '';

        $xfs[] = '<xf numFmtId="0" fontId="'.$fontId.'" fillId="'.$fillId.'" borderId="'.$borderId.'"'
               . ' applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
               . '<alignment horizontal="'.$hA.'" vertical="center"'.$wT.'/>'
               . '</xf>';
    }

    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<fonts count="'.count($fonts).'">'.implode('',$fonts).'</fonts>';
    $xml .= '<fills count="'.count($fills).'">'.implode('',$fills).'</fills>';
    $xml .= '<borders count="'.count($borders).'">'.implode('',$borders).'</borders>';
    $xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
    $xml .= '<cellXfs count="'.count($xfs).'">'.implode('',$xfs).'</cellXfs>';
    $xml .= '</styleSheet>';
    return $xml;
}

function buildSharedStrings(array $strings): string {
    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
    $xml .= ' count="'.count($strings).'" uniqueCount="'.count($strings).'">';
    foreach ($strings as $s) {
        $xml .= '<si><t xml:space="preserve">'.htmlspecialchars($s, ENT_XML1, 'UTF-8').'</t></si>';
    }
    $xml .= '</sst>';
    return $xml;
}

// Kumpulkan semua sheet XML dulu (ini membangun $strings)
$sheets   = [$sh1, $sh2, $sh3];
$sheetXmls = [];
foreach ($sheets as $sh) {
    $sheetXmls[] = $sh->toXml();
}

$stylesXml  = buildStylesXml($styleList);
$ssXml      = buildSharedStrings($strings);

// ZIP builder tanpa library — pakai ZipArchive (tersedia by default di PHP)
$tmpFile = tempnam(sys_get_temp_dir(), 'sla_xlsx_') . '.xlsx';
$zip     = new ZipArchive();
$zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

// [Content_Types].xml
$ct  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$ct .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
$ct .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
$ct .= '<Default Extension="xml"  ContentType="application/xml"/>';
$ct .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
for ($i = 0; $i < count($sheets); $i++) {
    $ct .= '<Override PartName="/xl/worksheets/sheet'.($i+1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
}
$ct .= '<Override PartName="/xl/styles.xml"        ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
$ct .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
$ct .= '</Types>';
$zip->addFromString('[Content_Types].xml', $ct);

// _rels/.rels
$rels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
$rels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
$rels .= '</Relationships>';
$zip->addFromString('_rels/.rels', $rels);

// xl/workbook.xml
$wb_xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$wb_xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
$wb_xml .= '<sheets>';
foreach ($sheets as $i => $sh) {
    $wb_xml .= '<sheet name="'.htmlspecialchars($sh->name, ENT_XML1).'" sheetId="'.($i+1).'" r:id="rId'.($i+1).'"/>';
}
$wb_xml .= '</sheets></workbook>';
$zip->addFromString('xl/workbook.xml', $wb_xml);

// xl/_rels/workbook.xml.rels
$wbr  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$wbr .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
foreach ($sheets as $i => $sh) {
    $wbr .= '<Relationship Id="rId'.($i+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i+1).'.xml"/>';
}
$n = count($sheets);
$wbr .= '<Relationship Id="rId'.($n+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
$wbr .= '<Relationship Id="rId'.($n+2).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
$wbr .= '</Relationships>';
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbr);

// Sheet XMLs
foreach ($sheetXmls as $i => $xml) {
    $zip->addFromString('xl/worksheets/sheet'.($i+1).'.xml', $xml);
}

$zip->addFromString('xl/styles.xml',       $stylesXml);
$zip->addFromString('xl/sharedStrings.xml', $ssXml);
$zip->close();

// ── Kirim ke browser ──────────────────────────────────────────────────────────
$filename = 'Laporan_SLA_' . str_replace(' ', '_', $bulan_str) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
readfile($tmpFile);
@unlink($tmpFile);
exit;