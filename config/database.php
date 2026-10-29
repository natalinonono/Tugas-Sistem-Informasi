<?php
/**
 * Konfigurasi Database PDO Multi-Driver (MySQL Primary + Supabase PostgreSQL + SQLite Fallback)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */

require_once __DIR__ . '/supabase.php';

// 1. Parsing DATABASE_URL jika diberikan oleh Cloud Hosting (Vercel/Railway/Supabase)
$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
$parsedUrl = $dbUrl ? parse_url($dbUrl) : [];

// Konfigurasi MySQL Lokal XAMPP (Prioritas Utama untuk Praktikum / Tugas)
define('DB_HOST', $parsedUrl['host'] ?? (getenv('DB_HOST') ?: '127.0.0.1'));
define('DB_PORT', (string)($parsedUrl['port'] ?? (getenv('DB_PORT') ?: '3306')));
define('DB_USER', $parsedUrl['user'] ?? (getenv('DB_USER') ?: 'root'));
define('DB_PASS', isset($parsedUrl['pass']) ? $parsedUrl['pass'] : (getenv('DB_PASS') !== false && getenv('DB_PASS') !== null ? (string)getenv('DB_PASS') : ''));
define('DB_NAME', isset($parsedUrl['path']) ? trim($parsedUrl['path'], '/') : (getenv('DB_NAME') ?: 'sidinar_db'));

// Konfigurasi Supabase PostgreSQL (Direct / Session Pooler)
// Isi SUPABASE_DB_PASS dengan password database Supabase Anda untuk mengaktifkan koneksi langsung
define('SUPABASE_DB_HOST', getenv('SUPABASE_DB_HOST') ?: 'db.tkclthikptiotudrtkty.supabase.co');
define('SUPABASE_DB_PORT', (string)(getenv('SUPABASE_DB_PORT') ?: '5432'));
define('SUPABASE_DB_NAME', getenv('SUPABASE_DB_NAME') ?: 'postgres');
define('SUPABASE_DB_USER', getenv('SUPABASE_DB_USER') ?: 'postgres');
define('SUPABASE_DB_PASS', getenv('SUPABASE_DB_PASS') ?: '');

// File SQLite lokal (fallback darurat jika belum terhubung ke MySQL/Supabase)
$sqlitePath = getenv('VERCEL') ? '/tmp/sidinar.sqlite' : (__DIR__ . '/../database/sidinar.sqlite');
define('SQLITE_FILE', $sqlitePath);

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // 1. Coba koneksi ke Supabase PostgreSQL jika SUPABASE_DB_PASS diisi & pdo_pgsql aktif
    if (!empty(SUPABASE_DB_PASS) && extension_loaded('pdo_pgsql')) {
        try {
            $pgDsn = "pgsql:host=" . SUPABASE_DB_HOST . ";port=" . SUPABASE_DB_PORT . ";dbname=" . SUPABASE_DB_NAME . ";sslmode=require";
            $pdo = new PDO($pgDsn, SUPABASE_DB_USER, SUPABASE_DB_PASS, $options);
            return $pdo;
        } catch (Exception $pgEx) {
            // Lanjut ke MySQL lokal jika Supabase PostgreSQL belum terhubung
        }
    }

    // 2. Coba koneksi ke MySQL Lokal (XAMPP / Server Lokal)
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        // Verifikasi apakah tabel sudah terbentuk di database lokal
        try {
            $pdo->query("SELECT 1 FROM profiles LIMIT 1");
        } catch (Exception $notExist) {
            init_mysql_tables($pdo);
        }

        return $pdo;
    } catch (PDOException $e) {
        // Jika database belum ada di MySQL lokal, coba create otomatis
        if (DB_HOST === '127.0.0.1' || DB_HOST === 'localhost') {
            try {
                $dsnInit = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
                $tmpPdo = new PDO($dsnInit, DB_USER, DB_PASS, $options);
                $tmpPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                $tmpPdo->exec("USE `" . DB_NAME . "`;");
                init_mysql_tables($tmpPdo);
                $pdo = $tmpPdo;
                return $pdo;
            } catch (Exception $e2) {
                // Lanjut ke fallback SQLite jika MySQL lokal mati
            }
        }
    }

    // 3. Fallback Terakhir ke SQLite Lokal
    try {
        $dbDir = dirname(SQLITE_FILE);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        $isNew = !file_exists(SQLITE_FILE);
        $pdo = new PDO("sqlite:" . SQLITE_FILE, null, null, $options);
        if ($isNew) {
            init_sqlite_tables($pdo);
        }
        return $pdo;
    } catch (Exception $sqe) {
        die("Koneksi Database Gagal: " . htmlspecialchars($sqe->getMessage()));
    }
}

function init_mysql_tables(PDO $pdo): void {
    $sqlFile = __DIR__ . '/../database.sql';
    if (!file_exists($sqlFile)) return;

    $sql = file_get_contents($sqlFile);
    // Hapus CREATE DATABASE dan USE agar aman dieksekusi di database cloud manapun
    $cleanSql = preg_replace('/CREATE DATABASE[^;]+;/i', '', $sql);
    $cleanSql = preg_replace('/USE `?[a-zA-Z0-9_\-]+`?;/i', '', $cleanSql);

    // Jalankan setiap statement
    $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            try {
                $pdo->exec($stmt);
            } catch (Exception $ex) {
                // Ignore jika sudah ada
            }
        }
    }
}

function init_sqlite_tables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            nama_lengkap TEXT NOT NULL,
            nama_panggilan TEXT NOT NULL,
            no_telepon TEXT NOT NULL,
            asal_wilayah_teks TEXT NOT NULL,
            jenjang TEXT NOT NULL DEFAULT 'SMA/K',
            role TEXT NOT NULL DEFAULT 'anggota',
            status TEXT NOT NULL DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS mass_schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            judul_misa TEXT NOT NULL,
            kategori TEXT NOT NULL DEFAULT 'mingguan',
            tanggal_misa DATE NOT NULL,
            jam_mulai TIME NOT NULL,
            kuota_maksimal INTEGER NOT NULL DEFAULT 12,
            minggu_ke INTEGER NOT NULL DEFAULT 1,
            tahun INTEGER NOT NULL DEFAULT 2026,
            tahun_liturgi TEXT DEFAULT 'Tahun B / Liturgi Biasa',
            warna_liturgi TEXT NOT NULL DEFAULT 'Hijau',
            nama_selebran TEXT DEFAULT '',
            qr_token TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS bookings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            schedule_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            kategori_misa TEXT NOT NULL DEFAULT 'mingguan',
            minggu_ke INTEGER NOT NULL DEFAULT 1,
            tahun INTEGER NOT NULL DEFAULT 2026,
            posisi_tugas TEXT NOT NULL DEFAULT 'Petugas Altar',
            status_kehadiran TEXT NOT NULL DEFAULT 'terdaftar',
            waktu_checkin DATETIME,
            wa_reminder_sent INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (schedule_id) REFERENCES mass_schedules (id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES profiles (id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            judul TEXT NOT NULL,
            isi TEXT NOT NULL,
            is_pinned INTEGER NOT NULL DEFAULT 0,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES profiles (id) ON DELETE SET NULL
        );
    ");

    $hash = '$2y$10$rlcRkXHQJWbezY1XmPHXKu0WARiAcs/r5JQHtUme4fvhIlUMpjIUS'; // password123
    $today = date('Y-m-d');

    // Seed Akun
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO profiles (id, email, password, nama_lengkap, nama_panggilan, no_telepon, asal_wilayah_teks, jenjang, role, status) VALUES 
        (1, 'pengurus@sidinar.org', ?, 'Pengurus Sakristi SPMR', 'Admin', '085643985341', 'Jebres, Wilayah St. Ignatius', 'Umum', 'pengurus', 'aktif'),
        (2, 'misdinar@sidinar.org', ?, 'Stefanus Natalino Putra Pratama', 'Inok', '081228788912', 'Jebres Barat, C2 Santa Rosa De Lima', 'SMA/K', 'anggota', 'aktif'),
        (3, 'calon@sidinar.org', ?, 'Yohanes Pembaptis', 'Yohan', '081399887766', 'Kentingan, St. Yohanes', 'SMP', 'anggota', 'pending')");
    $stmt->execute([$hash, $hash, $hash]);

    // Seed Jadwal Misa
    $stmt2 = $pdo->prepare("INSERT OR IGNORE INTO mass_schedules (id, judul_misa, kategori, tanggal_misa, jam_mulai, kuota_maksimal, minggu_ke, tahun, tahun_liturgi, warna_liturgi, nama_selebran, qr_token) VALUES 
        (1, 'Misa Harian Pagi', 'harian', ?, '05:30:00', 6, ?, 2026, 'Tahun B / Liturgi Biasa', 'Hijau', '', ?),
        (2, 'Misa Mingguan Sabtu Sore', 'mingguan', ?, '17:00:00', 12, ?, 2026, 'Tahun B / Liturgi Biasa', 'Hijau', '', ?),
        (3, 'Misa Mingguan Minggu Pagi I', 'mingguan', ?, '06:30:00', 12, ?, 2026, 'Tahun B / Liturgi Biasa', 'Putih', '', ?),
        (4, 'Misa Mingguan Minggu Pagi II', 'mingguan', ?, '08:30:00', 12, ?, 2026, 'Tahun B / Liturgi Biasa', 'Hijau', '', ?)");
    
    $week = (int)date('W');
    $sabtu = date('Y-m-d', strtotime('next Saturday'));
    $minggu = date('Y-m-d', strtotime('next Sunday'));
    $stmt2->execute([
        $today, $week, md5("misa-1-" . $today),
        $sabtu, $week, md5("misa-2-" . $sabtu),
        $minggu, $week, md5("misa-3-" . $minggu),
        $minggu, $week, md5("misa-4-" . $minggu)
    ]);

    // Seed Booking
    $pdo->exec("INSERT OR IGNORE INTO bookings (id, schedule_id, user_id, kategori_misa, minggu_ke, tahun, posisi_tugas, status_kehadiran, wa_reminder_sent) VALUES 
        (1, 1, 2, 'harian', 44, 2026, 'Petugas Altar', 'terdaftar', 1)");

    // Seed Warta
    $pdo->exec("INSERT OR IGNORE INTO announcements (id, judul, isi, is_pinned, created_by) VALUES 
        (1, 'Tata Tertib Sakristi & Ketentuan Presensi Tepat Waktu', 'Berkah Dalem seluruh Putra-Putri Altar SPMR Purbowardayan. Diingatkan kembali bahwa untuk Misa Harian presensi dibuka pukul 05.00 WIB (H-30 menit) dan batas tepat waktu adalah pukul 05.10 WIB (H-20 menit). Untuk Misa Mingguan, presensi wajib dilakukan paling lambat H-30 menit sebelum perayaan Ekaristi dimulai dalam kondisi sudah mengenakan jubah dan singel rapi.', 1, 1),
        (2, 'Latihan Rutin Petugas Hari Raya Mendatang', 'Diberitahukan kepada seluruh misdinar bahwa gladi bersih dan latihan tugas liturgi (petugas dupa dan pembawa salib) akan dilaksanakan hari Sabtu pukul 15.00 WIB di gedung gereja.', 0, 1)");
}
