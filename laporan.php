<?php
/**
 * Laporan Kehadiran Guru — per minggu dan per bulan.
 * Kepala madrasah dapat mencetak / menyimpan sebagai PDF.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';

require_login();

$mode = (string) ($_GET['mode'] ?? 'minggu');
$mode = $mode === 'bulan' ? 'bulan' : 'minggu';

$tanggalPilih = tgl_ok($_GET['tanggal'] ?? '') ?? date('Y-m-d');
$bulanPilih = bulan_ok($_GET['bulan'] ?? '') ?? date('Y-m');

if ($mode === 'minggu') {
    [$dari, $sampai, $label] = minggu_rentang($tanggalPilih);
} else {
    [$dari, $sampai, $label] = bulan_rentang($bulanPilih);
}

// Rekap TIDAK boleh mundur ke sebelum aplikasi dipakai (permintaan pemilik: mulai 1 November 2026).
[$dariEf, $sampaiEf, $dipotong] = rentang_efektif($dari, $sampai);
$tidakAdaData = $dariEf > $sampaiEf;

if ($mode === 'minggu') {
    $judulPeriode = 'Minggu ' . ($tidakAdaData ? label_rentang($dari, $sampai) : label_rentang($dariEf, $sampaiEf));
    $tautanCetak = 'laporan_cetak.php?mode=minggu&tanggal=' . rawurlencode($tanggalPilih);
    $tautanCetakRinci = $tautanCetak . '&rincian=1';
} else {
    $judulPeriode = 'Bulan ' . $label;
    $tautanCetak = 'laporan_cetak.php?mode=bulan&bulan=' . rawurlencode($bulanPilih);
    $tautanCetakRinci = $tautanCetak . '&rincian=1';
}

$rekap = rekap_guru($dari, $sampai);
$total = rekap_total($rekap);
$harian = rekap_harian($dari, $sampai);

page_top('Laporan Kehadiran Guru', 'Rekap per guru untuk dicetak atau disimpan sebagai PDF',
    '<a class="btn btn-ghost btn-sm" href="' . e($tautanCetak) . '" target="_blank" rel="noopener">'
    . ikon('print', 15) . ' Cetak Ringkasan</a>'
    . '<a class="btn btn-primary btn-sm" href="' . e($tautanCetakRinci) . '" target="_blank" rel="noopener">'
    . ikon('print', 15) . ' Cetak + Rincian</a>');
?>

<?php if ($tidakAdaData): ?>
  <div class="alert alert-warn">
    <?= ikon('alert', 16) ?>
    <span>Periode <b><?= e($label) ?></b> seluruhnya berada sebelum aplikasi mulai digunakan
      (<b><?= e(tanggal_id(tanggal_mulai())) ?></b>), sehingga belum ada data yang dapat direkap.</span>
  </div>
<?php elseif ($dipotong): ?>
  <div class="alert alert-ok">
    <?= ikon('check', 16) ?>
    <span>Rekap dihitung mulai <b><?= e(tanggal_id(tanggal_mulai())) ?></b> — tanggal aplikasi mulai
      digunakan. Hari-hari sebelumnya tidak ikut dihitung.</span>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <div>
      <h2 class="card-title"><?= ikon('filter', 18) ?> Pilih Periode Laporan</h2>
      <p class="card-sub">Minggu dihitung Senin–Minggu; bulan mengikuti bulan kalender.</p>
    </div>
    <div class="chip-list">
      <a class="chip <?= $mode === 'minggu' ? 'on' : '' ?>" href="laporan.php?mode=minggu&amp;tanggal=<?= e($tanggalPilih) ?>">Per Minggu</a>
      <a class="chip <?= $mode === 'bulan' ? 'on' : '' ?>" href="laporan.php?mode=bulan&amp;bulan=<?= e($bulanPilih) ?>">Per Bulan</a>
    </div>
  </div>

  <?php if ($mode === 'minggu'): ?>
    <form method="get" action="laporan.php" class="form-bar">
      <input type="hidden" name="mode" value="minggu">
      <div class="fb">
        <label class="f-label" for="tanggal">Tanggal dalam minggu</label>
        <input type="date" id="tanggal" name="tanggal" value="<?= e($tanggalPilih) ?>">
      </div>
      <button class="btn btn-primary" type="submit"><?= ikon('calendar', 16) ?> Tampilkan</button>
      <div class="fb" style="margin-left:auto">
        <span class="pill"><?= e($judulPeriode) ?></span>
      </div>
    </form>
  <?php else: ?>
    <form method="get" action="laporan.php" class="form-bar">
      <input type="hidden" name="mode" value="bulan">
      <div class="fb">
        <label class="f-label" for="bulan">Bulan</label>
        <input type="month" id="bulan" name="bulan" value="<?= e($bulanPilih) ?>">
      </div>
      <button class="btn btn-primary" type="submit"><?= ikon('calendar', 16) ?> Tampilkan</button>
      <div class="fb" style="margin-left:auto">
        <span class="pill"><?= e($judulPeriode) ?></span>
      </div>
    </form>
  <?php endif; ?>
  <p class="hint">Rentang tanggal rekap:
    <?php if ($tidakAdaData): ?>
      <b>&mdash;</b> <span class="mut">(seluruh periode sebelum <?= e(tanggal_id(tanggal_mulai())) ?>)</span>
    <?php else: ?>
      <b><?= e(tanggal_id($dariEf, true)) ?></b> sampai
      <b><?= e(tanggal_id($sampaiEf, true)) ?></b> &middot;
      <?= count(array_filter($harian, static fn(array $r): bool => $r['seharusnya'] > 0)) ?> hari efektif mengajar.
      <?php if ($dipotong): ?>
        &middot; dihitung mulai <b><?= e(tanggal_id(tanggal_mulai())) ?></b> (tanggal aplikasi mulai digunakan).
      <?php endif; ?>
    <?php endif; ?>
  </p>
</div>

<div class="grid g4 mt16">
  <div class="stat">
    <span class="stat-ikon"><?= ikon('list', 21) ?></span>
    <div>
      <div class="stat-num"><?= $total['seharusnya'] ?></div>
      <div class="stat-label">Sesi seharusnya</div>
      <div class="stat-ket"><?= count($rekap) ?> guru</div>
    </div>
  </div>
  <div class="stat">
    <span class="stat-ikon"><?= ikon('check', 21) ?></span>
    <div>
      <div class="stat-num"><?= $total['H'] ?></div>
      <div class="stat-label">Hadir (H)</div>
      <div class="stat-ket"><?= e(persen($total['H'], $total['seharusnya'], 1)) ?> dari seharusnya</div>
    </div>
  </div>
  <div class="stat">
    <span class="stat-ikon emas"><?= ikon('alert', 21) ?></span>
    <div>
      <div class="stat-num"><?= $total['S'] + $total['I'] + $total['A'] + $total['T'] ?></div>
      <div class="stat-label">Tidak hadir</div>
      <div class="stat-ket">S <?= $total['S'] ?> &middot; I <?= $total['I'] ?> &middot; A <?= $total['A'] ?> &middot; T <?= $total['T'] ?></div>
    </div>
  </div>
  <div class="stat">
    <span class="stat-ikon emas"><?= ikon('clipboard', 21) ?></span>
    <div>
      <div class="stat-num"><?= $total['belum'] ?></div>
      <div class="stat-label">Belum diisi</div>
      <div class="stat-ket"><?= $total['terisi'] ?> sesi sudah terisi</div>
    </div>
  </div>
</div>

<div class="card mt16">
  <div class="card-head">
    <div>
      <h2 class="card-title"><?= ikon('report', 18) ?> Rekap Kehadiran per Guru — <?= e($judulPeriode) ?></h2>
      <p class="card-sub">Persentase kehadiran = jumlah H dibagi jumlah sesi seharusnya.</p>
    </div>
    <div class="card-aksi">
      <a class="btn btn-primary btn-sm" href="<?= e($tautanCetakRinci) ?>" target="_blank" rel="noopener"><?= ikon('print', 15) ?> Cetak Laporan</a>
    </div>
  </div>

  <?php if ($rekap === []): ?>
    <p class="mut mb0"><?= $tidakAdaData
        ? 'Periode ini seluruhnya sebelum aplikasi mulai digunakan (' . e(tanggal_id(tanggal_mulai())) . ').'
        : 'Belum ada jadwal/kehadiran pada periode ini.' ?></p>
  <?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th class="kolom-sempit">No</th>
            <th>Nama Guru</th>
            <th class="c">Seharusnya</th>
            <th class="c">H</th>
            <th class="c">S</th>
            <th class="c">I</th>
            <th class="c">A</th>
            <th class="c">T</th>
            <th class="c">Belum</th>
            <th class="c">Hadir (%)</th>
          </tr>
        </thead>
        <tbody>
          <?php $no = 0; foreach ($rekap as $r): $no++; ?>
            <tr>
              <td class="c num"><?= $no ?></td>
              <td class="nama-guru"><?= e($r['nama']) ?></td>
              <td class="c num"><?= $r['seharusnya'] ?></td>
              <td class="c num"><?= $r['H'] ?></td>
              <td class="c num"><?= $r['S'] ?></td>
              <td class="c num"><?= $r['I'] ?></td>
              <td class="c num"><?= $r['A'] ?></td>
              <td class="c num"><?= $r['T'] ?></td>
              <td class="c num"><?= $r['belum'] ?></td>
              <td class="c nowrap">
                <span class="progress sempit"><span style="width:<?= (int) round((float) $r['persen']) ?>%"></span></span>
                <b style="margin-left:7px"><?= e(persen($r['H'], $r['seharusnya'], 1)) ?></b>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2">Jumlah</td>
            <td class="c num"><?= $total['seharusnya'] ?></td>
            <td class="c num"><?= $total['H'] ?></td>
            <td class="c num"><?= $total['S'] ?></td>
            <td class="c num"><?= $total['I'] ?></td>
            <td class="c num"><?= $total['A'] ?></td>
            <td class="c num"><?= $total['T'] ?></td>
            <td class="c num"><?= $total['belum'] ?></td>
            <td class="c num"><?= e(persen($total['H'], $total['seharusnya'], 1)) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="hint">Keterangan: <b>H</b> Hadir &middot; <b>S</b> Sakit &middot; <b>I</b> Izin &middot;
      <b>A</b> Alpa &middot; <b>T</b> Terlambat.</p>
  <?php endif; ?>
</div>

<div class="card mt16">
  <div class="card-head">
    <div>
      <h2 class="card-title"><?= ikon('calendar', 18) ?> Rekap per Tanggal</h2>
      <p class="card-sub">Memudahkan melihat tanggal mana yang belum lengkap diisi.</p>
    </div>
  </div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th class="kolom-sempit">No</th>
          <th>Hari / Tanggal</th>
          <th class="c">Seharusnya</th>
          <th class="c">H</th>
          <th class="c">S</th>
          <th class="c">I</th>
          <th class="c">A</th>
          <th class="c">T</th>
          <th class="c">Belum</th>
          <th class="c">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php $no = 0; foreach ($harian as $h): $no++;
            $tanpaJadwal = (int) $h['seharusnya'] === 0;
        ?>
          <tr class="<?= $tanpaJadwal ? 'tr-minggu' : '' ?>">
            <td class="c num"><?= $no ?></td>
            <td class="nowrap"><?= e($h['hari']) ?>, <?= e(tanggal_id($h['tanggal'])) ?></td>
            <td class="c num"><?= $tanpaJadwal ? '<span class="mut">&middot;</span>' : (int) $h['seharusnya'] ?></td>
            <td class="c num"><?= $h['H'] ?></td>
            <td class="c num"><?= $h['S'] ?></td>
            <td class="c num"><?= $h['I'] ?></td>
            <td class="c num"><?= $h['A'] ?></td>
            <td class="c num"><?= $h['T'] ?></td>
            <td class="c num"><?= $tanpaJadwal ? '<span class="mut">&middot;</span>' : (int) $h['belum'] ?></td>
            <td class="c nowrap">
              <?php if (!$tanpaJadwal): ?>
                <?php if ((int) $h['belum'] > 0): ?>
                  <a class="btn btn-soft btn-sm" href="kehadiran.php?tanggal=<?= e($h['tanggal']) ?>">Isi</a>
                <?php else: ?>
                  <span class="pil st-h">Lengkap</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="mut">&middot;</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="2">Jumlah</td>
          <td class="c num"><?= $total['seharusnya'] ?></td>
          <td class="c num"><?= $total['H'] ?></td>
          <td class="c num"><?= $total['S'] ?></td>
          <td class="c num"><?= $total['I'] ?></td>
          <td class="c num"><?= $total['A'] ?></td>
          <td class="c num"><?= $total['T'] ?></td>
          <td class="c num"><?= $total['belum'] ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<div class="card mt16">
  <div class="card-head">
    <div>
      <h2 class="card-title"><?= ikon('clipboard', 18) ?> Rincian Kehadiran per Guru</h2>
      <p class="card-sub">Buka nama guru untuk melihat keterangan tiap sesi mengajar.</p>
    </div>
  </div>
  <?php foreach ($rekap as $r):
      $sesi = rincian_sesi($dari, $sampai, $r['key']);
      if ($sesi === []) {
          continue;
      }
  ?>
    <details class="rincian">
      <summary>
        <?= e($r['nama']) ?>
        <span class="pil pil-ok" style="margin-left:auto">H <?= $r['H'] ?> / <?= $r['seharusnya'] ?></span>
        <span class="xs mut"><?= count($sesi) ?> sesi</span>
      </summary>
      <div class="rincian-isi">
        <div class="tbl-wrap">
          <table class="tbl">
            <thead>
              <tr><th>Tanggal</th><th class="c">Jam</th><th>Waktu</th><th>Kelas</th><th>Mapel</th><th class="c">Kehadiran</th><th>Catatan</th></tr>
            </thead>
            <tbody>
              <?php foreach ($sesi as $s): ?>
                <tr>
                  <td class="nowrap"><?= e($s['hari']) ?>, <?= e(tanggal_id($s['tanggal'])) ?></td>
                  <td class="c num"><?= (int) $s['jam_ke'] ?></td>
                  <td class="nowrap"><?= e($s['waktu']) ?></td>
                  <td><?= e($s['kelas'] !== '' ? $s['kelas'] : '-') ?></td>
                  <td><?= e($s['mapel'] !== '' ? $s['mapel'] : '-') ?></td>
                  <td class="c"><?= status_pil($s['status']) ?></td>
                  <td><?= e($s['catatan'] !== '' ? $s['catatan'] : '-') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </details>
  <?php endforeach; ?>
  <?php if ($rekap === []): ?><p class="mut mb0">Belum ada data.</p><?php endif; ?>
</div>

<?php page_bottom();
