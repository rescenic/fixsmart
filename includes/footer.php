<?php // includes/footer.php ?>
</div><!-- /.main -->

<script>
function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const mn = document.getElementById('main');
  const tn = document.getElementById('topnav');
  if (window.innerWidth > 768) {
    const closed = sb.style.width === '0px' || sb.style.width === '';
    if (closed) {
      sb.style.width = '230px'; mn.style.marginLeft = '230px'; tn.style.left = '230px';
    } else {
      sb.style.width = '0px'; sb.style.overflow = 'hidden';
      mn.style.marginLeft = '0'; tn.style.left = '0';
    }
  } else {
    sb.classList.toggle('open');
  }
}

function toggleDropdown(el) {
  const m = el.querySelector('.tn-dropdown');
  if (m) m.style.display = m.style.display === 'block' ? 'none' : 'block';
}
document.addEventListener('click', function(e) {
  document.querySelectorAll('.tn-user').forEach(u => {
    if (!u.contains(e.target)) {
      const m = u.querySelector('.tn-dropdown');
      if (m) m.style.display = 'none';
    }
  });
});

// Tab switching
function switchTab(btn, tabId) {
  const container = btn.closest('.panel') || btn.parentElement.closest('div');
  container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  container.querySelectorAll('.tab-c').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  const target = document.getElementById(tabId);
  if (target) target.classList.add('active');
}

// Modal helpers
function openModal(id) { document.getElementById(id)?.classList.add('show'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('show'); }
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-ov')) e.target.classList.remove('show');
});

// Auto dismiss alerts
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(a => {
    a.style.transition = 'opacity .4s';
    a.style.opacity = '0';
    setTimeout(() => a.remove(), 400);
  });
}, 5000);
</script>
</body>
</html>
