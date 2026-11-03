<?php
/**
 * Fungsi bantu aplikasi Kontrol Kehadiran Guru.
 *
 * Berkas ini TIDAK memanggil db() saat di-include (dipanggil secara lazy).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// ------------------------------------------------------------------ dasar

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Tanggal valid (YYYY-MM-DD) atau null. */
function tgl_ok($v): ?string
{
    $v = trim((string) $v);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) !== 1) {
        return null;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $v));
    return checkdate($m, $d, $y) ? $v : null;
}

function bulan_id(int $n): string
{
    $b = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $b[$n] ?? '-';
}

function hari_id(?string $ymd): string
{
    if (!$ymd) {
        return '-';
    }
    $ts = strtotime((string) $ymd);
    if ($ts === false) {
        return '-';
    }
    $h = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
          'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
    return $h[date('l', $ts)] ?? '-';
}

function tanggal_id(?string $ymd, bool $denganHari = false): string
{
    if (!$ymd) {
        return '-';
    }
    $ts = strtotime((string) $ymd);
    if ($ts === false) {
        return (string) $ymd;
    }
    $t = date('j', $ts) . ' ' . bulan_id((int) date('n', $ts)) . ' ' . date('Y', $ts);
    return $denganHari ? hari_id($ymd) . ', ' . $t : $t;
}

function tanggal_singkat(?string $ymd): string
{
    if (!$ymd) {
        return '-';
    }
    $ts = strtotime((string) $ymd);
    return $ts === false ? (string) $ymd : date('j/n/Y', $ts) . ' (' . hari_id($ymd) . ')';
}

/** Waktu sekarang menurut zona waktu aplikasi: "2026-11-01 09:12:00". */
function sekarang(): string
{
    return date('Y-m-d H:i:s');
}

function waktu_id(?string $dt, bool $denganTanggal = true): string
{
    $dt = trim((string) $dt);
    if ($dt === '') {
        return '-';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return $dt;
    }
    $jam = date('H:i', $ts);
    return $denganTanggal ? tanggal_id(date('Y-m-d', $ts), true) . ', ' . $jam : $jam;
}

// ------------------------------------------------------------------ operasi tanggal/rentang

/** Daftar tanggal (Y-m-d) dari $dari sampai $sampai (inklusif). */
function tanggal_list(string $dari, string $sampai): array
{
    $out = [];
    $a = strtotime($dari);
    $b = strtotime($sampai);
    if ($a === false || $b === false || $a > $b) {
        return $out;
    }
    $batas = 0;
    for ($t = $a; $t <= $b && $batas < 400; $t = strtotime('+1 day', $t), $batas++) {
        $out[] = date('Y-m-d', $t);
    }
    return $out;
}

// ------------------------------------------------------------------ tanggal mulai pemakaian

/**
 * Tanggal aplikasi MULAI DIPAKAI (permintaan pemilik: aplikasi baru digunakan 1 November 2026).
 * Seluruh rekap & pengisian SEBELUM tanggal ini diabaikan, sehingga periode minggu/bulan yang
 * terlanjur mencakup hari-hari sebelumnya (mis. minggu 26 Okt - 1 Nov) tidak ikut dihitung.
 * Ubah nilainya di inc/config.php (konstanta TANGGAL_MULAI) bila pemakaian dimulai tanggal lain.
 */
function tanggal_mulai(): string
{
    return tgl_ok(TANGGAL_MULAI) ?? '1970-01-01';
}

/** Benar bila tanggal tersebut sebelum aplikasi mulai dipakai. */
function sebelum_mulai(string $tanggal): bool
{
    return tgl_ok($tanggal) !== null && $tanggal < tanggal_mulai();
}

/**
 * Potong rentang supaya tidak mendahului tanggal mulai pemakaian.
 * @return array{0:string,1:string,2:bool} [dari efektif, sampai, dipotong?]
 */
function rentang_efektif(string $dari, string $sampai): array
{
    $mulai = tanggal_mulai();
    $dipotong = $dari < $mulai;
    return [$dipotong ? $mulai : $dari, $sampai, $dipotong];
}

/** Benar bila seluruh rentang berada sebelum aplikasi dipakai (tidak ada yang bisa direkap). */
function rentang_kosong(string $dari, string $sampai): bool
{
    [$a, $b] = rentang_efektif($dari, $sampai);
    return $a > $b;
}

/** Label rentang: "1 November 2026" atau "1 November 2026 - 7 November 2026". */
function label_rentang(string $dari, string $sampai): string
{
    return $dari === $sampai ? tanggal_id($dari) : tanggal_id($dari) . ' - ' . tanggal_id($sampai);
}

/** Senin pada minggu yang memuat tanggal tersebut. */
function minggu_mulai(string $ymd): string
{
    $ts = strtotime($ymd);
    $dow = (int) date('N', $ts); // 1 = Senin ... 7 = Minggu
    return date('Y-m-d', strtotime('-' . ($dow - 1) . ' day', $ts));
}

/** Minggu (7 hari) yang memuat tanggal: [mulai, sampai, label]. */
function minggu_rentang(string $ymd): array
{
    $mulai = minggu_mulai($ymd);
    $sampai = date('Y-m-d', strtotime('+6 day', strtotime($mulai)));
    return [$mulai, $sampai, 'Minggu ' . tanggal_id($mulai) . ' - ' . tanggal_id($sampai)];
}

/** Bulan (YYYY-MM) yang sah atau null. */
function bulan_ok($v): ?string
{
    $v = trim((string) $v);
    return preg_match('/^\d{4}-\d{2}$/', $v) === 1 ? $v : null;
}

/** Rentang satu bulan: [awal, akhir, label]. */
function bulan_rentang(string $ym): array
{
    $awal = $ym . '-01';
    $akhir = date('Y-m-t', strtotime($awal));
    $label = bulan_id((int) date('n', strtotime($awal))) . ' ' . date('Y', strtotime($awal));
    return [$awal, $akhir, $label];
}

// ------------------------------------------------------------------ status kehadiran

function status_sah(): array
{
    return STATUS_KEHADIRAN;
}

function status_label(?string $k): string
{
    return STATUS_KEHADIRAN[(string) $k] ?? '-';
}

function status_singkat(?string $k): string
{
    $k = (string) $k;
    return isset(STATUS_KEHADIRAN[$k]) ? $k : '-';
}

/** Kelas CSS pil status (dipakai tabel & cetak). */
function status_kelas(?string $k): string
{
    $k = (string) $k;
    return in_array($k, ['H', 'S', 'I', 'A', 'T'], true) ? 'st-' . strtolower($k) : 'st-kosong';
}

/** Pil status berwarna (HTML). */
function status_pil(?string $k): string
{
    if ($k === null || $k === '' || !isset(STATUS_KEHADIRAN[$k])) {
        return '<span class="pil pil-kosong">Belum diisi</span>';
    }
    return '<span class="pil ' . e(status_kelas($k)) . '">' . e($k) . ' &middot; ' . e(status_label($k)) . '</span>';
}

// ------------------------------------------------------------------ flash & CSRF

function flash(string $pesan, string $tipe = 'ok'): void
{
    $_SESSION['flash'][] = ['pesan' => $pesan, 'tipe' => $tipe];
}

function flash_take(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($f) ? $f : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    $t = (string) ($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return $t !== '' && hash_equals(csrf_token(), $t);
}

function csrf_guard(): void
{
    if (csrf_ok()) {
        return;
    }
    http_response_code(419);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="id"><meta charset="utf-8"><title>419</title>'
       . '<body style="font-family:system-ui;padding:40px"><h1>419 - Keamanan formulir tidak valid</h1>'
       . '<p>Sesi Anda sudah berakhir atau halaman dibuka terlalu lama. Muat ulang halaman lalu coba lagi.</p></body></html>';
    exit;
}

function tolak403(string $pesan): void
{
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="id"><meta charset="utf-8"><title>403</title>'
       . '<body style="font-family:system-ui;padding:40px"><h1>403 - Tidak diizinkan</h1>'
       . '<p>' . e($pesan) . '</p><p><a href="index.php">Kembali ke Dashboard</a></p></body></html>';
    exit;
}

function tolak404(string $pesan, string $kembali = 'index.php'): void
{
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="id"><meta charset="utf-8"><title>404</title>'
        . '<body style="font-family:system-ui;padding:40px"><h1>404 - Data tidak ditemukan</h1>'
        . '<p>' . e($pesan) . '</p><p><a href="' . e($kembali) . '">Kembali</a></p></body></html>';
    exit;
}

// ------------------------------------------------------------------ pengguna

function current_user(): ?array
{
    static $u = null;
    static $sudah = false;
    if ($sudah) {
        return $u;
    }
    $sudah = true;
    $id = (int) ($_SESSION['uid'] ?? 0);
    if ($id <= 0) {
        return $u = null;
    }
    $row = one('SELECT * FROM users WHERE id = ? AND aktif = 1', [$id]);
    if (!$row) {
        unset($_SESSION['uid']);
        return $u = null;
    }
    return $u = $row;
}

function role_label(?array $u = null): string
{
    $u = $u ?? current_user();
    return (string) ($u['role'] ?? 'kepala') === 'kepala' ? 'Kepala Madrasah/Sekolah' : 'Pengguna';
}

/** Seluruh halaman aplikasi ini hanya untuk kepala madrasah/sekolah yang sudah masuk. */
function require_login(): array
{
    $u = current_user();
    if (!$u) {
        unset($_SESSION['uid']);
        $tujuan = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $next = $tujuan !== '' ? $tujuan . ($qs !== '' ? '?' . $qs : '') : 'index.php';
        redirect('login.php?next=' . rawurlencode($next));
    }
    if ((string) $u['role'] !== 'kepala') {
        tolak403('Aplikasi ini hanya dapat dipakai oleh akun kepala madrasah/sekolah.');
    }
    // Nama bawaan akun diselaraskan sekali dengan nama kepala pada identitas lembaga
    // (dapat diubah sendiri oleh pengguna melalui menu Akun Saya).
    $u['nama'] = selaras_nama_kepala($u);
    return $u;
}

/**
 * Bila akun masih memakai nama bawaan "Kepala Madrasah", selaraskan dengan nama kepala
 * pada identitas lembaga (dibaca dari Website Sekolah). Nama yang sudah disunting pengguna
 * tidak pernah ditimpa.
 */
function selaras_nama_kepala(array $u): string
{
    $nama = trim((string) ($u['nama'] ?? ''));
    if ($nama !== 'Kepala Madrasah' || !function_exists('kepala_nama')) {
        return $nama;
    }
    $kepala = kepala_nama();
    if ($kepala === '') {
        return $nama;
    }
    q('UPDATE users SET nama = ?, diubah = ? WHERE id = ?', [$kepala, sekarang(), (int) $u['id']]);
    return $kepala;
}

function sandi_bawaan(): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    return password_verify('kepala123', (string) $u['sandi']);
}

function nama_pengguna(?array $u = null): string
{
    $u = $u ?? current_user();
    return trim((string) ($u['nama'] ?? '')) !== '' ? (string) $u['nama'] : (string) ($u['username'] ?? 'Kepala Madrasah');
}

// ------------------------------------------------------------------ kunci nama guru

/**
 * Kunci pembanding nama guru: huruf kecil, tanda baca dibuang, spasi dirapatkan.
 * Dipakai supaya satu guru yang namanya ditulis sedikit berbeda pada jadwal tetap terhitung satu.
 */
function guru_key(string $nama): string
{
    $s = strtolower(trim($nama));
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? '';
    return trim(preg_replace('/\s+/', ' ', $s) ?? '');
}

// ------------------------------------------------------------------ bantu tampilan

function persen(float $bagian, float $total, int $desimal = 0): string
{
    if ($total <= 0) {
        return '-';
    }
    return number_format($bagian / $total * 100, $desimal, ',', '.') . '%';
}

/** Jarak antara dua tanggal dalam hari (boleh negatif). */
function selisih_hari(string $a, string $b): int
{
    $ta = strtotime($a);
    $tb = strtotime($b);
    if ($ta === false || $tb === false) {
        return 0;
    }
    return (int) round(($tb - $ta) / 86400);
}
