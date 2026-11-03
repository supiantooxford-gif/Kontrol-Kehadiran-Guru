<?php
/**
 * Lapisan database aplikasi Kontrol Kehadiran Guru (SQLite via PDO).
 *
 * Tabel:
 *   users      -> akun kepala madrasah/sekolah (hanya peran `kepala`)
 *   kehadiran  -> kehadiran tiap sesi jadwal pada satu tanggal (H/S/I/A/T)
 *
 * ensure_schema() dijalankan pada setiap permintaan dan SELURUH isinya dibungkus SATU
 * transaksi (BEGIN IMMEDIATE) supaya statement idempoten tidak membayar satu fsync masing-masing.
 * Penanda `data/.schema_ok` bisa lebih baru daripada isi basis data, karena itu selain nomor versi
 * diperiksa juga SCHEMA_KOLOM_WAJIB + SCHEMA_TABEL_WAJIB (skema_terkini()).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

const SCHEMA_VERSION = '1';

const SCHEMA_KOLOM_WAJIB = [
    'users'     => ['foto', 'login_terakhir'],
    'kehadiran' => ['guru_key', 'catatan', 'dicatat_oleh'],
];

const SCHEMA_TABEL_WAJIB = ['users', 'kehadiran'];

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = dirname(DB_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');

    ensure_schema($pdo);

    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function val(string $sql, array $params = [], $default = null)
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? $default : $v;
}

function db_has_column(PDO $pdo, string $table, string $column): bool
{
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')') as $r) {
        if (strcasecmp((string) $r['name'], $column) === 0) {
            return true;
        }
    }
    return false;
}

function db_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!db_has_column($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }
}

function db_table_ada(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SELECT name FROM sqlite_master WHERE type = \'table\' AND name = ?');
    $st->execute([$table]);
    return $st->fetchColumn() !== false;
}

/** Penanda skema cocok DAN seluruh tabel/kolom wajib benar-benar ada. */
function skema_terkini(PDO $pdo): bool
{
    $penanda = @file_get_contents(dirname(DB_FILE) . '/.schema_ok');
    if (trim((string) $penanda) !== SCHEMA_VERSION) {
        return false;
    }
    foreach (SCHEMA_TABEL_WAJIB as $tabel) {
        if (!db_table_ada($pdo, $tabel)) {
            return false;
        }
    }
    foreach (SCHEMA_KOLOM_WAJIB as $tabel => $kolom) {
        foreach ((array) $kolom as $k) {
            if (!db_has_column($pdo, $tabel, $k)) {
                return false;
            }
        }
    }
    return true;
}

function ensure_schema(PDO $pdo): void
{
    if (skema_terkini($pdo)) {
        return;
    }

    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL DEFAULT '',
            username TEXT NOT NULL UNIQUE,
            sandi TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'kepala',
            aktif INTEGER NOT NULL DEFAULT 1,
            foto TEXT DEFAULT '',
            dibuat TEXT,
            diubah TEXT,
            login_terakhir TEXT
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kehadiran (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tanggal TEXT NOT NULL,
            hari TEXT NOT NULL DEFAULT '',
            jadwal_id INTEGER NOT NULL,
            guru TEXT NOT NULL DEFAULT '',
            guru_key TEXT NOT NULL DEFAULT '',
            kelas TEXT NOT NULL DEFAULT '',
            mapel TEXT NOT NULL DEFAULT '',
            jam_ke INTEGER NOT NULL DEFAULT 0,
            waktu TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'H',
            catatan TEXT NOT NULL DEFAULT '',
            dicatat_oleh INTEGER,
            dicatat_pada TEXT,
            diubah TEXT,
            UNIQUE (tanggal, jadwal_id)
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_kehadiran_tanggal ON kehadiran (tanggal)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_kehadiran_guru ON kehadiran (guru_key, tanggal)');

        // Kolom yang mungkin belum ada pada basis data lama (pemulihan diri).
        db_add_column($pdo, 'users', 'foto', "TEXT DEFAULT ''");
        db_add_column($pdo, 'users', 'login_terakhir', 'TEXT');
        db_add_column($pdo, 'kehadiran', 'guru_key', "TEXT NOT NULL DEFAULT ''");
        db_add_column($pdo, 'kehadiran', 'catatan', "TEXT NOT NULL DEFAULT ''");
        db_add_column($pdo, 'kehadiran', 'dicatat_oleh', 'INTEGER');

        // Akun bawaan HANYA dibuat bila belum ada satu akun pun.
        $jumlah = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($jumlah === 0) {
            $st = $pdo->prepare('INSERT INTO users (nama, username, sandi, role, aktif, dibuat)
                                 VALUES (?, ?, ?, ?, 1, ?)');
            $namaBawaan = function_exists('kepala_nama') && kepala_nama() !== '' ? kepala_nama() : 'Kepala Madrasah';
            $st->execute([
                $namaBawaan,
                'kepala',
                password_hash('kepala123', PASSWORD_DEFAULT),
                'kepala',
                sekarang(),
            ]);
        }

        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }

    @file_put_contents(dirname(DB_FILE) . '/.schema_ok', SCHEMA_VERSION);
}
