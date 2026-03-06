<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }

// ── Parameter filter ──────────────────────────────────────────────────────────
$tgl_dari   = $_GET['tgl_dari']   ?? date('Y-m-01');
$tgl_sampai = $_GET['tgl_sampai'] ?? date('Y-m-t');
$fkat       = (int)($_GET['kat']       ?? 0);
$fstatus    = $_GET['status']    ?? '';
$fprioritas = $_GET['prioritas'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_dari))   $tgl_dari   = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_sampai)) $tgl_sampai = date('Y-m-t');
if (!in_array($fstatus,    ['','menunggu','diproses','selesai','ditolak','tidak_bisa'])) $fstatus    = '';
if (!in_array($fprioritas, ['','Tinggi','Sedang','Rendah']))                             $fprioritas = '';

$label_dari   = date('d/m/Y', strtotime($tgl_dari));
$label_sampai = date('d/m/Y', strtotime($tgl_sampai));
$periode_str  = $label_dari.' s.d. '.$label_sampai;

// ── Query ─────────────────────────────────────────────────────────────────────
$where  = ["DATE(t.waktu_submit) BETWEEN ? AND ?"];
$params = [$tgl_dari, $tgl_sampai];
if ($fkat)       { $where[] = 't.kategori_id = ?'; $params[] = $fkat; }
if ($fstatus)    { $where[] = 't.status = ?';       $params[] = $fstatus; }
if ($fprioritas) { $where[] = 't.prioritas = ?';    $params[] = $fprioritas; }
$wsql = implode(' AND ', $where);

$st = $pdo->prepare("
    SELECT t.*, k.nama AS kat_nama, k.sla_jam,
           u.nama AS req_nama, u.divisi,
           tek.nama AS tek_nama,
           TIMESTAMPDIFF(MINUTE, t.waktu_submit, IFNULL(t.waktu_selesai, NOW())) AS dur_aktual
    FROM tiket t
    LEFT JOIN kategori k  ON k.id   = t.kategori_id
    LEFT JOIN users     u ON u.id   = t.user_id
    LEFT JOIN users   tek ON tek.id = t.teknisi_id
    WHERE $wsql ORDER BY t.waktu_submit ASC
");
$st->execute($params);
$tikets = $st->fetchAll();

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = ['total'=>0,'menunggu'=>0,'diproses'=>0,'selesai'=>0,'ditolak'=>0,'tidak_bisa'=>0,'sla_met'=>0];
foreach ($tikets as $t) {
    $stats['total']++;
    if (isset($stats[$t['status']])) $stats[$t['status']]++;
    if ($t['status']==='selesai' && $t['sla_jam'] && $t['durasi_selesai_menit'] <= $t['sla_jam']*60)
        $stats['sla_met']++;
}
$sla_pct = $stats['selesai'] > 0 ? round($stats['sla_met']/$stats['selesai']*100) : 0;

// Label filter
$kat_label = 'Semua';
if ($fkat) {
    $kr = $pdo->prepare("SELECT nama FROM kategori WHERE id=?");
    $kr->execute([$fkat]); $kr = $kr->fetch();
    if ($kr) $kat_label = $kr['nama'];
}
$status_label    = $fstatus    ? ucfirst(str_replace('_',' ',$fstatus)) : 'Semua';
$prioritas_label = $fprioritas ?: 'Semua';


// ══════════════════════════════════════════════════════════════════════════════
// XLSX BUILDER — Pure PHP, zero dependencies
// ══════════════════════════════════════════════════════════════════════════════

// ── Shared Strings ────────────────────────────────────────────────────────────
$XI_SS  = [];   // list of strings (ordered)
$XI_SSM = [];   // map string => index

function xs(string $s): int {
    global $XI_SS, $XI_SSM;
    if (!isset($XI_SSM[$s])) {
        $XI_SSM[$s] = count($XI_SS);
        $XI_SS[] = $s;
    }
    return $XI_SSM[$s];
}

// ── Cell helper ───────────────────────────────────────────────────────────────
function xcol(int $n): string {          // 1-based column index → letter(s)
    $s = '';
    while ($n > 0) {
        $n--;
        $s = chr(65 + ($n % 26)) . $s;
        $n = (int)($n / 26);
    }
    return $s;
}
function xref(int $c, int $r): string { return xcol($c).$r; }

function xcell(string $ref, $v, int $s): string {
    if ($v === null || $v === '') return "<c r=\"$ref\" s=\"$s\"/>";
    if (is_int($v) || is_float($v))  return "<c r=\"$ref\" t=\"n\" s=\"$s\"><v>$v</v></c>";
    $i = xs((string)$v);
    return "<c r=\"$ref\" t=\"s\" s=\"$s\"><v>$i</v></c>";
}

// ── Style index constants (matches HARDCODED styles.xml below) ────────────────
const XS_DEFAULT    =  0;
const XS_HD_MAIN    =  1;
const XS_HD_SUB     =  2;
const XS_HD_COL     =  3;
const XS_SUB_INFO   =  4;
const XS_FILT_LBL   =  5;
const XS_FILT_VAL   =  6;
const XS_HD_VAL     =  7;
const XS_DATA_L     =  8;
const XS_DATA_C     =  9;
const XS_DATA_L_A   = 10;
const XS_DATA_C_A   = 11;
const XS_DATA_G     = 12;
const XS_DATA_G_A   = 13;
const XS_DATA_W     = 14;
const XS_DATA_W_A   = 15;
const XS_TOT        = 16;
const XS_TOT_L      = 17;
const XS_ST_WAIT    = 18;
const XS_ST_PROC    = 19;
const XS_ST_DONE    = 20;
const XS_ST_REJ     = 21;
const XS_ST_CANT    = 22;
const XS_PR_H       = 23;
const XS_PR_M       = 24;
const XS_PR_L       = 25;
const XS_SLA_OK     = 26;
const XS_SLA_WARN   = 27;
const XS_SLA_BAD    = 28;
const XS_SLA_NA     = 29;
const XS_FOOTER     = 30;

function xStatusStyle(string $s): int {
    return ['menunggu'=>XS_ST_WAIT,'diproses'=>XS_ST_PROC,'selesai'=>XS_ST_DONE,
            'ditolak'=>XS_ST_REJ,'tidak_bisa'=>XS_ST_CANT][$s] ?? XS_DATA_C;
}
function xPrStyle(string $p): int {
    return ['tinggi'=>XS_PR_H,'sedang'=>XS_PR_M,'rendah'=>XS_PR_L][strtolower($p)] ?? XS_DATA_C;
}
function xStatusLabel(string $s): string {
    return ['menunggu'=>'Menunggu','diproses'=>'Diproses','selesai'=>'Selesai',
            'ditolak'=>'Ditolak','tidak_bisa'=>'Tidak Bisa'][$s] ?? ucfirst($s);
}
function xDur(int $m): string {
    if ($m <= 0)    return '-';
    if ($m < 60)    return $m.' mnt';
    if ($m < 1440)  return floor($m/60).' jam '.($m%60).' mnt';
    return floor($m/1440).' hr '.floor(($m%1440)/60).' jam';
}

// ── Sheet class ───────────────────────────────────────────────────────────────
class XSheet {
    public string $name;
    public array  $rows=[], $merges=[], $cw=[], $rh=[];
    public string $freeze='';
    public function __construct(string $n) { $this->name=$n; }

    public function s(int $r, int $c, $v, int $st): void { $this->rows[$r][$c]=[$v,$st]; }
    public function m(int $r1, int $c1, int $r2, int $c2): void {
        if ($r1===$r2 && $c1===$c2) return;
        $this->merges[] = xref($c1,$r1).':'.xref($c2,$r2);
    }
    public function cw(int $c, float $w): void { $this->cw[$c]=$w; }
    public function rh(int $r, float $h): void { $this->rh[$r]=$h; }
    public function title(int $r, int $c1, int $c2, string $t, int $st, float $h=30): void {
        $this->s($r,$c1,$t,$st); $this->m($r,$c1,$r,$c2); $this->rh($r,$h);
    }

    public function xml(): string {
        $x  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $x .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $x .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0">';
        if ($this->freeze) {
            preg_match('/([A-Z]+)(\d+)/', $this->freeze, $fm);
            $cn = 0; for ($i=0;$i<strlen($fm[1]);$i++) $cn=$cn*26+(ord($fm[1][$i])-64);
            $x .= '<pane xSplit="'.($cn-1).'" ySplit="'.($fm[2]-1).'" topLeftCell="'.$this->freeze.'" activePane="bottomRight" state="frozen"/>';
        }
        $x .= '</sheetView></sheetViews>';
        if ($this->cw) {
            ksort($this->cw);
            $x .= '<cols>';
            foreach ($this->cw as $c=>$w)
                $x .= '<col min="'.$c.'" max="'.$c.'" width="'.$w.'" customWidth="1"/>';
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        ksort($this->rows);
        foreach ($this->rows as $rn => $cols) {
            $ht = isset($this->rh[$rn]) ? ' ht="'.$this->rh[$rn].'" customHeight="1"' : '';
            $x .= '<row r="'.$rn.'"'.$ht.'>';
            ksort($cols);
            foreach ($cols as $cn => [$v, $st])
                $x .= xcell(xref($cn,$rn), $v, $st);
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if ($this->merges) {
            $x .= '<mergeCells count="'.count($this->merges).'">';
            foreach ($this->merges as $mg) $x .= '<mergeCell ref="'.$mg.'"/>';
            $x .= '</mergeCells>';
        }
        $x .= '<pageSetup orientation="landscape" paperSize="9" fitToPage="1" fitToWidth="1" fitToHeight="0"/>';
        $x .= '</worksheet>';
        return $x;
    }
}

// ── Styles XML (HARDCODED — sudah divalidasi, tidak pakai dynamic builder) ────
// Fill index:  0=none, 1=gray125, 2=navy(1E3A5F), 3=teal(26B99A), 4=subhd(2A4A6B),
//              5=gray(F8FAFC),  6=blue(DBEAFE),  7=light(EBF5F0), 8=yellow(FFF9C4),
//              9=green(D1FAE5), 10=red(FEE2E2), 11=purple(EDE9FE), 12=orange(FEF3C7)
// Font index:  0=normal, 1=bold13white, 2=bold10white, 3=bold9white, 4=gray9italic, 5=bold9dark
// Border:      0=none,   1=thin-gray
$XI_STYLES = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<numFmts count="0"/>
<fonts count="6">
  <font><sz val="10"/><color rgb="FF000000"/><name val="Arial"/></font>
  <font><b/><sz val="13"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>
  <font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>
  <font><b/><sz val="9"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>
  <font><i/><sz val="9"/><color rgb="FF888888"/><name val="Arial"/></font>
  <font><b/><sz val="9"/><color rgb="FF374151"/><name val="Arial"/></font>
</fonts>
<fills count="13">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF1E3A5F"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF26B99A"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF2A4A6B"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFEBF5F0"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFFF9C4"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFEDE9FE"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>
</fills>
<borders count="2">
  <border><left/><right/><top/><bottom/><diagonal/></border>
  <border>
    <left style="thin"><color rgb="FFD1D5DB"/></left>
    <right style="thin"><color rgb="FFD1D5DB"/></right>
    <top style="thin"><color rgb="FFD1D5DB"/></top>
    <bottom style="thin"><color rgb="FFD1D5DB"/></bottom>
    <diagonal/>
  </border>
</borders>
<cellStyleXfs count="1">
  <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
</cellStyleXfs>
<cellXfs count="31">
  <xf numFmtId="0" fontId="0" fillId="0" borderId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
  <xf numFmtId="0" fontId="1" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="2" fillId="3" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="4" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="5" fillId="6" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
  <xf numFmtId="0" fontId="0" fillId="6" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
  <xf numFmtId="0" fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
  <xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="0" fillId="7" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
  <xf numFmtId="0" fontId="0" fillId="7" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="0" borderId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="7" borderId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="0" fillId="7" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="3" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="8" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="6" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="9" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="10" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="11" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="10" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="12" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="9" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="9" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="12" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="10" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="0" borderId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
</cellXfs>
<cellStyles count="1">
  <cellStyle name="Normal" xfId="0" builtinId="0"/>
</cellStyles>
</styleSheet>
XML;


// ══════════════════════════════════════════════════════════════════════════════
// SHEET 1 — RINGKASAN
// ══════════════════════════════════════════════════════════════════════════════
$sh1 = new XSheet('Ringkasan');
$sh1->cw(1,2);
for ($c=2;$c<=14;$c++) $sh1->cw($c,12);
$sh1->rh(1,6);
$sh1->title(2,2,14,'LAPORAN TIKET  —  '.strtoupper($periode_str), XS_HD_MAIN, 34);
$sh1->s(3,2,'Dicetak: '.date('d F Y  H:i').'   |   FixSmart Helpdesk', XS_SUB_INFO);
$sh1->m(3,2,3,14); $sh1->rh(3,15); $sh1->rh(4,6);

$fInfo = [['Periode',$periode_str],['Kategori',$kat_label],['Status',$status_label],
          ['Prioritas',$prioritas_label],['Total',(string)$stats['total']],['SLA Met',$sla_pct.'%']];
$fcols = [2,4,6,8,10,12];
$sh1->rh(5,6); $sh1->rh(6,16); $sh1->rh(7,16); $sh1->rh(8,6);
foreach ($fInfo as $i=>[$l,$v]) {
    $c=$fcols[$i];
    $sh1->s(6,$c,$l,XS_FILT_LBL); $sh1->m(6,$c,6,$c+1);
    $sh1->s(7,$c,$v,XS_FILT_VAL); $sh1->m(7,$c,7,$c+1);
}

$cards = [
    ['TOTAL',   $stats['total'],    2,  'c_navy'],
    ['SELESAI', $stats['selesai'],  4,  'c_green'],
    ['MENUNGGU',$stats['menunggu'], 6,  'c_orange'],
    ['DIPROSES',$stats['diproses'], 8,  'c_blue'],
    ['DITOLAK', $stats['ditolak'],  10, 'c_red'],
    ['TDK BISA',$stats['tidak_bisa'],12,'c_purple'],
];
// card colors: navy=XS_HD_MAIN/XS_TOT, green=XS_ST_DONE, orange=XS_ST_WAIT(yellow→orange),
// blue=XS_ST_PROC, red=XS_ST_REJ, purple=XS_ST_CANT
$cardStyles = [XS_HD_MAIN,XS_ST_DONE,XS_ST_WAIT,XS_ST_PROC,XS_ST_REJ,XS_ST_CANT];
$sh1->rh(9,14); $sh1->rh(10,28); $sh1->rh(11,8);
foreach ($cards as $i=>[$lbl,$val,$c,$_]) {
    $st=$cardStyles[$i];
    $sh1->s(9,$c,$lbl,$st);       $sh1->m(9,$c,9,$c+1);
    $sh1->s(10,$c,(int)$val,XS_HD_VAL); $sh1->m(10,$c,10,$c+1);
    $sh1->s(11,$c,'',$st);        $sh1->m(11,$c,11,$c+1);
}

$sh1->rh(12,8);
$sh1->title(13,2,14,'RINGKASAN PER STATUS', XS_HD_SUB, 18);
$sh1->rh(14,18);
foreach ([2=>'Status',5=>'Jumlah',8=>'%',11=>'SLA Met'] as $c=>$h) {
    $sh1->s(14,$c,$h,XS_HD_COL); $sh1->m(14,$c,14,$c+2);
}
$R=15;
$statusRows=[['Menunggu',$stats['menunggu'],'menunggu'],['Diproses',$stats['diproses'],'diproses'],
             ['Selesai',$stats['selesai'],'selesai'],['Ditolak',$stats['ditolak'],'ditolak'],
             ['Tidak Bisa',$stats['tidak_bisa'],'tidak_bisa']];
foreach ($statusRows as $idx=>[$slbl,$sn,$sk]) {
    $dC = $idx%2===0 ? XS_DATA_C_A : XS_DATA_C;
    $pct = $stats['total']>0 ? round($sn/$stats['total']*100) : 0;
    $sh1->s($R,2,$slbl,xStatusStyle($sk)); $sh1->m($R,2,$R,4);
    $sh1->s($R,5,(int)$sn,$dC);           $sh1->m($R,5,$R,7);
    $sh1->s($R,8,$pct.'%',$dC);           $sh1->m($R,8,$R,10);
    $slaCell = ($sk==='selesai') ? $stats['sla_met'].' ('.$sla_pct.'%)' : '—';
    $sh1->s($R,11,$slaCell,$dC);          $sh1->m($R,11,$R,13);
    $sh1->rh($R,15); $R++;
}
$R++;
$sh1->s($R,2,'Laporan dibuat otomatis oleh FixSmart Helpdesk  —  '.date('d/m/Y H:i'),XS_FOOTER);
$sh1->m($R,2,$R,14); $sh1->rh($R,14);


// ══════════════════════════════════════════════════════════════════════════════
// SHEET 2 — DETAIL TIKET
// ══════════════════════════════════════════════════════════════════════════════
$sh2 = new XSheet('Detail Tiket');
$sh2->cw(1,2);
$cols2 = [
    [2,'#',4],[3,'No. Tiket',15],[4,'Judul',28],[5,'Kategori',16],
    [6,'Prioritas',11],[7,'Pemohon',16],[8,'Divisi',13],[9,'Teknisi',16],
    [10,'Status',12],[11,'Tgl Masuk',13],[12,'Tgl Selesai',13],
    [13,'Durasi',11],[14,'SLA Target',11],[15,'SLA Status',13],
];
$sh2->rh(1,6);
$sh2->title(2,2,15,'DETAIL TIKET  —  '.strtoupper($periode_str), XS_HD_MAIN, 30);
$sh2->rh(3,6);
$sh2->s(4,2,'Periode: '.$periode_str.'  |  Kategori: '.$kat_label.'  |  Status: '.$status_label.'  |  Prioritas: '.$prioritas_label, XS_SUB_INFO);
$sh2->m(4,2,4,15); $sh2->rh(4,15); $sh2->rh(5,6);
$R2=6;
foreach ($cols2 as [$c,$h,$w]) { $sh2->s($R2,$c,$h,XS_HD_COL); $sh2->cw($c,$w); }
$sh2->rh($R2,22); $R2++;

if (empty($tikets)) {
    $sh2->s($R2,2,'Tidak ada data tiket.',XS_DATA_G); $sh2->m($R2,2,$R2,15); $sh2->rh($R2,18);
} else {
    foreach ($tikets as $idx=>$t) {
        $alt = $idx%2===0;
        $dL  = $alt ? XS_DATA_L_A  : XS_DATA_L;
        $dC  = $alt ? XS_DATA_C_A  : XS_DATA_C;
        $dW  = $alt ? XS_DATA_W_A  : XS_DATA_W;
        $dG  = $alt ? XS_DATA_G_A  : XS_DATA_G;
        $isFinal = in_array($t['status'],['selesai','ditolak','tidak_bisa']);
        $durMen  = $isFinal ? (int)($t['durasi_selesai_menit']??0) : (int)($t['dur_aktual']??0);

        // SLA
        $slaStr='—'; $slaS=XS_SLA_NA;
        if ($t['sla_jam']) {
            if ($t['status']==='selesai') {
                $ok = $t['durasi_selesai_menit'] <= $t['sla_jam']*60;
                $slaStr = $ok ? 'Dalam SLA' : 'Melewati';
                $slaS   = $ok ? XS_SLA_OK   : XS_SLA_BAD;
            } elseif (in_array($t['status'],['menunggu','diproses'])) {
                $over   = (int)$t['dur_aktual'] > $t['sla_jam']*60;
                $slaStr = $over ? 'Melewati' : 'Berjalan';
                $slaS   = $over ? XS_SLA_BAD : XS_SLA_WARN;
            } else { $slaStr='N/A'; }
        }

        $sh2->s($R2, 2,  $idx+1,                      $dC);
        $sh2->s($R2, 3,  $t['nomor'],                  $dC);
        $sh2->s($R2, 4,  $t['judul'],                  $dW);
        $sh2->s($R2, 5,  $t['kat_nama']??'—',          $dL);
        $sh2->s($R2, 6,  $t['prioritas'],               xPrStyle($t['prioritas']));
        $sh2->s($R2, 7,  $t['req_nama']??'—',           $dL);
        $sh2->s($R2, 8,  $t['divisi']??'—',             $dG);
        $sh2->s($R2, 9,  $t['tek_nama']??'—',           $dL);
        $sh2->s($R2,10,  xStatusLabel($t['status']),    xStatusStyle($t['status']));
        $sh2->s($R2,11,  $t['waktu_submit'] ? date('d/m/Y H:i',strtotime($t['waktu_submit'])) : '-', $dC);
        $sh2->s($R2,12,  $t['waktu_selesai']? date('d/m/Y H:i',strtotime($t['waktu_selesai'])): '-', $dC);
        $sh2->s($R2,13,  xDur($durMen),                 $dC);
        $sh2->s($R2,14,  $t['sla_jam'] ? $t['sla_jam'].' jam' : '-', $dG);
        $sh2->s($R2,15,  $slaStr,                        $slaS);
        $sh2->rh($R2,16); $R2++;
    }
    $R2++;
    $sh2->s($R2,2,'TOTAL',XS_TOT_L); $sh2->m($R2,2,$R2,12);
    $sh2->s($R2,13,(int)$stats['total'],XS_TOT); $sh2->m($R2,13,$R2,14);
    $sh2->s($R2,15,'SLA Met: '.$stats['sla_met'].' ('.$sla_pct.'%)',XS_TOT);
    $sh2->rh($R2,16);
}
$sh2->freeze='B7';


// ══════════════════════════════════════════════════════════════════════════════
// SHEET 3 — REKAP PER KATEGORI
// ══════════════════════════════════════════════════════════════════════════════
$rekapKat=[];
foreach ($tikets as $t) {
    $kn = $t['kat_nama']??'Tanpa Kategori';
    if (!isset($rekapKat[$kn])) $rekapKat[$kn]=['nama'=>$kn,'total'=>0,'selesai'=>0,'ditolak'=>0,
        'tidak_bisa'=>0,'menunggu'=>0,'diproses'=>0,'sla_met'=>0,'dur_sum'=>0,'dur_cnt'=>0];
    $rekapKat[$kn]['total']++;
    if (isset($rekapKat[$kn][$t['status']])) $rekapKat[$kn][$t['status']]++;
    if ($t['status']==='selesai' && $t['sla_jam'] && $t['durasi_selesai_menit']<=$t['sla_jam']*60)
        $rekapKat[$kn]['sla_met']++;
    if ($t['durasi_selesai_menit']>0) {
        $rekapKat[$kn]['dur_sum']+=$t['durasi_selesai_menit'];
        $rekapKat[$kn]['dur_cnt']++;
    }
}

$sh3 = new XSheet('Rekap Per Kategori');
$sh3->cw(1,2); $sh3->rh(1,6);
$sh3->title(2,2,12,'REKAP PER KATEGORI  —  '.strtoupper($periode_str), XS_HD_MAIN, 30);
$sh3->rh(3,6);
$cols3=[[2,'Kategori',28],[3,'Total',9],[4,'Selesai',9],[5,'Menunggu',9],[6,'Diproses',9],
        [7,'Ditolak',9],[8,'Tdk Bisa',9],[9,'SLA Met',9],[10,'% SLA',9],[11,'Avg Selesai',14],[12,'% Selesai',10]];
$R3=4;
foreach ($cols3 as [$c,$h,$w]) { $sh3->s($R3,$c,$h,XS_HD_COL); $sh3->cw($c,$w); }
$sh3->rh($R3,22); $R3++;
foreach (array_values($rekapKat) as $idx=>$k) {
    $alt=$idx%2===0; $dL=$alt?XS_DATA_L_A:XS_DATA_L; $dC=$alt?XS_DATA_C_A:XS_DATA_C;
    $slaPct = $k['selesai']>0 ? round($k['sla_met']/$k['selesai']*100) : 0;
    $slsP   = $k['total']>0   ? round($k['selesai']/$k['total']*100)   : 0;
    $avgD   = $k['dur_cnt']>0 ? round($k['dur_sum']/$k['dur_cnt'])     : 0;
    $slaS   = $slaPct>=90 ? XS_SLA_OK : ($slaPct>=70 ? XS_SLA_WARN : ($k['selesai']>0 ? XS_SLA_BAD : XS_SLA_NA));
    $sh3->s($R3,2,$k['nama'],$dL);
    $sh3->s($R3,3,(int)$k['total'],$dC); $sh3->s($R3,4,(int)$k['selesai'],$dC);
    $sh3->s($R3,5,(int)$k['menunggu'],$dC); $sh3->s($R3,6,(int)$k['diproses'],$dC);
    $sh3->s($R3,7,(int)$k['ditolak'],$dC); $sh3->s($R3,8,(int)$k['tidak_bisa'],$dC);
    $sh3->s($R3,9,(int)$k['sla_met'],$dC); $sh3->s($R3,10,$slaPct.'%',$slaS);
    $sh3->s($R3,11,xDur($avgD),$dC); $sh3->s($R3,12,$slsP.'%',$dC);
    $sh3->rh($R3,15); $R3++;
}
$sh3->rh($R3,8); $R3++;
$sh3->s($R3,2,'TOTAL',XS_TOT_L);
$sh3->s($R3,3,(int)$stats['total'],XS_TOT); $sh3->s($R3,4,(int)$stats['selesai'],XS_TOT);
$sh3->s($R3,5,(int)$stats['menunggu'],XS_TOT); $sh3->s($R3,6,(int)$stats['diproses'],XS_TOT);
$sh3->s($R3,7,(int)$stats['ditolak'],XS_TOT); $sh3->s($R3,8,(int)$stats['tidak_bisa'],XS_TOT);
$sh3->s($R3,9,(int)$stats['sla_met'],XS_TOT); $sh3->s($R3,10,$sla_pct.'%',XS_TOT);
$sh3->rh($R3,16); $sh3->freeze='B5';


// ══════════════════════════════════════════════════════════════════════════════
// BANGUN FILE XLSX
// ══════════════════════════════════════════════════════════════════════════════
$sheets = [$sh1,$sh2,$sh3];
$n      = count($sheets);

$tmpFile = tempnam(sys_get_temp_dir(),'xlsx_').'.xlsx';
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE|ZipArchive::OVERWRITE) !== true) {
    http_response_code(500); die('Gagal membuat file ZIP.');
}

// [Content_Types].xml
$ct  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$ct .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
$ct .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
$ct .= '<Default Extension="xml"  ContentType="application/xml"/>';
$ct .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
for ($i=0;$i<$n;$i++)
    $ct .= '<Override PartName="/xl/worksheets/sheet'.($i+1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
$ct .= '<Override PartName="/xl/styles.xml"        ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
$ct .= '<Override PartName="/xl/sharedStrings.xml"  ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
$ct .= '</Types>';
$zip->addFromString('[Content_Types].xml', $ct);

// _rels/.rels
$zip->addFromString('_rels/.rels',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    .'</Relationships>');

// xl/workbook.xml
$wb  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$wb .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
     . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
$wb .= '<sheets>';
foreach ($sheets as $i=>$sh)
    $wb .= '<sheet name="'.htmlspecialchars($sh->name,ENT_XML1).'" sheetId="'.($i+1).'" r:id="rId'.($i+1).'"/>';
$wb .= '</sheets></workbook>';
$zip->addFromString('xl/workbook.xml', $wb);

// xl/_rels/workbook.xml.rels
$wr  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$wr .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
foreach ($sheets as $i=>$sh)
    $wr .= '<Relationship Id="rId'.($i+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i+1).'.xml"/>';
$wr .= '<Relationship Id="rId'.($n+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"        Target="styles.xml"/>';
$wr .= '<Relationship Id="rId'.($n+2).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
$wr .= '</Relationships>';
$zip->addFromString('xl/_rels/workbook.xml.rels', $wr);

// Worksheet XMLs
foreach ($sheets as $i=>$sh)
    $zip->addFromString('xl/worksheets/sheet'.($i+1).'.xml', $sh->xml());

// Styles — pakai hardcoded XML
$zip->addFromString('xl/styles.xml', $XI_STYLES);

// SharedStrings
$ssXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' count="'.count($XI_SS).'" uniqueCount="'.count($XI_SS).'">';
foreach ($XI_SS as $str)
    $ssXml .= '<si><t xml:space="preserve">'.htmlspecialchars($str,ENT_XML1,'UTF-8').'</t></si>';
$ssXml .= '</sst>';
$zip->addFromString('xl/sharedStrings.xml', $ssXml);

$zip->close();

// Output
$filename = 'Laporan_Tiket_'.str_replace([' ','/',':'],['-','-','-'],$periode_str).'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($tmpFile));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
readfile($tmpFile);
@unlink($tmpFile);
exit;