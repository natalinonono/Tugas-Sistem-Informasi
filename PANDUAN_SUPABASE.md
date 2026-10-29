# ⚡ Panduan Setup Project Supabase Baru (Khusus PHP Native)

Sesuai arsitektur sistem, **Project PHP Native** menggunakan project Supabase tersendiri (terpisah dari project Supabase Web Next.js & Android).

---

## 🛠️ Langkah-Langkah Membuat Project Supabase Baru (1-2 Menit)

### Langkah 1: Buat Project Baru di Supabase
1. Buka browser dan login ke: **[https://supabase.com/dashboard](https://supabase.com/dashboard)**.
2. Klik tombol **New Project**.
3. Isi informasi project:
   - **Name**: `sidinar-php-native` (atau nama lain yang kamu suka).
   - **Database Password**: Masukkan password yang kuat dan catat password ini.
   - **Region**: Pilih yang paling dekat, misalnya **Singapore (`ap-southeast-1`)**.
4. Klik **Create new project** dan tunggu sekitar 1-2 menit hingga proses inisialisasi selesai.

---

### Langkah 2: Buat Tabel & Data Awal (Sekali Klik)
1. Di sidebar kiri project Supabase baru kamu, klik menu **SQL Editor** (ikon terminal/lembaran SQL).
2. Klik tombol **New Query**.
3. Buka file **`supabase_schema.sql`** yang ada di folder `php_native/`, copy semua isinya, lalu paste ke SQL Editor Supabase.
4. Klik tombol **Run** (atau tekan `Ctrl + Enter`).
5. Selesai! Semua tabel (`profiles`, `mass_schedules`, `bookings`, `announcements`), Row Level Security (RLS), dan data akun demo langsung terbuat otomatis.

---

### Langkah 3: Ambil Kredensial API Project Baru
1. Masuk ke menu **Project Settings** (ikon gerigi ⚙️ di pojok kiri bawah) > **API**.
2. Salin 3 nilai berikut:
   - **Project URL**: `https://xxxxxxxxxxxxxxxx.supabase.co`
   - **Project API Keys (anon / public)**: `eyJhbGciOi...`
   - **Project API Keys (service_role / secret)**: `eyJhbGciOi...`

---

### Langkah 4: Masukkan Kredensial ke PHP Native
Buat file bernama **`.env`** di dalam folder `php_native/` (atau copy dari `.env.example`), lalu isi dengan kredensial project baru kamu:

```env
# Konfigurasi Supabase Khusus PHP Native
SUPABASE_URL=https://xxxxxxxxxxxxxxxx.supabase.co
SUPABASE_ANON_KEY=eyJhbGciOi...
SUPABASE_SERVICE_ROLE_KEY=eyJhbGciOi...

# (Opsional) Jika ingin koneksi direct PDO PostgreSQL
SUPABASE_DB_HOST=db.xxxxxxxxxxxxxxxx.supabase.co
SUPABASE_DB_PORT=5432
SUPABASE_DB_NAME=postgres
SUPABASE_DB_USER=postgres
SUPABASE_DB_PASS=PASSWORD_DATABASE_YANG_KAMU_BUAT
```

---

## 🚀 Cara Penggunaan di Kode PHP:

Setelah `.env` diisi, file `config/supabase.php` otomatis membaca project Supabase baru tersebut:

```php
require_once __DIR__ . '/config/supabase.php';

// Ambil data jadwal dari project Supabase baru
$res = SupabaseClient::get('mass_schedules', 'select=*&order=tanggal_misa.asc');
$jadwalList = $res['data'];

// Tambah anggota baru
SupabaseClient::insert('profiles', [
    'email'             => 'misdinarbaru@sidinar.org',
    'nama_lengkap'      => 'Fransiskus Xaverius',
    'nama_panggilan'    => 'Frans',
    'no_telepon'        => '081234567890',
    'asal_wilayah_teks' => 'Jebres Barat',
    'role'              => 'anggota',
    'status'            => 'aktif'
]);
```

---

## 🗄️ Status Database PHP Native Saat Ini
1. **Supabase Baru**: Siap digunakan kapan pun dengan mengisi file `.env` di atas.
2. **MySQL Lokal XAMPP (Default)**: Menghubungkan ke `127.0.0.1:3306/sidinar_db` secara lokal.
3. **SQLite Lokal (Fallback)**: Aktif otomatis jika MySQL XAMPP sedang tidak dinyalakan.
