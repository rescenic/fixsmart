<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasRole('user')) { http_response_code(403); exit('Akses ditolak.'); }

// Cari dompdf
$dompdf_available = false; $dp_autoload = '';
foreach ([
    __DIR__ . '/../dompdf/autoload.inc.php',
    __DIR__ . '/../dompdf/vendor/autoload.php',
    __DIR__ . '/../vendor/dompdf/dompdf/autoload.inc.php',
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__) . '/dompdf/autoload.inc.php',
    dirname(__DIR__) . '/vendor/autoload.php',
] as $p) { if (file_exists($p)) { $dp_autoload=$p; $dompdf_available=true; break; } }

// Ambil data
$raw = trim($_GET['id'] ?? '');
$ids = array_values(array_filter(array_map('intval', explode(',', $raw))));
if (empty($ids)) die('<p style="color:#ef4444;padding:20px;">Parameter id tidak valid.</p>');
$ph  = implode(',', array_fill(0, count($ids), '?'));
$stm = $pdo->prepare("SELECT a.*, b.nama AS bagian_nama, b.kode AS bagian_kode, u.nama AS pj_nama_db
    FROM aset_it a LEFT JOIN bagian b ON b.id=a.bagian_id LEFT JOIN users u ON u.id=a.pj_user_id
    WHERE a.id IN ($ph) ORDER BY a.id ASC");
$stm->execute($ids);
$asets = $stm->fetchAll(PDO::FETCH_ASSOC);
if (empty($asets)) die('<p style="color:#ef4444;padding:20px;">Data tidak ditemukan.</p>');


// ============================================================
// QR CODE GENERATOR - Pure PHP, no internet, no library needed
// ============================================================
function makeQR(string $text, int $px = 3): string {
    // Coba phpqrcode dulu jika ada
    foreach ([
        __DIR__ . '/../phpqrcode/qrlib.php',
        __DIR__ . '/../phpqrcode/phpqrcode/qrlib.php',
        dirname(__DIR__) . '/phpqrcode/qrlib.php',
    ] as $p) {
        if (file_exists($p)) {
            require_once $p;
            ob_start();
            QRcode::png($text, false, QR_ECLEVEL_M, $px, 1);
            $png = ob_get_clean();
            if ($png) return 'data:image/png;base64,' . base64_encode($png);
        }
    }
    // Fallback pure PHP SVG
    return makeSVGQR($text, $px);
}

function makeSVGQR(string $text, int $px = 3): string {
    $m = buildQR($text);
    $n = count($m); $q = $px*4; $d = $n*$px+$q*2;
    $r = '';
    for ($i=0;$i<$n;$i++) for ($j=0;$j<$n;$j++)
        if ($m[$i][$j]) $r .= "<rect x='".($q+$j*$px)."' y='".($q+$i*$px)."' width='$px' height='$px'/>";
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='$d' height='$d'>"
         . "<rect width='$d' height='$d' fill='#fff'/><g fill='#000'>$r</g></svg>";
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function buildQR(string $text): array {
    $bytes=$data=array_values(unpack('C*',$text)); $len=count($bytes);
    $caps=[1=>14,2=>26,3=>42,4=>62,5=>84,6=>106,7=>122,8=>154,9=>180,10=>213];
    $ver=10; foreach($caps as $v=>$c){if($len<=$c){$ver=$v;break;}}
    $size=17+$ver*4;
    $mat=array_fill(0,$size,array_fill(0,$size,0));
    $res=array_fill(0,$size,array_fill(0,$size,false));
    // Finder
    $pf=function($tr,$tc)use(&$mat,&$res,$size){
        for($i=0;$i<7;$i++)for($j=0;$j<7;$j++){
            $v=($i===0||$i===6||$j===0||$j===6||($i>=2&&$i<=4&&$j>=2&&$j<=4))?1:0;
            if($tr+$i<$size&&$tc+$j<$size){$mat[$tr+$i][$tc+$j]=$v;$res[$tr+$i][$tc+$j]=true;}}
        for($k=-1;$k<=7;$k++)foreach([[$tr-1,$tc+$k],[$tr+7,$tc+$k],[$tr+$k,$tc-1],[$tr+$k,$tc+7]]as[$rr,$cc])
            if($rr>=0&&$rr<$size&&$cc>=0&&$cc<$size&&!$res[$rr][$cc]){$mat[$rr][$cc]=0;$res[$rr][$cc]=true;}};
    $pf(0,0);$pf(0,$size-7);$pf($size-7,0);
    // Timing
    for($i=8;$i<$size-8;$i++){$v=($i%2===0)?1:0;
        if(!$res[6][$i]){$mat[6][$i]=$v;$res[6][$i]=true;}
        if(!$res[$i][6]){$mat[$i][6]=$v;$res[$i][6]=true;}}
    $mat[$size-8][8]=1;$res[$size-8][8]=true;
    // Format reserve
    foreach([0,1,2,3,4,5,7,8]as$i){$res[8][$i]=true;$res[$i][8]=true;}
    $res[8][8]=true;
    for($i=0;$i<8;$i++){$res[$size-1-$i][8]=true;$res[8][$size-1-$i]=true;}
    // Alignment
    $at=[1=>[],2=>[6,18],3=>[6,22],4=>[6,26],5=>[6,30],6=>[6,34],7=>[6,22,38],8=>[6,24,42],9=>[6,26,46],10=>[6,28,50]];
    $ap=$at[$ver]??[];
    foreach($ap as$ar)foreach($ap as$ac){$last=end($ap);
        if(($ar===6&&$ac===6)||($ar===6&&$ac===$last)||($ac===6&&$ar===$last))continue;
        for($i=-2;$i<=2;$i++)for($j=-2;$j<=2;$j++){
            $v=($i===-2||$i===2||$j===-2||$j===2||($i===0&&$j===0))?1:0;
            if($ar+$i>=0&&$ar+$i<$size&&$ac+$j>=0&&$ac+$j<$size&&!$res[$ar+$i][$ac+$j])
                {$mat[$ar+$i][$ac+$j]=$v;$res[$ar+$i][$ac+$j]=true;}}}
    // Encode
    $ew=[1=>10,2=>16,3=>26,4=>36,5=>48,6=>64,7=>72,8=>88,9=>110,10=>130];
    $dw=[1=>16,2=>28,3=>44,4=>64,5=>86,6=>108,7=>124,8=>154,9=>182,10=>216];
    $dW=$dw[$ver]??16; $eW=$ew[$ver]??10;
    $bits='0100'.sprintf('%08b',min($len,$dW-3));
    foreach(array_slice($bytes,0,$dW-3)as$b)$bits.=sprintf('%08b',$b);
    $bits.='0000'; while(strlen($bits)%8)$bits.='0';
    $pads=['11101100','00010001'];$pi=0;
    while(strlen($bits)<$dW*8)$bits.=$pads[$pi++%2];
    $cw=[];for($i=0;$i<strlen($bits);$i+=8)$cw[]=bindec(substr($bits,$i,8));
    $ecw=rsECC($cw,$eW); $all=array_merge($cw,$ecw);
    $rem=[0,7,7,7,7,7,0,0,0,0,0]; $fb='';
    foreach($all as$b)$fb.=sprintf('%08b',$b&0xFF);
    for($i=0;$i<($rem[min($ver,10)]??0);$i++)$fb.='0';
    // Place with best mask
    $bm=0;$bp=PHP_INT_MAX;$mc=[];
    for($mask=0;$mask<8;$mask++){
        $m=$mat;$bi=0;$dir=-1;$cp=$size-1;
        while($cp>=1){$c2=($cp<=6)?$cp-1:$cp;
            for($cnt=0;$cnt<$size;$cnt++){$r=($dir===-1)?($size-1-$cnt):$cnt;
                for($dc=0;$dc<=1;$dc++){$c=$c2-$dc;
                    if($c>=0&&!$res[$r][$c]){$bit=($bi<strlen($fb))?(int)$fb[$bi++]:0;
                        if(qrMask($mask,$r,$c))$bit^=1;$m[$r][$c]=$bit;}}}
            $dir*=-1;$cp-=2;if($cp===6)$cp--;}
        qrFmt($m,$mask,$size);$mc[$mask]=$m;
        $pen=qrPen($m,$size);if($pen<$bp){$bp=$pen;$bm=$mask;}}
    return $mc[$bm];
}
function qrMask($k,$r,$c){return match($k){0=>($r+$c)%2===0,1=>$r%2===0,2=>$c%3===0,3=>($r+$c)%3===0,4=>(intdiv($r,2)+intdiv($c,3))%2===0,5=>(($r*$c)%2+($r*$c)%3)===0,6=>(($r*$c)%2+($r*$c)%3)%2===0,7=>(($r+$c)%2+($r*$c)%3)%2===0};}
function qrFmt(&$mat,$mask,$size){$fd=(0b00<<3)|$mask;$g=0b10100110111;$b=$fd<<10;
    for($i=14;$i>=10;$i--)if($b&(1<<$i))$b^=($g<<($i-10));
    $fmt=(($fd<<10)|$b)^0b101010000010010;
    $pos=[[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],[7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
    for($i=0;$i<15;$i++){$bit=($fmt>>(14-$i))&1;[$fr,$fc]=$pos[$i];if($fr<$size&&$fc<$size)$mat[$fr][$fc]=$bit;}
    for($i=0;$i<7;$i++)$mat[$size-1-$i][8]=($fmt>>$i)&1;
    $mat[$size-8][8]=1;
    for($i=0;$i<8;$i++)if($size-1-$i>=0)$mat[8][$size-1-$i]=($fmt>>(14-$i-7))&1;}
function qrPen($m,$size){$p=0;
    for($r=0;$r<$size;$r++){$run=1;for($c=1;$c<$size;$c++){if($m[$r][$c]===$m[$r][$c-1]){$run++;if($run===5)$p+=3;elseif($run>5)$p++;}else $run=1;}}
    for($r=0;$r<$size-1;$r++)for($c=0;$c<$size-1;$c++)
        if($m[$r][$c]===$m[$r+1][$c]&&$m[$r][$c]===$m[$r][$c+1]&&$m[$r][$c]===$m[$r+1][$c+1])$p+=3;return $p;}
function rsECC($data,$el){static $exp=null,$log=null;
    if($exp===null){$exp=[];$log=array_fill(0,256,0);$x=1;
        for($i=0;$i<255;$i++){$exp[$i]=$x;$log[$x]=$i;$x<<=1;if($x>=256)$x^=0x11D;}$exp[255]=$exp[0];}
    $g=[1];for($i=0;$i<$el;$i++){$ng=array_fill(0,count($g)+1,0);$a=$exp[$i];
        foreach($g as$j=>$c){$ng[$j]^=$c;$ng[$j+1]^=($c===0)?0:$exp[($log[$c]+$log[$a])%255];}$g=$ng;}
    $msg=array_merge($data,array_fill(0,$el,0));
    for($i=0;$i<count($data);$i++){$co=$msg[$i];if($co!==0)
        for($j=1;$j<count($g);$j++)$msg[$i+$j]^=$exp[($log[$co]+$log[$g[$j]])%255];}
    return array_slice($msg,count($data));}


function detailUrl(int $id): string { return APP_URL . '/aset_detail_publik.php?id=' . $id; }
function kondisiColor(string $k): array {
    return match($k){'Baik'=>['#d1fae5','#065f46'],'Rusak'=>['#fee2e2','#991b1b'],
        'Dalam Perbaikan'=>['#fef9c3','#854d0e'],default=>['#f1f5f9','#475569']};}

function buildLabelHTML(array $a, bool $pdf = false): string {
    $id     = (int)$a['id'];
    $no_inv = htmlspecialchars($a['no_inventaris'] ?? '');
    $nama   = htmlspecialchars(mb_strimwidth($a['nama_aset']??'',0,44,'...'));
    $kat    = htmlspecialchars($a['kategori'] ?? '');
    $merek  = htmlspecialchars(trim(($a['merek']??'').' '.($a['model_aset']??'')));
    $sn     = htmlspecialchars($a['serial_number'] ?? '');
    $kondisi= htmlspecialchars($a['kondisi'] ?? 'Baik');
    $lokasi = htmlspecialchars($a['bagian_nama']
        ? (($a['bagian_kode']?'['.$a['bagian_kode'].'] ':'').$a['bagian_nama'])
        : ($a['lokasi']??'-'));
    $pj     = htmlspecialchars($a['pj_nama_db'] ?? $a['penanggung_jawab'] ?? '-');
    [$kb_bg,$kb_col] = kondisiColor($a['kondisi']??'');

    // QR base64 inline - tampil sama di browser DAN PDF
    $qrSrc = makeQR(detailUrl($id), $pdf ? 3 : 4);
    $qrSz  = $pdf ? '88' : '108';

    // Barlines - gunakan karakter sederhana ASCII saja
    $bars = '';
    foreach ([9,5,13,7,10,5,12,6,9,14,5,10,7,13,5,9,11,6,14,5,10,8,13,6,9,5,12,7] as $i=>$h) {
        $col = ($i%3===0)?'#00e5b0':'#1e2f42';
        $bars .= "<span style='display:inline-block;width:2px;height:{$h}px;background:{$col};border-radius:1px;margin-right:1.5px;'></span>";
    }

    $merek_line = $merek ? "<div style='font-size:7pt;color:#555;margin-bottom:3px;'>{$merek}</div>" : '';
    $sn_line    = $sn    ? "<div style='font-size:6pt;color:#999;margin-top:3px;font-family:monospace;'>S/N: {$sn}</div>" : '';

    // Gunakan &bull; (•) bukan karakter UTF8 agar aman di semua font/dompdf
    return "
<div class='lc'>
  <div style='background:#0a0f14;padding:5px 9px;display:table;width:100%;box-sizing:border-box;'>
    <span style='display:table-cell;color:#00e5b0;font-size:7.5pt;font-weight:bold;'>FixSmart Helpdesk</span>
    <span style='display:table-cell;text-align:right;color:rgba(255,255,255,.7);font-size:6.5pt;
          background:rgba(0,229,176,.15);border:1px solid rgba(0,229,176,.3);
          padding:1px 6px;border-radius:8px;width:1%;white-space:nowrap;'>{$kat}</span>
  </div>
  <div style='display:table;width:100%;padding:7px;box-sizing:border-box;'>
    <div style='display:table-cell;width:30mm;text-align:center;vertical-align:middle;
                padding-right:7px;border-right:1px dashed #ccc;'>
      <img src='{$qrSrc}' width='{$qrSz}' height='{$qrSz}'
           style='display:block;margin:0 auto;border:1px solid #e2e8f0;
                  border-radius:3px;padding:2px;background:#fff;' alt='QR'>
      <div style='font-size:5.5pt;color:#999;margin-top:4px;line-height:1.4;'>Scan untuk<br>info lengkap</div>
    </div>
    <div style='display:table-cell;padding-left:8px;vertical-align:top;'>
      <div style='font-family:monospace;font-size:9pt;font-weight:bold;color:#1e293b;
                  background:#dbeafe;border:1px solid #bfdbfe;
                  padding:2px 8px;border-radius:4px;display:inline-block;margin-bottom:4px;'>{$no_inv}</div>
      <div style='font-size:8.5pt;font-weight:bold;color:#1e293b;line-height:1.3;margin-bottom:3px;'>{$nama}</div>
      {$merek_line}
      <div style='font-size:7pt;color:#333;margin-bottom:2px;'>&bull; {$lokasi}</div>
      <div style='font-size:7pt;color:#333;margin-bottom:4px;'>&bull; {$pj}</div>
      <span style='display:inline-block;padding:2px 8px;border-radius:9px;font-size:6.5pt;
                   font-weight:bold;background:{$kb_bg};color:{$kb_col};'>{$kondisi}</span>
      {$sn_line}
    </div>
  </div>
  <div style='background:#f8fafc;border-top:1px solid #e2e8f0;padding:4px 9px;
              display:table;width:100%;box-sizing:border-box;'>
    <div style='display:table-cell;line-height:0;vertical-align:bottom;'>{$bars}</div>
    <span style='display:table-cell;text-align:right;font-size:6pt;color:#999;
                 font-family:monospace;white-space:nowrap;vertical-align:middle;'>ID #{$id}</span>
  </div>
</div>";
}

function buildGrid(array $asets, bool $pdf=false): string {
    $html='';
    for($i=0;$i<count($asets);$i+=2){
        $c1=buildLabelHTML($asets[$i],$pdf);
        $c2=isset($asets[$i+1])?buildLabelHTML($asets[$i+1],$pdf):'';
        $html.="<tr><td class='col'>{$c1}</td><td class='col'>{$c2}</td></tr>";}
    return $html;}

// ── Mode PDF ──────────────────────────────────────────────────────────────────
if ($dompdf_available) {
    require_once $dp_autoload;
    $dC=class_exists('\Dompdf\Dompdf')?'\Dompdf\Dompdf':'Dompdf\Dompdf';
    $oC=class_exists('\Dompdf\Options')?'\Dompdf\Options':'Dompdf\Options';
    if (!class_exists($dC)) $dompdf_available=false;
}
if ($dompdf_available) {
    $n=count($asets); $grid=buildGrid($asets,true);
    $hp='<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
      .'@page{size:A4 portrait;margin:10mm;}'
      .'*{box-sizing:border-box;margin:0;padding:0;}'
      .'body{font-family:Arial,Helvetica,sans-serif;font-size:8pt;}'
      .'table.grid{width:100%;border-collapse:separate;border-spacing:0;}'
      .'td.col{width:50%;padding:3mm;vertical-align:top;}'
      .'.lc{border:1.5px solid #1e2f42;border-radius:4px;overflow:hidden;background:#fff;}'
      .'</style></head><body><table class="grid">'.$grid.'</table></body></html>';
    $opt=new $oC();
    $opt->set('isHtml5ParserEnabled',true);
    $opt->set('isRemoteEnabled',false); // base64 inline, tidak perlu remote
    $opt->set('defaultFont','Arial');
    $dp=new $dC($opt);
    $dp->loadHtml($hp,'UTF-8');
    $dp->setPaper('A4','portrait');
    $dp->render();
    $slug=$n===1?preg_replace('/[^a-zA-Z0-9_\-]/','_',$asets[0]['no_inventaris']??'aset'):'label-'.$n.'item';
    $dp->stream($slug.'-'.date('Ymd').'.pdf',['Attachment'=>false]);
    exit;
}

// ── Mode Browser Print ────────────────────────────────────────────────────────
$n=count($asets);
$title=$n===1?'Label - '.($asets[0]['no_inventaris']??'Aset'):"Label Aset IT ({$n} item)";
$grid=buildGrid($asets,false);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Arial,Helvetica,sans-serif;font-size:8pt;background:#f0f4f8;color:#1e293b;}
.bar{background:linear-gradient(135deg,#0a0f14,#0d1a2a);padding:12px 20px;display:flex;
     align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.btn-p{display:inline-flex;align-items:center;gap:8px;padding:9px 22px;
       background:linear-gradient(135deg,#00e5b0,#00c896);color:#0a0f14;
       border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;}
.btn-b{padding:7px 14px;background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);
       border:1px solid rgba(255,255,255,.12);border-radius:7px;font-size:12px;
       cursor:pointer;font-family:inherit;text-decoration:none;}
.info{background:#fff;border-bottom:1px solid #e2e8f0;padding:8px 20px;font-size:11.5px;color:#475569;}
.pw{max-width:210mm;margin:20px auto;background:#fff;border-radius:8px;
    box-shadow:0 4px 24px rgba(0,0,0,.1);padding:10mm;}
table.grid{width:100%;border-collapse:separate;border-spacing:0;}
td.col{width:50%;padding:3mm;vertical-align:top;}
.lc{border:1.5px solid #1e2f42;border-radius:6px;overflow:hidden;background:#fff;}
@media print{
  *{-webkit-print-color-adjust:exact !important;print-color-adjust:exact !important;}
  @page{size:A4 portrait;margin:10mm;}
  body{background:#fff;}
  .bar,.info{display:none !important;}
  .pw{max-width:100%;margin:0;padding:0;box-shadow:none;border-radius:0;}
  .lc{page-break-inside:avoid;}
}
</style>
</head>
<body>
<div class="bar">
  <div>
    <div style="color:#00e5b0;font-size:14px;font-weight:bold;">FixSmart Helpdesk</div>
    <div style="color:rgba(255,255,255,.4);font-size:11px;"><?=htmlspecialchars($title)?> &middot; <?=$n?> label</div>
  </div>
  <div style="display:flex;gap:8px;">
    <a class="btn-b" href="javascript:history.back()">&#8592; Kembali</a>
    <button class="btn-p" onclick="window.print()">Cetak / Simpan PDF</button>
  </div>
</div>
<div class="info">&#128203; Pratinjau <strong><?=$n?> label</strong> &mdash; A4, 2 kolom per baris.</div>
<div class="pw"><table class="grid"><?=$grid?></table></div>
<script>
if(new URLSearchParams(window.location.search).get('autoprint')==='1')
    window.addEventListener('load',()=>setTimeout(()=>window.print(),800));
</script>
</body>
</html>