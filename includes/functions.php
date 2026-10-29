<?php
/**
 * Fungsi Pembantu Umum (Helper Functions)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */

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
 * - Antara H-20 menit s/d masa tugas + toleransi (120 menit / 2 jam setelah jam mulai misa): 'terlambat'
 * - Melewati batas toleransi (45 menit pasca misa / 120 menit setelah jam mulai): 'alpa'
 * Contoh: Misa 05:30 -> Toleransi s/d 07:30. Lewat dari 07:30 otomatis 'alpa'.
 */
function evaluate_checkin_status(string $tanggal_misa, string $jam_mulai): string {
    $now = time();
    $massTimestamp = strtotime("$tanggal_misa $jam_mulai");

    // Batas toleransi akhir: durasi misa (~75 menit) + 45 menit toleransi = 120 menit (2 jam)
    $limitToleransiAkhir = $massTimestamp + (120 * 60);

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
 * Otomatis update seluruh pendaftaran yang belum absen ('terdaftar') menjadi 'alpa'
 * jika waktu sekarang sudah melewati batas toleransi tugas misa (120 menit setelah jam mulai).
 */
function auto_update_expired_attendance(PDO $pdo): int {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("
                UPDATE bookings 
                SET status_kehadiran = 'alpa' 
                WHERE status_kehadiran = 'terdaftar' 
                  AND schedule_id IN (
                      SELECT id FROM mass_schedules 
                      WHERE datetime(tanggal_misa || ' ' || jam_mulai, '+120 minutes') < datetime('now', 'localtime')
                  )
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } else {
            // MySQL / MariaDB
            $stmt = $pdo->prepare("
                UPDATE bookings b
                JOIN mass_schedules s ON b.schedule_id = s.id
                SET b.status_kehadiran = 'alpa'
                WHERE b.status_kehadiran = 'terdaftar'
                  AND TIMESTAMP(s.tanggal_misa, s.jam_mulai) < DATE_SUB(NOW(), INTERVAL 120 MINUTE)
            ");
            $stmt->execute();
            return $stmt->rowCount();
        }
    } catch (Exception $e) {
        // Silent catch agar tidak mengganggu rendering halaman utama
        error_log("Auto update alpa failed: " . $e->getMessage());
        return 0;
    }
}
