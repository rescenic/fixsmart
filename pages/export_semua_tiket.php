<?php
// pages/export_semua_tiket_ipsrs.php
// Export Laporan Tiket IPSRS ke Excel (.xlsx) — Pure PHP, tanpa library tambahan
// Parameter GET: tgl_dari, tgl_sampai, kat, status, prioritas, jenis

session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'teknisi_ipsrs'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

// ── Parameter filter ──────────────────────────────────────────────────────────
$tgl_dari   = $_GET['tgl_dari']   ?? date('Y-m-01');
$tgl_sampai = $_GET['tgl_sampai'] ?? date('Y-m-t');
$fkat       = (int)($_GET['kat']       ?? 0);
$fstatus    = $_GET['status']    ?? '';
$fprioritas = $_GET['prioritas'] ?? '';
$fjenis     = $_GET['jenis']     ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_dari))   $tgl_dari   = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_sampai)) $tgl_sampai = date('Y-m-t');
if (!in_array($fstatus,    ['','menunggu','diproses','selesai','ditolak','tidak_bisa'])) $fstatus    = '';
if (!in_array($fprioritas, ['','Tinggi','Sedang','Rendah']))                             $fprioritas = '';
if (!in_array($fjenis,     ['','Medis','Non-Medis']))                                    $fjenis     = '';

$label_dari   = date('d/m/Y', strtotime($tgl_dari));
$label_sampai = date('d/m/Y', strtotime($tgl_sampai));
$periode_str  = $label_dari . ' s.d. ' . $label_sampai;

// ── Query ─────────────────────────────────────────────────────────────────────
$where  = ["DATE(t.waktu_submit) BETWEEN ? AND ?"];
$params = [$tgl_dari, $tgl_sampai];
if ($fkat)       { $where[] = 't.kategori_id = ?'; $params[] = $fkat; }
if ($fstatus)    { $where[] = 't.status = ?';       $params[] = $fstatus; }
if ($fprioritas) { $where[] = 't.prioritas = ?';    $params[] = $fprioritas; }
if ($fjenis)     { $where[] = 't.jenis_tiket = ?';  $params[] = $fjenis; }
$wsql = implode(' AND ', $where);

$st = $pdo->prepare("
    SELECT t.*, k.nama AS kat_nama, k.sla_jam, k.jenis AS kat_jenis,
           u.nama AS req_nama, u.divisi,
           tek.nama AS tek_nama,
           a.nama_aset, a.no_inventaris AS aset_inv,
           TIMESTAMPDIFF(MINUTE, t.waktu_submit, IFNULL(t.waktu_selesai, NOW())) AS dur_aktual
    FROM tiket_ipsrs t
    LEFT JOIN kategori_ipsrs k ON k.id    = t.kategori_id
    LEFT JOIN users           u ON u.id   = t.user_id
    LEFT JOIN users         tek ON tek.id = t.teknisi_id
    LEFT JOIN aset_ipsrs      a ON a.id   = t.aset_id
    WHERE $wsql ORDER BY t.waktu_submit ASC
");
$st->execute($params);
$tikets = $st->fetchAll();

// ── Statistik ─────────────────────────────────────────────────────────────────
$stats = ['total'=>0,'menunggu'=>0,'diproses'=>0,'selesai'=>0,'ditolak'=>0,'tidak_bisa'=>0,'sla_met'=>0,'medis'=>0,'non_medis'=>0];
foreach ($tikets as $t) {
    $stats['total']++;
    $s = $t['status'];
    if (isset($stats[$s])) $stats[$s]++;
    if (($t['jenis_tiket']??'') === 'Medis')     $stats['medis']++;
    if (($t['jenis_tiket']??'') === 'Non-Medis') $stats['non_medis']++;
    if ($s==='selesai' && $t['sla_jam'] && $t['durasi_selesai_menit'] <= $t['sla_jam']*60)
        $stats['sla_met']++;
}
$sla_pct = $stats['selesai'] > 0 ? round($stats['sla_met'] / $stats['selesai'] * 100) : 0;

// Label filter
$kat_label = '';
if ($fkat) {
    $kr = $pdo->prepare("SELECT nama FROM kategori_ipsrs WHERE id=?");
    $kr->execute([$fkat]); $kr = $kr->fetch();
    $kat_label = $kr ? $kr['nama'] : '';
}
$status_labels   = ['menunggu'=>'Menunggu','diproses'=>'Diproses','selesai'=>'Selesai','ditolak'=>'Ditolak','tidak_bisa'=>'Tidak Bisa'];
$status_label    = $fstatus    ? ($status_labels[$fstatus] ?? $fstatus) : 'Semua';
$prioritas_label = $fprioritas ?: 'Semua';
$kat_label_disp  = $kat_label  ?: 'Semua';
$jenis_label     = $fjenis     ?: 'Semua';


// ══════════════════════════════════════════════════════════════════════════════
// XLSX BUILDER — Pure PHP, zero dependencies
// Semua fungsi/konstanta diawali xi_ / XI_ agar tidak konflik dengan file lain
// ══════════════════════════════════════════════════════════════════════════════
define('XI_NAVY',      '1e3a5f');
define('XI_TEAL',      '26B99A');
define('XI_SUBHD',     '2a4a6b');
define('XI_WHITE',     'FFFFFF');
define('XI_LIGHT',     'EBF5F0');
define('XI_GRAY',      'F8FAFC');
define('XI_GREEN',     'D1FAE5'); define('XI_GREENTXT',  '065F46');
define('XI_ORANGE',    'FEF3C7'); define('XI_ORANGETXT', '92400E');
define('XI_RED',       'FEE2E2'); define('XI_REDTXT',    '991B1B');
define('XI_YELLOW',    'FFF9C4'); define('XI_YELLOWTXT', '5C4E00');
define('XI_PURPLE',    'EDE9FE'); define('XI_PURPLETXT', '4C1D95');
define('XI_BLUE',      'DBEAFE'); define('XI_BLUETXT',   '1E40AF');
define('XI_PINK',      'FCE7F3'); define('XI_PINKTXT',   '9D174D');

$_xi_strings = [];
function xi_si(string $s): int {
    global $_xi_strings;
    $k = array_search($s, $_xi_strings, true);
    if ($k === false) { $_xi_strings[] = $s; $k = array_key_last($_xi_strings); }
    return $k;
}
function xi_col2l(int $n): string {
    $l=''; while($n>0){$n--;$l=chr(65+($n%26)).$l;$n=(int)($n/26);} return $l;
}
function xi_ref(int $c, int $r): string { return xi_col2l($c).$r; }
function xi_cellS(string $ref, $v, int $s): string {
    if ($v===null||$v==='') return "<c r=\"$ref\" s=\"$s\"><v></v></c>";
    if (is_numeric($v))     return "<c r=\"$ref\" t=\"n\" s=\"$s\"><v>$v</v></c>";
    $i=xi_si((string)$v); return "<c r=\"$ref\" t=\"s\" s=\"$s\"><v>$i</v></c>";
}

$_xi_styles = [];
function xi_addStyle(array $s): int {
    global $_xi_styles;
    $k=json_encode($s);
    foreach($_xi_styles as $i=>$st){if(json_encode($st)===$k)return $i;}
    $_xi_styles[]=$s; return array_key_last($_xi_styles);
}
function xi_ms(bool $bold=false,int $sz=10,string $fg='000000',string $bg='',
               string $ha='left',bool $wrap=false,bool $italic=false,bool $border=true): array {
    return compact('bold','sz','fg','bg','ha','wrap','italic','border');
}

$XS=[];
$XS['default']    = xi_addStyle(xi_ms());
$XS['hd_main']    = xi_addStyle(xi_ms(true,13,XI_WHITE,XI_NAVY,'center'));
$XS['hd_sub']     = xi_addStyle(xi_ms(true,10,XI_WHITE,XI_TEAL,'center'));
$XS['hd_col']     = xi_addStyle(xi_ms(true,9,XI_WHITE,XI_SUBHD,'center',true));
$XS['sub_info']   = xi_addStyle(xi_ms(false,9,'888888',XI_GRAY,'center',false,true));
$XS['filter_lbl'] = xi_addStyle(xi_ms(true,9,'374151',XI_BLUE,'left'));
$XS['filter_val'] = xi_addStyle(xi_ms(false,9,XI_BLUETXT,XI_BLUE,'left'));
$XS['c_navy_l']   = xi_addStyle(xi_ms(true,8,XI_WHITE,XI_NAVY,'center'));
$XS['c_navy_v']   = xi_addStyle(xi_ms(true,16,XI_WHITE,XI_NAVY,'center'));
$XS['c_green_l']  = xi_addStyle(xi_ms(true,8,XI_WHITE,'16a34a','center'));
$XS['c_green_v']  = xi_addStyle(xi_ms(true,16,XI_WHITE,'16a34a','center'));
$XS['c_blue_l']   = xi_addStyle(xi_ms(true,8,XI_WHITE,'0369a1','center'));
$XS['c_blue_v']   = xi_addStyle(xi_ms(true,16,XI_WHITE,'0369a1','center'));
$XS['c_orange_l'] = xi_addStyle(xi_ms(true,8,XI_WHITE,'d97706','center'));
$XS['c_orange_v'] = xi_addStyle(xi_ms(true,16,XI_WHITE,'d97706','center'));
$XS['c_red_l']    = xi_addStyle(xi_ms(true,8,XI_WHITE,'dc2626','center'));
$XS['c_red_v']    = xi_addStyle(xi_ms(true,16,XI_WHITE,'dc2626','center'));
$XS['c_purple_l'] = xi_addStyle(xi_ms(true,8,XI_WHITE,'6d28d9','center'));
$XS['c_purple_v'] = xi_addStyle(xi_ms(true,16,XI_WHITE,'6d28d9','center'));
$XS['c_pink_l']   = xi_addStyle(xi_ms(true,8,XI_WHITE,'db2777','center'));
$XS['c_pink_v']   = xi_addStyle(xi_ms(true,16,XI_WHITE,'db2777','center'));
$XS['data_l']     = xi_addStyle(xi_ms(false,9,'1e293b','','left'));
$XS['data_c']     = xi_addStyle(xi_ms(false,9,'1e293b','','center'));
$XS['data_l_alt'] = xi_addStyle(xi_ms(false,9,'1e293b',XI_LIGHT,'left'));
$XS['data_c_alt'] = xi_addStyle(xi_ms(false,9,'1e293b',XI_LIGHT,'center'));
$XS['data_gray']  = xi_addStyle(xi_ms(false,9,'888888','','center',false,true));
$XS['data_gray_a']= xi_addStyle(xi_ms(false,9,'888888',XI_LIGHT,'center',false,true));
$XS['data_wrap']  = xi_addStyle(xi_ms(false,9,'1e293b','','left',true));
$XS['data_wrap_a']= xi_addStyle(xi_ms(false,9,'1e293b',XI_LIGHT,'left',true));
$XS['data_mono']  = xi_addStyle(xi_ms(false,8,'6d28d9',XI_PURPLE,'center'));
$XS['data_mono_a']= xi_addStyle(xi_ms(false,8,'6d28d9',XI_PURPLE,'center'));
$XS['footer']     = xi_addStyle(xi_ms(false,8,'aaaaaa',XI_GRAY,'center',false,true));
$XS['tot_navy']   = xi_addStyle(xi_ms(true,9,XI_WHITE,XI_NAVY,'center'));
$XS['tot_navy_l'] = xi_addStyle(xi_ms(true,9,XI_WHITE,XI_NAVY,'left'));
$XS['st_menunggu']= xi_addStyle(xi_ms(true,8,XI_YELLOWTXT,XI_YELLOW,'center'));
$XS['st_diproses']= xi_addStyle(xi_ms(true,8,XI_BLUETXT,XI_BLUE,'center'));
$XS['st_selesai'] = xi_addStyle(xi_ms(true,8,XI_GREENTXT,XI_GREEN,'center'));
$XS['st_ditolak'] = xi_addStyle(xi_ms(true,8,XI_REDTXT,XI_RED,'center'));
$XS['st_tidakbisa']=xi_addStyle(xi_ms(true,8,XI_PURPLETXT,XI_PURPLE,'center'));
$XS['pr_tinggi']  = xi_addStyle(xi_ms(true,8,XI_REDTXT,XI_RED,'center'));
$XS['pr_sedang']  = xi_addStyle(xi_ms(true,8,XI_ORANGETXT,XI_ORANGE,'center'));
$XS['pr_rendah']  = xi_addStyle(xi_ms(true,8,XI_GREENTXT,XI_GREEN,'center'));
$XS['jenis_medis']= xi_addStyle(xi_ms(true,8,XI_PINKTXT,XI_PINK,'center'));
$XS['jenis_nonmed']=xi_addStyle(xi_ms(true,8,XI_BLUETXT,XI_BLUE,'center'));
$XS['sla_ok']     = xi_addStyle(xi_ms(true,8,XI_GREENTXT,XI_GREEN,'center'));
$XS['sla_warn']   = xi_addStyle(xi_ms(true,8,XI_ORANGETXT,XI_ORANGE,'center'));
$XS['sla_bad']    = xi_addStyle(xi_ms(true,8,XI_REDTXT,XI_RED,'center'));
$XS['sla_na']     = xi_addStyle(xi_ms(false,8,'aaaaaa','','center',false,true));

function xi_statusStyle(string $s): int { global $XS; $m=['menunggu'=>'st_menunggu','diproses'=>'st_diproses','selesai'=>'st_selesai','ditolak'=>'st_ditolak','tidak_bisa'=>'st_tidakbisa']; return $XS[$m[$s]??'data_c']; }
function xi_prStyle(string $p): int     { global $XS; return $XS['pr_'.strtolower($p)] ?? $XS['data_c']; }
function xi_jenisStyle(string $j): int  { global $XS; return $j==='Medis'?$XS['jenis_medis']:$XS['jenis_nonmed']; }
function xi_fmtDur(int $m): string      { if($m<=0)return'-'; if($m<60)return$m.' mnt'; if($m<1440)return floor($m/60).' jam '.($m%60).' mnt'; return floor($m/1440).' hr '.floor(($m%1440)/60).' jam'; }
function xi_statusLabel(string $s): string { $m=['menunggu'=>'Menunggu','diproses'=>'Diproses','selesai'=>'Selesai','ditolak'=>'Ditolak','tidak_bisa'=>'Tidak Bisa']; return $m[$s]??ucfirst($s); }

class XiSheet {
    public string $name; public array $rows=[],$merges=[],$colW=[],$rowH=[]; public string $freeze='';
    public function __construct(string $n){$this->name=$n;}
    public function set(int $r,int $c,$v,int $s):void{$this->rows[$r][$c]=[$v,$s];}
    public function merge(int $r1,int $c1,int $r2,int $c2):void{ if($r1===$r2&&$c1===$c2)return; $this->merges[]=xi_ref($c1,$r1).':'.xi_ref($c2,$r2); }
    public function colW(int $c,float $w):void{$this->colW[$c]=$w;}
    public function rowH(int $r,float $h):void{$this->rowH[$r]=$h;}
    public function title(int $r,int $c1,int $c2,string $t,int $s,float $h=30):void{$this->set($r,$c1,$t,$s);$this->merge($r,$c1,$r,$c2);$this->rowH($r,$h);}
    public function toXml():string{
        $x='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetViews><sheetView workbookViewId="0" showGridLines="0">';
        if($this->freeze){preg_match('/([A-Z]+)(\d+)/',$this->freeze,$m);$col=0;for($i=0;$i<strlen($m[1]);$i++)$col=$col*26+(ord($m[1][$i])-64);$x.='<pane xSplit="'.($col-1).'" ySplit="'.($m[2]-1).'" topLeftCell="'.$this->freeze.'" activePane="bottomRight" state="frozen"/>';}
        $x.='</sheetView></sheetViews>';
        if($this->colW){ksort($this->colW);$x.='<cols>';foreach($this->colW as $c=>$w)$x.='<col min="'.$c.'" max="'.$c.'" width="'.$w.'" customWidth="1"/>';$x.='</cols>';}
        $x.='<sheetData>';ksort($this->rows);
        foreach($this->rows as $rn=>$cols){$ht=isset($this->rowH[$rn])?' ht="'.$this->rowH[$rn].'" customHeight="1"':'';$x.='<row r="'.$rn.'"'.$ht.'>';ksort($cols);foreach($cols as $cn=>[$v,$s])$x.=xi_cellS(xi_ref($cn,$rn),$v,$s);$x.='</row>';}
        $x.='</sheetData>';
        if($this->merges){$x.='<mergeCells count="'.count($this->merges).'">';foreach($this->merges as $m)$x.='<mergeCell ref="'.$m.'"/>';$x.='</mergeCells>';}
        $x.='<pageSetup orientation="landscape" paperSize="9" fitToPage="1" fitToWidth="1" fitToHeight="0"/><pageMargins left="0.5" right="0.5" top="0.5" bottom="0.5" header="0.3" footer="0.3"/></worksheet>';
        return $x;
    }
}

function xi_buildStyles(array $sl):string{
    $fonts=$fills=$borders=$xfs=[];
    $fonts[]='<font><sz val="10"/><name val="Arial"/></font>';
    $fills[]='<fill><patternFill patternType="none"/></fill>';
    $fills[]='<fill><patternFill patternType="gray125"/></fill>';
    $borders[]='<border><left/><right/><top/><bottom/><diagonal/></border>';
    $fm=$fi=$bm=[];
    $tb='<border><left style="thin"><color rgb="D1D5DB"/></left><right style="thin"><color rgb="D1D5DB"/></right><top style="thin"><color rgb="D1D5DB"/></top><bottom style="thin"><color rgb="D1D5DB"/></bottom><diagonal/></border>';
    foreach($sl as $st){
        $fx='<font>'.($st['bold']?'<b/>':'').($st['italic']?'<i/>':'').'<sz val="'.$st['sz'].'"/><color rgb="FF'.strtoupper($st['fg']).'"/><name val="Arial"/></font>';
        if(!isset($fm[$fx])){$fm[$fx]=count($fonts);$fonts[]=$fx;}$fid=$fm[$fx];
        $fix=$st['bg']?'<fill><patternFill patternType="solid"><fgColor rgb="FF'.strtoupper($st['bg']).'"/></patternFill></fill>':'<fill><patternFill patternType="none"/></fill>';
        if(!isset($fi[$fix])){$fi[$fix]=count($fills);$fills[]=$fix;}$fiid=$fi[$fix];
        $bx=$st['border']?$tb:'<border><left/><right/><top/><bottom/><diagonal/></border>';
        if(!isset($bm[$bx])){$bm[$bx]=count($borders);$borders[]=$bx;}$bid=$bm[$bx];
        $ha=$st['ha']==='center'?'center':($st['ha']==='right'?'right':'left');$wt=$st['wrap']?' wrapText="1"':'';
        $xfs[]='<xf numFmtId="0" fontId="'.$fid.'" fillId="'.$fiid.'" borderId="'.$bid.'" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="'.$ha.'" vertical="center"'.$wt.'/></xf>';
    }
    $x='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $x.='<fonts count="'.count($fonts).'">'.implode('',$fonts).'</fonts>';
    $x.='<fills count="'.count($fills).'">'.implode('',$fills).'</fills>';
    $x.='<borders count="'.count($borders).'">'.implode('',$borders).'</borders>';
    $x.='<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
    $x.='<cellXfs count="'.count($xfs).'">'.implode('',$xfs).'</cellXfs></styleSheet>';
    return $x;
}
function xi_buildSS(array $strings):string{
    $x='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">';
    foreach($strings as $s)$x.='<si><t xml:space="preserve">'.htmlspecialchars($s,ENT_XML1,'UTF-8').'</t></si>';
    return $x.'</sst>';
}


// ══════════════════════════════════════════════════════════════════════════════
// SHEET 1 — RINGKASAN
// ══════════════════════════════════════════════════════════════════════════════
$sh1=new XiSheet('Ringkasan');
$sh1->colW(1,2); foreach(range(2,14) as $c) $sh1->colW($c,12);
$sh1->rowH(1,6);
$sh1->title(2,2,14,'LAPORAN TIKET IPSRS  —  '.strtoupper($periode_str),$XS['hd_main'],34);
$sh1->set(3,2,'Dicetak: '.date('d F Y  H:i').'   |   FixSmart Helpdesk — IPSRS',$XS['sub_info']);
$sh1->merge(3,2,3,14); $sh1->rowH(3,15); $sh1->rowH(4,6);

$fInfo=[['Periode',$periode_str],['Jenis',$jenis_label],['Kategori',$kat_label_disp],
        ['Status',$status_label],['Prioritas',$prioritas_label],['Total',(string)$stats['total']]];
$fcols=[2,4,6,8,10,12];
$sh1->rowH(5,6); $sh1->rowH(6,16); $sh1->rowH(7,16); $sh1->rowH(8,6);
foreach($fInfo as $i=>[$l,$v]){$c=$fcols[$i];$sh1->set(6,$c,$l,$XS['filter_lbl']);$sh1->merge(6,$c,6,$c+1);$sh1->set(7,$c,$v,$XS['filter_val']);$sh1->merge(7,$c,7,$c+1);}

$cards=[
    ['TOTAL TIKET',$stats['total'],'c_navy_l','c_navy_v'],
    ['SELESAI',$stats['selesai'],'c_green_l','c_green_v'],
    ['MENUNGGU',$stats['menunggu'],'c_orange_l','c_orange_v'],
    ['DIPROSES',$stats['diproses'],'c_blue_l','c_blue_v'],
    ['MEDIS',$stats['medis'],'c_pink_l','c_pink_v'],
    ['NON-MEDIS',$stats['non_medis'],'c_purple_l','c_purple_v'],
];
$sh1->rowH(9,14); $sh1->rowH(10,28); $sh1->rowH(11,8);
foreach($cards as $i=>[$lbl,$val,$ls,$vs]){$c=$fcols[$i];$sh1->set(9,$c,$lbl,$XS[$ls]);$sh1->merge(9,$c,9,$c+1);$sh1->set(10,$c,(string)$val,$XS[$vs]);$sh1->merge(10,$c,10,$c+1);$sh1->set(11,$c,'',$XS[$ls]);$sh1->merge(11,$c,11,$c+1);}

// Ringkasan per status
$sh1->rowH(12,8);
$sh1->title(13,2,14,'RINGKASAN PER STATUS',$XS['hd_sub'],18);
$sh1->rowH(14,18);
foreach([2,5,8,11] as $ci=>$c){$sh1->set(14,$c,['Status','Jumlah','%','Jenis Tiket'][$ci],$XS['hd_col']);$sh1->merge(14,$c,14,$c+2);}
$R=15;
foreach([['Menunggu',$stats['menunggu'],'menunggu'],['Diproses',$stats['diproses'],'diproses'],
         ['Selesai',$stats['selesai'],'selesai'],['Ditolak',$stats['ditolak'],'ditolak'],
         ['Tidak Bisa',$stats['tidak_bisa'],'tidak_bisa']] as $idx=>[$slbl,$sn,$sk]){
    $alt=$idx%2===0; $dC=$alt?$XS['data_c_alt']:$XS['data_c'];
    $pct=$stats['total']>0?round($sn/$stats['total']*100):0;
    $sh1->set($R,2,$slbl,xi_statusStyle($sk));$sh1->merge($R,2,$R,4);
    $sh1->set($R,5,$sn,$dC);$sh1->merge($R,5,$R,7);
    $sh1->set($R,8,$pct.'%',$dC);$sh1->merge($R,8,$R,10);
    $sh1->rowH($R,15);$R++;
}
$R++;
$sh1->title($R,2,14,'MEDIS vs NON-MEDIS',$XS['hd_sub'],18);$R++;
$pctM=$stats['total']>0?round($stats['medis']/$stats['total']*100):0;
$pctN=$stats['total']>0?round($stats['non_medis']/$stats['total']*100):0;
$sh1->set($R,2,'Medis',$XS['jenis_medis']);$sh1->merge($R,2,$R,4);$sh1->set($R,5,$stats['medis'],$XS['data_c']);$sh1->merge($R,5,$R,7);$sh1->set($R,8,$pctM.'%',$XS['data_c']);$sh1->merge($R,8,$R,10);$sh1->rowH($R,15);$R++;
$sh1->set($R,2,'Non-Medis',$XS['jenis_nonmed']);$sh1->merge($R,2,$R,4);$sh1->set($R,5,$stats['non_medis'],$XS['data_c_alt']);$sh1->merge($R,5,$R,7);$sh1->set($R,8,$pctN.'%',$XS['data_c_alt']);$sh1->merge($R,8,$R,10);$sh1->rowH($R,15);$R+=2;
$sh1->set($R,2,'Laporan dibuat otomatis oleh FixSmart Helpdesk  --  '.date('d/m/Y H:i'),$XS['footer']);$sh1->merge($R,2,$R,14);


// ══════════════════════════════════════════════════════════════════════════════
// SHEET 2 — DETAIL TIKET
// ══════════════════════════════════════════════════════════════════════════════
$sh2=new XiSheet('Detail Tiket');
$sh2->colW(1,2);
$colsDet=[[2,'#',4],[3,'No. Tiket',15],[4,'Judul',28],[5,'Jenis',10],[6,'Kategori',16],
          [7,'Prioritas',11],[8,'Pemohon',16],[9,'Divisi',13],[10,'Teknisi',16],
          [11,'Aset',16],[12,'No. Inv.',12],[13,'Status',12],[14,'Tgl Masuk',13],
          [15,'Tgl Selesai',13],[16,'Durasi',11],[17,'SLA Target',11],[18,'SLA Status',13]];
$sh2->rowH(1,6);
$sh2->title(2,2,18,'DETAIL TIKET IPSRS  —  '.strtoupper($periode_str).' | Jenis: '.$jenis_label,$XS['hd_main'],30);
$sh2->rowH(3,6);
$sh2->set(4,2,'Periode: '.$periode_str.'  |  Jenis: '.$jenis_label.'  |  Kategori: '.$kat_label_disp.'  |  Status: '.$status_label,$XS['sub_info']);
$sh2->merge(4,2,4,18);$sh2->rowH(4,15);$sh2->rowH(5,6);
$R2=6;
foreach($colsDet as[$c,$h,$w]){$sh2->set($R2,$c,$h,$XS['hd_col']);$sh2->colW($c,$w);}
$sh2->rowH($R2,22);$R2++;

if(empty($tikets)){
    $sh2->set($R2,2,'Tidak ada data tiket.',$XS['data_gray']);$sh2->merge($R2,2,$R2,18);$sh2->rowH($R2,18);
} else {
    foreach($tikets as $idx=>$t){
        $alt=$idx%2===0;
        $dL=$alt?$XS['data_l_alt']:$XS['data_l'];
        $dC=$alt?$XS['data_c_alt']:$XS['data_c'];
        $dW=$alt?$XS['data_wrap_a']:$XS['data_wrap'];
        $dG=$alt?$XS['data_gray_a']:$XS['data_gray'];
        $isFinal=in_array($t['status'],['selesai','ditolak','tidak_bisa']);
        $durMen=$isFinal?(int)($t['durasi_selesai_menit']??0):(int)($t['dur_aktual']??0);
        // SLA
        $slaStr='—';$slaS=$XS['sla_na'];
        if($t['sla_jam']){
            if($t['status']==='selesai'){$slaStr=$t['durasi_selesai_menit']<=$t['sla_jam']*60?'Dalam SLA':'Melewati';$slaS=$slaStr==='Dalam SLA'?$XS['sla_ok']:$XS['sla_bad'];}
            elseif(in_array($t['status'],['menunggu','diproses'])){$slaStr=(int)$t['dur_aktual']>$t['sla_jam']*60?'Melewati':'Dalam SLA';$slaS=$slaStr==='Melewati'?$XS['sla_bad']:$XS['sla_warn'];}
            else{$slaStr='N/A';}
        }
        $sh2->set($R2,2,$idx+1,$dC);
        $sh2->set($R2,3,$t['nomor'],$dC);
        $sh2->set($R2,4,$t['judul'],$dW);
        $sh2->set($R2,5,$t['jenis_tiket']??'—',xi_jenisStyle($t['jenis_tiket']??''));
        $sh2->set($R2,6,$t['kat_nama']??'—',$dL);
        $sh2->set($R2,7,$t['prioritas'],xi_prStyle($t['prioritas']));
        $sh2->set($R2,8,$t['req_nama']??'—',$dL);
        $sh2->set($R2,9,$t['divisi']??'—',$dG);
        $sh2->set($R2,10,$t['tek_nama']??'—',$dL);
        $sh2->set($R2,11,$t['nama_aset']??'—',$dL);
        $sh2->set($R2,12,$t['aset_inv']??'—',$XS[$alt?'data_mono_a':'data_mono']);
        $sh2->set($R2,13,xi_statusLabel($t['status']),xi_statusStyle($t['status']));
        $sh2->set($R2,14,$t['waktu_submit']?date('d/m/Y H:i',strtotime($t['waktu_submit'])):'-',$dC);
        $sh2->set($R2,15,$t['waktu_selesai']?date('d/m/Y H:i',strtotime($t['waktu_selesai'])):'-',$dC);
        $sh2->set($R2,16,xi_fmtDur($durMen),$dC);
        $sh2->set($R2,17,$t['sla_jam']?$t['sla_jam'].' jam':'-',$dG);
        $sh2->set($R2,18,$slaStr,$slaS);
        $sh2->rowH($R2,16);$R2++;
    }
    $R2++;
    $sh2->set($R2,2,'TOTAL',$XS['tot_navy_l']);$sh2->merge($R2,2,$R2,12);
    $sh2->set($R2,13,$stats['total'],$XS['tot_navy']);$sh2->merge($R2,13,$R2,14);
    $sh2->set($R2,15,'SLA Met: '.$stats['sla_met'].' ('.$sla_pct.'%)',$XS['tot_navy']);$sh2->merge($R2,15,$R2,18);
    $sh2->rowH($R2,16);
}
$sh2->freeze='B7';


// ══════════════════════════════════════════════════════════════════════════════
// SHEET 3 — REKAP PER KATEGORI
// ══════════════════════════════════════════════════════════════════════════════
$rekapKat=[];
foreach($tikets as $t){
    $kn=($t['kat_jenis']??'').' — '.($t['kat_nama']??'Tanpa Kategori');
    $jen=$t['jenis_tiket']??'';
    if(!isset($rekapKat[$kn]))$rekapKat[$kn]=['nama'=>$kn,'jenis'=>$jen,'total'=>0,'selesai'=>0,'ditolak'=>0,'tdk_bisa'=>0,'menunggu'=>0,'diproses'=>0,'sla_met'=>0,'dur_sum'=>0,'dur_cnt'=>0];
    $rekapKat[$kn]['total']++; $s=$t['status'];
    if(isset($rekapKat[$kn][$s]))$rekapKat[$kn][$s]++;
    if($s==='selesai'&&$t['sla_jam']&&$t['durasi_selesai_menit']<=$t['sla_jam']*60)$rekapKat[$kn]['sla_met']++;
    if($t['durasi_selesai_menit']>0){$rekapKat[$kn]['dur_sum']+=$t['durasi_selesai_menit'];$rekapKat[$kn]['dur_cnt']++;}
}
$sh3=new XiSheet('Rekap Per Kategori');
$sh3->colW(1,2);$sh3->rowH(1,6);
$sh3->title(2,2,13,'REKAP PER KATEGORI IPSRS  —  '.strtoupper($periode_str),$XS['hd_main'],30);
$sh3->rowH(3,6);
$colsKat=[[2,'Jenis / Kategori',28],[3,'Total',9],[4,'Selesai',9],[5,'Menunggu',9],[6,'Diproses',9],[7,'Ditolak',9],[8,'Tdk Bisa',9],[9,'SLA Met',9],[10,'% SLA',9],[11,'Avg Selesai',13],[12,'% Selesai',10],[13,'Jenis',10]];
$R3=4;foreach($colsKat as[$c,$h,$w]){$sh3->set($R3,$c,$h,$XS['hd_col']);$sh3->colW($c,$w);}$sh3->rowH($R3,22);$R3++;
foreach(array_values($rekapKat) as $idx=>$k){
    $alt=$idx%2===0;$dL=$alt?$XS['data_l_alt']:$XS['data_l'];$dC=$alt?$XS['data_c_alt']:$XS['data_c'];
    $slaPct=$k['selesai']>0?round($k['sla_met']/$k['selesai']*100):0;
    $slsP=$k['total']>0?round($k['selesai']/$k['total']*100):0;
    $avgD=$k['dur_cnt']>0?round($k['dur_sum']/$k['dur_cnt']):0;
    $slaS=$slaPct>=90?$XS['sla_ok']:($slaPct>=70?$XS['sla_warn']:($k['selesai']>0?$XS['sla_bad']:$XS['sla_na']));
    $sh3->set($R3,2,$k['nama'],$dL);$sh3->set($R3,3,$k['total'],$dC);$sh3->set($R3,4,$k['selesai'],$dC);
    $sh3->set($R3,5,$k['menunggu'],$dC);$sh3->set($R3,6,$k['diproses'],$dC);$sh3->set($R3,7,$k['ditolak'],$dC);
    $sh3->set($R3,8,$k['tdk_bisa'],$dC);$sh3->set($R3,9,$k['sla_met'],$dC);$sh3->set($R3,10,$slaPct.'%',$slaS);
    $sh3->set($R3,11,xi_fmtDur($avgD),$dC);$sh3->set($R3,12,$slsP.'%',$dC);$sh3->set($R3,13,$k['jenis'],xi_jenisStyle($k['jenis']));
    $sh3->rowH($R3,15);$R3++;
}
$sh3->rowH($R3,8);$R3++;
$sh3->set($R3,2,'TOTAL',$XS['tot_navy_l']);foreach(range(3,13)as $c)$sh3->set($R3,$c,'-',$XS['tot_navy']);
$sh3->set($R3,3,$stats['total'],$XS['tot_navy']);$sh3->set($R3,4,$stats['selesai'],$XS['tot_navy']);
$sh3->set($R3,5,$stats['menunggu'],$XS['tot_navy']);$sh3->set($R3,6,$stats['diproses'],$XS['tot_navy']);
$sh3->set($R3,7,$stats['ditolak'],$XS['tot_navy']);$sh3->set($R3,8,$stats['tidak_bisa'],$XS['tot_navy']);
$sh3->set($R3,9,$stats['sla_met'],$XS['tot_navy']);$sh3->set($R3,10,$sla_pct.'%',$XS['tot_navy']);
$sh3->rowH($R3,16);$sh3->freeze='B5';


// ══════════════════════════════════════════════════════════════════════════════
// BANGUN XLSX
// ══════════════════════════════════════════════════════════════════════════════
$sheets=[$sh1,$sh2,$sh3];
$sheetXmls=array_map(fn($s)=>$s->toXml(),$sheets);
$stylesXml=xi_buildStyles($_xi_styles);
$ssXml=xi_buildSS($_xi_strings);

$tmpFile=tempnam(sys_get_temp_dir(),'ipsrs_xlsx_').'.xlsx';
$zip=new ZipArchive();
$zip->open($tmpFile,ZipArchive::CREATE|ZipArchive::OVERWRITE);

$ct='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
for($i=0;$i<count($sheets);$i++)$ct.='<Override PartName="/xl/worksheets/sheet'.($i+1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
$ct.='<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>';
$zip->addFromString('[Content_Types].xml',$ct);
$zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$wb='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
foreach($sheets as $i=>$s)$wb.='<sheet name="'.htmlspecialchars($s->name,ENT_XML1).'" sheetId="'.($i+1).'" r:id="rId'.($i+1).'"/>';
$wb.='</sheets></workbook>';
$zip->addFromString('xl/workbook.xml',$wb);
$wr='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
foreach($sheets as $i=>$s)$wr.='<Relationship Id="rId'.($i+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i+1).'.xml"/>';
$n=count($sheets);
$wr.='<Relationship Id="rId'.($n+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rId'.($n+2).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>';
$zip->addFromString('xl/_rels/workbook.xml.rels',$wr);
foreach($sheetXmls as $i=>$xml)$zip->addFromString('xl/worksheets/sheet'.($i+1).'.xml',$xml);
$zip->addFromString('xl/styles.xml',$stylesXml);
$zip->addFromString('xl/sharedStrings.xml',$ssXml);
$zip->close();

$filename='Laporan_IPSRS_'.str_replace([' ','/',':'],['_','-','-'],$periode_str).'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($tmpFile));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
readfile($tmpFile);
@unlink($tmpFile);
exit;