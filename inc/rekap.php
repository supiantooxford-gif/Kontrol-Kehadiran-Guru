<?php
/**
 * Perhitungan kehadiran guru: penyimpanan H/S/I/A/T, rekap mingguan & bulanan,
 * serta daftar sesi yang belum diisi.
 *
 * Sesi "seharusnya" SELALU dihitung dari jadwal mengajar yang berlaku di Website Sekolah
 * (baris jadwal yang punya guru). Data kehadiran menyimpan SNAPSHOT nama guru/kelas/mapel,
 * sehingga riwayat tetap terbaca walau baris jadwal kelak diubah/dihapus.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sekolah.php';

// ------------------------------------------------------------------ baca kehadiran

/** Kehadiran satu tanggal: [jadwal_id => baris]. */
function kehadiran_tanggal(string $ymd): array
{
    $out = [];
    foreach (all('SELECT * FROM kehadiran WHERE tanggal = ?', [$ymd]) as $r) {
        $out[(int) $r['jadwal_id']] = $r;
    }
    return $out;
}

/** Kehadiran pada rentang tanggal: ["tanggal|jadwal_id" => baris]. */
function kehadiran_rentang(string $dari, string $sampai): array
{
    $out = [];
    foreach (all('SELECT * FROM kehadiran WHERE tanggal BETWEEN ? AND ?', [$dari, $sampai]) as $r) {
        $out[$r['tanggal'] . '|' . (int) $r['jadwal_id']] = $r;
    }
    return $out;
}

// ------------------------------------------------------------------ simpan kehadiran

/**
 * Simpan kehadiran satu tanggal.
 *
 * @param array $status  [jadwal_id => 'H'|'S'|'I'|'A'|'T'|''] (kosong = hapus catatan)
 * @param array $catatan [jadwal_id => teks]
 * @return array ['tersimpan'=>int, 'dikosongkan'=>int]
 */
function simpan_kehadiran(string $ymd, array $status, array $catatan, int $userId): array
{
    $jadwal = [];
    foreach (jadwal_tanggal($ymd) as $j) {
        $jadwal[$j['id']] = $j;
    }
    if ($jadwal === []) {
        return ['tersimpan' => 0, 'dikosongkan' => 0];
    }

    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    $tersimpan = 0;
    $dikosongkan = 0;
    try {
        foreach ($status as $jid => $st) {
            $jid = (int) $jid;
            if (!isset($jadwal[$jid])) {
                continue; // sesi di luar jadwal tanggal itu diabaikan (anti-IDOR)
            }
            $st = strtoupper(trim((string) $st));
            $ket = trim((string) ($catatan[$jid] ?? ''));
            if ($ket !== '' && function_exists('mb_substr')) {
                $ket = mb_substr($ket, 0, CATATAN_MAKS);
            } elseif ($ket !== '') {
                $ket = substr($ket, 0, CATATAN_MAKS);
            }

            if (!isset(STATUS_KEHADIRAN[$st])) {
                // Status kosong/tidak sah = hapus catatan sesi ini.
                $jml = q('DELETE FROM kehadiran WHERE tanggal = ? AND jadwal_id = ?', [$ymd, $jid])->rowCount();
                $dikosongkan += $jml;
                continue;
            }

            $j = $jadwal[$jid];
            $lama = one('SELECT id FROM kehadiran WHERE tanggal = ? AND jadwal_id = ?', [$ymd, $jid]);
            if ($lama) {
                q('UPDATE kehadiran SET status = ?, catatan = ?, guru = ?, guru_key = ?, kelas = ?, mapel = ?,
                       jam_ke = ?, waktu = ?, hari = ?, dicatat_oleh = ?, diubah = ?
                   WHERE id = ?',
                    [$st, $ket, $j['guru'], $j['key'], $j['kelas'], $j['mapel'], $j['jam_ke'], $j['waktu'],
                     $j['hari'], $userId, sekarang(), (int) $lama['id']]);
            } else {
                q('INSERT INTO kehadiran (tanggal, hari, jadwal_id, guru, guru_key, kelas, mapel, jam_ke, waktu,
                       status, catatan, dicatat_oleh, dicatat_pada)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$ymd, $j['hari'], $jid, $j['guru'], $j['key'], $j['kelas'], $j['mapel'], $j['jam_ke'],
                     $j['waktu'], $st, $ket, $userId, sekarang()]);
            }
            $tersimpan++;
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }

    return ['tersimpan' => $tersimpan, 'dikosongkan' => $dikosongkan];
}

// ------------------------------------------------------------------ rekap

/** Jumlah sesi seharusnya per guru (kunci guru) pada rentang tanggal. */
function seharusnya_per_guru(string $dari, string $sampai): array
{
    [$dari, $sampai] = rentang_efektif($dari, $sampai);
    $hariCount = [];
    foreach (tanggal_list($dari, $sampai) as $t) {
        $h = hari_id($t);
        $hariCount[$h] = ($hariCount[$h] ?? 0) + 1;
    }
    $out = [];
    foreach (jadwal_per_guru() as $k => $g) {
        $n = 0;
        foreach ($hariCount as $h => $c) {
            $n += ($g['per_hari'][$h] ?? 0) * $c;
        }
        if ($n > 0) {
            $out[$k] = ['nama' => $g['nama'], 'seharusnya' => $n];
        }
    }
    return $out;
}

/** Jumlah kehadiran per guru per status pada rentang tanggal. */
function kehadiran_per_guru(string $dari, string $sampai): array
{
    [$dari, $sampai] = rentang_efektif($dari, $sampai);
    $out = [];
    $rows = all('SELECT guru_key, guru, status, COUNT(*) AS n
                 FROM kehadiran WHERE tanggal BETWEEN ? AND ?
                 GROUP BY guru_key, status', [$dari, $sampai]);
    foreach ($rows as $r) {
        $k = (string) $r['guru_key'];
        $out[$k] = $out[$k] ?? [
            'nama' => (string) $r['guru'], 'H' => 0, 'S' => 0, 'I' => 0, 'A' => 0, 'T' => 0, 'terisi' => 0,
        ];
        $st = (string) $r['status'];
        if (isset(STATUS_KEHADIRAN[$st])) {
            $out[$k][$st] += (int) $r['n'];
            $out[$k]['terisi'] += (int) $r['n'];
        }
    }
    return $out;
}

/**
 * Rekap per guru dalam satu rentang: seharusnya, terisi, H/S/I/A/T, belum, persen hadir.
 * Guru yang punya jadwal tetapi belum pernah diisi tetap muncul (belum = seharusnya).
 */
function rekap_guru(string $dari, string $sampai): array
{
    $seharusnya = seharusnya_per_guru($dari, $sampai);
    $kehadiran  = kehadiran_per_guru($dari, $sampai);

    $keys = array_unique(array_merge(array_keys($seharusnya), array_keys($kehadiran)));
    $rows = [];
    foreach ($keys as $k) {
        $s = $seharusnya[$k]['seharusnya'] ?? 0;
        $h = $kehadiran[$k] ?? ['nama' => $seharusnya[$k]['nama'] ?? $k, 'H' => 0, 'S' => 0, 'I' => 0, 'A' => 0, 'T' => 0, 'terisi' => 0];
        $terisi = (int) $h['terisi'];
        $nama = trim((string) $h['nama']) !== '' ? (string) $h['nama'] : (string) ($seharusnya[$k]['nama'] ?? $k);
        $belum = max(0, $s - $terisi);
        $rows[] = [
            'key'        => $k,
            'nama'       => $nama,
            'seharusnya' => $s,
            'terisi'     => $terisi,
            'H'          => (int) $h['H'],
            'S'          => (int) $h['S'],
            'I'          => (int) $h['I'],
            'A'          => (int) $h['A'],
            'T'          => (int) $h['T'],
            'belum'      => $belum,
            'persen'     => $s > 0 ? $h['H'] / $s * 100 : 0.0,
        ];
    }
    usort($rows, static fn(array $a, array $b): int => strcasecmp($a['nama'], $b['nama']));
    return $rows;
}

/** Jumlahkan baris rekap menjadi satu ringkasan. */
function rekap_total(array $rows): array
{
    $t = ['seharusnya' => 0, 'terisi' => 0, 'H' => 0, 'S' => 0, 'I' => 0, 'A' => 0, 'T' => 0, 'belum' => 0, 'persen' => 0.0];
    foreach ($rows as $r) {
        foreach (['seharusnya', 'terisi', 'H', 'S', 'I', 'A', 'T', 'belum'] as $f) {
            $t[$f] += (int) $r[$f];
        }
    }
    $t['persen'] = $t['seharusnya'] > 0 ? $t['H'] / $t['seharusnya'] * 100 : 0.0;
    return $t;
}

/**
 * Rekap per TANGGAL dalam rentang: sesi seharusnya, terisi, H/S/I/A/T, belum.
 * Tanggal tanpa jadwal tetap dicantumkan dengan tanda "-" (mis. hari Jumat).
 */
function rekap_harian(string $dari, string $sampai): array
{
    [$dari, $sampai] = rentang_efektif($dari, $sampai);
    $rows = [];
    $adaKehadiran = [];
    foreach (all('SELECT tanggal, status, COUNT(*) AS n FROM kehadiran WHERE tanggal BETWEEN ? AND ?
                  GROUP BY tanggal, status', [$dari, $sampai]) as $r) {
        $t = (string) $r['tanggal'];
        $adaKehadiran[$t] = $adaKehadiran[$t] ?? ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0, 'T' => 0, 'terisi' => 0];
        $st = (string) $r['status'];
        if (isset(STATUS_KEHADIRAN[$st])) {
            $adaKehadiran[$t][$st] += (int) $r['n'];
            $adaKehadiran[$t]['terisi'] += (int) $r['n'];
        }
    }
    foreach (tanggal_list($dari, $sampai) as $t) {
        $seharusnya = jadwal_tanggal_jumlah($t);
        $h = $adaKehadiran[$t] ?? ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0, 'T' => 0, 'terisi' => 0];
        $rows[] = [
            'tanggal'    => $t,
            'hari'       => hari_id($t),
            'seharusnya' => $seharusnya,
            'terisi'     => (int) $h['terisi'],
            'H'          => (int) $h['H'],
            'S'          => (int) $h['S'],
            'I'          => (int) $h['I'],
            'A'          => (int) $h['A'],
            'T'          => (int) $h['T'],
            'belum'      => max(0, $seharusnya - (int) $h['terisi']),
        ];
    }
    return $rows;
}

/**
 * Rincian tiap sesi pada rentang tanggal (dipakai halaman laporan & lembar cetak).
 * Urut: tanggal, jam ke, kelas, guru. Status null = belum diisi.
 */
function rincian_sesi(string $dari, string $sampai, string $guruKey = ''): array
{
    [$dari, $sampai] = rentang_efektif($dari, $sampai);
    $kehadiran = kehadiran_rentang($dari, $sampai);
    $rows = [];
    foreach (tanggal_list($dari, $sampai) as $t) {
        foreach (jadwal_tanggal($t) as $j) {
            if ($guruKey !== '' && $j['key'] !== $guruKey) {
                continue;
            }
            $k = $kehadiran[$t . '|' . $j['id']] ?? null;
            $rows[] = [
                'tanggal' => $t,
                'hari'    => $j['hari'],
                'jam_ke'  => $j['jam_ke'],
                'waktu'   => $j['waktu'],
                'kelas'   => $j['kelas'],
                'mapel'   => $j['mapel'],
                'guru'    => $j['guru'],
                'key'     => $j['key'],
                'status'  => $k['status'] ?? null,
                'catatan' => (string) ($k['catatan'] ?? ''),
            ];
        }
    }
    return $rows;
}

// ------------------------------------------------------------------ progres pengisian

/** Progres pengisian satu tanggal: ['seharusnya'=>n, 'terisi'=>n, 'belum'=>n]. */
function progres_tanggal(string $ymd): array
{
    if (sebelum_mulai($ymd)) {
        return ['seharusnya' => 0, 'terisi' => 0, 'belum' => 0];
    }
    $seharusnya = jadwal_tanggal_jumlah($ymd);
    $terisi = (int) val('SELECT COUNT(*) FROM kehadiran WHERE tanggal = ?', [$ymd], 0);
    return ['seharusnya' => $seharusnya, 'terisi' => $terisi, 'belum' => max(0, $seharusnya - $terisi)];
}

/** Tanggal dalam rentang yang pengisiannya belum lengkap (dan punya sesi mengajar). */
function tanggal_belum_lengkap(string $dari, string $sampai): array
{
    [$dari, $sampai] = rentang_efektif($dari, $sampai);
    $out = [];
    $jumlah = [];
    foreach (all('SELECT tanggal, COUNT(*) AS n FROM kehadiran WHERE tanggal BETWEEN ? AND ?
                  GROUP BY tanggal', [$dari, $sampai]) as $r) {
        $jumlah[(string) $r['tanggal']] = (int) $r['n'];
    }
    foreach (tanggal_list($dari, $sampai) as $t) {
        $s = jadwal_tanggal_jumlah($t);
        if ($s === 0) {
            continue;
        }
        $isi = $jumlah[$t] ?? 0;
        if ($isi < $s) {
            $out[] = ['tanggal' => $t, 'hari' => hari_id($t), 'seharusnya' => $s, 'terisi' => $isi, 'belum' => $s - $isi];
        }
    }
    return $out;
}

/** Ringkasan dashboard satu rentang: total seharusnya/terisi/status + persen. */
function ringkas_rentang(string $dari, string $sampai): array
{
    $rows = rekap_guru($dari, $sampai);
    return rekap_total($rows);
}

/** Rekap per guru untuk grafik dashboard (persen hadir). */
function grafik_guru_bulan(string $ym): array
{
    [$dari, $sampai] = bulan_rentang($ym);
    if (rentang_kosong($dari, $sampai)) {
        return [];
    }
    $rows = rekap_guru($dari, $sampai);
    $out = [];
    foreach ($rows as $r) {
        if ((int) $r['seharusnya'] === 0) {
            continue;
        }
        $out[] = ['nama' => $r['nama'], 'persen' => round((float) $r['persen'], 1), 'seharusnya' => (int) $r['seharusnya']];
    }
    return $out;
}
