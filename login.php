<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => false,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);
require_once 'config.php';
require_once 'includes/login_helper.php';

if (isLoggedIn()) redirect(APP_URL . '/dashboard.php');

// ── DROPDOWN DATA ──────────────────────────────────────────────────────────────
$divisi_list  = [];
$jabatan_list = [];
try { $divisi_list  = getBagianList($pdo); }   catch (Exception $e) {}
try { $jabatan_list = $pdo->query("SELECT id,nama,kode FROM jabatan WHERE status='aktif' ORDER BY urutan ASC,level ASC,nama ASC")->fetchAll(); } catch (Exception $e) {}

// ── RATE LIMITER (login) ───────────────────────────────────────────────────────
define('MAX_ATTEMPTS', 5);
define('LOCKOUT_SECS', 180);
function loginKey()        { return 'login_' . md5($_SERVER['REMOTE_ADDR'] ?? 'x'); }
function getAttempts()     { $d = $_SESSION[loginKey()] ?? null; return $d ?: ['count'=>0,'first'=>0,'locked_until'=>0]; }
function recordFail()      { $k=loginKey();$d=getAttempts();if(!$d['count'])$d['first']=time();$d['count']++;if($d['count']>=MAX_ATTEMPTS)$d['locked_until']=time()+LOCKOUT_SECS;$_SESSION[$k]=$d; }
function resetAttempts()   { unset($_SESSION[loginKey()]); }
function isLocked()        { $d=getAttempts();if($d['locked_until']>time())return true;if($d['locked_until']>0&&$d['locked_until']<=time())resetAttempts();return false; }
function remainingSeconds(){ return max(0, getAttempts()['locked_until']-time()); }
function remainingAttempts(){ return max(0, MAX_ATTEMPTS-getAttempts()['count']); }

// ── CAPTCHA GENERATORS ─────────────────────────────────────────────────────────
function makeCaptcha(string $pfx = '') {
    $ops=['+','-','+','+','-']; $op=$ops[array_rand($ops)];
    if($op==='+'){$a=rand(1,9);$b=rand(1,9);$ans=$a+$b;}
    else{$a=rand(3,9);$b=rand(1,$a);$ans=$a-$b;}
    $_SESSION[$pfx.'captcha_answer']=$ans;
    return "$a $op $b";
}
// Login captcha
if (empty($_SESSION['captcha_answer']))     $captcha_soal = makeCaptcha();
else $captcha_soal = $_SESSION['captcha_soal'] ?? makeCaptcha();
$_SESSION['captcha_soal'] = $captcha_soal;

// Register captcha
if (empty($_SESSION['reg_captcha_answer'])) $reg_captcha = makeCaptcha('reg_');
else $reg_captcha = $_SESSION['reg_captcha_soal'] ?? makeCaptcha('reg_');
$_SESSION['reg_captcha_soal'] = $reg_captcha;

// ── CSRF TOKENS ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token']))     $_SESSION['csrf_token']     = bin2hex(random_bytes(32));
if (empty($_SESSION['csrf_token_reg'])) $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));

// ── USERNAME GENERATOR ─────────────────────────────────────────────────────────
function generateUsername(PDO $pdo, string $nama): string {
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]/','',explode(' ',trim($nama))[0]));
    if (strlen($base)<3) $base='user'.$base;
    $u=$base; $i=1;
    while(true){$st=$pdo->prepare("SELECT id FROM users WHERE username=?");$st->execute([$u]);if(!$st->fetch())break;$u=$base.$i++;}
    return $u;
}

// ── STATE ─────────────────────────────────────────────────────────────────────
$login_error = '';
$login_msg   = '';
$reg_errors  = [];
$reg_success = false;
$show_reg    = false;   // auto-open modal jika register gagal

$msg_map = ['timeout'=>'Sesi Anda berakhir, silakan login kembali.','logout'=>'Anda berhasil keluar.'];
if (isset($msg_map[$_GET['msg'] ?? ''])) $login_msg = $msg_map[$_GET['msg']];

// ══════════════════════════════════════════════════════════════
// POST HANDLERS
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_act'] ?? 'login';

    /* ─── LOGIN ─── */
    if ($act === 'login') {
        $log_ip     = getClientIP();
        $log_ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log_parsed = parseUserAgent($log_ua);
        $log_input  = trim($_POST['email'] ?? '');

        if (isLocked()) {
            $sisa = remainingSeconds();
            $login_error = "Terlalu banyak percobaan. Coba lagi dalam <strong>{$sisa} detik</strong>.";
            recordLoginLog($pdo,['user_id'=>null,'username_input'=>$log_input,'status'=>'terkunci','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>"Terkunci {$sisa}s",'is_new_ip'=>0]);

        } elseif (!hash_equals($_SESSION['csrf_token']??'', $_POST['csrf_token']??'')) {
            $login_error = 'Permintaan tidak valid. Muat ulang halaman.';

        } elseif ((int)($_POST['captcha']??-999) !== (int)($_SESSION['captcha_answer']??-1)) {
            $login_error  = 'Jawaban captcha salah.';
            $captcha_soal = makeCaptcha(); $_SESSION['captcha_soal'] = $captcha_soal;
            recordFail();
            recordLoginLog($pdo,['user_id'=>null,'username_input'=>$log_input,'status'=>'gagal','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>'Captcha salah','is_new_ip'=>0]);

        } else {
            $u = trim($_POST['email']??''); $p = $_POST['password']??'';
            if (!$u || !$p) {
                $login_error = 'Email dan password wajib diisi.';
            } else {
                $st = $pdo->prepare("SELECT * FROM users WHERE email=? AND status='aktif' LIMIT 1");
                $st->execute([$u]); $user = $st->fetch();
                if ($user && password_verify($p, $user['password'])) {
                    $new_ip = isNewIPForUser($pdo,(int)$user['id'],$log_ip);
                    recordLoginLog($pdo,['user_id'=>$user['id'],'username_input'=>$u,'status'=>'berhasil','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>null,'is_new_ip'=>$new_ip?1:0]);
                    resetAttempts(); session_regenerate_id(true);
                    $_SESSION['user_id']=$user['id']; $_SESSION['user_nama']=$user['nama'];
                    $_SESSION['user_role']=$user['role']; $_SESSION['user_divisi']=$user['divisi'];
                    $_SESSION['pokja_id']=$user['pokja_id']??null;
                    $_SESSION['is_akreditasi']=(int)($user['is_akreditasi']??0);
                    $_SESSION['last_activity']=time(); $_SESSION['login_ip']=$log_ip;
                    unset($_SESSION['csrf_token'],$_SESSION['captcha_answer'],$_SESSION['captcha_soal']);
                    if ($new_ip) setFlash('warning','<i class="fa fa-shield-halved"></i> Login dari lokasi baru <strong>('.htmlspecialchars($log_ip).')</strong>.');
                    redirect(APP_URL.'/dashboard.php');
                } else {
                    recordFail();
                    $captcha_soal = makeCaptcha(); $_SESSION['captcha_soal'] = $captcha_soal;
                    $sc = remainingAttempts();
                    recordLoginLog($pdo,['user_id'=>$user['id']??null,'username_input'=>$u,'status'=>'gagal','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>$user?'Password salah':'Email tidak ditemukan','is_new_ip'=>0]);
                    if (isLocked())   { $s=remainingSeconds(); $login_error="Akun dikunci <strong>".LOCKOUT_SECS." detik</strong>."; }
                    elseif ($sc<=2)   { $login_error="Email atau password salah. <strong>Sisa: {$sc}×</strong>"; }
                    else              { $login_error = 'Email atau password salah.'; }
                }
            }
        }
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /* ─── REGISTER ─── */
    if ($act === 'register') {
        $show_reg = true;

        if (!hash_equals($_SESSION['csrf_token_reg']??'', $_POST['csrf_token_reg']??'')) {
            $reg_errors[] = 'Permintaan tidak valid.';

        } elseif ((int)($_POST['reg_captcha']??-999) !== (int)($_SESSION['reg_captcha_answer']??-1)) {
            $reg_errors[] = 'Jawaban captcha salah.';
            $reg_captcha = makeCaptcha('reg_'); $_SESSION['reg_captcha_soal'] = $reg_captcha;

        } else {
            $nama    = trim($_POST['nama']         ?? '');
            $email   = trim($_POST['reg_email']    ?? '');
            $divisi  = $_POST['divisi']             ?? '';
            $no_hp   = trim($_POST['no_hp']        ?? '');
            $nik     = trim($_POST['nik']          ?? '');
            $pass    = $_POST['reg_password']      ?? '';
            $cnf     = $_POST['reg_confirm']       ?? '';

            if (!$nama)                                     $reg_errors[] = 'Nama lengkap wajib diisi.';
            if (!filter_var($email,FILTER_VALIDATE_EMAIL))  $reg_errors[] = 'Format email tidak valid.';
            if (!$nik)                                      $reg_errors[] = 'NIK RS wajib diisi.';
            if (strlen($pass) < 6)                          $reg_errors[] = 'Password minimal 6 karakter.';
            if ($pass !== $cnf)                             $reg_errors[] = 'Konfirmasi password tidak cocok.';

            // Cek NIK duplikat
            if (!$reg_errors && $nik) {
                try {
                    $ck = $pdo->prepare("SELECT u.nama FROM sdm_karyawan s JOIN users u ON u.id=s.user_id WHERE s.nik_rs=? LIMIT 1");
                    $ck->execute([$nik]); $who = $ck->fetchColumn();
                    if ($who) $reg_errors[] = 'NIK RS sudah digunakan oleh <strong>'.htmlspecialchars($who).'</strong>.';
                } catch (Exception $e) {}
            }

            if (empty($reg_errors)) {
                $dup = $pdo->prepare("SELECT id FROM users WHERE email=?"); $dup->execute([$email]);
                if ($dup->fetch()) {
                    $reg_errors[] = 'Email sudah digunakan. Silakan login atau gunakan email lain.';
                } else {
                    $uname = generateUsername($pdo, $nama);
                    $pdo->prepare("INSERT INTO users (nama,username,email,password,divisi,no_hp) VALUES (?,?,?,?,?,?)")
                        ->execute([$nama,$uname,$email,password_hash($pass,PASSWORD_BCRYPT),$divisi?:null,$no_hp?:null]);
                    $new_id = (int)$pdo->lastInsertId();
                    try {
                        $pdo->prepare("
                            INSERT INTO sdm_karyawan (user_id,nik_rs,no_hp,divisi,jabatan_id,updated_by)
                            VALUES (?,?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE
                                nik_rs=COALESCE(VALUES(nik_rs),nik_rs),
                                no_hp=COALESCE(VALUES(no_hp),no_hp),
                                divisi=COALESCE(VALUES(divisi),divisi)
                        ")->execute([$new_id,$nik?:null,$no_hp?:null,$divisi?:null,null,$new_id]);
                    } catch (Exception $e) {}

                    $reg_success = true;
                    $show_reg    = false;
                    $login_msg   = 'Akun berhasil dibuat! Silakan login dengan email dan password Anda.';
                    unset($_SESSION['reg_captcha_answer'],$_SESSION['reg_captcha_soal']);
                    $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));
                }
            }
            if (!empty($reg_errors)) {
                $reg_captcha = makeCaptcha('reg_'); $_SESSION['reg_captcha_soal'] = $reg_captcha;
                $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));
            }
        }
    }
}

$attempts_data = getAttempts(); $is_locked = isLocked(); $sisa_coba = remainingAttempts();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Login — <?= APP_NAME ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ══ RESET — NO SCROLL ══ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;overflow:hidden;font-family:'Outfit',sans-serif;background:#0a0e13;color:#e8f0f8;}

:root{
  --ink:#0a0e13;--ink2:#111820;--ink3:#161f2b;
  --edge:#1c2a38;--edge2:#243446;
  --lime:#c8f135;--lime2:#b0d92a;
  --lime-dim:rgba(200,241,53,0.08);--lime-glow:rgba(200,241,53,0.18);
  --rose:#fb7185;--amber:#fbbf24;--sky:#38bdf8;--success:#10b981;
  --text:#e8f0f8;--text2:#7a99b4;--text3:#3d5572;
  --mono:'DM Mono',monospace;--sans:'Outfit',sans-serif;
}

/* ══ BACKGROUND ══ */
.bg{position:fixed;inset:0;z-index:0;pointer-events:none;}
.bg-grid{position:absolute;inset:0;
  background-image:linear-gradient(rgba(200,241,53,0.022) 1px,transparent 1px),linear-gradient(90deg,rgba(200,241,53,0.022) 1px,transparent 1px);
  background-size:56px 56px;
  mask-image:radial-gradient(ellipse 90% 90% at 40% 50%,#000 30%,transparent 100%);}
.bg-scan{position:absolute;inset:0;background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,0.06) 2px,rgba(0,0,0,0.06) 4px);}
.orb{position:absolute;border-radius:50%;filter:blur(100px);animation:orbf 18s ease-in-out infinite;}
.o1{width:600px;height:600px;background:radial-gradient(circle,rgba(200,241,53,0.055),transparent 70%);top:-180px;left:-150px;}
.o2{width:450px;height:450px;background:radial-gradient(circle,rgba(56,189,248,0.04),transparent 70%);bottom:-80px;right:-80px;animation-delay:-9s;}
.o3{width:250px;height:250px;background:radial-gradient(circle,rgba(251,113,133,0.035),transparent 70%);top:35%;right:30%;animation-delay:-5s;}
@keyframes orbf{0%,100%{transform:translate(0,0);}40%{transform:translate(25px,-35px);}70%{transform:translate(-18px,20px);}}
.geo{position:absolute;opacity:0.035;animation:spin linear infinite;}
.g1{width:160px;height:160px;border:1.5px solid var(--lime);top:12%;right:5%;animation-duration:45s;}
.g2{width:80px;height:80px;border:1px solid var(--sky);bottom:18%;left:4%;animation-duration:60s;animation-direction:reverse;}
@keyframes spin{from{transform:rotate(0deg);}to{transform:rotate(360deg);}}
.ticker{position:absolute;bottom:0;left:0;right:0;height:32px;background:rgba(200,241,53,0.03);border-top:1px solid rgba(200,241,53,0.08);overflow:hidden;display:flex;align-items:center;}
.ticker-inner{display:flex;align-items:center;animation:tick 70s linear infinite;white-space:nowrap;}
.ti{font-family:var(--mono);font-size:9.5px;color:rgba(200,241,53,0.38);padding:0 20px;letter-spacing:.4px;}
.ts{color:rgba(200,241,53,0.18);font-size:7px;}
@keyframes tick{from{transform:translateX(0);}to{transform:translateX(-50%);}}

/* ══ SHELL — fixed, no scroll ══ */
.shell{position:fixed;inset:0;z-index:1;display:grid;grid-template-rows:48px 1fr 32px;grid-template-columns:1fr 420px;}

/* ── TOPBAR ── */
.topbar{grid-column:1/-1;grid-row:1;display:flex;align-items:center;justify-content:space-between;padding:0 28px;background:rgba(10,14,19,0.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--edge);}
.brand{display:flex;align-items:center;gap:10px;}
.brand-mark{width:32px;height:32px;background:var(--lime);border-radius:6px;display:flex;align-items:center;justify-content:center;font-family:var(--mono);font-size:12px;font-weight:500;color:var(--ink);flex-shrink:0;box-shadow:0 0 0 3px rgba(200,241,53,0.12);}
.brand-name{font-family:var(--mono);font-size:14px;font-weight:500;color:var(--text);letter-spacing:.3px;}
.brand-name span{color:var(--lime);}
.brand-sep{width:1px;height:20px;background:var(--edge);margin:0 12px;}
.brand-sub{font-size:11px;color:var(--text3);letter-spacing:.3px;}
.topbar-r{display:flex;align-items:center;gap:14px;}
.sys-pill{display:flex;align-items:center;gap:5px;font-family:var(--mono);font-size:9.5px;color:var(--lime);background:var(--lime-dim);border:1px solid rgba(200,241,53,0.12);padding:3px 10px;border-radius:3px;letter-spacing:.4px;}
.sdot{width:5px;height:5px;border-radius:50%;background:var(--lime);animation:blink 2.5s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:.25;}}
.tclock{font-family:var(--mono);font-size:11px;color:var(--text3);}

/* ── LEFT PANEL ── */
.left{grid-column:1;grid-row:2;position:relative;z-index:1;display:flex;flex-direction:column;justify-content:center;padding:0 44px 0 40px;border-right:1px solid var(--edge);overflow:hidden;}
.hl-eye{font-family:var(--mono);font-size:9.5px;color:var(--lime);letter-spacing:2px;text-transform:uppercase;display:flex;align-items:center;gap:7px;margin-bottom:12px;}
.hl-eye::before{content:'//';opacity:.5;}
.hl-h1{font-size:clamp(28px,3.2vw,46px);font-weight:900;line-height:1.0;letter-spacing:-2px;margin-bottom:10px;}
.hl-h1 .stroke{color:transparent;-webkit-text-stroke:1.5px var(--lime);}
.hl-p{font-size:clamp(11px,1.1vw,13.5px);color:var(--text2);line-height:1.7;max-width:400px;margin-bottom:20px;font-weight:300;}
.mod-lbl{font-family:var(--mono);font-size:9px;color:var(--text3);letter-spacing:1.5px;text-transform:uppercase;display:flex;align-items:center;gap:8px;margin-bottom:10px;}
.mod-lbl::after{content:'';flex:1;height:1px;background:var(--edge);}
.mod-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-bottom:16px;}
.mc{background:var(--ink2);border:1px solid var(--edge);border-radius:7px;padding:10px 11px;transition:border-color .2s,transform .2s;position:relative;overflow:hidden;}
.mc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:transparent;transition:background .2s;}
.mc:hover{border-color:rgba(200,241,53,0.25);transform:translateY(-2px);}
.mc:hover::before,.mc.ft::before{background:var(--lime);}
.mc.ft{border-color:rgba(200,241,53,0.15);}
.mc-ico{font-size:clamp(14px,1.4vw,18px);margin-bottom:5px;display:block;}
.mc-n{font-size:clamp(10px,0.95vw,12px);font-weight:700;color:var(--text);}
.mc-s{font-size:clamp(9px,0.8vw,10.5px);color:var(--text3);margin-top:2px;line-height:1.4;}
.stat-bar{display:grid;grid-template-columns:repeat(4,1fr);border:1px solid var(--edge);border-radius:7px;overflow:hidden;}
.sb{padding:8px 6px;text-align:center;border-right:1px solid var(--edge);}
.sb:last-child{border-right:none;}
.sb-n{font-family:var(--mono);font-size:clamp(14px,1.5vw,20px);font-weight:500;color:var(--lime);line-height:1;}
.sb-l{font-size:clamp(8px,0.75vw,10px);color:var(--text3);margin-top:2px;}

/* ── RIGHT PANEL — LOGIN ── */
.right{grid-column:2;grid-row:2;position:relative;z-index:1;background:var(--ink2);border-left:1px solid var(--edge);display:flex;flex-direction:column;justify-content:center;padding:20px 32px;overflow:hidden;}
.cr{position:absolute;width:16px;height:16px;opacity:.35;}
.cr-tl{top:14px;left:14px;border-top:1.5px solid var(--lime);border-left:1.5px solid var(--lime);}
.cr-tr{top:14px;right:14px;border-top:1.5px solid var(--lime);border-right:1.5px solid var(--lime);}
.cr-bl{bottom:14px;left:14px;border-bottom:1.5px solid var(--lime);border-left:1.5px solid var(--lime);}
.cr-br{bottom:14px;right:14px;border-bottom:1.5px solid var(--lime);border-right:1.5px solid var(--lime);}
.fh{margin-bottom:16px;}
.fh-tag{font-family:var(--mono);font-size:9px;color:var(--lime);letter-spacing:2px;text-transform:uppercase;display:inline-flex;align-items:center;gap:5px;background:var(--lime-dim);border:1px solid rgba(200,241,53,0.14);padding:3px 9px;border-radius:3px;margin-bottom:10px;}
.fh-t{font-size:clamp(18px,1.8vw,24px);font-weight:800;letter-spacing:-1px;margin-bottom:3px;}
.fh-s{font-size:clamp(11px,1vw,12.5px);color:var(--text2);}

/* Alerts */
.alert{display:flex;align-items:flex-start;gap:8px;padding:9px 12px;border-radius:5px;font-size:clamp(11px,0.95vw,12px);margin-bottom:12px;animation:aIn .25s ease;}
@keyframes aIn{from{opacity:0;transform:translateX(-4px);}to{opacity:1;transform:none;}}
.alert i{margin-top:1px;flex-shrink:0;font-size:11px;}
.a-danger{background:rgba(251,113,133,0.08);border:1px solid rgba(251,113,133,0.2);color:#fda4af;}
.a-success{background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);color:#6ee7b7;}

/* Attempts bar */
.att-h{display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-bottom:5px;}
.att-h strong{color:var(--text);}
.att-t{display:flex;gap:3px;margin-bottom:12px;}
.att-s{flex:1;height:2px;border-radius:1px;background:var(--edge2);transition:background .3s;}
.att-s.w{background:var(--amber);}
.att-s.d{background:var(--rose);}

/* Form fields */
.field{margin-bottom:11px;}
.fl{font-family:var(--mono);font-size:9px;color:var(--text3);letter-spacing:1px;text-transform:uppercase;display:block;margin-bottom:5px;}
.fw{position:relative;}
.fi{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:12px;pointer-events:none;transition:color .2s;z-index:1;}
.fw:focus-within .fi{color:var(--lime);}
.finp{width:100%;height:42px;background:var(--ink3);border:1px solid var(--edge2);border-radius:5px;color:var(--text);font-family:var(--sans);font-size:clamp(12px,1.1vw,13.5px);padding:0 40px 0 38px;outline:none;transition:border-color .2s,box-shadow .2s,background .2s;appearance:none;}
.finp:focus{border-color:var(--lime);box-shadow:0 0 0 2px var(--lime-dim);background:rgba(200,241,53,0.015);}
.finp::placeholder{color:var(--text3);font-size:clamp(11px,1vw,12.5px);}
.eye-btn{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text3);cursor:pointer;padding:4px;font-size:12px;transition:color .2s;}
.eye-btn:hover{color:var(--lime);}

/* Captcha login */
.cap-box{background:var(--ink3);border:1px solid var(--edge2);border-radius:5px;padding:11px 12px;margin-bottom:11px;}
.cap-lbl{font-family:var(--mono);font-size:9px;color:var(--text3);letter-spacing:1px;text-transform:uppercase;display:flex;align-items:center;gap:5px;margin-bottom:9px;}
.cap-lbl i{color:var(--lime);font-size:9px;}
.cap-row{display:flex;align-items:center;gap:9px;}
.cap-expr{font-family:var(--mono);font-size:clamp(16px,1.6vw,20px);font-weight:500;color:var(--lime);background:rgba(200,241,53,0.06);border:1px solid rgba(200,241,53,0.14);padding:6px 14px;border-radius:4px;letter-spacing:2px;white-space:nowrap;flex-shrink:0;}
.cap-eq{font-family:var(--mono);font-size:18px;color:var(--text3);flex-shrink:0;}
.cap-inp{width:60px;height:38px;background:var(--ink);border:1px solid var(--edge2);border-radius:4px;color:var(--lime);font-family:var(--mono);font-size:18px;font-weight:500;text-align:center;outline:none;transition:border-color .2s,box-shadow .2s;-moz-appearance:textfield;}
.cap-inp::-webkit-outer-spin-button,.cap-inp::-webkit-inner-spin-button{-webkit-appearance:none;}
.cap-inp:focus{border-color:var(--lime);box-shadow:0 0 0 2px var(--lime-dim);}
.extras{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;}
.rem{display:flex;align-items:center;gap:6px;font-size:clamp(11px,1vw,12px);color:var(--text2);cursor:pointer;user-select:none;}
.rem input{accent-color:var(--lime);width:12px;height:12px;}
.fgot{font-family:var(--mono);font-size:clamp(10px,0.9vw,11.5px);color:var(--lime);text-decoration:none;}
.fgot:hover{text-decoration:underline;}
.btn-go{width:100%;height:44px;background:var(--lime);border:none;border-radius:5px;color:var(--ink);font-family:var(--mono);font-size:clamp(11px,1vw,12.5px);font-weight:500;letter-spacing:.5px;text-transform:uppercase;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;box-shadow:0 4px 16px rgba(200,241,53,0.15);position:relative;overflow:hidden;}
.btn-go::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,0.1),transparent);opacity:0;transition:opacity .2s;}
.btn-go:hover{background:var(--lime2);transform:translateY(-1px);box-shadow:0 8px 24px rgba(200,241,53,0.22);}
.btn-go:hover::after{opacity:1;}
.btn-go:active{transform:none;}
.btn-go:disabled{opacity:.4;cursor:not-allowed;transform:none;}
.sec-note{display:flex;align-items:center;gap:7px;margin-top:12px;padding:8px 10px;background:rgba(200,241,53,0.025);border:1px solid rgba(200,241,53,0.07);border-radius:4px;}
.sec-note i{font-size:10px;color:var(--lime);flex-shrink:0;}
.sec-note span{font-family:var(--mono);font-size:9px;color:var(--text3);letter-spacing:.3px;line-height:1.6;}
.reg-row{text-align:center;margin-top:12px;font-family:var(--mono);font-size:10px;color:var(--text3);}
.reg-btn{background:none;border:none;color:var(--lime);cursor:pointer;font-family:var(--mono);font-size:10px;text-decoration:underline;padding:0;}
.reg-btn:hover{color:var(--lime2);}

/* Lockout */
.lock-state{text-align:center;padding:12px 0;}
.lk-ring{width:60px;height:60px;border:2px solid var(--rose);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--rose);margin:0 auto 12px;animation:lp 2s ease-in-out infinite;}
@keyframes lp{0%,100%{box-shadow:0 0 0 0 rgba(251,113,133,0.3);}50%{box-shadow:0 0 0 10px rgba(251,113,133,0);}}
.lk-t{font-size:clamp(16px,1.6vw,20px);font-weight:800;letter-spacing:-1px;margin-bottom:4px;}
.lk-cnt{font-family:var(--mono);font-size:clamp(36px,4vw,52px);font-weight:500;color:var(--lime);letter-spacing:-3px;line-height:1;margin:4px 0 8px;}
.lk-s{font-size:11px;color:var(--text3);line-height:1.7;}

/* ── FOOTER ── */
.footbar{grid-column:1/-1;grid-row:3;position:relative;z-index:10;display:flex;align-items:center;justify-content:space-between;padding:0 28px;background:rgba(10,14,19,0.94);backdrop-filter:blur(16px);border-top:1px solid var(--edge);}
.fc{font-family:var(--mono);font-size:9.5px;color:var(--text3);}
.fc span{color:var(--lime);}
.fl-links{display:flex;gap:16px;}
.fl-links a{font-family:var(--mono);font-size:9.5px;color:var(--text3);text-decoration:none;transition:color .2s;}
.fl-links a:hover{color:var(--lime);}

/* ══════════════════════════════════════════
   REGISTER MODAL
══════════════════════════════════════════ */
.reg-overlay{
  display:none;position:fixed;inset:0;z-index:9999;
  background:rgba(4,7,11,0.88);
  backdrop-filter:blur(10px);
  align-items:center;justify-content:center;
  padding:16px;
}
.reg-overlay.open{display:flex;animation:ovIn .2s ease;}
@keyframes ovIn{from{opacity:0;}to{opacity:1;}}

.reg-modal{
  background:var(--ink2);
  border:1px solid var(--edge2);
  border-radius:14px;
  width:100%;max-width:620px;
  max-height:92vh;
  display:flex;flex-direction:column;
  box-shadow:0 32px 80px rgba(0,0,0,0.6),0 0 0 1px rgba(200,241,53,0.04);
  animation:modalUp .28s cubic-bezier(.34,1.4,.64,1);
  overflow:hidden;
  position:relative;
}
/* Modal corner accents */
.reg-modal::before,.reg-modal::after{content:'';position:absolute;width:20px;height:20px;opacity:.3;z-index:1;pointer-events:none;}
.reg-modal::before{top:12px;left:12px;border-top:1.5px solid var(--lime);border-left:1.5px solid var(--lime);}
.reg-modal::after{top:12px;right:12px;border-top:1.5px solid var(--lime);border-right:1.5px solid var(--lime);}
@keyframes modalUp{from{opacity:0;transform:translateY(24px) scale(.96);}to{opacity:1;transform:none;}}

/* Modal header */
.rm-head{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;background:linear-gradient(135deg,rgba(200,241,53,0.05),rgba(200,241,53,0.02));border-bottom:1px solid var(--edge);flex-shrink:0;}
.rm-head-l{display:flex;align-items:center;gap:11px;}
.rm-icon{width:36px;height:36px;background:var(--lime);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--ink);font-size:15px;flex-shrink:0;}
.rm-title{font-size:16px;font-weight:800;letter-spacing:-.5px;}
.rm-sub{font-size:11px;color:var(--text3);margin-top:2px;}
.rm-close{width:30px;height:30px;border-radius:6px;border:none;background:rgba(255,255,255,0.06);color:var(--text3);cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0;}
.rm-close:hover{background:var(--rose);color:#fff;}

/* Modal body */
.rm-body{padding:20px 22px;overflow-y:auto;flex:1;scrollbar-width:thin;scrollbar-color:var(--edge2) transparent;}
.rm-body::-webkit-scrollbar{width:4px;}
.rm-body::-webkit-scrollbar-thumb{background:var(--edge2);border-radius:2px;}

/* Modal alerts */
.rm-alert{display:flex;align-items:flex-start;gap:9px;padding:10px 13px;border-radius:6px;font-size:11.5px;margin-bottom:14px;animation:aIn .25s ease;}
.rm-alert i{margin-top:1px;flex-shrink:0;font-size:12px;}
.rm-a-danger{background:rgba(251,113,133,0.08);border:1px solid rgba(251,113,133,0.2);color:#fda4af;}
.rm-alert ul{margin:4px 0 0 14px;}
.rm-alert ul li{margin-bottom:2px;}

/* Section headers */
.rm-sec{font-family:var(--mono);font-size:9px;color:var(--lime);letter-spacing:1.5px;text-transform:uppercase;display:flex;align-items:center;gap:8px;margin-bottom:11px;padding-bottom:8px;border-bottom:1px solid var(--edge);}
.rm-sec:not(:first-child){margin-top:18px;}
.rm-sec i{font-size:10px;}

/* Grid & fields */
.rm-g2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.rm-fg{margin-bottom:11px;}
.rm-fl{font-family:var(--mono);font-size:9px;color:var(--text3);letter-spacing:.8px;text-transform:uppercase;display:block;margin-bottom:5px;}
.rm-fl .req{color:var(--rose);}
.rm-iw{position:relative;}
.rm-ii{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:11px;pointer-events:none;transition:color .2s;z-index:1;}
.rm-iw:focus-within .rm-ii{color:var(--lime);}
.rm-inp{width:100%;height:40px;background:var(--ink3);border:1px solid var(--edge2);border-radius:5px;color:var(--text);font-family:var(--sans);font-size:13px;padding:0 36px 0 32px;outline:none;transition:border-color .2s,box-shadow .2s;appearance:none;}
.rm-inp:focus{border-color:var(--lime);box-shadow:0 0 0 2px var(--lime-dim);}
.rm-inp::placeholder{color:var(--text3);font-size:12px;}
.rm-inp.sel{cursor:pointer;}
.rm-inp option{background:var(--ink2);}
.rm-eye{position:absolute;right:9px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text3);cursor:pointer;padding:3px;font-size:11px;transition:color .2s;}
.rm-eye:hover{color:var(--lime);}
.rm-hint{font-size:10px;color:var(--text3);margin-top:3px;display:flex;align-items:center;gap:4px;line-height:1.5;}
.rm-hint i{color:var(--lime);font-size:9px;flex-shrink:0;}

/* NIK info box */
.nik-box{background:rgba(200,241,53,0.04);border:1px solid rgba(200,241,53,0.1);border-radius:5px;padding:9px 12px;margin:6px 0 11px;font-size:10.5px;color:var(--text3);display:flex;gap:8px;line-height:1.7;}
.nik-box i{color:var(--lime);font-size:10px;flex-shrink:0;margin-top:2px;}
.nik-box table{border-collapse:collapse;width:100%;}
.nik-box td{padding:1px 8px 1px 0;vertical-align:top;}
.nik-box td:first-child{color:var(--lime);font-family:var(--mono);font-size:9.5px;font-weight:500;white-space:nowrap;}

/* Password strength */
.rm-str-bar{height:2px;background:var(--edge2);border-radius:1px;margin-top:4px;overflow:hidden;}
.rm-str-fill{height:100%;width:0;border-radius:1px;transition:width .3s,background .3s;}
.rm-str-lbl{font-size:10px;margin-top:3px;font-weight:600;}
.rm-match{font-size:10px;margin-top:3px;font-weight:600;}

/* Register captcha */
.rm-cap{background:var(--ink3);border:1px solid var(--edge2);border-radius:5px;padding:11px 12px;margin-bottom:12px;}
.rm-cap-lbl{font-family:var(--mono);font-size:9px;color:var(--text3);letter-spacing:.8px;text-transform:uppercase;display:flex;align-items:center;gap:5px;margin-bottom:9px;}
.rm-cap-lbl i{color:var(--lime);}
.rm-cap-row{display:flex;align-items:center;gap:9px;}
.rm-cap-expr{font-family:var(--mono);font-size:19px;font-weight:500;color:var(--lime);background:rgba(200,241,53,0.06);border:1px solid rgba(200,241,53,0.14);padding:6px 14px;border-radius:4px;letter-spacing:2px;white-space:nowrap;flex-shrink:0;}
.rm-cap-eq{font-family:var(--mono);font-size:17px;color:var(--text3);flex-shrink:0;}
.rm-cap-inp{width:58px;height:38px;background:var(--ink);border:1px solid var(--edge2);border-radius:4px;color:var(--lime);font-family:var(--mono);font-size:18px;font-weight:500;text-align:center;outline:none;transition:border-color .2s,box-shadow .2s;-moz-appearance:textfield;}
.rm-cap-inp::-webkit-outer-spin-button,.rm-cap-inp::-webkit-inner-spin-button{-webkit-appearance:none;}
.rm-cap-inp:focus{border-color:var(--lime);box-shadow:0 0 0 2px var(--lime-dim);}

/* Modal footer */
.rm-foot{padding:14px 22px;border-top:1px solid var(--edge);flex-shrink:0;background:rgba(10,14,19,0.5);}
.rm-btn{width:100%;height:44px;background:var(--lime);border:none;border-radius:5px;color:var(--ink);font-family:var(--mono);font-size:12px;font-weight:500;letter-spacing:.5px;text-transform:uppercase;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;box-shadow:0 4px 16px rgba(200,241,53,0.15);}
.rm-btn:hover{background:var(--lime2);transform:translateY(-1px);}
.rm-btn:active{transform:none;}
.rm-btn:disabled{opacity:.4;cursor:not-allowed;transform:none;}
.rm-back{text-align:center;margin-top:10px;font-family:var(--mono);font-size:10px;color:var(--text3);}
.rm-back-btn{background:none;border:none;color:var(--lime);cursor:pointer;font-family:var(--mono);font-size:10px;text-decoration:underline;padding:0;}

/* Register success */
.rm-success{text-align:center;padding:32px 20px;}
.rm-suc-ring{width:70px;height:70px;border-radius:50%;background:rgba(16,185,129,0.1);border:2px solid rgba(16,185,129,0.3);display:flex;align-items:center;justify-content:center;font-size:28px;color:var(--success);margin:0 auto 16px;animation:pop .4s cubic-bezier(.34,1.56,.64,1);}
@keyframes pop{from{transform:scale(0);opacity:0;}to{transform:scale(1);opacity:1;}}
.rm-suc-t{font-size:20px;font-weight:800;letter-spacing:-.5px;margin-bottom:8px;}
.rm-suc-s{font-size:12px;color:var(--text3);line-height:1.8;margin-bottom:20px;}
.rm-suc-btn{display:inline-flex;align-items:center;gap:7px;background:var(--lime);color:var(--ink);font-family:var(--mono);font-size:11.5px;font-weight:500;padding:11px 24px;border-radius:5px;border:none;cursor:pointer;letter-spacing:.5px;transition:all .2s;}
.rm-suc-btn:hover{background:var(--lime2);}

/* ── RESPONSIVE ── */
@media(max-width:960px){
  .shell{grid-template-columns:1fr;grid-template-rows:48px 1fr 32px;}
  .left{display:none;}
  .right{grid-column:1;border-left:none;padding:20px 24px;}
  .fl-links{display:none;}
}
@media(max-width:620px){.rm-g2{grid-template-columns:1fr;}}
@media(max-width:480px){.right{padding:16px 18px;}.topbar{padding:0 18px;}.footbar{padding:0 18px;}.tclock{display:none;}}
@media(max-height:650px){
  .fh{margin-bottom:10px;}.field{margin-bottom:7px;}.finp{height:36px;}
  .cap-box{padding:8px 10px;margin-bottom:7px;}.extras{margin-bottom:10px;}
  .btn-go{height:38px;}.sec-note{margin-top:8px;padding:6px 10px;}
  .reg-row{margin-top:7px;}.hl-p{margin-bottom:14px;}
  .mod-grid{gap:5px;margin-bottom:10px;}.mc{padding:7px 9px;}.sb{padding:6px 4px;}
}
</style>
</head>
<body>

<!-- BACKGROUND -->
<div class="bg">
  <div class="bg-grid"></div><div class="bg-scan"></div>
  <div class="orb o1"></div><div class="orb o2"></div><div class="orb o3"></div>
  <div class="geo g1"></div><div class="geo g2"></div>
  <div class="ticker">
    <div class="ticker-inner">
      <?php
      $mItems=['Dashboard','Tiket IT','Antrian Tiket','Semua Tiket','Laporan SLA','Buat Tiket IT','Tiket Saya','Tiket IPSRS','Antrian IPSRS','Semua Tiket IPSRS','Laporan SLA IPSRS','Buat Tiket IPSRS','Berita Acara IT','Aset IT','Lacak Aset','Mutasi Aset','Maintenance IT','Monitoring Koneksi','Monitoring Server','Master Karyawan','Berkas Karyawan','Master Berkas','Jabatan','Shift Kerja','Jadwal','Laporan Kehadiran','Lokasi Absen','Kategori IT','Kategori IPSRS','Bagian / Divisi','Pengguna','Log Login','Notif Telegram','Backup Database','Absen Sekarang','Rekap Absensi','Ajukan Cuti','Approval Cuti','Master Cuti','Laporan Cuti','Profil Saya','Data Kepegawaian','Berkas Saya','Ganti Password'];
      $all = array_merge($mItems,$mItems);
      foreach($all as $m){echo '<span class="ti">'.htmlspecialchars($m).'</span><span class="ts">◆</span>';}
      ?>
    </div>
  </div>
</div>

<!-- SHELL -->
<div class="shell">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="brand">
      <div class="brand-mark">FS</div>
      <div class="brand-name">Fix<span>Smart</span></div>
      <div class="brand-sep"></div>
      <div class="brand-sub">Management Work System</div>
    </div>
    <div class="topbar-r">
      <div class="sys-pill"><div class="sdot"></div>System Online</div>
      <div class="tclock" id="clock">--:--:--</div>
    </div>
  </header>

  <!-- LEFT -->
  <div class="left">
    <div class="hl-eye">v1.0.0 — Platform Terpadu</div>
    <h1 class="hl-h1">Kendali Penuh<br>di <span class="stroke">Satu Sistem.</span></h1>
    <p class="hl-p">Platform manajemen terpadu untuk IT, IPSRS, SDM, dokumen akreditasi, absensi &amp; cuti dalam satu dashboard.</p>
    <div class="mod-lbl">Modul Tersedia</div>
    <div class="mod-grid">
      <div class="mc ft"><span class="mc-ico" style="color:#38bdf8;">🖥</span><div class="mc-n">Tiket IT</div><div class="mc-s">Order, SLA, tracking</div></div>
      <div class="mc"><span class="mc-ico" style="color:#fb923c;">🔧</span><div class="mc-n">Tiket IPSRS</div><div class="mc-s">Antrian, maintenance</div></div>
      <div class="mc"><span class="mc-ico" style="color:#c8f135;">💾</span><div class="mc-n">Aset IT</div><div class="mc-s">Lacak, mutasi, monitor</div></div>
      <div class="mc"><span class="mc-ico" style="color:#a78bfa;">👥</span><div class="mc-n">Management</div><div class="mc-s">SDM, berkas, jadwal</div></div>
      <div class="mc"><span class="mc-ico" style="color:#fb7185;">📅</span><div class="mc-n">Cuti</div><div class="mc-s">Approval 2 level</div></div>
      <div class="mc"><span class="mc-ico" style="color:#fbbf24;">🏅</span><div class="mc-n">Akreditasi</div><div class="mc-s">Pokja, dokumen</div></div>
    </div>
    <div class="stat-bar">
      <div class="sb"><div class="sb-n">44+</div><div class="sb-l">Menu</div></div>
      <div class="sb"><div class="sb-n">6</div><div class="sb-l">Role</div></div>
      <div class="sb"><div class="sb-n">100%</div><div class="sb-l">Responsif</div></div>
      <div class="sb"><div class="sb-n">Free</div><div class="sb-l">Open Source</div></div>
    </div>
  </div>

  <!-- RIGHT — LOGIN -->
  <div class="right">
    <div class="cr cr-tl"></div><div class="cr cr-tr"></div>
    <div class="cr cr-bl"></div><div class="cr cr-br"></div>

    <div class="fh">
      <div class="fh-tag"><i class="fa fa-terminal"></i> AUTH_REQUIRED</div>
      <div class="fh-t">Masuk Akun</div>
      <div class="fh-s">Gunakan email &amp; password yang terdaftar</div>
    </div>

    <?php if($login_error): ?>
    <div class="alert a-danger"><i class="fa fa-circle-exclamation"></i><span><?= $login_error ?></span></div>
    <?php endif; ?>
    <?php if($login_msg): ?>
    <div class="alert a-success"><i class="fa fa-circle-check"></i><span><?= htmlspecialchars($login_msg) ?></span></div>
    <?php endif; ?>

    <?php if($is_locked): ?>
    <div class="lock-state">
      <div class="lk-ring"><i class="fa fa-lock"></i></div>
      <div class="lk-t">Akun Dikunci Sementara</div>
      <div class="lk-cnt" id="countdown"><?= gmdate('i:s', remainingSeconds()) ?></div>
      <div class="lk-s">Terlalu banyak percobaan gagal.<br>Tunggu hingga hitungan selesai.</div>
    </div>
    <?php else: ?>

    <?php if($attempts_data['count'] > 0): ?>
    <div class="att-h">
      <span>Gagal: <strong><?= $attempts_data['count'] ?>/<?= MAX_ATTEMPTS ?></strong></span>
      <span>Sisa: <strong style="color:<?= $sisa_coba<=2?'var(--rose)':'var(--lime)' ?>;"><?= $sisa_coba ?>×</strong></span>
    </div>
    <div class="att-t">
      <?php for($i=0;$i<MAX_ATTEMPTS;$i++): $cls=$i<$attempts_data['count']?($sisa_coba<=2?'d':'w'):''; ?>
      <div class="att-s <?= $cls ?>"></div>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="lform" autocomplete="off">
      <input type="hidden" name="_act" value="login">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <div class="field">
        <label class="fl">Email</label>
        <div class="fw">
          <i class="fa fa-at fi"></i>
          <input type="email" name="email" class="finp" placeholder="nama@rumahsakit.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autofocus autocomplete="email">
        </div>
      </div>

      <div class="field">
        <label class="fl">Password</label>
        <div class="fw">
          <i class="fa fa-key fi"></i>
          <input type="password" name="password" id="lpwd" class="finp" placeholder="••••••••••" autocomplete="current-password">
          <button type="button" class="eye-btn" onclick="tgl('lpwd','lec')" tabindex="-1"><i class="fa fa-eye" id="lec"></i></button>
        </div>
      </div>

      <div class="cap-box">
        <div class="cap-lbl"><i class="fa fa-shield-halved"></i> Verifikasi Anti-Bot</div>
        <div class="cap-row">
          <div class="cap-expr"><?= htmlspecialchars($captcha_soal) ?></div>
          <div class="cap-eq">=</div>
          <input type="number" name="captcha" class="cap-inp" placeholder="?" required autocomplete="off" min="0" max="99">
        </div>
      </div>

      <div class="extras">
        <label class="rem"><input type="checkbox" name="remember"> Ingat saya</label>
        <a href="#" class="fgot">Lupa password?</a>
      </div>

      <button type="submit" class="btn-go" id="btn-go">
        <i class="fa fa-arrow-right-to-bracket"></i> MASUK KE SISTEM
      </button>
    </form>
    <?php endif; ?>

    <div class="sec-note">
      <i class="fa fa-shield-halved"></i>
      <span>SESI TERENKRIPSI &bull; AKSES BERBASIS PERAN &bull; LOG AKTIVITAS AKTIF</span>
    </div>
    <div class="reg-row">
      Belum punya akun?
      <button type="button" class="reg-btn" onclick="openReg()">Daftar di sini →</button>
    </div>
  </div>

  <!-- FOOTER -->
  <footer class="footbar">
    <div class="fc">© <?= date('Y') ?> <span>FixSmart</span>  Open Source &amp; Free Forever -  Donasi BSI 7134197557 M. WIRA SATRIA BUANA</div>
    <div class="fl-links">
      <a href="#">Dokumentasi</a>
      <a href="#">Kebijakan Privasi</a>
      <a href="#">Hubungi Admin</a>
    </div>
  </footer>

</div><!-- /shell -->


<!-- ════════════════════════════════════════════
     MODAL REGISTER
════════════════════════════════════════════ -->
<div id="reg-overlay" class="reg-overlay <?= $show_reg ? 'open' : '' ?>" onclick="if(event.target===this)closeReg()">
  <div class="reg-modal">

    <!-- Header -->
    <div class="rm-head">
      <div class="rm-head-l">
        <div class="rm-icon"><i class="fa fa-user-plus"></i></div>
        <div>
          <div class="rm-title">Buat Akun Baru</div>
          <div class="rm-sub">Isi data untuk mendaftar ke sistem FixSmart</div>
        </div>
      </div>
      <button type="button" class="rm-close" onclick="closeReg()"><i class="fa fa-times"></i></button>
    </div>

    <!-- Body -->
    <div class="rm-body">

      <?php if($reg_success): ?>
      <!-- ── SUCCESS STATE ── -->
      <div class="rm-success">
        <div class="rm-suc-ring"><i class="fa fa-check"></i></div>
        <div class="rm-suc-t">Akun Berhasil Dibuat!</div>
        <div class="rm-suc-s">
          Selamat, akun Anda sudah terdaftar di sistem.<br>
          Silakan login menggunakan <strong style="color:var(--lime);">email</strong> dan password Anda.
        </div>
        <button type="button" class="rm-suc-btn" onclick="closeReg()">
          <i class="fa fa-sign-in-alt"></i> Tutup &amp; Login Sekarang
        </button>
      </div>

      <?php else: ?>

      <?php if(!empty($reg_errors)): ?>
      <div class="rm-alert rm-a-danger">
        <i class="fa fa-circle-exclamation"></i>
        <div>
          <strong>Harap perbaiki kesalahan berikut:</strong>
          <ul>
            <?php foreach($reg_errors as $re): ?><li><?= $re ?></li><?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" id="rform" autocomplete="off">
        <input type="hidden" name="_act" value="register">
        <input type="hidden" name="csrf_token_reg" value="<?= htmlspecialchars($_SESSION['csrf_token_reg']) ?>">

        <!-- DATA DIRI -->
        <div class="rm-sec"><i class="fa fa-user"></i> Data Diri</div>

        <div class="rm-g2">
          <div class="rm-fg">
            <label class="rm-fl">Nama Lengkap <span class="req">*</span></label>
            <div class="rm-iw">
              <i class="fa fa-id-card rm-ii"></i>
              <input type="text" name="nama" class="rm-inp" placeholder="Nama lengkap..."
                     value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
            </div>
          </div>
          <div class="rm-fg">
            <label class="rm-fl">NIK RS <span class="req">*</span></label>
            <div class="rm-iw">
              <i class="fa fa-id-badge rm-ii"></i>
              <input type="text" name="nik" class="rm-inp" placeholder="Nomor induk karyawan RS..."
                     value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>" maxlength="30">
            </div>
            <div class="rm-hint"><i class="fa fa-circle-info"></i> Bukan NIK KTP 16 digit</div>
          </div>
        </div>

        <!-- NIK info table -->
        <div class="nik-box">
          <i class="fa fa-circle-info"></i>
          <div>
            <table>
              <tr><td>NIK RS</td><td>Nomor induk internal dari rumah sakit → isi di sini</td></tr>
              <tr><td>NIK KTP</td><td>16 digit nomor identitas → dilengkapi di Data Kepegawaian</td></tr>
            </table>
          </div>
        </div>

        <div class="rm-fg">
          <label class="rm-fl">Email <span class="req">*</span></label>
          <div class="rm-iw">
            <i class="fa fa-envelope rm-ii"></i>
            <input type="email" name="reg_email" class="rm-inp" placeholder="email@domain.com"
                   value="<?= htmlspecialchars($_POST['reg_email'] ?? '') ?>">
          </div>
          <div class="rm-hint"><i class="fa fa-circle-info"></i> Email digunakan untuk login ke sistem</div>
        </div>

        <div class="rm-g2">
          <div class="rm-fg">
            <label class="rm-fl">Divisi / Unit</label>
            <div class="rm-iw">
              <i class="fa fa-building rm-ii"></i>
              <select name="divisi" class="rm-inp sel">
                <option value="">-- Pilih Divisi --</option>
                <?php foreach($divisi_list as $d): ?>
                <option value="<?= htmlspecialchars($d['nama']) ?>"
                        <?= (($_POST['divisi']??'') === $d['nama']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($d['nama']) ?><?= $d['kode'] ? ' ('.$d['kode'].')' : '' ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="rm-fg">
            <label class="rm-fl">No. HP / WhatsApp</label>
            <div class="rm-iw">
              <i class="fa fa-phone rm-ii"></i>
              <input type="text" name="no_hp" class="rm-inp" placeholder="08xxxxxxxxxx"
                     value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>">
            </div>
          </div>
        </div>

        <!-- KEAMANAN -->
        <div class="rm-sec"><i class="fa fa-lock"></i> Keamanan Akun</div>

        <div class="rm-g2">
          <div class="rm-fg">
            <label class="rm-fl">Password <span class="req">*</span></label>
            <div class="rm-iw">
              <i class="fa fa-lock rm-ii"></i>
              <input type="password" name="reg_password" id="rpw1" class="rm-inp"
                     placeholder="Min. 6 karakter..."
                     oninput="rmStr(this.value)" autocomplete="new-password">
              <button type="button" class="rm-eye" onclick="tgl('rpw1','re1')" tabindex="-1"><i class="fa fa-eye" id="re1"></i></button>
            </div>
            <div class="rm-str-bar"><div class="rm-str-fill" id="rm-sfill"></div></div>
            <div class="rm-str-lbl" id="rm-slbl"></div>
          </div>
          <div class="rm-fg">
            <label class="rm-fl">Konfirmasi <span class="req">*</span></label>
            <div class="rm-iw">
              <i class="fa fa-lock rm-ii"></i>
              <input type="password" name="reg_confirm" id="rpw2" class="rm-inp"
                     placeholder="Ulangi password..."
                     oninput="rmMatch()" autocomplete="new-password">
              <button type="button" class="rm-eye" onclick="tgl('rpw2','re2')" tabindex="-1"><i class="fa fa-eye" id="re2"></i></button>
            </div>
            <div class="rm-match" id="rm-match"></div>
          </div>
        </div>

        <!-- Captcha -->
        <div class="rm-cap">
          <div class="rm-cap-lbl"><i class="fa fa-robot"></i> Verifikasi Anti-Bot — berapa hasilnya?</div>
          <div class="rm-cap-row">
            <div class="rm-cap-expr"><?= htmlspecialchars($reg_captcha) ?></div>
            <div class="rm-cap-eq">=</div>
            <input type="number" name="reg_captcha" class="rm-cap-inp" placeholder="?" required autocomplete="off" min="0" max="99">
          </div>
        </div>

      </form>
      <?php endif; ?>

    </div><!-- /rm-body -->

    <!-- Footer (sembunyi saat success) -->
    <?php if(!$reg_success): ?>
    <div class="rm-foot">
      <button type="submit" form="rform" class="rm-btn" id="rm-btn">
        <i class="fa fa-user-plus"></i> BUAT AKUN SEKARANG
      </button>
      <div class="rm-back">
        Sudah punya akun?
        <button type="button" class="rm-back-btn" onclick="closeReg()">Login di sini</button>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /reg-modal -->
</div><!-- /reg-overlay -->


<script>
/* ── Toggle password visibility ── */
function tgl(id, ic) {
  var e=document.getElementById(id), i=document.getElementById(ic);
  e.type = e.type==='password' ? 'text' : 'password';
  i.className = e.type==='text' ? 'fa fa-eye-slash' : 'fa fa-eye';
}

/* ── Register modal open/close ── */
function openReg() {
  var ov = document.getElementById('reg-overlay');
  ov.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeReg() {
  var ov = document.getElementById('reg-overlay');
  ov.classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeReg(); });

/* ── Login form loading state ── */
document.getElementById('lform')?.addEventListener('submit', function(){
  var b = document.getElementById('btn-go');
  if(b){ b.disabled=true; b.innerHTML='<i class="fa fa-spinner fa-spin"></i> MEMPROSES...'; }
});

/* ── Register form loading state ── */
document.getElementById('rform')?.addEventListener('submit', function(){
  var b = document.getElementById('rm-btn');
  if(b){ b.disabled=true; b.innerHTML='<i class="fa fa-spinner fa-spin"></i> MENDAFTAR...'; }
});

/* ── Live clock ── */
(function tick(){
  var el=document.getElementById('clock'); if(!el)return;
  var n=new Date();
  el.textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');
  setTimeout(tick,1000);
})();

/* ── Lockout countdown ── */
<?php if($is_locked): ?>
var _s=<?= remainingSeconds() ?>, _el=document.getElementById('countdown');
(function cd(){
  _s--;
  if(_s<=0){location.reload();return;}
  var m=Math.floor(_s/60),s=_s%60;
  _el.textContent=(m<10?'0':'')+m+':'+(s<10?'0':'')+s;
  setTimeout(cd,1000);
})();
<?php endif; ?>

/* ── Password strength ── */
function rmStr(v){
  var s=0;
  if(v.length>=6)s++;if(v.length>=10)s++;
  if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^a-zA-Z0-9]/.test(v))s++;
  var lvl=[
    {w:'20%',c:'#fb7185',t:'Sangat Lemah'},{w:'40%',c:'#f97316',t:'Lemah'},
    {w:'60%',c:'#fbbf24',t:'Cukup'},{w:'80%',c:'#00c896',t:'Kuat'},{w:'100%',c:'#c8f135',t:'Sangat Kuat'}
  ];
  var f=document.getElementById('rm-sfill'),l=document.getElementById('rm-slbl');
  if(!v.length){f.style.width='0';l.textContent='';return;}
  var idx=Math.max(0,Math.min(s-1,4));
  f.style.width=lvl[idx].w;f.style.background=lvl[idx].c;
  l.textContent=lvl[idx].t;l.style.color=lvl[idx].c;
}
function rmMatch(){
  var p1=document.getElementById('rpw1').value,
      p2=document.getElementById('rpw2').value,
      h=document.getElementById('rm-match');
  if(!p2){h.textContent='';return;}
  if(p1===p2){h.textContent='✅ Password cocok';h.style.color='var(--lime)';}
  else{h.textContent='❌ Tidak cocok';h.style.color='var(--rose)';}
}

/* ── Stagger module cards ── */
document.querySelectorAll('.mc').forEach(function(el,i){
  el.style.cssText+='opacity:0;transform:translateY(12px);transition:opacity .35s ease '+((80+i*50)+'ms')+',transform .35s ease '+((80+i*50)+'ms')+';';
  requestAnimationFrame(function(){
    setTimeout(function(){el.style.opacity='1';el.style.transform='translateY(0)';},80+i*50);
  });
});

/* ── Auto-open modal if register error or re-open ── */
<?php if($show_reg): ?>
document.addEventListener('DOMContentLoaded', function(){ openReg(); });
<?php endif; ?>
</script>
</body>
</html>