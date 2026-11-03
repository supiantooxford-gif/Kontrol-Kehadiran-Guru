<?php
/**
 * Dashboard kepala madrasah: ringkasan pengisian kehadiran guru berdasarkan jadwal.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';

$u = require_login();
$hariIni = date('Y-m-d');
$bulanIni = date('Y-m');

$jadwalHariIni = jadwal_tanggal($hariIni);
$kehadiranHariIni = kehadiran_tanggal($hariIni);
$progresHariIni = progres_tanggal($hariIni);

[$mAw, $mAkh, $mLabel] = bulan_rentang($bulanIni);
$ringkasBulan = ringkas_rentang($mAw, $mAkh);
[$wAw, $wAkh] = minggu_rentang($hariIni);
// Minggu berjalan tidak boleh mundur ke sebelum aplikasi dipakai (mulai 1 November 2026).
[$wAwEf, $wAkhEf, $wDipotong] = rentang_efektif($wAw, $wAkh);
$wLabel = 'Minggu ' . ($wDipotong ? label_rentang($wAwEf, $wAkhEf) : label_rentang($wAw, $wAkh));
$ringkasMinggu = ringkas_rentang($wAw, $wAkh);

$rekapBulan = rekap_guru($mAw, $mAkh);
$perHari = rekap_harian($mAw, $mAkh);
$grafikGuru = grafik_guru_bulan($bulanIni);

$belumLengkap = array_reverse(tanggal_belum_lengkap($mAw, $hariIni));
$belumLengkap = array_slice($belumLengkap, 0, 8);

$guruTerbaik = array_values(array_filter($rekapBulan, static fn(array $r): bool => $r['seharusnya'] > 0));
usort($guruTerbaik, static fn(array $a, array $b): int => [$b['persen'], $a['nama']] <=> [$a['persen'], $b['nama']]);

$dataGrafik = [
    'guru'   => array_slice($grafikGuru, 0, 14),
    'status' => [
        'H' => $ringkasBulan['H'], 'S' => $ringkasBulan['S'], 'I' => $ringkasBulan['I'],
        'A' => $ringkasBulan['A'], 'T' => $ringkasBulan['T'], 'belum' => $ringkasBulan['belum'],
    ],
    'harian' => [
        'labels' => array_map(static fn(array $r): string => (string) (int) date('j', strtotime($r['tanggal'])), $perHari),
        'hadir'  => array_map(static fn(array $r): int => (int) $r['H'], $perHari),
        'tidak'  => array_map(static fn(array $r): int => (int) $r['S'] + (int) $r['I'] + (int) $r['A'] + (int) $r['T'], $perHari),
    ],
];

page_top('Dashboard', '', '<a class="btn btn-primary btn-sm" href="kehadiran.php?tanggal=' . e($hariIni) . '">'
    . ikon('clipboard', 16) . ' Isi Kehadiran Hari Ini</a>');
?>

<div class="hero">
  <div>
    <h2>Selamat datang, <?= e(nama_pengguna($u)) ?></h2>
    <p><?= e(hari_id($hariIni)) ?>, <?= e(tanggal_id($hariIni)) ?> &middot;
      kontrol kehadiran guru pada <?= count($jadwalHariIni) ?> sesi mengajar hari ini.</p>
  </div>
  <div class="hero-nilai">
    <div>
      <strong><?= $progresHariIni['terisi'] ?>/<?= $progresHariIni['seharusnya'] ?></strong>
      <span>sesi terisi hari ini</span>
    </div>
    <div>
      <strong><?= e($ringkasBulan['seharusnya'] > 0 ? persen($ringkasBulan['H'], $ringkasBulan['seharusnya']) : '-') ?></strong>
      <span>kehadiran <?= e($mLabel) ?></span>
    </div>
  </div>
</div>

<div class="grid g4 mt16">
  <div class="stat">
    <span class="stat-ikon"><?= ikon('calendar', 21) ?></span>
    <div>
      <div class="stat-num"><?= count($jadwalHariIni) ?></div>
      <div class="stat-label">Sesi mengajar hari ini</div>
      <div class="stat-ket"><?= $progresHariIni['belum'] ?> sesi belum diisi</div>
    </div>
  </div>
  <div class="stat">
    <span class="stat-ikon emas"><?= ikon('list', 21) ?></span>
    <div>
      <div class="stat-num"><?= count(jadwal_per_guru()) ?></div>
      <div class="stat-label">Guru pada jadwal</div>
      <div class="stat-ket"><?= count(jadwal_semua()) ?> sesi per minggu</div>
    </div>
  </div>
  <div class="stat">
    <span class="stat-ikon"><?= ikon('checklist', 21) ?></span>
    <div>
      <div class="stat-num"><?= $ringkasMinggu['terisi'] ?>/<?= $ringkasMinggu['seharusnya'] ?></div>
      <div class="stat-label">Terisi minggu ini</div>
      <div class="stat-ket"><?= e($wLabel) ?></div>
    </div>
  </div>
  <div class="stat">
    <span class="stat-ikon emas"><?= ikon('trend', 21) ?></span>
    <div>
      <div class="stat-num"><?= $ringkasBulan['A'] + $ringkasBulan['S'] + $ringkasBulan['I'] + $ringkasBulan['T'] ?></div>
      <div class="stat-label">Tidak hadir <?= e($mLabel) ?></div>
      <div class="stat-ket">S <?= $ringkasBulan['S'] ?> &middot; I <?= $ringkasBulan['I'] ?>
        &middot; A <?= $ringkasBulan['A'] ?> &middot; T <?= $ringkasBulan['T'] ?></div>
    </div>
  </div>
</div>

<div class="grid g2 mt16">
  <div class="card">
    <div class="card-head">
      <div>
        <h2 class="card-title"><?= ikon('checklist', 18) ?> Sebaran Kehadiran</h2>
        <p class="card-sub"><?= e($mLabel) ?> &middot; <?= $ringkasBulan['terisi'] ?> sesi terisi
          dari <?= $ringkasBulan['seharusnya'] ?> seharusnya</p>
      </div>
    </div>
    <div class="chart-box rendah"><canvas id="chStatus" aria-label="Sebaran kehadiran guru"></canvas></div>
  </div>
  <div class="card">
    <div class="card-head">
      <div>
        <h2 class="card-title"><?= ikon('trend', 18) ?> Persentase Kehadiran per Guru</h2>
        <p class="card-sub">Hadir dibanding sesi seharusnya pada <?= e($mLabel) ?>.</p>
      </div>
    </div>
    <div class="chart-box"><canvas id="chGuru" aria-label="Persentase kehadiran per guru"></canvas></div>
  </div>
</div>

<div class="grid g2 mt16">
  <div class="card">
    <div class="card-head">
      <div>
        <h2 class="card-title"><?= ikon('clipboard', 18) ?> Harian <?= e($mLabel) ?></h2>
        <p class="card-sub">Jumlah sesi hadir dan tidak hadir tiap tanggal.</p>
      </div>
    </div>
    <div class="chart-box"><canvas id="chHarian" aria-label="Kehadiran harian"></canvas></div>
  </div>
  <div class="card">
    <div class="card-head">
      <div>
        <h2 class="card-title"><?= ikon('alert', 18) ?> Pengisian Belum Lengkap</h2>
        <p class="card-sub">Tanggal pada <?= e($mLabel) ?> yang kehadirannya belum terisi penuh.</p>
      </div>
      <div class="card-aksi"><a class="btn btn-ghost btn-sm" href="laporan.php?mode=bulan&amp;bulan=<?= e($bulanIni) ?>">Laporan Bulanan</a></div>
    </div>
    <?php if ($belumLengkap === []): ?>
      <div class="alert alert-ok" style="margin-bottom:0"><?= ikon('check', 16) ?>
        <span>Seluruh sesi pada bulan ini sudah terisi. Terima kasih.</span></div>
    <?php else: ?>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>Tanggal</th><th class="c">Seharusnya</th><th class="c">Terisi</th><th class="c">Belum</th><th class="c">Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($belumLengkap as $b): ?>
              <tr>
                <td class="nowrap"><b><?= e($b['hari']) ?></b>, <?= e(tanggal_id($b['tanggal'])) ?></td>
                <td class="c num"><?= $b['seharusnya'] ?></td>
                <td class="c num"><?= $b['terisi'] ?></td>
                <td class="c num"><span class="pil pil-warn"><?= $b['belum'] ?></span></td>
                <td class="c"><a class="btn btn-soft btn-sm" href="kehadiran.php?tanggal=<?= e($b['tanggal']) ?>">Isi</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<div class="card mt16">
    <div class="card-head">
      <div>
        <h2 class="card-title"><?= ikon('user', 18) ?> Ringkas per Guru</h2>
        <p class="card-sub"><?= e($mLabel) ?> &middot; diurutkan dari persentase kehadiran tertinggi.</p>
      </div>
      <div class="card-aksi"><a class="btn btn-ghost btn-sm" href="laporan.php?mode=bulan&amp;bulan=<?= e($bulanIni) ?>">Lihat Semua</a></div>
    </div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Guru</th><th class="c">Seharusnya</th><th class="c">H</th><th class="c">S/I/A/T</th><th class="c">Hadir</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($guruTerbaik, 0, 8) as $r): ?>
            <tr>
              <td class="nama-guru"><?= e($r['nama']) ?></td>
              <td class="c num"><?= $r['seharusnya'] ?></td>
              <td class="c num"><?= $r['H'] ?></td>
              <td class="c num"><?= $r['S'] + $r['I'] + $r['A'] + $r['T'] ?></td>
              <td class="c num"><b><?= e(persen($r['H'], $r['seharusnya'], 1)) ?></b></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($guruTerbaik === []): ?>
            <tr><td colspan="5" class="c mut">Belum ada data kehadiran pada bulan ini.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
</div>

<script src="assets/vendor/chart.umd.min.js"></script>
<script>window.KKG_GRAFIK = <?= json_encode($dataGrafik, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php page_bottom();
