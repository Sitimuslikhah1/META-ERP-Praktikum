// META ERP - Interactive UI Enhancements
document.addEventListener('DOMContentLoaded', () => {
  // 1. Auto fade-out alerts after 5 seconds
  const alertBox = document.querySelector('.alert');
  if (alertBox) {
    setTimeout(() => {
      alertBox.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      alertBox.style.opacity = '0';
      alertBox.style.transform = 'translateY(-10px)';
      setTimeout(() => alertBox.remove(), 500);
    }, 5000);
  }

  // 2. Button click feedback & state
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', (e) => {
      const submitBtn = form.querySelector('button[type="submit"], button:not([type])');
      if (submitBtn && !submitBtn.disabled) {
        submitBtn.style.opacity = '0.85';
        submitBtn.style.pointerEvents = 'none';
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Memproses...`;
      }
    });
  });

  // 3. Highlight status text in tables
  document.querySelectorAll('table td').forEach(td => {
    const text = td.textContent.trim();
    if (text === 'Disetujui' || text === 'Barang Diterima') {
      td.innerHTML = `<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>${text}</span>`;
    } else if (text === 'Menunggu Verifikasi') {
      td.innerHTML = `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1 rounded-pill"><i class="bi bi-clock-fill me-1"></i>${text}</span>`;
    } else if (text === 'Diterima Sebagian') {
      td.innerHTML = `<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1 rounded-pill"><i class="bi bi-pie-chart-fill me-1"></i>${text}</span>`;
    } else if (text === 'Ditolak' || text === 'Dikoreksi') {
      td.innerHTML = `<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill"><i class="bi bi-x-circle-fill me-1"></i>${text}</span>`;
    } else if (text === 'Duplikat simulasi') {
      td.innerHTML = `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill"><i class="bi bi-exclamation-triangle-fill me-1"></i>${text}</span>`;
    } else if (text === 'Aktif') {
      td.innerHTML = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill"><i class="bi bi-record-circle-fill me-1"></i>${text}</span>`;
    }
  });
});