<?php
/**
 * Konfigurasi dasar aplikasi KONTROL KEHADIRAN GURU.
 *
 * Aplikasi dipakai HANYA oleh kepala madrasah/sekolah untuk mengontrol kehadiran guru
 * berdasarkan jadwal mengajar tiap hari. Jadwal mengajar TIDAK disalin: aplikasi membaca
 * langsung basis data aplikasi Website Sekolah (folder `web-sekolah/`) yang juga melayani
 * halaman publik https://ma-stm.sch.id/halaman/jadwal-mengajar — jadi jadwal di sini selalu
 * sama dengan jadwal di web sekolah, tanpa perlu impor ulang.
 *
 * Data kehadiran (H/S/I/A/T) disimpan pada berkas SQLite di dalam folder aplikasi ini.
 */
declare(strict_types=1);

define('APP_NAME', 'Kontrol Kehadiran Guru');
define('APP_SUB', 'Kontrol Kehadiran Guru Berdasarkan Jadwal Mengajar');
define('APP_VERSION', '1.1.0');

// Pembuat aplikasi (tampil pada footer & halaman masuk).
define('APP_AUTHOR', 'Supianto');
define('APP_AUTHOR_JABATAN', 'Kepala MA. Sirojuth Tholibiin Mootilango');

// Zona waktu lembaga: WITA (Asia/Makassar, UTC+8). Ubah bila lembaga memakai zona lain.
define('APP_ZONA', 'Asia/Makassar');
define('APP_ZONA_LABEL', 'WITA');

/** Berkas database aplikasi ini (data kehadiran + akun kepala madrasah). */
define('DB_FILE', __DIR__ . '/../data/kehadiran.sqlite');

/**
 * Berkas database aplikasi Website Sekolah (SUMBER JADWAL MENGAJAR).
 * Hanya DIBACA (mode baca-saja) — aplikasi ini tidak pernah menulis ke basis data tersebut.
 */
define('SEKOLAH_DB', dirname(__DIR__, 2) . '/web-sekolah/data/sekolah.sqlite');

/** Halaman publik jadwal mengajar pada Website Sekolah (untuk tautan "lihat sumber"). */
define('JADWAL_URL', 'https://ma-stm.sch.id/halaman/jadwal-mengajar');

/** Kode kehadiran guru: H, S, I, A, T. */
define('STATUS_KEHADIRAN', [
    'H' => 'Hadir',
    'S' => 'Sakit',
    'I' => 'Izin',
    'A' => 'Alpa',
    'T' => 'Terlambat',
]);

/**
 * TANGGAL APLIKASI MULAI DIPAKAI.
 * Permintaan pemilik: aplikasi baru digunakan pada 1 November 2026, sehingga rekap laporan
 * (mingguan maupun bulanan) TIDAK boleh mundur ke bulan Oktober. Seluruh perhitungan "seharusnya",
 * rekap per tanggal, dan pengisian kehadiran sebelum tanggal ini diabaikan/ditolak.
 * Ubah tanggal di sini bila pemakaian resmi dimulai pada tanggal lain.
 */
define('TANGGAL_MULAI', '2026-11-01');

/** Urutan hari mengikuti jadwal madrasah (dipakai untuk pengurutan & rekap). */
define('HARI_LIST', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']);

/** Batas panjang catatan per sesi kehadiran. */
define('CATATAN_MAKS', 200);

// Aplikasi diakses di bawah subfolder-nya sendiri (https://.../kontrol-kehadiran-guru/),
// jadi SEMUA tautan memakai path relatif (tanpa leading slash).
define('BASE', '');

date_default_timezone_set(APP_ZONA);
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Simpan berkas sesi di dalam folder aplikasi (folder sesi sistem bisa tidak writable).
    $sessDir = __DIR__ . '/../data/sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0775, true);
    }
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
    }
    session_start();
}
