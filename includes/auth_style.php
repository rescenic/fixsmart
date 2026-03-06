<style>
*{margin:0;padding:0;box-sizing:border-box;}
html,body{height:100%;overflow:hidden;}
body{
  font-family:'Source Sans Pro',sans-serif;font-size:13px;
  background:#2a3f54;
  display:flex;align-items:center;justify-content:center;
}
body::before{
  content:'';position:fixed;inset:0;
  background:repeating-linear-gradient(45deg,rgba(255,255,255,.015) 0,rgba(255,255,255,.015) 1px,transparent 1px,transparent 28px);
  pointer-events:none;
}

/* ─── Wrapper: ukuran SAMA di login & register ─── */
.wrap{
  position:relative;z-index:1;
  display:flex;
  width:min(920px,97vw);
  height:min(680px,97vh);
  border-radius:6px;overflow:hidden;
  box-shadow:0 20px 60px rgba(0,0,0,.45);
}

/* ─── LEFT (identik di keduanya) ─── */
.left{
  flex:1;min-width:0;
  background:linear-gradient(150deg,#1a2e3f,#2a3f54);
  padding:20px 22px;
  display:flex;flex-direction:column;
  overflow:hidden;
}
.brand{display:flex;align-items:center;gap:11px;margin-bottom:12px;}
.brand-icon{width:40px;height:40px;background:#26B99A;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0;}
.brand-icon i{color:#fff;}
.brand-name{font-size:15px;font-weight:700;color:#fff;line-height:1.2;}
.brand-sub{font-size:9.5px;color:rgba(255,255,255,.45);margin-top:2px;}
.left h2{font-size:13px;font-weight:700;color:#fff;margin-bottom:5px;}
.left>p{color:rgba(255,255,255,.55);font-size:11px;line-height:1.65;margin-bottom:11px;}
.modul-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:11px;}
.modul-card{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:7px 8px;display:flex;align-items:flex-start;gap:7px;}
.modul-ic{width:25px;height:25px;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
.modul-ic.c-it{background:rgba(59,130,246,.35);color:#93c5fd;}
.modul-ic.c-ipsrs{background:rgba(16,185,129,.35);color:#6ee7b7;}
.modul-ic.c-maint{background:rgba(245,158,11,.35);color:#fcd34d;}
.modul-ic.c-aset{background:rgba(168,85,247,.35);color:#d8b4fe;}
.modul-title{font-size:10px;font-weight:700;color:#fff;margin-bottom:1px;}
.modul-desc{font-size:9px;color:rgba(255,255,255,.5);line-height:1.4;}
.feat{list-style:none;margin-bottom:11px;}
.feat li{display:flex;align-items:center;gap:8px;font-size:10.5px;color:rgba(255,255,255,.7);margin-bottom:4px;}
.feat li i{color:#26B99A;width:13px;flex-shrink:0;}
.wa-box{margin-top:auto;}
.wa-link{display:inline-flex;align-items:center;gap:7px;padding:7px 11px;background:#25D366;color:#fff;border-radius:5px;font-weight:600;text-decoration:none;font-size:11px;transition:.2s;}
.wa-link:hover{background:#1ebe5d;color:#fff;}
.wa-link i{font-size:13px;}
.wa-sub{display:block;margin-top:4px;font-size:9.5px;color:rgba(255,255,255,.38);}
.wa-qr{margin-top:7px;display:flex;align-items:center;gap:8px;}
.wa-qr img{width:64px;height:64px;border-radius:6px;border:2px solid #25D366;object-fit:cover;flex-shrink:0;}
.wa-qr-info{font-size:9.5px;color:rgba(255,255,255,.42);line-height:1.5;}
.wa-qr-info strong{display:block;color:#25D366;font-size:10px;margin-bottom:1px;}
.left-foot{padding-top:8px;font-size:9.5px;color:rgba(255,255,255,.2);}

/* ─── RIGHT: lebar SAMA di login & register ─── */
.right{
  flex:0 0 400px;
  background:#fff;
  padding:20px 24px;
  display:flex;flex-direction:column;justify-content:center;
  overflow-y:auto;
}
.right h3{font-size:17px;font-weight:700;color:#333;margin-bottom:3px;}
.right .sub{font-size:12px;color:#aaa;margin-bottom:14px;}

/* Alerts */
.alert{padding:8px 11px;border-radius:4px;font-size:12px;margin-bottom:10px;display:flex;align-items:flex-start;gap:7px;line-height:1.5;}
.alert i{margin-top:1px;flex-shrink:0;}
.alert-danger {background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;}
.alert-info   {background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;}
.alert ul{padding-left:14px;margin-top:4px;}
.alert ul li{margin-bottom:2px;}

/* Lockout */
.lockout-box{background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:12px;text-align:center;margin-bottom:11px;}
.lc-icon{font-size:22px;color:#dc2626;margin-bottom:5px;}
.lc-title{font-size:12px;font-weight:700;color:#991b1b;margin-bottom:4px;}
.lc-timer{font-size:22px;font-weight:700;color:#dc2626;font-family:monospace;letter-spacing:2px;}
.lc-sub{font-size:11px;color:#b91c1c;margin-top:3px;}

/* Attempts */
.attempts-bar{display:flex;gap:4px;margin-bottom:9px;}
.att-dot{flex:1;height:4px;border-radius:2px;background:#e5e7eb;transition:background .3s;}
.att-dot.used{background:#ef4444;}
.att-dot.warn{background:#f59e0b;}

/* Section title (register) */
.sec-title{font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;display:flex;align-items:center;gap:5px;}

/* Form */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:9px;}
.fg{margin-bottom:10px;}
.fg label{display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:4px;}
.req{color:#e74c3c;}
.iw{position:relative;}
.iw i.ic{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#ccc;font-size:12px;}
.iw input,.iw select{width:100%;padding:7px 10px 7px 29px;border:1px solid #ddd;border-radius:3px;font-size:12px;font-family:inherit;outline:none;color:#555;transition:border-color .2s;background:#fff;}
.iw input:focus,.iw select:focus{border-color:#26B99A;box-shadow:0 0 0 2px rgba(38,185,154,.1);}
.iw input:disabled{background:#f9fafb;color:#aaa;cursor:not-allowed;}
.eye{position:absolute;right:9px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#ccc;font-size:12px;}

/* Password strength */
.str-bar{height:3px;border-radius:2px;background:#eee;margin-top:3px;overflow:hidden;}
.str-fill{height:100%;border-radius:2px;transition:width .3s,background .3s;}
.str-lbl{font-size:10px;color:#aaa;margin-top:2px;}
.match-hint{font-size:10px;margin-top:2px;}

/* Captcha */
.captcha-box{background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:8px 10px;margin-bottom:10px;}
.captcha-label{font-size:11px;font-weight:700;color:#166534;margin-bottom:5px;display:flex;align-items:center;gap:5px;}
.captcha-row{display:flex;align-items:center;gap:8px;}
.captcha-soal{font-size:17px;font-weight:700;color:#1e293b;background:#fff;border:1px solid #ddd;border-radius:4px;padding:4px 10px;letter-spacing:2px;min-width:70px;text-align:center;}
.captcha-eq{font-size:15px;color:#64748b;font-weight:700;}
.captcha-input{flex:1;padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:14px;font-weight:700;text-align:center;font-family:inherit;outline:none;color:#1e293b;}
.captcha-input:focus{border-color:#26B99A;box-shadow:0 0 0 2px rgba(38,185,154,.15);}

/* Remember row (login) */
.rem-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;font-size:12px;}
.rem-row label{display:flex;align-items:center;gap:5px;cursor:pointer;color:#666;}
.rem-row a{color:#26B99A;text-decoration:none;}

/* Submit button */
.btn-submit{width:100%;padding:9px;background:#26B99A;color:#fff;border:none;border-radius:3px;font-size:13px;font-weight:700;font-family:inherit;cursor:pointer;transition:background .2s;display:flex;align-items:center;justify-content:center;gap:7px;}
.btn-submit:hover{background:#1e9980;}
.btn-submit:disabled{background:#aaa;cursor:not-allowed;}

/* Divider & switch link */
.hr{border:none;border-top:1px solid #eee;margin:12px 0;}
.switch-link{text-align:center;font-size:12px;color:#aaa;}
.switch-link a{color:#26B99A;font-weight:700;text-decoration:none;}

/* ─── Layar pendek ─── */
@media(max-height:600px){
  .left{padding:13px 18px;}
  .right{padding:13px 20px;}
  .brand{margin-bottom:8px;}
  .modul-grid{gap:4px;margin-bottom:8px;}
  .modul-card{padding:5px 7px;}
  .feat li{margin-bottom:2px;}
  .wa-qr{display:none;}
  .fg{margin-bottom:7px;}
  .captcha-box{padding:6px 9px;margin-bottom:7px;}
  .right .sub{margin-bottom:10px;}
  .sec-title{margin-bottom:5px;}
  .hr{margin:8px 0;}
}

/* ─── HP: panel kiri sembunyi ─── */
@media(max-width:600px){
  html,body{overflow:auto;}
  .left{display:none;}
  .wrap{width:100%;height:auto;max-height:none;border-radius:0;}
  .right{flex:0 0 100%;padding:28px 22px;}
  .form-row{grid-template-columns:1fr;}
}
</style>