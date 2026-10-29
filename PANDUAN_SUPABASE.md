# ⚡ Panduan Integrasi Supabase untuk PHP Native (SiDinar Regina)

Dokumen ini menjelaskan cara menghubungkan aplikasi **PHP Native** ke basis data **Supabase** (`https://tkclthikptiotudrtkty.supabase.co`), menggantikan konfigurasi TiDB Cloud lama secara penuh.

---

## 📌 Dua Metode Menghubungkan PHP ke Supabase

Supabase menyediakan dua metode akses:
1. **Metode 1: Supabase REST API (Direkomendasikan - 100% Siap Pakai Tanpa Install Apapun)**
2. **Metode 2: Direct PostgreSQL Connection via PDO (Menggunakan PostgreSQL Driver)**

---

## 🚀 Metode 1: Lewat Supabase REST API (Paling Praktis)
Metode ini menggunakan HTTPS cURL standar yang sudah aktif di semua versi PHP XAMPP tanpa perlu mengaktifkan ekstensi PostgreSQL tambahan.

Modul ini sudah terpasang di:
📂 **`config/supabase.php`**

### Contoh Penggunaan di File PHP:
```php
require_once __DIR__ . '/config/supabase.php';

// 1. Mengambil data jadwal misa dari Supabase
$result = SupabaseClient::get('mass_schedules', 'select=*&order=tanggal_misa.asc');
if ($result['status'] === 200) {
    $jadwalList = $result['data'];
    foreach ($jadwalList as $jadwal) {
        echo $jadwal['judul_misa'] . ' - ' . $jadwal['tanggal_misa'] . '<br>';
    }
}

// 2. Menambah anggota baru ke Supabase
$newMember = SupabaseClient::insert('profiles', [
    'email'             => 'userbaru@sidinar.org',
    'nama_lengkap'      => 'Fransiskus Xaverius',
    'nama_panggilan'    => 'Frans',
    'no_telepon'        => '081234567890',
    'asal_wilayah_teks' => 'Jebres Barat',
    'role'              => 'anggota',
    'status'            => 'aktif'
]);

// 3. Update status kehadiran booking
SupabaseClient::update('bookings', 'id=eq.' . $bookingId, [
    'status_kehadiran' => 'hadir',
    'waktu_checkin'    => date('Y-m-d H:i:s')
]);

// 4. Hapus data
SupabaseClient::delete('bookings', 'id=eq.' . $bookingId);
```

---

## 🐘 Metode 2: Lewat Direct PostgreSQL PDO (Opsional)
Jika Anda ingin menggunakan query SQL standar (`$db->query("SELECT ...")`) langsung ke server PostgreSQL Supabase:

### Langkah 1: Aktifkan Ekstensi PDO PostgreSQL di XAMPP
1. Buka file `php.ini` di: `D:\xampp\php\php.ini` (atau via XAMPP Control Panel > Tombol **Config** di Apache > `PHP (php.ini)`).
2. Cari baris berikut (tekan `Ctrl + F` dan ketik `pdo_pgsql`):
   ```ini
   ;extension=pdo_pgsql
   ```
3. Hapus tanda titik koma (`;`) di depannya sehingga menjadi:
   ```ini
   extension=pdo_pgsql
   ```
4. Simpan file `php.ini` dan restart Apache di XAMPP.

### Langkah 2: Dapatkan Password Database Supabase
1. Buka dashboard: **[https://supabase.com/dashboard](https://supabase.com/dashboard)**
2. Pilih project **SiDinar Regina** (`tkclthikptiotudrtkty`).
3. Masuk ke menu **Project Settings** (ikon gerigi) > **Database**.
4. Di bagian **Connection parameters**:
   - **Host**: `db.tkclthikptiotudrtkty.supabase.co`
   - **Database name**: `postgres`
   - **Port**: `5432` (atau `6543` untuk Session Pooler)
   - **User**: `postgres`
   - **Password**: *(Password database yang Anda buat saat pertama kali membuat project Supabase)*.

### Langkah 3: Masukkan Password ke `config/database.php`
Di file `config/database.php`, isi konstanta `SUPABASE_DB_PASS`:
```php
define('SUPABASE_DB_PASS', 'PASSWORD_DATABASE_SUPABASE_ANDA');
```
Begitu password diisi dan ekstensi `pdo_pgsql` aktif, `get_db()` akan **langsung otomatis terhubung ke PostgreSQL Supabase**!

---

## 🗄️ Status Urutan Driver Database Saat Ini
Di dalam `config/database.php`, hierarki koneksi otomatis berjalan sebagai berikut:
1. **Supabase PostgreSQL**: Aktif otomatis jika `SUPABASE_DB_PASS` diisi & ekstensi `pdo_pgsql` aktif.
2. **MySQL Lokal XAMPP (Default Utama)**: Menghubungkan ke `127.0.0.1:3306/sidinar_db` secara otomatis.
3. **SQLite Lokal (Fallback)**: Berjalan otomatis jika MySQL lokal mati, sehingga web selalu aman 100% tanpa error.
