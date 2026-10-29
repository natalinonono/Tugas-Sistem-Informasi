<?php
/**
 * Fungsi Pembantu Umum (Helper Functions)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */

// Pastikan zona waktu selalu Indonesia Barat (WIB / Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

const LITURGICAL_POSITIONS = [
    'Petugas Altar',
    'Akolit (Pembawa Lilin)',
    'Krusifer (Pembawa Salib)',
    'Turifer (Pembawa Dupa)',
    'Navikula (Pemegang Perahu Dupa)',
    'Pembawa Evangeliarium',
    'Petugas Sakristi'
];

const JENJANG_OPTIONS = ['SD', 'SMP', 'SMA/K', 'Mahasiswa', 'Umum'];

const WARNA_LITURGI_OPTIONS = ['Hijau', 'Putih', 'Merah', 'Ungu', 'Merah Muda'];

function format_indo_date(string $date): string {
    if (empty($date)) return '-';
    $timestamp = strtotime($date);
    if (!$timestamp) return $date;

    $hari = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];

    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $dayName = $hari[date('l', $timestamp)] ?? '';
    $day = date('d', $timestamp);
    $month = $bulan[(int)date('m', $timestamp)] ?? '';
    $year = date('Y', $timestamp);

    return "$dayName, $day $month $year";
}

function format_indo_time(string $time): string {
    if (empty($time)) return '-';
    return substr($time, 0, 5) . ' WIB';
}

function get_liturgy_bg_class(string $warna = 'Hijau'): string {
    $warna = strtolower(trim($warna));
    return match($warna) {
        'putih' => 'bg-slate-300 border border-slate-400',
        'merah' => 'bg-rose-500',
        'ungu' => 'bg-purple-600',
        'merah muda' => 'bg-pink-400',
        default => 'bg-emerald-500',
    };
}

function get_liturgical_badge(string $warna = 'Hijau'): string {
    $wLower = strtolower(trim($warna));
    $classes = match($wLower) {
        'putih' => 'bg-slate-100 text-slate-800 border-slate-300',
        'merah' => 'bg-rose-100 text-rose-800 border-rose-300',
        'ungu' => 'bg-purple-100 text-purple-800 border-purple-300',
        'merah muda' => 'bg-pink-100 text-pink-800 border-pink-300',
        default => 'bg-emerald-100 text-emerald-800 border-emerald-300',
    };
    return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold border ' . $classes . '">'
         . '<span class="w-1.5 h-1.5 rounded-full ' . get_liturgy_bg_class($warna) . '"></span>'
         . htmlspecialchars(ucfirst($warna))
         . '</span>';
}

function get_attendance_badge(string $status): string {
    $status = strtolower($status);
    $map = [
        'hadir' => ['bg' => 'badge-emerald', 'label' => 'Hadir'],
        'terlambat' => ['bg' => 'badge-amber', 'label' => 'Terlambat'],
        'izin' => ['bg' => 'badge-blue', 'label' => 'Izin'],
        'alpa' => ['bg' => 'badge-red', 'label' => 'Alpa'],
        'terdaftar' => ['bg' => 'badge-gray', 'label' => 'Terdaftar']
    ];
    $badge = $map[$status] ?? ['bg' => 'badge-gray', 'label' => ucfirst($status)];
    return '<span class="badge ' . $badge['bg'] . '">' . htmlspecialchars($badge['label']) . '</span>';
}

function generate_whatsapp_reminder_link(array $schedule, array $member): array {
    $phone = preg_replace('/[^0-9]/', '', $member['no_telepon'] ?? '');
    if (str_starts_with($phone, '0')) {
        $phone = '62' . substr($phone, 1);
    }

    $nama = (!empty($member['nama_panggilan'])) ? $member['nama_panggilan'] : (!empty($member['nama_lengkap']) ? $member['nama_lengkap'] : 'Misdinar');
    $tanggal = format_indo_date($schedule['tanggal_misa'] ?? '');
    $jam = format_indo_time($schedule['jam_mulai'] ?? '');
    $judul = $schedule['judul_misa'] ?? 'Misa Kudus';
    $posisi = $schedule['posisi_tugas'] ?? 'Petugas Altar';

    $text = "Halo {$nama}, pengurus sakristi mengingatkan jadwal tugas altar Anda pada:\n\n"
          . "⛪ {$judul}\n"
          . "📅 {$tanggal}\n"
          . "⏰ {$jam}\n"
          . "🛡️ Posisi: {$posisi}\n\n"
          . "Harap hadir di sakristi paling lambat 30 menit sebelum perayaan Ekaristi dimulai dalam kondisi siap dengan jubah rapi. Berkah Dalem! 🙏✨";

    $url = "https://wa.me/{$phone}?text=" . rawurlencode($text);

    return [
        'url' => $url,
        'message' => $text,
        'phone' => $phone
    ];
}

/**
 * Evaluasi status checkin berdasarkan waktu:
 * - Sebelum H-20 menit: 'hadir' (tepat waktu)
 * - Antara H-20 menit s/d batas toleransi: 'terlambat'
 * - Melewati batas toleransi (45 menit pasca misa): 'alpa'
 *
 * Durasi misa & batas cut-off:
 * - Harian: Durasi maks 30 menit + 45 menit toleransi = 75 menit dari jam mulai misa.
 *   (Contoh: Misa 05:30 -> Cutoff tepat 06:45 WIB. Lewat 06:45 otomatis ALPA).
 * - Mingguan: Durasi maks 75 menit (1 jam 15 menit) + 45 menit toleransi = 120 menit (2 jam) dari jam mulai misa.
 *   (Contoh: Misa 17:00 -> Cutoff tepat 19:00 WIB. Lewat 19:00 otomatis ALPA).
 */
function evaluate_checkin_status(string $tanggal_misa, string $jam_mulai, string $kategori = 'mingguan'): string {
    $now = time();
    $massTimestamp = strtotime("$tanggal_misa $jam_mulai");

    $isHarian = (strtolower($kategori) === 'harian');
    $toleranceMinutes = $isHarian ? 75 : 120; // 30m+45m vs 75m+45m
    $limitToleransiAkhir = $massTimestamp + ($toleranceMinutes * 60);

    // Batas tepat waktu: H-20 menit
    $limitTepatWaktu = $massTimestamp - (20 * 60);

    if ($now > $limitToleransiAkhir) {
        return 'alpa';
    } elseif ($now <= $limitTepatWaktu) {
        return 'hadir';
    } else {
        return 'terlambat';
    }
}

/**
 * Cek apakah jadwal misa telah melewati batas toleransi presensi (+45 menit setelah perkiraan misa selesai).
 * - Harian: 30 menit misa + 45 menit toleransi = 75 menit dari jam mulai. (Contoh: Misa 05:30 -> Cutoff 06:45 WIB).
 * - Mingguan: 75 menit misa + 45 menit toleransi = 120 menit dari jam mulai. (Contoh: Misa 05:30 -> Cutoff 07:30 WIB).
 */
function is_mass_tolerance_expired(string $tanggal_misa, string $jam_mulai, string $kategori = 'mingguan'): bool {
    $now = time();
    $massTimestamp = strtotime("$tanggal_misa $jam_mulai");
    $isHarian = (strtolower($kategori) === 'harian');
    $toleranceMinutes = $isHarian ? 75 : 120;
    $limitToleransiAkhir = $massTimestamp + ($toleranceMinutes * 60);
    return $now > $limitToleransiAkhir;
}

/**
 * Otomatis update seluruh pendaftaran yang belum absen ('terdaftar') menjadi 'alpa'
 * jika waktu sekarang sudah melewati batas toleransi tugas misa (+45 menit setelah misa selesai):
 * - Harian (30m + 45m = 75m): Misa 05:30 WIB -> Lewat 06:45 WIB langsung otomatis ALPA.
 * - Mingguan (75m + 45m = 120m): Misa 05:30 WIB -> Lewat 07:30 WIB langsung otomatis ALPA.
 */
function auto_update_expired_attendance(PDO $pdo): int {
    try {
        $nowWib = date('Y-m-d H:i:s');
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("
                UPDATE bookings 
                SET status_kehadiran = 'alpa' 
                WHERE status_kehadiran = 'terdaftar' 
                  AND schedule_id IN (
                      SELECT id FROM mass_schedules 
                      WHERE (kategori = 'harian' AND datetime(tanggal_misa || ' ' || jam_mulai, '+75 minutes') < ?)
                         OR (kategori != 'harian' AND datetime(tanggal_misa || ' ' || jam_mulai, '+120 minutes') < ?)
                  )
            ");
            $stmt->execute([$nowWib, $nowWib]);
            return $stmt->rowCount();
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->prepare("
                UPDATE public.bookings b
                SET status_kehadiran = 'alpa'
                FROM public.mass_schedules s
                WHERE b.schedule_id = s.id
                  AND b.status_kehadiran = 'terdaftar'
                  AND (
                      (s.kategori = 'harian' AND (s.tanggal_misa + s.jam_mulai + interval '75 minutes') < ?::timestamp)
                      OR
                      (s.kategori != 'harian' AND (s.tanggal_misa + s.jam_mulai + interval '120 minutes') < ?::timestamp)
                  )
            ");
            $stmt->execute([$nowWib, $nowWib]);
            return $stmt->rowCount();
        } else {
            // MySQL / MariaDB
            $stmt = $pdo->prepare("
                UPDATE bookings b
                JOIN mass_schedules s ON b.schedule_id = s.id
                SET b.status_kehadiran = 'alpa'
                WHERE b.status_kehadiran = 'terdaftar'
                  AND (
                      (s.kategori = 'harian' AND DATE_ADD(TIMESTAMP(s.tanggal_misa, s.jam_mulai), INTERVAL 75 MINUTE) < ?)
                      OR
                      (s.kategori != 'harian' AND DATE_ADD(TIMESTAMP(s.tanggal_misa, s.jam_mulai), INTERVAL 120 MINUTE) < ?)
                  )
            ");
            $stmt->execute([$nowWib, $nowWib]);
            return $stmt->rowCount();
        }
    } catch (Exception $e) {
        // Silent catch agar tidak mengganggu rendering halaman utama
        error_log("Auto update alpa failed: " . $e->getMessage());
        return 0;
    }
}
