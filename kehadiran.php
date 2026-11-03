<?php
/**
 * Input Kehadiran Guru Berdasarkan Jadwal.
 * Kepala madrasah memberi keterangan H / S / I / A / T pada setiap sesi mengajar
 * sesuai jadwal hari yang dipilih.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';

$u = require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_guard();
    $tanggal = tgl_ok($_POST['tanggal'] ?? '');
    if (!$tanggal) {
        flash('Tanggal yang dikirim tidak sah.', 'err');
        redirect('kehadiran.php');
    }
    if (sebelum_mulai($tanggal)) {
        flash('Kehadiran tanggal ' . tanggal_id($tanggal) . ' tidak dapat disimpan: aplikasi mulai '
            . 'digunakan pada ' . tanggal_id(tanggal_mulai()) . '.', 'err');
        redirect('kehadiran.php');
    }
    $status = is_array($_POST['status'] ?? null) ? $_POST['status'] : [];
    $catatan = is_array($_POST['catatan'] ?? null) ? $_POST['catatan'] : [];
    $hasil = simpan_kehadiran($tanggal, $status, $catatan, (int) $u['id']);

    $pesan = 'Kehadiran ' . tanggal_id($tanggal, true) . ' disimpan: ' . $hasil['tersimpan'] . ' sesi.';
    if ($hasil['dikosongkan'] > 0) {
        $pesan .= ' ' . $hasil['dikosongkan'] . ' sesi dikosongkan.';
    }
    flash($pesan);
    redirect('kehadiran.php?tanggal=' . $tanggal);
}

$tanggal = tgl_ok($_GET['tanggal'] ?? '') ?? date('Y-m-d');
if (sebelum_mulai($tanggal)) {
    // Aplikasi baru dipakai sejak tanggal_mulai(); tanggal sebelumnya tidak perlu diisi.
    flash('Aplikasi mulai digunakan pada ' . tanggal_id(tanggal_mulai())
        . ', jadi kehadiran sebelum tanggal itu tidak perlu diisi.', 'warn');
    $tanggal = date('Y-m-d') >= tanggal_mulai() ? date('Y-m-d') : tanggal_mulai();
}
$hari = hari_id($tanggal);
$jadwal = jadwal_tanggal($tanggal);
$tersimpan = kehadiran_tanggal($tanggal);
$progres = progres_tanggal($tanggal);

$sebelum = date('Y-m-d', strtotime('-1 day', strtotime($tanggal)));
$sesudah = date('Y-m-d', strtotime('+1 day', strtotime($tanggal)));

page_top('Input Kehadiran Guru',
    'Keterangan: H = Hadir, S = Sakit, I = Izin, A = Alpa, T = Terlambat');
?>

<div class="card">
  <div class="card-head">
    <div>
      <h2 class="card-title"><?= ikon('clipboard', 18) ?> Pilih Tanggal</h2>
      <p class="card-sub">Sesi yang tampil mengikuti jadwal mengajar pada hari yang dipilih.</p>
    </div>
    <div class="card-aksi">
      <a class="btn btn-ghost btn-sm" href="kehadiran.php?tanggal=<?= e($sebelum) ?>">&larr; Sebelumnya</a>
      <a class="btn btn-ghost btn-sm" href="kehadiran.php?tanggal=<?= e(date('Y-m-d')) ?>">Hari Ini</a>
      <a class="btn btn-ghost btn-sm" href="kehadiran.php?tanggal=<?= e($sesudah) ?>">Berikutnya &rarr;</a>
    </div>
  </div>

  <form method="get" action="kehadiran.php" class="form-bar">
    <div class="fb">
      <label class="f-label" for="tanggal">Tanggal</label>
      <input type="date" id="tanggal" name="tanggal" value="<?= e($tanggal) ?>">
    </div>
    <button class="btn btn-primary" type="submit"><?= ikon('calendar', 16) ?> Tampilkan Jadwal</button>
    <div class="fb" style="margin-left:auto">
      <span class="pil <?= $progres['belum'] === 0 && $progres['seharusnya'] > 0 ? 'pil-ok' : 'pil-warn' ?>">
        <?= $progres['terisi'] ?>/<?= $progres['seharusnya'] ?> sesi terisi
      </span>
    </div>
  </form>
  <p class="hint">Menurut jadwal Website <?= e(sebutan_lembaga()) ?>, hari <b><?= e($hari) ?></b>
    memiliki <?= count($jadwal) ?> sesi mengajar.
    <a href="jadwal.php?hari=<?= e($hari) ?>">Lihat jadwal <?= e($hari) ?></a>.</p>
</div>

<?php if ($jadwal === []): ?>
  <div class="card mt16">
    <div class="card-head" style="margin-bottom:6px">
      <div>
        <h2 class="card-title"><?= ikon('calendar', 18) ?> Tidak Ada Sesi Mengajar</h2>
        <p class="card-sub"><?= e($hari) ?>, <?= e(tanggal_id($tanggal)) ?> tidak memiliki jadwal mengajar
          (mis. hari libur atau jadwal madrasah kosong), sehingga tidak ada kehadiran yang perlu diisi.</p>
      </div>
    </div>
    <a class="btn btn-ghost btn-sm" href="laporan.php?mode=minggu&amp;tanggal=<?= e($tanggal) ?>">Lihat Laporan Mingguan</a>
  </div>
<?php else: ?>
  <form method="post" action="kehadiran.php" id="formKehadiran" class="mt16">
    <?= csrf_field() ?>
    <input type="hidden" name="tanggal" value="<?= e($tanggal) ?>">

    <div class="card">
      <div class="card-head">
        <div>
          <h2 class="card-title"><?= ikon('checklist', 18) ?> Kehadiran <?= e($hari) ?>, <?= e(tanggal_id($tanggal)) ?></h2>
          <p class="card-sub">Pilih keterangan pada tiap sesi. Pilihan <b>— belum</b> mengosongkan catatan sesi itu.</p>
        </div>
        <div class="card-aksi">
          <span class="pil" id="hitungTerisi"><?= $progres['terisi'] ?> dari <?= count($jadwal) ?> sesi terisi</span>
          <button class="btn btn-ghost btn-sm" type="button" data-tandai-hadir><?= ikon('check', 15) ?> Tandai Semua Hadir</button>
          <button class="btn btn-ghost btn-sm" type="button" data-kosongkan>Kosongkan</button>
        </div>
      </div>

      <div class="tbl-wrap">
        <table class="tbl sesi-tbl">
          <thead>
            <tr>
              <th class="kolom-sempit">Jam</th>
              <th>Waktu</th>
              <th>Kelas</th>
              <th>Mata Pelajaran</th>
              <th>Guru</th>
              <th class="kolom-sempit">Kehadiran</th>
              <th>Catatan (opsional)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jadwal as $j):
                $lama = $tersimpan[$j['id']] ?? null;
                $st = (string) ($lama['status'] ?? '');
                $ket = (string) ($lama['catatan'] ?? '');
            ?>
              <tr>
                <td class="num"><b><?= $j['jam_ke'] ?></b></td>
                <td class="nowrap"><?= e($j['waktu']) ?></td>
                <td class="nowrap"><?= e($j['kelas'] !== '' ? $j['kelas'] : '-') ?></td>
                <td><?= e($j['mapel'] !== '' ? $j['mapel'] : '-') ?></td>
                <td class="nama-guru"><?= e($j['guru']) ?></td>
                <td class="sel-status">
                  <div class="opsi-status" role="group" aria-label="Kehadiran <?= e($j['guru']) ?> jam <?= $j['jam_ke'] ?>">
                    <?php foreach (STATUS_KEHADIRAN as $kode => $label): ?>
                      <label class="pilih-<?= e(strtolower($kode)) ?>" title="<?= e($label) ?>">
                        <input type="radio" name="status[<?= $j['id'] ?>]" value="<?= e($kode) ?>"
                          <?= $st === $kode ? 'checked' : '' ?>>
                        <span><?= e($kode) ?></span>
                      </label>
                    <?php endforeach; ?>
                    <label class="pilih-x" title="Belum diisi / kosongkan">
                      <input type="radio" name="status[<?= $j['id'] ?>]" value="" <?= $st === '' ? 'checked' : '' ?>>
                      <span>&mdash;</span>
                    </label>
                  </div>
                </td>
                <td>
                  <input class="catatan-in" type="text" name="catatan[<?= $j['id'] ?>]"
                         value="<?= e($ket) ?>" maxlength="<?= CATATAN_MAKS ?>"
                         placeholder="mis. tugas luar / surat izin">
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="form-aksi" style="margin-top:16px">
        <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Kehadiran</button>
        <a class="btn btn-ghost" href="laporan.php?mode=minggu&amp;tanggal=<?= e($tanggal) ?>"><?= ikon('report', 16) ?> Laporan Minggu Ini</a>
        <span class="hint" style="margin:0 0 0 4px">Tersimpan atas nama <?= e(nama_pengguna($u)) ?>.</span>
      </div>
    </div>
  </form>
<?php endif; ?>

<?php page_bottom();
