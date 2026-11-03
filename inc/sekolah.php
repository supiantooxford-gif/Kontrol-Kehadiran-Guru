<?php
/**
 * Jembatan BACA-SAJA ke basis data aplikasi Website Sekolah (sumber jadwal mengajar).
 *
 * Aplikasi ini TIDAK pernah menulis ke basis data tersebut: koneksi dibuka dengan
 * `PRAGMA query_only = ON`. Bila berkasnya tidak dapat dibaca, seluruh halaman yang
 * membutuhkannya menampilkan keterangan ramah (bukan galat PHP).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Koneksi baca-saja ke basis data Website Sekolah (null bila tidak tersedia). */
function sekolah_db(): ?PDO
{
    static $pdo = null;
    static $dicoba = false;
    if ($dicoba) {
        return $pdo;
    }
    $dicoba = true;

    if (!is_file(SEKOLAH_DB) || !is_readable(SEKOLAH_DB)) {
        return $pdo = null;
    }
    try {
        $pdo = new PDO('sqlite:' . SEKOLAH_DB, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA busy_timeout = 3000');
        $pdo->exec('PRAGMA query_only = ON');
        // Pastikan tabel jadwal benar-benar ada.
        $ada = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='kurikulum_jadwal'")->fetchColumn();
        if ((int) $ada === 0) {
            $pdo = null;
        }
    } catch (Throwable $ex) {
        $pdo = null;
    }
    return $pdo;
}

function sekolah_siap(): bool
{
    return sekolah_db() instanceof PDO;
}

/** Satu baris query pada basis data Website Sekolah. */
function sk_one(string $sql, array $params = []): ?array
{
    $pdo = sekolah_db();
    if (!$pdo) {
        return null;
    }
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    } catch (Throwable $ex) {
        return null;
    }
}

/** Banyak baris query pada basis data Website Sekolah. */
function sk_all(string $sql, array $params = []): array
{
    $pdo = sekolah_db();
    if (!$pdo) {
        return [];
    }
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $ex) {
        return [];
    }
}

// ------------------------------------------------------------------ identitas lembaga

/** Identitas madrasah/sekolah dari Website Sekolah (dengan cadangan aman). */
function sekolah_row(): array
{
    static $row = null;
    if (is_array($row)) {
        return $row;
    }
    $r = sk_one('SELECT * FROM sekolah ORDER BY id LIMIT 1');
    return $row = is_array($r) ? $r : [];
}

function sekolah_nilai(string $kunci, string $default = ''): string
{
    $r = sekolah_row();
    $v = trim((string) ($r[$kunci] ?? ''));
    // Nilai kosong / tanda hubung dianggap belum diisi.
    if ($v === '' || in_array($v, ['-', '–', '—', '.', '_'], true)) {
        return $default;
    }
    return $v;
}

function nama_madrasah(bool $denganCadangan = true): string
{
    return sekolah_nilai('nama', $denganCadangan ? 'Madrasah' : '');
}

function jenis_madrasah(): string
{
    return strtolower(sekolah_nilai('jenis', 'madrasah')) === 'umum' ? 'umum' : 'madrasah';
}

function is_madrasah(): bool
{
    return jenis_madrasah() === 'madrasah';
}

function sebutan_lembaga(): string
{
    return is_madrasah() ? 'Madrasah' : 'Sekolah';
}

function jabatan_kepala(): string
{
    return 'Kepala ' . sebutan_lembaga();
}

function kepala_nama(): string
{
    return sekolah_nilai('kepala', '');
}

function kepala_nip(): string
{
    return sekolah_nilai('nip_kepala', '');
}

function logo_madrasah(): string
{
    return sekolah_nilai('logo', '');
}

/** Baris alamat wilayah: "Desa …, Kec. …, Kab. …, Provinsi …". */
function alamat_wilayah(): string
{
    $bagian = [];
    $desa = sekolah_nilai('desa', '');
    $kec  = sekolah_nilai('kecamatan', '');
    $kab  = sekolah_nilai('kabupaten', '');
    $prov = sekolah_nilai('provinsi', '');
    if ($desa !== '') {
        $bagian[] = 'Desa/Kelurahan ' . $desa;
    }
    if ($kec !== '') {
        $bagian[] = 'Kec. ' . $kec;
    }
    if ($kab !== '') {
        $bagian[] = 'Kab. ' . $kab;
    }
    if ($prov !== '') {
        $bagian[] = $prov;
    }
    return implode(', ', $bagian);
}

function kecamatan_madrasah(): string
{
    return sekolah_nilai('kecamatan', '');
}

// ------------------------------------------------------------------ periode (tahun ajaran)

function periode_aktif(): array
{
    static $p = null;
    if (is_array($p)) {
        return $p;
    }
    $r = sk_one('SELECT ta, semester FROM periode WHERE aktif = 1 ORDER BY bawaan DESC, urutan, id LIMIT 1');
    if (!$r) {
        $r = sk_one('SELECT ta, semester FROM periode ORDER BY bawaan DESC, urutan, id LIMIT 1');
    }
    return $p = [
        'ta'       => trim((string) ($r['ta'] ?? '')),
        'semester' => trim((string) ($r['semester'] ?? '')),
    ];
}

// ------------------------------------------------------------------ jadwal mengajar

/**
 * Seluruh baris jadwal mengajar yang BENAR-BENAR mengajar (ada guru).
 * Baris kegiatan (Sholat Dhuha, Istirahat, Upacara tanpa guru) tidak dihitung sebagai sesi.
 */
function jadwal_semua(): array
{
    static $rows = null;
    if (is_array($rows)) {
        return $rows;
    }
    $data = sk_all("SELECT id, hari, waktu, jam_ke, kelas, mapel, guru, urutan
                    FROM kurikulum_jadwal
                    WHERE TRIM(COALESCE(guru, '')) <> ''
                    ORDER BY urutan, jam_ke, id");
    $urutHari = array_flip(HARI_LIST);
    foreach ($data as &$r) {
        $r['id']      = (int) $r['id'];
        $r['jam_ke']  = (int) $r['jam_ke'];
        $r['guru']    = trim((string) $r['guru']);
        $r['kelas']   = trim((string) $r['kelas']);
        $r['mapel']   = trim((string) $r['mapel']);
        $r['waktu']   = trim((string) $r['waktu']);
        $r['hari']    = trim((string) $r['hari']);
        $r['key']     = guru_key($r['guru']);
        $r['hari_ke'] = $urutHari[$r['hari']] ?? 99;
    }
    unset($r);
    usort($data, static function (array $a, array $b): int {
        return [$a['hari_ke'], $a['urutan'], $a['jam_ke'], $a['id']] <=> [$b['hari_ke'], $b['urutan'], $b['jam_ke'], $b['id']];
    });
    return $rows = $data;
}

function jadwal_by_id(int $id): ?array
{
    foreach (jadwal_semua() as $r) {
        if ($r['id'] === $id) {
            return $r;
        }
    }
    return null;
}

/** Jadwal pada satu hari (nama hari Indonesia). */
function jadwal_hari(string $hari): array
{
    return array_values(array_filter(jadwal_semua(), static fn(array $r): bool => $r['hari'] === $hari));
}

/** Jadwal pada satu tanggal (mengikuti nama harinya). */
function jadwal_tanggal(string $ymd): array
{
    return jadwal_hari(hari_id($ymd));
}

/** Jumlah sesi mengajar pada satu tanggal. */
function jadwal_tanggal_jumlah(string $ymd): int
{
    return count(jadwal_tanggal($ymd));
}

/**
 * Ringkasan jadwal per guru (kunci guru):
 *   nama, jumlah (sesi/minggu), per_hari[hari] => jumlah, mapel[], kelas[]
 */
function jadwal_per_guru(): array
{
    static $out = null;
    if (is_array($out)) {
        return $out;
    }
    $out = [];
    foreach (jadwal_semua() as $r) {
        $k = $r['key'];
        if ($k === '') {
            continue;
        }
        if (!isset($out[$k])) {
            $out[$k] = ['key' => $k, 'nama' => $r['guru'], 'jumlah' => 0, 'per_hari' => [], 'mapel' => [], 'kelas' => []];
        }
        $out[$k]['jumlah']++;
        $out[$k]['per_hari'][$r['hari']] = ($out[$k]['per_hari'][$r['hari']] ?? 0) + 1;
        if ($r['mapel'] !== '') {
            $out[$k]['mapel'][$r['mapel']] = true;
        }
        if ($r['kelas'] !== '') {
            $out[$k]['kelas'][$r['kelas']] = true;
        }
    }
    foreach ($out as $k => $g) {
        $out[$k]['mapel'] = array_keys($g['mapel']);
        $out[$k]['kelas'] = array_keys($g['kelas']);
    }
    ksort($out);
    uasort($out, static fn(array $a, array $b): int => strcasecmp($a['nama'], $b['nama']));
    return $out;
}

/** Jumlah hari <hari> di dalam rentang tanggal. */
function jumlah_hari_dalam_rentang(string $hari, string $dari, string $sampai): int
{
    $n = 0;
    foreach (tanggal_list($dari, $sampai) as $t) {
        if (hari_id($t) === $hari) {
            $n++;
        }
    }
    return $n;
}
