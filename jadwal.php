<?php
/**
 * Jadwal Mengajar — dibaca langsung dari jadwal Website Sekolah (halaman publik
 * https://ma-stm.sch.id/halaman/jadwal-mengajar), plus rekapan per guru:
 * berapa kali seharusnya mengajar dalam 1 minggu dan dalam 1 bulan.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';

require_login();

$hariPilih = trim((string) ($_GET['hari'] ?? ''));
$hariPilih = in_array($hariPilih, HARI_LIST, true) ? $hariPilih : '';

$bulanPilih = bulan_ok($_GET['bulan'] ?? '') ?? date('Y-m');
[$bAw, $bAkh, $bLabel] = bulan_rentang($bulanPilih);
// Rekap bulanan tidak boleh mundur ke sebelum aplikasi dipakai (mulai 1 November 2026).
[$bAwEf, $bAkhEf, $bDipotong] = rentang_efektif($bAw, $bAkh);
$bKosong = $bAwEf > $bAkhEf;

$jadwalSemua = jadwal_semua();
$perGuru = jadwal_per_guru();
$seharusnyaBulan = seharusnya_per_guru($bAw, $bAkh);

$jadwalTampil = $hariPilih !== '' ? jadwal_hari($hariPilih) : $jadwalSemua;

// Kegiatan pada jadwal sumber (Sholat/Istirahat/Upacara tanpa guru) tidak dihitung sebagai sesi.
$totalBarisSumber = (int) (sk_one('SELECT COUNT(*) AS n FROM kurikulum_jadwal')['n'] ?? 0);
$kegiatan = max(0, $totalBarisSumber - count($jadwalSemua));

// Tanggal terdekat untuk hari yang dipilih (dipakai tombol "Input kehadiran").
function tanggal_hari_terdekat(string $hari): string
{
    $hr = date('Y-m-d');
    foreach (tanggal_list($hr, date('Y-m-d', strtotime('+6 day'))) as $t) {
        if (hari_id($t) === $hari) {
            return $t;
        }
    }
    return $hr;
}

$totalPerHari = [];
foreach (HARI_LIST as $h) {
    $totalPerHari[$h] = count(jadwal_hari($h));
}

page_top('Jadwal Mengajar', 'Tersinkron otomatis dengan jadwal Website Sekolah', 
    '<a class="btn btn-ghost btn-sm" href="' . e(JADWAL_URL) . '" target="_blank" rel="noopener noreferrer">'
    . ikon('link', 15) . ' Lihat Jadwal Asli</a>');
?>

<div class="card">
  <div class="card-head">
    <div>
      <h2 class="card-title"><?= ikon('calendar', 18) ?> Sinkronisasi Jadwal</h2>
      <p class="card-sub">
        Jadwal diambil langsung dari halaman <b>Jadwal Mengajar</b> Website <?= e(sebutan_lembaga()) ?>
        (<?= e(JADWAL_URL) ?>), sehingga setiap perubahan jadwal di web sekolah langsung berlaku di sini —
        tanpa impor ulang.
      </p>
    </div>
  </div>
  <div class="grid g4">
    <div>
      <div class="xs mut">Sesi mengajar per minggu</div>
      <strong style="font-size:1.15rem"><?= count($jadwalSemua) ?></strong>
    </div>
    <div>
      <div class="xs mut">Guru pada jadwal</div>
      <strong style="font-size:1.15rem"><?= count($perGuru) ?></strong>
    </div>
    <div>
      <div class="xs mut">Baris kegiatan (bukan sesi)</div>
      <strong style="font-size:1.15rem"><?= $kegiatan ?></strong>
      <div class="xs mut">Sholat, istirahat, upacara</div>
    </div>
    <div>
      <div class="xs mut">Tahun ajaran aktif</div>
      <strong style="font-size:1.05rem"><?= e(periode_aktif()['ta'] !== '' ? periode_aktif()['ta'] : '-') ?></strong>
      <div class="xs mut">Semester <?= e(periode_aktif()['semester'] !== '' ? periode_aktif()['semester'] : '-') ?></div>
    </div>
  </div>
</div>

<div class="card mt16">
  <div class="card-head">
    <div>
      <h2 class="card-title"><?= ikon('list', 18) ?> Daftar Jadwal Mengajar</h2>
      <p class="card-sub">Baris tanpa guru (kegiatan madrasah) tidak ditampilkan karena bukan sesi mengajar.</p>
    </div>
    <div class="card-aksi">
      <div class="chip-list">
        <a class="chip <?= $hariPilih === '' ? 'on' : '' ?>" href="jadwal.php?bulan=<?= e($bulanPilih) ?>">Semua <span class="chip-count"><?= count($jadwalSemua) ?></span></a>
        <?php foreach (HARI_LIST as $h): ?>
          <a class="chip <?= $hariPilih === $h ? 'on' : '' ?>" href="jadwal.php?hari=<?= e($h) ?>&amp;bulan=<?= e($bulanPilih) ?>">
            <?= e($h) ?> <span class="chip-count"><?= $totalPerHari[$h] ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if ($jadwalTampil === []): ?>
    <p class="mut mb0">Tidak ada sesi mengajar pada pilihan ini.</p>
  <?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th class="kolom-sempit">No</th>
            <th>Hari</th>
            <th class="kolom-sempit">Jam Ke</th>
            <th>Waktu</th>
            <th>Kelas</th>
            <th>Mata Pelajaran</th>
            <th>Guru</th>
            <?php if ($hariPilih !== ''): ?><th class="kolom-sempit">Aksi</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php $no = 0; foreach ($jadwalTampil as $j): $no++; ?>
            <tr>
              <td class="c num"><?= $no ?></td>
              <td class="nowrap"><?= e($j['hari']) ?></td>
              <td class="c num"><?= $j['jam_ke'] ?></td>
              <td class="nowrap"><?= e($j['waktu']) ?></td>
              <td class="nowrap"><?= e($j['kelas'] !== '' ? $j['kelas'] : '-') ?></td>
              <td><?= e($j['mapel'] !== '' ? $j['mapel'] : '-') ?></td>
              <td class="nama-guru"><?= e($j['guru']) ?></td>
              <?php if ($hariPilih !== ''): ?>
                <td class="c nowrap">
                  <a class="btn btn-soft btn-sm" href="kehadiran.php?tanggal=<?= e(tanggal_hari_terdekat($hariPilih)) ?>">Input</a>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="grid g2 mt16">
  <div class="card">
    <div class="card-head">
      <div>
        <h2 class="card-title"><?= ikon('clipboard', 18) ?> Rekap per Guru — per Minggu</h2>
        <p class="card-sub">Berapa kali seharusnya mengajar dalam satu minggu penuh (Senin–Minggu).</p>
      </div>
    </div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Guru</th>
            <?php foreach (HARI_LIST as $h): ?><th class="c"><?= e(substr($h, 0, 3)) ?></th><?php endforeach; ?>
            <th class="c">Jumlah</th>
          </tr>
        </thead>
        <tbody>
          <?php $totHari = array_fill_keys(HARI_LIST, 0); $totSemua = 0; ?>
          <?php foreach ($perGuru as $g): ?>
            <tr>
              <td class="nama-guru"><?= e($g['nama']) ?>
                <span class="sub-kecil"><?= e(implode(', ', array_slice($g['mapel'], 0, 2))) ?></span>
              </td>
              <?php foreach (HARI_LIST as $h): $n = (int) ($g['per_hari'][$h] ?? 0); $totHari[$h] += $n; ?>
                <td class="c num"><?= $n > 0 ? $n : '<span class="mut">&middot;</span>' ?></td>
              <?php endforeach; ?>
              <?php $totSemua += (int) $g['jumlah']; ?>
              <td class="c num"><b><?= (int) $g['jumlah'] ?></b></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($perGuru === []): ?>
            <tr><td colspan="9" class="c mut">Jadwal belum tersedia.</td></tr>
          <?php endif; ?>
        </tbody>
        <?php if ($perGuru !== []): ?>
          <tfoot>
            <tr>
              <td>Jumlah sesi</td>
              <?php foreach (HARI_LIST as $h): ?><td class="c num"><?= $totHari[$h] ?></td><?php endforeach; ?>
              <td class="c num"><?= $totSemua ?></td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h2 class="card-title"><?= ikon('trend', 18) ?> Rekap per Guru — per Bulan</h2>
        <p class="card-sub">Jumlah sesi mengajar seharusnya pada <?= e($bLabel) ?><?= $bDipotong
            ? ' &middot; dihitung mulai ' . e(tanggal_id(tanggal_mulai())) : '' ?>.</p>
      </div>
      <div class="card-aksi">
        <form method="get" action="jadwal.php" class="form-aksi">
          <?php if ($hariPilih !== ''): ?><input type="hidden" name="hari" value="<?= e($hariPilih) ?>"><?php endif; ?>
          <input type="month" name="bulan" value="<?= e($bulanPilih) ?>" aria-label="Pilih bulan" onchange="this.form.submit()">
          <noscript><button class="btn btn-ghost btn-sm" type="submit">Tampilkan</button></noscript>
        </form>
      </div>
    </div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr><th>Guru</th><th class="c">Hari mengajar</th><th class="c">Sesi seharusnya</th></tr>
        </thead>
        <tbody>
          <?php $totalSeharusnya = 0; ?>
          <?php foreach ($seharusnyaBulan as $k => $s):
              // Hitung hari mengajar yang benar-benar ada di bulan ini.
              $hariMengajar = 0;
              $rincianHari = [];
              foreach (HARI_LIST as $h) {
                  $jmlHari = $bKosong ? 0 : jumlah_hari_dalam_rentang($h, $bAwEf, $bAkhEf);
                  $sesiHari = (int) ($perGuru[$k]['per_hari'][$h] ?? 0);
                  if ($sesiHari > 0) {
                      $hariMengajar += $jmlHari;
                      $rincianHari[] = $h . ' (' . $sesiHari . '&times;' . $jmlHari . ')';
                  }
              }
              $totalSeharusnya += (int) $s['seharusnya'];
          ?>
            <tr>
              <td class="nama-guru"><?= e($s['nama']) ?>
                <span class="sub-kecil"><?= implode(' &middot; ', $rincianHari) ?></span>
              </td>
              <td class="c num"><?= $hariMengajar ?></td>
              <td class="c num"><b><?= (int) $s['seharusnya'] ?></b></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($seharusnyaBulan === []): ?>
            <tr><td colspan="3" class="c mut"><?= $bKosong
                ? 'Bulan ini seluruhnya sebelum aplikasi mulai digunakan (' . e(tanggal_id(tanggal_mulai())) . ').'
                : 'Jadwal belum tersedia.' ?></td></tr>
          <?php endif; ?>
        </tbody>
        <?php if ($seharusnyaBulan !== []): ?>
          <tfoot>
            <tr>
              <td>Jumlah seluruh guru</td>
              <td class="c">-</td>
              <td class="c num"><?= $totalSeharusnya ?></td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>
    <p class="hint">Contoh pembacaan: <b>Senin (3&times;4)</b> berarti guru mengajar 3 sesi setiap Senin dan
      ada 4 hari Senin pada bulan tersebut, sehingga 12 sesi pada hari Senin.</p>
  </div>
</div>

<?php page_bottom();
