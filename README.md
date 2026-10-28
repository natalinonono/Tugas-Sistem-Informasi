# ⛪ SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
### Proyek Tugas Konsentrasi Keahlian (KK) Mata Pelajaran Pemrograman Web (PW)
**Teknologi:** PHP Native (PDO) &bull; MySQL / SQLite Fallback &bull; Vanilla CSS (Material 3 Liturgical Design) &bull; Vanilla JavaScript

---

## 📌 Ringkasan Proyek
**SiDinar Regina** adalah aplikasi web Sistem Informasi & Presensi Digital untuk Putra-Putri Altar (Misdinar) Paroki Santa Perawan Maria Diangkat ke Surga (SPMR) Purbowardayan. Aplikasi ini dibangun **100% menggunakan PHP Native murni tanpa framework**, memenuhi seluruh kriteria kurikulum kejuruan tingkat mahir:
- Arsitektur berbasis Modular PHP dengan pembagian direktori rapi (`config/`, `includes/`, `pengurus/`, `anggota/`, `api/`, `assets/`).
- Akses basis data menggunakan **PHP Data Objects (PDO)** dengan *Prepared Statements* penuh untuk mencegah celah keamanan **SQL Injection**.
- Sistem Multi-Driver cerdas: Mendukung **MySQL** (`sidinar_db`) sebagai basis data utama di XAMPP, dan secara otomatis beralih ke **SQLite** (`database/sidinar.sqlite`) jika MySQL belum diaktifkan, sehingga aplikasi dijamin dapat langsung berjalan tanpa error.
- Enkripsi kata sandi menggunakan standar industri **Bcrypt** (`password_hash` & `password_verify`).
- Sanitasi input dan proteksi **Cross-Site Scripting (XSS)** menggunakan `htmlspecialchars`.
- Hak akses ganda (Role-Based Access Control): **Pengurus Sakristi (Admin)** dan **Anggota Misdinar**.
- Desain antarmuka eksklusif **Material 3 Liturgical Design** dengan dukungan **Mode Terang (Light)** dan **Mode Gelap (Dark)** serta efek suara lonceng digital (*Web Audio API Synthetic Chime*).

---

## 🚀 Fitur-Fitur Lengkap (100% Feature Parity)

### 1. 🛡️ Modul Pengurus Sakristi (Administrator)
1. **Dashboard Statistik**:
   - Menampilkan total anggota aktif, pendaftaran yang menunggu persetujuan (*pending approval*), total jadwal misa aktif, dan rekapitulasi kehadiran hari ini.
   - Tabel jadwal misa terdekat dan stasiun pemantauan presensi langsung.
2. **Manajemen Jadwal Misa & Penugasan**:
   - Tambah, edit, dan hapus jadwal Misa Harian & Misa Mingguan.
   - Penentuan tanggal, jam mulai, kuota maksimal, nama romo selebran, dan warna liturgi (Hijau, Putih, Merah, Ungu, Merah Muda).
   - **Fitur Spesial ⚡ Auto-Generate 30 Hari**: Membuat jadwal misa harian dan mingguan secara otomatis untuk 30 hari ke depan hanya dengan 1 kali klik.
   - **Kelola Roster Petugas**: Melihat misdinar yang terdaftar, mengalokasikan misdinar aktif ke posisi liturgi tertentu, dan membatalkan tugas.
   - **Pengingat WhatsApp Otomatis**: Membuat tautan langsung `wa.me` dengan format pesan resmi sakristi yang rapi dan sopan.
   - **Broadcast Notifikasi Sekali Klik**: Tombol *Ingatkan Semua* untuk mengirim pengingat ke seluruh akun petugas pada misa tersebut.
3. **Stasiun Presensi Sakristi & QR Code**:
   - Tampilan stasiun presensi untuk layar tablet/proyektor di sakristi gereja.
   - Kode QR beresolusi tinggi otomatis beserta token kode teks manual.
   - Kontrol manual kehadiran: Pengurus dapat mengubah status kehadiran secara langsung (`Hadir Tepat Waktu`, `Terlambat`, `Izin`, `Alpa`).
4. **Lembar Cetak Resmi Penugasan Altar (Print-Ready)**:
   - Dokumen format resmi dengan KOP Surat Paroki Purbowardayan.
   - Rincian liturgi, tabel daftar petugas dengan kolom tanda tangan fisik, catatan tata tertib, dan kolom pengesahan Romo Pendamping & Ketua Sakristi.
   - Otomatis terformat untuk kertas A4 saat dicetak (`window.print()`).
5. **Manajemen Anggota & Verifikasi Pendaftaran**:
   - Menyetujui calon anggota baru (*approval workflow*).
   - Mengubah peran (*promote* ke Pengurus atau *demote* ke Anggota).
   - Menonaktifkan/mengaktifkan akun misdinar.
   - Fitur *Reset Password* cepat ke nilai default (`password123`).
   - Tautan langsung chat WhatsApp ke setiap anggota.
6. **Warta Sakristi / Pengumuman**:
   - Publikasi informasi penting, jadwal gladi liturgi, dan tata tertib sakristi.
   - Fitur sematkan (*Pin to top*) pengumuman prioritas utama.

### 2. 🕊️ Modul Anggota Misdinar (Putra-Putri Altar)
1. **Hero Card Tugas Terdekat**:
   - Menampilkan penugasan misa mendatang yang harus dilayani oleh misdinar bersangkutan lengkap dengan warna liturgi, waktu misa, dan posisi tugas.
   - Tombol cepat presensi digital.
2. **Pendaftaran Tugas Mandiri (Booking)**:
   - Misdinar dapat melihat seluruh misa terbuka yang masih memiliki kuota kosong.
   - Memilih posisi tugas liturgi yang diinginkan (Akolit, Krusifer, Turifer, Navikula, Sakristi, dll.).
   - Dapat membatalkan pendaftaran mandiri jika berhalangan.
3. **Presensi Digital Sakristi (Check-in)**:
   - Melakukan presensi dengan memasukkan token kode dari stasiun presensi.
   - Evaluasi waktu otomatis berdasarkan aturan liturgi gereja:
     - **Misa Harian**: Dibuka H-30 menit, batas tepat waktu H-20 menit.
     - **Misa Mingguan**: Wajib hadir paling lambat H-30 menit dalam kondisi rapi berbusana jubah liturgi.
     - Sistem otomatis melabeli `Hadir Tepat Waktu` atau `Terlambat`.
4. **Riwayat Tugas Pribadi**:
   - Rekapitulasi jumlah pelayanan yang telah diselesaikan.
   - Persentase tingkat kehadiran (*attendance rate*).
   - Log tanggal, waktu check-in, dan posisi tugas yang pernah diemban.
5. **Profil & Pengaturan Akun**:
   - Memperbarui data diri, nama panggilan, nomor WhatsApp, jenjang sekolah, dan wilayah/lingkungan.
   - Ganti kata sandi mandiri.
6. **Pusat Notifikasi & Bunyi Lonceng Altar Digital**:
   - Notifikasi real-time yang melakukan pengecekan setiap 6 detik.
   - Suara sintetis lonceng altar (*E5 -> A5 tone*) berbasis Web Audio API tanpa memerlukan file audio eksternal.
   - Pop-up banner *Heads-Up* melayang di sudut layar.

---

## 📂 Struktur Direktori Proyek

```
Project Sistem Informasi/
├── config/
│   └── database.php          # Koneksi PDO (MySQL primary + SQLite auto-fallback)
├── includes/
│   ├── auth.php              # Otentikasi, session, role check, base_url
│   ├── functions.php         # Format tanggal Indo, badge liturgi, link WA, aturan jam presensi
│   ├── header.php            # Navbar, menu navigasi multi-role, lonceng notifikasi
│   └── footer.php            # Footer aplikasi & modal pusat notifikasi
├── assets/
│   ├── css/
│   │   └── style.css         # Vanilla CSS Material 3 Liturgical (Light & Dark theme)
│   └── js/
│       └── app.js            # Web Audio Chime, polling realtime, banner toast, modal helper
├── api/
│   ├── notifications.php     # Endpoint polling status pengingat & warta
│   ├── reminder.php          # Endpoint kirim broadcast WA & pengingat lonceng
│   ├── checkin.php           # Endpoint proses presensi mandiri & evaluasi waktu
│   └── generate_schedules.php# Endpoint generator kalender misa 30 hari otomatis
├── pengurus/
│   ├── index.php             # Dashboard ringkasan pengurus
│   ├── jadwal.php            # Kelola jadwal misa, roster petugas & broadcast WA
│   ├── presensi.php          # Stasiun presensi sakristi & QR Code live
│   ├── cetak_jadwal.php      # Lembar cetak resmi penugasan altar (A4 print-ready)
│   ├── anggota.php           # Verifikasi calon anggota baru & manajemen peran
│   └── warta.php             # Publikasi warta sakristi & pengumuman pinned
├── anggota/
│   ├── index.php             # Beranda misdinar: Tugas aktif & booking mandiri
│   ├── riwayat.php           # Log riwayat kehadiran & rekap persentase tugas
│   └── profil.php            # Pengaturan biodata & ganti password
├── database/
│   └── sidinar.sqlite        # Basis data SQLite otomatis (jika MySQL mati)
├── database.sql              # Script DDL/DML untuk phpMyAdmin / MySQL
├── index.php                 # Halaman portal selamat datang
├── login.php                 # Halaman masuk sistem (ada tombol akun demo cepat)
├── register.php              # Formulir pendaftaran calon anggota baru
├── logout.php                # Pembersihan sesi login
└── README.md                 # Dokumentasi panduan lengkap
```

---

## 🛠️ Cara Menjalankan Aplikasi

### Opsi 1: Menggunakan XAMPP (Direkomendasikan untuk Pengujian Penguji/Guru)
1. Buka aplikasi **XAMPP Control Panel**.
2. Klik tombol **Start** pada modul **Apache** dan **MySQL**.
3. Buka browser dan akses **phpMyAdmin** di `http://localhost/phpmyadmin`.
4. Buat basis data baru bernama `sidinar_db`.
5. Klik menu **Import**, pilih file `database.sql` yang ada di dalam folder proyek ini, lalu klik **Go**.
6. Letakkan folder ini di dalam direktori `htdocs` XAMPP (atau buat *VirtualHost* / *Symbolic Link*).
7. Buka browser Anda dan akses:
   ```
   http://localhost/Project Sistem Informasi/
   ```

### Opsi 2: Menggunakan PHP Built-in Server (Super Cepat, Tanpa Perlu Setup Apapun!)
Aplikasi ini sudah dilengkapi **Auto-Fallback SQLite**, sehingga Anda dapat langsung menjalankannya dalam hitungan detik tanpa membuka phpMyAdmin sama sekali:
1. Buka PowerShell atau Terminal di dalam folder ini.
2. Jalankan perintah berikut:
   ```powershell
   & "D:\xampp\php\php.exe" -S localhost:8000
   ```
   *(Atau cukup ketik `php -S localhost:8000` jika PHP sudah terdaftar di PATH).*
3. Buka browser dan buka:
   ```
   http://localhost:8000
   ```
   *Sistem akan otomatis menginisialisasi tabel dan data seed awal secara instan!*

---

## 🔑 Akun Uji Coba Default

Semua akun di bawah menggunakan kata sandi: `password123`
*(Pada halaman login sudah tersedia tombol **Isi Akun Demo** untuk mengisi form dalam 1 klik).*

| Role / Hak Akses | Alamat Email | Kata Sandi | Keterangan |
| :--- | :--- | :--- | :--- |
| **Pengurus Sakristi (Admin)** | `pengurus@sidinar.org` | `password123` | Akses penuh: Jadwal, Presensi, Anggota, Warta, Cetak Lembar Tugas |
| **Anggota Misdinar (Aktif)** | `misdinar@sidinar.org` | `password123` | Akses anggota: Booking tugas misa, Presensi QR, Riwayat kehadiran |
| **Calon Anggota (Pending)** | `calon@sidinar.org` | `password123` | Simulasi akun baru yang menunggu verifikasi oleh Pengurus |

---
**Dibuat dengan dedikasi penuh untuk tugas Konsentrasi Keahlian Pemrograman Web.**  
*Berkah Dalem & Salam Pelayanan Altar! 🙏✨*
