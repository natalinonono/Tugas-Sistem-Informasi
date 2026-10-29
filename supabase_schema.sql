-- ========================================================
-- SKEMA SUPABASE POSTGRESQL KHUSUS PHP NATIVE (SIDINAR REGINA)
-- Jalankan script ini di SQL Editor Project Supabase Baru Anda
-- ========================================================

-- 1. Tabel Profil Pengguna (profiles)
CREATE TABLE IF NOT EXISTS public.profiles (
  id SERIAL PRIMARY KEY,
  email VARCHAR(191) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(255) NOT NULL,
  nama_panggilan VARCHAR(100) NOT NULL,
  no_telepon VARCHAR(50) NOT NULL,
  asal_wilayah_teks VARCHAR(255) NOT NULL,
  jenjang VARCHAR(50) NOT NULL DEFAULT 'SMA/K',
  role VARCHAR(50) NOT NULL DEFAULT 'anggota',
  status VARCHAR(50) NOT NULL DEFAULT 'aktif',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabel Jadwal Misa (mass_schedules)
CREATE TABLE IF NOT EXISTS public.mass_schedules (
  id SERIAL PRIMARY KEY,
  judul_misa VARCHAR(255) NOT NULL,
  kategori VARCHAR(50) NOT NULL DEFAULT 'mingguan',
  tanggal_misa DATE NOT NULL,
  jam_mulai TIME NOT NULL,
  kuota_maksimal INT NOT NULL DEFAULT 12,
  minggu_ke INT NOT NULL DEFAULT 1,
  tahun INT NOT NULL DEFAULT 2026,
  tahun_liturgi VARCHAR(100) DEFAULT 'Tahun B / Liturgi Biasa',
  warna_liturgi VARCHAR(50) NOT NULL DEFAULT 'Hijau',
  nama_selebran VARCHAR(255) DEFAULT '',
  qr_token VARCHAR(100) NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tabel Penugasan & Presensi (bookings)
CREATE TABLE IF NOT EXISTS public.bookings (
  id SERIAL PRIMARY KEY,
  schedule_id INT NOT NULL REFERENCES public.mass_schedules (id) ON DELETE CASCADE,
  user_id INT NOT NULL REFERENCES public.profiles (id) ON DELETE CASCADE,
  kategori_misa VARCHAR(50) NOT NULL DEFAULT 'mingguan',
  minggu_ke INT NOT NULL DEFAULT 1,
  tahun INT NOT NULL DEFAULT 2026,
  posisi_tugas VARCHAR(100) NOT NULL DEFAULT 'Petugas Altar',
  status_kehadiran VARCHAR(50) NOT NULL DEFAULT 'terdaftar',
  waktu_checkin TIMESTAMP WITH TIME ZONE NULL,
  wa_reminder_sent SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 4. Tabel Warta Sakristi / Pengumuman (announcements)
CREATE TABLE IF NOT EXISTS public.announcements (
  id SERIAL PRIMARY KEY,
  judul VARCHAR(255) NOT NULL,
  isi TEXT NOT NULL,
  is_pinned SMALLINT NOT NULL DEFAULT 0,
  created_by INT NULL REFERENCES public.profiles (id) ON DELETE SET NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexing untuk performa pencarian
CREATE INDEX IF NOT EXISTS idx_bookings_schedule ON public.bookings (schedule_id);
CREATE INDEX IF NOT EXISTS idx_bookings_user ON public.bookings (user_id);
CREATE INDEX IF NOT EXISTS idx_announcements_creator ON public.announcements (created_by);

-- Row Level Security (RLS) - Buka akses baca & tulis penuh untuk API
ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.mass_schedules ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.bookings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.announcements ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Allow public read-write on profiles" ON public.profiles;
CREATE POLICY "Allow public read-write on profiles" ON public.profiles FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow public read-write on mass_schedules" ON public.mass_schedules;
CREATE POLICY "Allow public read-write on mass_schedules" ON public.mass_schedules FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow public read-write on bookings" ON public.bookings;
CREATE POLICY "Allow public read-write on bookings" ON public.bookings FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow public read-write on announcements" ON public.announcements;
CREATE POLICY "Allow public read-write on announcements" ON public.announcements FOR ALL USING (true) WITH CHECK (true);

-- ========================================================
-- SEED DATA AWAL (Default Accounts & Schedules)
-- Password default semua akun: password123
-- ========================================================
INSERT INTO public.profiles (id, email, password, nama_lengkap, nama_panggilan, no_telepon, asal_wilayah_teks, jenjang, role, status) VALUES
(1, 'pengurus@sidinar.org', '$2y$10$rlcRkXHQJWbezY1XmPHXKu0WARiAcs/r5JQHtUme4fvhIlUMpjIUS', 'Pengurus Sakristi SPMR', 'Admin', '085643985341', 'Jebres, Wilayah St. Ignatius', 'Umum', 'pengurus', 'aktif'),
(2, 'misdinar@sidinar.org', '$2y$10$rlcRkXHQJWbezY1XmPHXKu0WARiAcs/r5JQHtUme4fvhIlUMpjIUS', 'Stefanus Natalino Putra Pratama', 'Inok', '081228788912', 'Jebres Barat, C2 Santa Rosa De Lima', 'SMA/K', 'anggota', 'aktif'),
(3, 'calon@sidinar.org', '$2y$10$rlcRkXHQJWbezY1XmPHXKu0WARiAcs/r5JQHtUme4fvhIlUMpjIUS', 'Yohanes Pembaptis', 'Yohan', '081399887766', 'Kentingan, St. Yohanes', 'SMP', 'anggota', 'aktif')
ON CONFLICT (id) DO NOTHING;

INSERT INTO public.mass_schedules (id, judul_misa, kategori, tanggal_misa, jam_mulai, kuota_maksimal, minggu_ke, tahun, tahun_liturgi, warna_liturgi, nama_selebran, qr_token) VALUES
(1, 'Misa Harian Pagi', 'harian', CURRENT_DATE, '05:30:00', 6, EXTRACT(WEEK FROM CURRENT_DATE), 2026, 'Tahun B / Liturgi Biasa', 'Hijau', '', MD5('misa-1-' || CURRENT_DATE)),
(2, 'Misa Mingguan Sabtu Sore', 'mingguan', CURRENT_DATE + ((6 - EXTRACT(DOW FROM CURRENT_DATE))::INT % 7), '17:00:00', 12, EXTRACT(WEEK FROM CURRENT_DATE), 2026, 'Tahun B / Liturgi Biasa', 'Hijau', '', MD5('misa-2-' || CURRENT_DATE)),
(3, 'Misa Mingguan Minggu Pagi I', 'mingguan', CURRENT_DATE + ((7 - EXTRACT(DOW FROM CURRENT_DATE))::INT % 7), '06:30:00', 12, EXTRACT(WEEK FROM CURRENT_DATE), 2026, 'Tahun B / Liturgi Biasa', 'Putih', '', MD5('misa-3-' || CURRENT_DATE)),
(4, 'Misa Mingguan Minggu Pagi II', 'mingguan', CURRENT_DATE + ((7 - EXTRACT(DOW FROM CURRENT_DATE))::INT % 7), '08:30:00', 12, EXTRACT(WEEK FROM CURRENT_DATE), 2026, 'Tahun B / Liturgi Biasa', 'Hijau', '', MD5('misa-4-' || CURRENT_DATE))
ON CONFLICT (id) DO NOTHING;

INSERT INTO public.bookings (id, schedule_id, user_id, kategori_misa, minggu_ke, tahun, posisi_tugas, status_kehadiran, wa_reminder_sent) VALUES
(1, 1, 2, 'harian', EXTRACT(WEEK FROM CURRENT_DATE), 2026, 'Petugas Altar', 'terdaftar', 1)
ON CONFLICT (id) DO NOTHING;

INSERT INTO public.announcements (id, judul, isi, is_pinned, created_by) VALUES
(1, 'Tata Tertib Sakristi & Ketentuan Presensi Tepat Waktu', 'Berkah Dalem seluruh Putra-Putri Altar SPMR Purbowardayan. Diingatkan kembali bahwa untuk Misa Harian presensi dibuka pukul 05.00 WIB (H-30 menit) dan batas tepat waktu adalah pukul 05.10 WIB (H-20 menit). Untuk Misa Mingguan, presensi wajib dilakukan paling lambat H-30 menit sebelum perayaan Ekaristi dimulai dalam kondisi sudah mengenakan jubah dan singel rapi.', 1, 1),
(2, 'Latihan Rutin Petugas Hari Raya Mendatang', 'Diberitahukan kepada seluruh misdinar bahwa gladi bersih dan latihan tugas liturgi (petugas dupa dan pembawa salib) akan dilaksanakan hari Sabtu pukul 15.00 WIB di gedung gereja.', 0, 1)
ON CONFLICT (id) DO NOTHING;

-- Sinkronisasi sequence ID auto increment
SELECT setval('profiles_id_seq', (SELECT MAX(id) FROM public.profiles));
SELECT setval('mass_schedules_id_seq', (SELECT MAX(id) FROM public.mass_schedules));
SELECT setval('bookings_id_seq', (SELECT MAX(id) FROM public.bookings));
SELECT setval('announcements_id_seq', (SELECT MAX(id) FROM public.announcements));

