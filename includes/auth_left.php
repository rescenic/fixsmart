<!-- LEFT PANEL — identik di login.php & register.php -->
<div class="left">
  <div class="brand">
    <div class="brand-icon"><i class="fa fa-desktop"></i></div>
    <div>
      <div class="brand-name"><?= APP_NAME ?></div>
      <div class="brand-sub">Integrated Helpdesk &amp; Asset Management</div>
    </div>
  </div>
  <h2>Platform Layanan Terpadu Rumah Sakit</h2>
  <p>Satu sistem untuk mengelola semua permintaan layanan IT, IPSRS, pemeliharaan, dan aset secara terintegrasi dan real-time.</p>
  <div class="modul-grid">
    <div class="modul-card"><div class="modul-ic c-it"><i class="fa fa-laptop"></i></div><div><div class="modul-title">Order Tiket IT</div><div class="modul-desc">Komputer, jaringan, printer &amp; perangkat IT</div></div></div>
    <div class="modul-card"><div class="modul-ic c-ipsrs"><i class="fa fa-toolbox"></i></div><div><div class="modul-title">Order Tiket IPSRS</div><div class="modul-desc">Alat medis, non-medis &amp; sarana prasarana RS</div></div></div>
    <div class="modul-card"><div class="modul-ic c-maint"><i class="fa fa-wrench"></i></div><div><div class="modul-title">Maintenance</div><div class="modul-desc">Jadwal &amp; histori pemeliharaan preventif</div></div></div>
    <div class="modul-card"><div class="modul-ic c-aset"><i class="fa fa-boxes-stacked"></i></div><div><div class="modul-title">Manajemen Aset</div><div class="modul-desc">Inventaris, kondisi &amp; tracking aset</div></div></div>
  </div>
  <ul class="feat">
    <li><i class="fa fa-check-circle"></i> Pantau status tiket real-time: Menunggu &rarr; Diproses &rarr; Selesai</li>
    <li><i class="fa fa-check-circle"></i> Notifikasi Telegram otomatis setiap perubahan status</li>
    <li><i class="fa fa-check-circle"></i> Upload foto bukti pengerjaan langsung dari tiket</li>
    <li><i class="fa fa-check-circle"></i> Pengukuran SLA &amp; laporan kinerja teknisi otomatis</li>
    <li><i class="fa fa-check-circle"></i> Riwayat &amp; histori lengkap semua aktivitas per tiket</li>
  </ul>
  <div class="wa-box">
    <a href="https://chat.whatsapp.com/JlLw0jaANMG0m1oAu7wYWP?mode=gi_t" target="_blank" class="wa-link">
      <i class="fab fa-whatsapp"></i> Gabung Grup WhatsApp FixSmart Helpdesk
    </a>
    <small class="wa-sub">Info update, pengumuman &amp; bantuan cepat seputar aplikasi FixSmart.</small>
    <div class="wa-qr">
      <img src="<?= APP_URL ?>/barcode_grup_wa.png" alt="QR Code Grup WhatsApp FixSmart">
      <div class="wa-qr-info"><strong>Scan QR Code</strong>Arahkan kamera HP untuk langsung bergabung ke grup WhatsApp FixSmart Helpdesk.</div>
    </div>
  </div>
  <div class="left-foot">&copy; <?= date('Y') ?> <?= APP_NAME ?>. M. Wira Sb.S.Kom &mdash; 082177846209.</div>
</div>