/* Kontrol Kehadiran Guru — skrip pelengkap (semua fitur tetap bekerja tanpa JS). */
(function () {
  'use strict';

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  // ---------------------------------------------------------------- navigasi HP
  function initNav() {
    var tombol = $('#navToggle');
    var sidebar = $('#sidebar');
    var scrim = $('#scrim');
    if (!tombol || !sidebar || !scrim) { return; }
    function tutup() {
      sidebar.classList.remove('buka');
      scrim.classList.remove('on');
    }
    tombol.addEventListener('click', function () {
      sidebar.classList.toggle('buka');
      scrim.classList.toggle('on', sidebar.classList.contains('buka'));
    });
    scrim.addEventListener('click', tutup);
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') { tutup(); }
    });
  }

  // ---------------------------------------------------------------- tombol cetak
  function initPrint() {
    $$('[data-print]').forEach(function (b) {
      b.addEventListener('click', function () { window.print(); });
    });
  }

  // ---------------------------------------------------------------- input kehadiran
  function initKehadiran() {
    var form = $('#formKehadiran');
    if (!form) { return; }
    var hitung = $('#hitungTerisi');
    var total = $$('.opsi-status', form).length;

    function terisi() {
      var n = 0;
      $$('.opsi-status', form).forEach(function (g) {
        if (g.querySelector('input[type=radio]:checked:not([value=""])')) { n++; }
      });
      return n;
    }
    function perbarui() {
      if (!hitung) { return; }
      hitung.textContent = terisi() + ' dari ' + total + ' sesi terisi';
    }
    form.addEventListener('change', function (ev) {
      if (ev.target && ev.target.type === 'radio') { perbarui(); }
    });
    perbarui();

    // Tandai semua Hadir
    $$('[data-tandai-hadir]').forEach(function (b) {
      b.addEventListener('click', function () {
        $$('.opsi-status', form).forEach(function (g) {
          var h = g.querySelector('input[value="H"]');
          if (h) { h.checked = true; }
        });
        perbarui();
      });
    });
    // Kosongkan semua pilihan
    $$('[data-kosongkan]').forEach(function (b) {
      b.addEventListener('click', function () {
        $$('.opsi-status', form).forEach(function (g) {
          var x = g.querySelector('input[value=""]');
          if (x) { x.checked = true; }
        });
        perbarui();
      });
    });
  }

  // ---------------------------------------------------------------- grafik dashboard
  function warna(varName, cadangan) {
    var v = getComputedStyle(document.body).getPropertyValue(varName).trim();
    return v !== '' ? v : cadangan;
  }

  function initGrafik() {
    if (typeof Chart === 'undefined' || !window.KKG_GRAFIK) { return; }
    var data = window.KKG_GRAFIK;
    var hijau = warna('--hijau-600', '#12805a');
    var hijauMuda = warna('--hijau-500', '#1a9c6d');
    var emas = warna('--emas', '#c8a13a');

    var kGuru = $('#chGuru');
    if (kGuru && data.guru && data.guru.length) {
      new Chart(kGuru, {
        type: 'bar',
        data: {
          labels: data.guru.map(function (g) { return g.nama; }),
          datasets: [{
            label: 'Hadir (%)',
            data: data.guru.map(function (g) { return g.persen; }),
            backgroundColor: hijauMuda,
            hoverBackgroundColor: hijau,
            borderRadius: 6,
            maxBarThickness: 34
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: 'y',
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (c) {
                  var g = data.guru[c.dataIndex];
                  return 'Hadir ' + c.parsed.x + '% dari ' + g.seharusnya + ' sesi';
                }
              }
            }
          },
          scales: {
            x: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + '%'; }, color: '#5f7168', font: { size: 11 } }, grid: { color: '#eef3f0' } },
            y: { ticks: { color: '#10261c', font: { size: 11 } }, grid: { display: false } }
          }
        }
      });
    }

    var kStatus = $('#chStatus');
    if (kStatus && data.status) {
      var s = data.status;
      new Chart(kStatus, {
        type: 'doughnut',
        data: {
          labels: ['Hadir', 'Sakit', 'Izin', 'Alpa', 'Terlambat', 'Belum diisi'],
          datasets: [{
            data: [s.H || 0, s.S || 0, s.I || 0, s.A || 0, s.T || 0, s.belum || 0],
            backgroundColor: [hijau, '#b45309', '#1d4ed8', '#b91c1c', '#7c3aed', '#cfd9d4'],
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '62%',
          plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, padding: 12, color: '#10261c', font: { size: 11.5 } } }
          }
        }
      });
    }

    var kHarian = $('#chHarian');
    if (kHarian && data.harian) {
      new Chart(kHarian, {
        type: 'line',
        data: {
          labels: data.harian.labels,
          datasets: [
            {
              label: 'Hadir',
              data: data.harian.hadir,
              borderColor: hijau,
              backgroundColor: 'rgba(26,156,109,.12)',
              fill: true,
              tension: .35,
              pointRadius: 3
            },
            {
              label: 'Tidak hadir (S/I/A/T)',
              data: data.harian.tidak,
              borderColor: emas,
              backgroundColor: 'rgba(200,161,58,.10)',
              fill: true,
              tension: .35,
              pointRadius: 3
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, padding: 12, color: '#10261c', font: { size: 11.5 } } } },
          scales: {
            y: { beginAtZero: true, ticks: { precision: 0, color: '#5f7168', font: { size: 11 } }, grid: { color: '#eef3f0' } },
            x: { ticks: { color: '#5f7168', font: { size: 11 } }, grid: { display: false } }
          }
        }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    initNav();
    initPrint();
    initKehadiran();
    initGrafik();
  });
})();
