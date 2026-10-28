-- ========================================================
-- SISTEM INFORMASI & PRESENSI PUTRA-PUTRI ALTAR (SIDINAR REGINA)
-- Database Script untuk phpMyAdmin / MySQL
-- ========================================================

CREATE DATABASE IF NOT EXISTS `sidinar_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sidinar_db`;

-- --------------------------------------------------------
-- 1. Tabel Profil Pengguna (profiles)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(255) NOT NULL,
  `nama_panggilan` VARCHAR(100) NOT NULL,
  `no_telepon` VARCHAR(50) NOT NULL,
  `asal_wilayah_teks` VARCHAR(255) NOT NULL,
  `jenjang` ENUM('SD', 'SMP', 'SMA/K', 'Mahasiswa', 'Umum') NOT NULL DEFAULT 'SMA/K',
  `role` ENUM('anggota', 'pengurus') NOT NULL DEFAULT 'anggota',
  `status` ENUM('pending', 'aktif', 'nonaktif') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. Tabel Jadwal Misa (mass_schedules)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mass_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `judul_misa` VARCHAR(255) NOT NULL,
  `kategori` ENUM('harian', 'mingguan') NOT NULL DEFAULT 'mingguan',
  `tanggal_misa` DATE NOT NULL,
  `jam_mulai` TIME NOT NULL,
  `kuota_maksimal` INT NOT NULL DEFAULT 12,
  `minggu_ke` INT NOT NULL DEFAULT 1,
  `tahun` INT NOT NULL DEFAULT 2026,
  `tahun_liturgi` VARCHAR(100) DEFAULT 'Tahun B / Liturgi Biasa',
  `warna_liturgi` ENUM('Hijau', 'Putih', 'Merah', 'Ungu', 'Merah Muda') NOT NULL DEFAULT 'Hijau',
  `nama_selebran` VARCHAR(255) DEFAULT 'Romo Paroki',
  `qr_token` VARCHAR(100) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Tabel Penugasan & Presensi (bookings)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `schedule_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `kategori_misa` ENUM('harian', 'mingguan') NOT NULL DEFAULT 'mingguan',
  `minggu_ke` INT NOT NULL DEFAULT 1,
  `tahun` INT NOT NULL DEFAULT 2026,
  `posisi_tugas` VARCHAR(100) NOT NULL DEFAULT 'Petugas Altar',
  `status_kehadiran` ENUM('terdaftar', 'hadir', 'terlambat', 'izin', 'alpa') NOT NULL DEFAULT 'terdaftar',
  `waktu_checkin` DATETIME NULL,
  `wa_reminder_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_schedule` (`schedule_id`),
  INDEX `idx_user` (`user_id`),
  CONSTRAINT `fk_bookings_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `mass_schedules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Tabel Warta Sakristi / Pengumuman (announcements)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `judul` VARCHAR(255) NOT NULL,
  `isi` TEXT NOT NULL,
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_creator` (`created_by`),
  CONSTRAINT `fk_announcements_creator` FOREIGN KEY (`created_by`) REFERENCES `profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- SEED DATA AWAL (Default Accounts & Schedules)
-- Password default semua akun: password123
-- Hash Bcrypt: $2y$10$wT8K5q7aF...
-- --------------------------------------------------------

INSERT INTO `profiles` (`id`, `email`, `password`, `nama_lengkap`, `nama_panggilan`, `no_telepon`, `asal_wilayah_teks`, `jenjang`, `role`, `status`) VALUES
(1, 'pengurus@sidinar.org', '$2y$10$rlcRkXHQJWbezY1XmPHXKu0WARiAcs/r5JQHtUme4fvhIlUMpjIUS', 'Pengurus Sakristi SPMR', 'Admin', '085643985341', 'Jebres, Wilayah St. Ignatius', 'Umum', 'pengurus', 'aktif'),
(2, 'misdinar@sidinar.org', '$2y$10$rlcRkXHQJWbezY1XmPHXKu0WARiAcs/r5JQHtUme4fvhIlUMpjIUS', 'Stefanus Natalino Putra Pratama', 'Inok', '081228788912', 'Jebres Barat, C2 Santa Rosa De Lima', 'SMA/K', 'anggota', 'aktif'),
(3, 'calon@sidinar.org', '$2y$10$rlcRkXHQJWbezY1XmPHXKu0WARiAcs/r5JQHtUme4fvhIlUMpjIUS', 'Yohanes Pembaptis', 'Yohan', '081399887766', 'Kentingan, St. Yohanes', 'SMP', 'anggota', 'pending')
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `mass_schedules` (`id`, `judul_misa`, `kategori`, `tanggal_misa`, `jam_mulai`, `kuota_maksimal`, `minggu_ke`, `tahun`, `tahun_liturgi`, `warna_liturgi`, `nama_selebran`, `qr_token`) VALUES
(1, 'Misa Harian Pagi', 'harian', CURDATE(), '05:30:00', 6, WEEK(CURDATE()), YEAR(CURDATE()), 'Tahun B / Liturgi Biasa', 'Hijau', 'Romo Robertus Budi Prastowo, Pr', MD5(CONCAT('misa-1-', CURDATE()))),
(2, 'Misa Mingguan Sabtu Sore', 'mingguan', DATE_ADD(CURDATE(), INTERVAL (6 - WEEKDAY(CURDATE())) DAY), '17:00:00', 12, WEEK(CURDATE()), YEAR(CURDATE()), 'Tahun B / Liturgi Biasa', 'Hijau', 'Romo Paroki Purbowardayan', MD5(CONCAT('misa-2-', CURDATE()))),
(3, 'Misa Mingguan Minggu Pagi I', 'mingguan', DATE_ADD(CURDATE(), INTERVAL (7 - WEEKDAY(CURDATE())) DAY), '06:30:00', 12, WEEK(CURDATE()), YEAR(CURDATE()), 'Tahun B / Liturgi Biasa', 'Putih', 'Romo Vikaris Paroki', MD5(CONCAT('misa-3-', CURDATE()))),
(4, 'Misa Mingguan Minggu Pagi II', 'mingguan', DATE_ADD(CURDATE(), INTERVAL (7 - WEEKDAY(CURDATE())) DAY), '08:30:00', 12, WEEK(CURDATE()), YEAR(CURDATE()), 'Tahun B / Liturgi Biasa', 'Hijau', 'Romo Robertus Budi Prastowo, Pr', MD5(CONCAT('misa-4-', CURDATE())))
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `bookings` (`id`, `schedule_id`, `user_id`, `kategori_misa`, `minggu_ke`, `tahun`, `posisi_tugas`, `status_kehadiran`, `waktu_checkin`, `wa_reminder_sent`) VALUES
(1, 1, 2, 'harian', WEEK(CURDATE()), YEAR(CURDATE()), 'Petugas Altar', 'terdaftar', NULL, 1)
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `announcements` (`id`, `judul`, `isi`, `is_pinned`, `created_by`) VALUES
(1, 'Tata Tertib Sakristi & Ketentuan Presensi Tepat Waktu', 'Berkah Dalem seluruh Putra-Putri Altar SPMR Purbowardayan. Diingatkan kembali bahwa untuk Misa Harian presensi dibuka pukul 05.00 WIB (H-30 menit) dan batas tepat waktu adalah pukul 05.10 WIB (H-20 menit). Untuk Misa Mingguan, presensi wajib dilakukan paling lambat H-30 menit sebelum perayaan Ekaristi dimulai dalam kondisi sudah mengenakan jubah dan singel rapi.', 1, 1),
(2, 'Latihan Rutin Petugas Hari Raya Mendatang', 'Diberitahukan kepada seluruh misdinar bahwa gladi bersih dan latihan tugas liturgi (petugas dupa dan pembawa salib) akan dilaksanakan hari Sabtu pukul 15.00 WIB di gedung gereja.', 0, 1)
ON DUPLICATE KEY UPDATE `id`=`id`;
