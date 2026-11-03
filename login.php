<?php
/**
 * Halaman masuk — HANYA untuk akun kepala madrasah/sekolah.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';

if (current_user()) {
    redirect('index.php');
}

$galat = '';
$username = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_guard();
    $username = trim((string) ($_POST['username'] ?? ''));
    $sandi    = (string) ($_POST['sandi'] ?? '');

    if ($username === '' || $sandi === '') {
        $galat = 'Nama pengguna dan kata sandi wajib diisi.';
    } else {
        $u = one('SELECT * FROM users WHERE username = ?', [$username]);
        if (!$u) {
            $galat = 'Nama pengguna tidak terdaftar.';
        } elseif (!password_verify($sandi, (string) $u['sandi'])) {
            $galat = 'Kata sandi salah. Silakan coba lagi.';
        } elseif ((int) $u['aktif'] !== 1) {
            $galat = 'Akun ini dinonaktifkan. Hubungi pengelola aplikasi.';
        } elseif ((string) $u['role'] !== 'kepala') {
            $galat = 'Aplikasi ini hanya dapat dipakai oleh akun kepala madrasah/sekolah.';
        } else {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $u['id'];
            q('UPDATE users SET login_terakhir = ? WHERE id = ?', [sekarang(), (int) $u['id']]);
            $u['nama'] = selaras_nama_kepala($u);
            flash('Selamat datang, ' . nama_pengguna($u) . '.');
            $next = (string) ($_POST['next'] ?? 'index.php');
            if (preg_match('/^[a-z0-9_\-]+\.php(\?[^\s]*)?$/i', $next) !== 1) {
                $next = 'index.php';
            }
            redirect($next);
        }
    }
}

$next = (string) ($_GET['next'] ?? 'index.php');
if (preg_match('/^[a-z0-9_\-]+\.php(\?[^\s]*)?$/i', $next) !== 1) {
    $next = 'index.php';
}

masuk_top('Masuk Aplikasi');
?>
    <h1 style="font-size:1.15rem;margin-bottom:4px">Masuk Aplikasi</h1>
    <p class="mut" style="font-size:.84rem;margin-bottom:16px">
      Silakan masuk dengan akun kepala <?= e(strtolower(sebutan_lembaga())) ?> untuk mengontrol kehadiran guru.
    </p>

    <?php if ($galat !== ''): ?>
      <div class="alert alert-err"><?= ikon('alert', 16) ?><span><?= e($galat) ?></span></div>
    <?php endif; ?>

    <form method="post" action="login.php" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="mb0" style="margin-bottom:13px">
        <label class="f-label" for="username">Nama Pengguna</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus
               placeholder="mis. kepala">
      </div>
      <div style="margin-bottom:16px">
        <label class="f-label" for="sandi">Kata Sandi</label>
        <input type="password" id="sandi" name="sandi" required placeholder="Kata sandi akun Anda">
      </div>
      <button class="btn btn-primary full" type="submit"><?= ikon('lock', 16) ?> Masuk</button>
    </form>
<?php
masuk_bottom();
