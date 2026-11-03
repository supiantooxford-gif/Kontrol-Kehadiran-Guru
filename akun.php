<?php
/**
 * Akun Saya — profil singkat + ganti kata sandi.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';

$u = require_login();
$galat = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'profil') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $uname = trim((string) ($_POST['username'] ?? ''));
        if ($nama === '' || $uname === '') {
            $galat = 'Nama dan nama pengguna wajib diisi.';
        } elseif (preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $uname) !== 1) {
            $galat = 'Nama pengguna 3-40 karakter, hanya huruf, angka, titik, garis bawah, atau tanda hubung.';
        } elseif (val('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?', [$uname, (int) $u['id']], 0) > 0) {
            $galat = 'Nama pengguna itu sudah dipakai.';
        } else {
            q('UPDATE users SET nama = ?, username = ?, diubah = ? WHERE id = ?', [$nama, $uname, sekarang(), (int) $u['id']]);
            flash('Profil berhasil diperbarui.');
            redirect('akun.php');
        }
    }

    if ($aksi === 'sandi') {
        $lama = (string) ($_POST['sandi_lama'] ?? '');
        $baru = (string) ($_POST['sandi_baru'] ?? '');
        $ulang = (string) ($_POST['sandi_ulang'] ?? '');
        if (!password_verify($lama, (string) $u['sandi'])) {
            $galat = 'Kata sandi saat ini tidak sesuai.';
        } elseif (strlen($baru) < 8) {
            $galat = 'Kata sandi baru minimal 8 karakter.';
        } elseif ($baru !== $ulang) {
            $galat = 'Ulangi kata sandi baru dengan tepat.';
        } elseif ($baru === $lama) {
            $galat = 'Kata sandi baru harus berbeda dari kata sandi sekarang.';
        } else {
            q('UPDATE users SET sandi = ?, diubah = ? WHERE id = ?',
                [password_hash($baru, PASSWORD_DEFAULT), sekarang(), (int) $u['id']]);
            flash('Kata sandi berhasil diganti. Gunakan kata sandi baru pada masuk berikutnya.');
            redirect('akun.php');
        }
    }
}

page_top('Akun Saya', 'Profil dan keamanan akun kepala ' . strtolower(sebutan_lembaga()));

if ($galat !== ''): ?>
  <div class="alert alert-err"><?= ikon('alert', 16) ?><span><?= e($galat) ?></span></div>
<?php endif; ?>

<div class="grid g2">
  <div class="card">
    <div class="card-head">
      <div>
        <h2 class="card-title"><?= ikon('user', 18) ?> Profil Akun</h2>
        <p class="card-sub">Nama ini tampil pada sisi kiri aplikasi dan pada catatan pengisian kehadiran.</p>
      </div>
    </div>
    <form method="post" action="akun.php">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="profil">
      <div class="form-grid">
        <div>
          <label class="f-label" for="nama">Nama Lengkap</label>
          <input type="text" id="nama" name="nama" value="<?= e((string) $u['nama']) ?>" required maxlength="80">
        </div>
        <div>
          <label class="f-label" for="username">Nama Pengguna</label>
          <input type="text" id="username" name="username" value="<?= e((string) $u['username']) ?>" required maxlength="40">
        </div>
      </div>
      <div class="form-aksi" style="margin-top:14px">
        <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Profil</button>
      </div>
    </form>
    <p class="hint">Peran akun: <b><?= e(role_label($u)) ?></b> &middot; terakhir masuk
      <?= e($u['login_terakhir'] ? waktu_id((string) $u['login_terakhir']) : 'baru pertama kali') ?>.</p>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h2 class="card-title"><?= ikon('key', 18) ?> Ganti Kata Sandi</h2>
        <p class="card-sub">Minimal 8 karakter. Gunakan kombinasi huruf dan angka yang mudah Anda ingat.</p>
      </div>
    </div>
    <form method="post" action="akun.php" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="sandi">
      <div style="display:grid;gap:13px">
        <div>
          <label class="f-label" for="sandi_lama">Kata Sandi Sekarang</label>
          <input type="password" id="sandi_lama" name="sandi_lama" required>
        </div>
        <div>
          <label class="f-label" for="sandi_baru">Kata Sandi Baru</label>
          <input type="password" id="sandi_baru" name="sandi_baru" required minlength="8">
        </div>
        <div>
          <label class="f-label" for="sandi_ulang">Ulangi Kata Sandi Baru</label>
          <input type="password" id="sandi_ulang" name="sandi_ulang" required minlength="8">
        </div>
      </div>
      <div class="form-aksi" style="margin-top:14px">
        <button class="btn btn-primary" type="submit"><?= ikon('lock', 16) ?> Simpan Kata Sandi</button>
      </div>
    </form>
  </div>
</div>

<div class="card mt16">
  <div class="card-head" style="margin-bottom:10px">
    <div>
      <h2 class="card-title"><?= ikon('school', 18) ?> Identitas Lembaga</h2>
      <p class="card-sub">Dibaca otomatis dari aplikasi Website Sekolah — dipakai pada kop laporan cetak.</p>
    </div>
  </div>
  <div class="grid g4">
    <div><div class="xs mut">Nama <?= e(sebutan_lembaga()) ?></div><strong><?= e(nama_madrasah()) ?></strong></div>
    <div><div class="xs mut">NPSN / NSM</div><strong><?= e(sekolah_nilai('npsn', '-')) ?> / <?= e(sekolah_nilai('nsm', '-')) ?></strong></div>
    <div><div class="xs mut">Kepala</div><strong><?= e(kepala_nama() !== '' ? kepala_nama() : '-') ?></strong></div>
    <div><div class="xs mut">Tahun Ajaran</div><strong><?= e(periode_aktif()['ta'] !== '' ? periode_aktif()['ta'] : '-') ?> &middot; <?= e(periode_aktif()['semester'] !== '' ? periode_aktif()['semester'] : '-') ?></strong></div>
  </div>
  <p class="hint">Alamat: <?= e(sekolah_nilai('alamat', '-')) ?><?= alamat_wilayah() !== '' ? ' &middot; ' . e(alamat_wilayah()) : '' ?></p>
</div>

<?php page_bottom();
