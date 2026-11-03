<?php
/**
 * Lembar cetak Laporan Kehadiran Guru (A4 landscape) — ringkasan per guru,
 * rekap per tanggal, dan (opsional) rincian tiap sesi mengajar.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';

require_login();

$mode = (string) ($_GET['mode'] ?? 'minggu') === 'bulan' ? 'bulan' : 'minggu';
$tanggalPilih = tgl_ok($_GET['tanggal'] ?? '') ?? date('Y-m-d');
$bulanPilih = bulan_ok($_GET['bulan'] ?? '') ?? date('Y-m');
$denganRincian = (string) ($_GET['rincian'] ?? '') === '1';

if ($mode === 'minggu') {
    [$dari, $sampai, $label] = minggu_rentang($tanggalPilih);
} else {
    [$dari, $sampai, $label] = bulan_rentang($bulanPilih);
}

// Rekap tidak boleh mundur ke sebelum aplikasi dipakai (mulai 1 November 2026).
[$dariEf, $sampaiEf, $dipotong] = rentang_efektif($dari, $sampai);
$tidakAdaData = $dariEf > $sampaiEf;

if ($mode === 'minggu') {
    $judulPeriode = 'Minggu: ' . ($tidakAdaData
        ? tanggal_id($dari) . ' s.d. ' . tanggal_id($sampai)
        : tanggal_id($dariEf) . ' s.d. ' . tanggal_id($sampaiEf));
} else {
    $judulPeriode = 'Bulan: ' . $label;
}

$rekap = rekap_guru($dari, $sampai);
$total = rekap_total($rekap);
$harian = rekap_harian($dari, $sampai);
$periode = periode_aktif();
$kembali = 'laporan.php?mode=' . $mode . ($mode === 'minggu' ? '&tanggal=' . $tanggalPilih : '&bulan=' . $bulanPilih);
$tglCetak = date('Y-m-d');

cetak_top('Laporan Kehadiran Guru', $kembali, false);
?>
  <div class="sheet-head <?= logo_madrasah() !== '' ? 'kop-logo' : '' ?>">
    <?php if (logo_madrasah() !== ''): ?>
      <img class="kop-gambar" src="<?= e(logo_madrasah()) ?>" alt="Logo <?= e(nama_madrasah()) ?>">
    <?php endif; ?>
    <div class="kop-teks">
      <p class="kop-nama"><?= e(nama_madrasah()) ?></p>
      <?php if (sekolah_nilai('alamat', '') !== ''): ?>
        <p class="kop-baris"><?= e(sekolah_nilai('alamat')) ?></p>
      <?php endif; ?>
      <?php if (alamat_wilayah() !== ''): ?>
        <p class="kop-baris"><?= e(alamat_wilayah()) ?></p>
      <?php endif; ?>
      <?php
        $kopKontak = [];
        if (sekolah_nilai('email', '') !== '') {
            $kopKontak[] = 'email: ' . sekolah_nilai('email');
        }
        if (sekolah_nilai('website', '') !== '') {
            $kopKontak[] = 'website: ' . sekolah_nilai('website');
        }
      ?>
      <?php if ($kopKontak !== []): ?>
        <p class="kop-baris"><?= e(implode(', ', $kopKontak)) ?></p>
      <?php endif; ?>
      <?php
        $kopIdent = [];
        if (sekolah_nilai('npsn', '') !== '') {
            $kopIdent[] = 'NPSN: ' . sekolah_nilai('npsn');
        }
        if (sekolah_nilai('nsm', '') !== '') {
            $kopIdent[] = 'NSM: ' . sekolah_nilai('nsm');
        }
        if (sekolah_nilai('telepon', '') !== '') {
            $kopIdent[] = 'HP: ' . sekolah_nilai('telepon');
        }
      ?>
      <?php if ($kopIdent !== []): ?>
        <p class="kop-baris"><?= e(implode(', ', $kopIdent)) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="sheet-judul">
    <h2>Laporan Kehadiran Guru Berdasarkan Jadwal Mengajar</h2>
    <p class="sheet-periode"><?= e($judulPeriode) ?></p>
  </div>

  <div class="sheet-meta">
    <span>Tahun Ajaran: <b><?= e($periode['ta'] !== '' ? $periode['ta'] : '-') ?></b></span>
    <span>Semester: <b><?= e($periode['semester'] !== '' ? $periode['semester'] : '-') ?></b></span>
    <span>Jumlah Guru: <b><?= count($rekap) ?></b></span>
    <span>Jumlah Sesi: <b><?= $total['seharusnya'] ?></b></span>
    <span>Keterangan: <b>H</b> Hadir, <b>S</b> Sakit, <b>I</b> Izin, <b>A</b> Alpa, <b>T</b> Terlambat</span>
    <?php if ($dipotong): ?>
      <span>Rekap dihitung mulai: <b><?= e(tanggal_id(tanggal_mulai())) ?></b></span>
    <?php endif; ?>
  </div>

  <table class="sheet-tbl">
    <thead>
      <tr>
        <th style="width:32px">No</th>
        <th>Nama Guru</th>
        <th style="width:70px">Seharusnya</th>
        <th style="width:42px">H</th>
        <th style="width:42px">S</th>
        <th style="width:42px">I</th>
        <th style="width:42px">A</th>
        <th style="width:42px">T</th>
        <th style="width:60px">Belum Diisi</th>
        <th style="width:70px">Hadir (%)</th>
      </tr>
    </thead>
    <tbody>
      <?php $no = 0; foreach ($rekap as $r): $no++; ?>
        <tr>
          <td class="c"><?= $no ?></td>
          <td><?= e($r['nama']) ?></td>
          <td class="c"><?= $r['seharusnya'] ?></td>
          <td class="c"><?= $r['H'] ?></td>
          <td class="c"><?= $r['S'] ?></td>
          <td class="c"><?= $r['I'] ?></td>
          <td class="c"><?= $r['A'] ?></td>
          <td class="c"><?= $r['T'] ?></td>
          <td class="c"><?= $r['belum'] ?></td>
          <td class="c"><?= e(persen($r['H'], $r['seharusnya'], 1)) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rekap === []): ?>
        <tr><td class="c" colspan="10"><?= $tidakAdaData
            ? 'Periode ini seluruhnya sebelum aplikasi mulai digunakan (' . e(tanggal_id(tanggal_mulai())) . ').'
            : 'Belum ada data kehadiran pada periode ini.' ?></td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td class="c" colspan="2">Jumlah</td>
        <td class="c"><?= $total['seharusnya'] ?></td>
        <td class="c"><?= $total['H'] ?></td>
        <td class="c"><?= $total['S'] ?></td>
        <td class="c"><?= $total['I'] ?></td>
        <td class="c"><?= $total['A'] ?></td>
        <td class="c"><?= $total['T'] ?></td>
        <td class="c"><?= $total['belum'] ?></td>
        <td class="c"><?= e(persen($total['H'], $total['seharusnya'], 1)) ?></td>
      </tr>
    </tfoot>
  </table>

  <h3 style="margin:16px 0 6px;font-size:.9rem">Rekap per Tanggal</h3>
  <table class="sheet-tbl">
    <thead>
      <tr>
        <th style="width:32px">No</th>
        <th>Hari / Tanggal</th>
        <th style="width:70px">Seharusnya</th>
        <th style="width:42px">H</th>
        <th style="width:42px">S</th>
        <th style="width:42px">I</th>
        <th style="width:42px">A</th>
        <th style="width:42px">T</th>
        <th style="width:60px">Belum Diisi</th>
      </tr>
    </thead>
    <tbody>
      <?php $no = 0; foreach ($harian as $h): $no++; ?>
        <tr>
          <td class="c"><?= $no ?></td>
          <td><?= e($h['hari']) ?>, <?= e(tanggal_id($h['tanggal'])) ?></td>
          <td class="c"><?= (int) $h['seharusnya'] ?></td>
          <td class="c"><?= $h['H'] ?></td>
          <td class="c"><?= $h['S'] ?></td>
          <td class="c"><?= $h['I'] ?></td>
          <td class="c"><?= $h['A'] ?></td>
          <td class="c"><?= $h['T'] ?></td>
          <td class="c"><?= (int) $h['belum'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td class="c" colspan="2">Jumlah</td>
        <td class="c"><?= $total['seharusnya'] ?></td>
        <td class="c"><?= $total['H'] ?></td>
        <td class="c"><?= $total['S'] ?></td>
        <td class="c"><?= $total['I'] ?></td>
        <td class="c"><?= $total['A'] ?></td>
        <td class="c"><?= $total['T'] ?></td>
        <td class="c"><?= $total['belum'] ?></td>
      </tr>
    </tfoot>
  </table>

  <?php if ($denganRincian): ?>
    <?php foreach ($rekap as $r):
        $sesi = rincian_sesi($dari, $sampai, $r['key']);
        if ($sesi === []) {
            continue;
        }
    ?>
      <h3 style="margin:18px 0 6px;font-size:.9rem">
        Rincian: <?= e($r['nama']) ?> &nbsp;|&nbsp; Seharusnya <?= $r['seharusnya'] ?> sesi,
        H <?= $r['H'] ?>, S <?= $r['S'] ?>, I <?= $r['I'] ?>, A <?= $r['A'] ?>, T <?= $r['T'] ?>
      </h3>
      <table class="sheet-tbl">
        <thead>
          <tr>
            <th style="width:32px">No</th>
            <th style="width:150px">Hari / Tanggal</th>
            <th style="width:46px">Jam Ke</th>
            <th style="width:90px">Waktu</th>
            <th style="width:80px">Kelas</th>
            <th>Mata Pelajaran</th>
            <th style="width:80px">Kehadiran</th>
            <th style="width:190px">Catatan</th>
          </tr>
        </thead>
        <tbody>
          <?php $n = 0; foreach ($sesi as $s): $n++; ?>
            <tr>
              <td class="c"><?= $n ?></td>
              <td><?= e($s['hari']) ?>, <?= e(tanggal_id($s['tanggal'])) ?></td>
              <td class="c"><?= (int) $s['jam_ke'] ?></td>
              <td><?= e($s['waktu']) ?></td>
              <td><?= e($s['kelas'] !== '' ? $s['kelas'] : '-') ?></td>
              <td><?= e($s['mapel'] !== '' ? $s['mapel'] : '-') ?></td>
              <td class="c"><?= $s['status'] !== null && isset(STATUS_KEHADIRAN[$s['status']])
                  ? e($s['status'] . ' - ' . STATUS_KEHADIRAN[$s['status']]) : 'belum diisi' ?></td>
              <td><?= e($s['catatan'] !== '' ? $s['catatan'] : '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="sheet-sign">
    <div class="ttd">
      <div class="ttd-tempat"><?= e((kecamatan_madrasah() !== '' ? kecamatan_madrasah() . ', ' : '') . tanggal_id($tglCetak)) ?></div>
      <p class="ttd-jabatan"><?= e(jabatan_kepala()) ?></p>
      <p class="ttd-nama"><?= e(kepala_nama() !== '' ? kepala_nama() : '(...........................................)') ?></p>
      <p class="ttd-nip">NIP. <?= e(kepala_nip() !== '' ? kepala_nip() : '..............................') ?></p>
    </div>
  </div>
<?php cetak_bottom();
