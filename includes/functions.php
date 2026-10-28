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

function get_liturgical_badge(string $warna): string {
    $warna = ucfirst(strtolower($warna));
    $classes = [
        'Hijau' => 'badge-emerald',
        'Putih' => 'badge-light',
        'Merah' => 'badge-red',
        'Ungu' => 'badge-purple',
        'Merah Muda' => 'badge-pink'
    ];
    $cls = $classes[$warna] ?? 'badge-emerald';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($warna) . '</span>';
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

    $nama = $member['nama_panggilan'] ?: $member['nama_lengkap'] ?: 'Misdinar';
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

function evaluate_checkin_status(string $tanggal_misa, string $jam_mulai): string {
    $now = time();
    $massTimestamp = strtotime("$tanggal_misa $jam_mulai");

    // Batas waktu: tepat waktu jika checkin sebelum H-20 menit
    $limitTepatWaktu = $massTimestamp - (20 * 60);

    if ($now <= $limitTepatWaktu) {
        return 'hadir';
    } else {
        return 'terlambat';
    }
}
