<?php
/**
 * Layout aplikasi Kontrol Kehadiran Guru.
 *   page_top()/page_bottom()     — halaman kepala madrasah (sidebar + topbar)
 *   masuk_top()/masuk_bottom()   — halaman masuk
 *   cetak_top()/cetak_bottom()   — lembar cetak A4 (tanpa sidebar)
 */
declare(strict_types=1);

function ikon(string $name, int $size = 18): string
{
    $p = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/>',
        'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2.8h6V4"/><path d="M9 11h6M9 15h4"/>',
        'report'    => '<path d="M4 4h11l5 5v11H4z"/><path d="M8 14h8M8 17h5M8 11h3"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5S20 17 20 21"/>',
        'logout'    => '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M15 8l4 4-4 4M19 12H9"/>',
        'print'     => '<path d="M7 9V4h10v5"/><rect x="3" y="9" width="18" height="7" rx="2"/><path d="M7 16h10v4H7z"/>',
        'check'     => '<path d="M4 12.5l5 5L20 6.5"/>',
        'alert'     => '<path d="M12 3.5l9 16H3z"/><path d="M12 9v5M12 17.2v.1"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'list'      => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1.3"/><circle cx="3.5" cy="12" r="1.3"/><circle cx="3.5" cy="18" r="1.3"/>',
        'save'      => '<path d="M5 3h11l3 3v15H5z"/><path d="M9 3v6h6V3"/><path d="M8 21v-6h8v6"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'key'       => '<circle cx="8" cy="15" r="4"/><path d="M11 12l8-8M17 6l2 2M15 8l2 2"/>',
        'school'    => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M6 10v5c0 2 2.7 3.5 6 3.5s6-1.5 6-3.5v-5"/><path d="M21 8v6"/>',
        'link'      => '<path d="M10 13a4 4 0 0 0 5.7 0l2.3-2.3a4 4 0 0 0-5.7-5.7L11 6.3"/><path d="M14 11a4 4 0 0 0-5.7 0L6 13.3a4 4 0 0 0 5.7 5.7l1.3-1.3"/>',
        'trend'     => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'lock'      => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'filter'    => '<path d="M3 5h18l-7 8v6l-4-2v-4z"/>',
        'checklist' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 11l2.5 2.5L16 8"/><path d="M8 17h8"/>',
    ];
    $d = $p[$name] ?? $p['report'];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

function favicon_datauri(): string
{
    return "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'>"
        . "<rect width='64' height='64' rx='14' fill='%230d6b45'/>"
        . "<rect x='14' y='13' width='36' height='38' rx='6' fill='%23ffffff'/>"
        . "<path d='M21 25h8M21 33h8M21 41h8' stroke='%230d6b45' stroke-width='3.4' stroke-linecap='round'/>"
        . "<path d='M36 29l3.4 3.4L46 25' stroke='%23c8a13a' stroke-width='3.4' stroke-linecap='round' stroke-linejoin='round' fill='none'/></svg>";
}

/** Navigasi sidebar. */
function menu_sidebar(): array
{
    return [
        ['index.php', 'Dashboard', 'dashboard'],
        ['jadwal.php', 'Jadwal Mengajar', 'calendar'],
        ['kehadiran.php', 'Input Kehadiran', 'clipboard'],
        ['laporan.php', 'Laporan', 'report'],
        ['akun.php', 'Akun Saya', 'user'],
    ];
}

function subjudul_app(): string
{
    $n = trim(nama_madrasah(false));
    return ($n !== '' ? $n . '  &middot;  ' : '') . tanggal_id(date('Y-m-d'), true);
}

function page_top(string $judul, string $sub = '', string $aksi = ''): void
{
    $u = current_user();
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $flashes = flash_take();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<link rel="icon" href="<?= favicon_datauri() ?>">
</head>
<body>
<button class="nav-toggle" id="navToggle" aria-label="Buka menu"><?= ikon('menu', 20) ?></button>
<div class="scrim" id="scrim"></div>

<aside class="sidebar" id="sidebar">
  <div class="brand">
    <?php if (logo_madrasah() !== ''): ?>
      <img class="brand-logo" src="<?= e(logo_madrasah()) ?>" alt="Logo">
    <?php else: ?>
      <span class="brand-logo brand-ikon"><?= ikon('school', 22) ?></span>
    <?php endif; ?>
    <div class="brand-teks">
      <div class="brand-name"><?= e(APP_NAME) ?></div>
      <div class="brand-sub"><?= e(nama_madrasah()) ?></div>
    </div>
  </div>

  <nav class="nav">
    <div class="nav-sec">Menu Kepala <?= e(sebutan_lembaga()) ?></div>
    <?php foreach (menu_sidebar() as [$alamat, $label, $ikon]): ?>
      <a class="nav-item <?= $here === $alamat ? 'on' : '' ?>" href="<?= e($alamat) ?>">
        <?= ikon($ikon) ?><span><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-info">
    <div class="sidebar-jadwal">
      <?= ikon('calendar', 16) ?>
      <div>
        <strong><?= count(jadwal_semua()) ?> sesi mengajar</strong>
        <small><?= count(jadwal_per_guru()) ?> guru &middot; jadwal tersinkron</small>
      </div>
    </div>
    <div class="who">
      <span class="avatar"><?= e(strtoupper(substr(nama_pengguna($u), 0, 1))) ?></span>
      <div class="who-txt">
        <strong><?= e(nama_pengguna($u)) ?></strong>
        <small><?= e(role_label($u)) ?></small>
      </div>
    </div>
    <a class="btn btn-ghost btn-sm full" href="logout.php"><?= ikon('logout', 16) ?> Keluar</a>
  </div>
</aside>

<main class="main">
  <header class="topbar">
    <div class="topbar-kiri">
      <h1><?= e($judul) ?></h1>
      <p class="topbar-sub"><?= $sub !== '' ? $sub : subjudul_app() ?></p>
    </div>
    <div class="topbar-aksi"><?= $aksi ?></div>
  </header>

  <div class="content">
  <?php if (!sekolah_siap()): ?>
    <div class="alert alert-err">
      <?= ikon('alert', 16) ?>
      <span>Jadwal mengajar belum dapat dibaca dari aplikasi Website Sekolah. Pastikan berkas basis data
      Website Sekolah tersedia sehingga dashboard, jadwal, input kehadiran, dan laporan dapat digunakan.</span>
    </div>
  <?php endif; ?>

  <?php if (sandi_bawaan()): ?>
    <div class="alert alert-warn">
      <?= ikon('alert', 16) ?>
      <span>Akun masih memakai kata sandi bawaan (<b>kepala123</b>). Segera ganti melalui menu
      <a href="akun.php">Akun Saya</a>.</span>
    </div>
  <?php endif; ?>

  <?php foreach ($flashes as $f): ?>
    <div class="alert <?= $f['tipe'] === 'ok' ? 'alert-ok' : ($f['tipe'] === 'warn' ? 'alert-warn' : 'alert-err') ?>">
      <?= ikon($f['tipe'] === 'ok' ? 'check' : 'alert', 16) ?><span><?= e($f['pesan']) ?></span>
    </div>
  <?php endforeach; ?>
<?php
}

function page_bottom(): void
{
    ?>
  </div>
  <footer class="foot">
    <span><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> &middot; <?= e(nama_madrasah()) ?></span>
    <span>Dikembangkan oleh <?= e(APP_AUTHOR) ?> (<?= e(APP_AUTHOR_JABATAN) ?>)</span>
  </footer>
</main>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
<?php
}

// ------------------------------------------------------------------ halaman masuk

function masuk_top(string $judul): void
{
    $logo = logo_madrasah();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<link rel="icon" href="<?= favicon_datauri() ?>">
</head>
<body class="publik">
<div class="login-latar" aria-hidden="true"><span class="bulat b1"></span><span class="bulat b2"></span></div>

<div class="login-wrap">
  <div class="login-kartu">
    <div class="login-brand">
      <div class="login-logo">
        <?php if ($logo !== ''): ?>
          <img src="<?= e($logo) ?>" alt="Logo <?= e(nama_madrasah()) ?>">
        <?php else: ?>
          <?= ikon('school', 30) ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="login-nama"><?= e(APP_NAME) ?></div>
        <div class="login-sub"><?= e(nama_madrasah()) ?></div>
      </div>
    </div>
<?php
}

function masuk_bottom(): void
{
    ?>
    <div class="login-ket">
      <?= ikon('lock', 14) ?>
      <span>Hanya untuk akun <b>kepala <?= e(strtolower(sebutan_lembaga())) ?></b>.</span>
    </div>
    <p class="xs mut center mt16"><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> &middot; <?= e(APP_SUB) ?></p>
  </div>
</div>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
<?php
}

// ------------------------------------------------------------------ lembar cetak

function cetak_top(string $judul, string $kembali = 'laporan.php', bool $auto = false): void
{
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<link rel="icon" href="<?= favicon_datauri() ?>">
</head>
<body class="halaman-cetak">
<div class="cetak-bar no-print">
  <a class="btn btn-ghost btn-sm" href="<?= e($kembali) ?>"><?= ikon('dashboard', 16) ?> Kembali</a>
  <div class="cetak-bar-kanan">
    <span class="cetak-bar-judul"><?= e($judul) ?></span>
    <button class="btn btn-primary btn-sm" type="button" data-print><?= ikon('print', 16) ?> Cetak / Simpan PDF</button>
  </div>
</div>
<?php if ($auto): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
<div class="print-sheet">
<?php
}

function cetak_bottom(): void
{
    ?>
  <p class="cetak-ket no-print">Dicetak dari <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> pada <?= e(waktu_id(date('Y-m-d H:i:s'))) ?> <?= e(APP_ZONA_LABEL) ?>.</p>
</div>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
<?php
}
