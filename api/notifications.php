<?php
/**
 * API Notifikasi Real-time
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = current_user();
$pdo = get_db();
$today = date('Y-m-d');

$reminders = [];
$latest_reminder = null;

try {
    if ($user['role'] === 'pengurus') {
        // Pengurus melihat pengingat yang telah disiarkan ke misdinar
        $stmt = $pdo->prepare("
            SELECT b.*, s.judul_misa, s.tanggal_misa, s.jam_mulai, s.kategori,
                   p.nama_lengkap, p.nama_panggilan
            FROM bookings b
            JOIN mass_schedules s ON b.schedule_id = s.id
            JOIN profiles p ON b.user_id = p.id
            WHERE b.wa_reminder_sent = 1 AND s.tanggal_misa >= ?
            ORDER BY b.created_at DESC LIMIT 10
        ");
        $stmt->execute([$today]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $r) {
            $petugas = $r['nama_panggilan'] ?: $r['nama_lengkap'];
            $reminders[] = [
                'id' => 'reminder-' . $r['id'],
                'booking_id' => $r['id'],
                'title' => '🔔 Pengingat Disiarkan: ' . strtoupper($r['kategori']) . ' (' . $petugas . ')',
                'message' => 'Pengingat tugas ' . $r['posisi_tugas'] . ' pada ' . format_indo_date($r['tanggal_misa']) . ' (' . format_indo_time($r['jam_mulai']) . ') telah disiarkan ke ' . $petugas . '.',
                'url' => base_url('pengurus/jadwal.php'),
                'type' => 'reminder'
            ];
        }
    } else {
        // Anggota hanya melihat pengingat miliknya sendiri
        $stmt = $pdo->prepare("
            SELECT b.*, s.judul_misa, s.tanggal_misa, s.jam_mulai, s.kategori
            FROM bookings b
            JOIN mass_schedules s ON b.schedule_id = s.id
            WHERE b.user_id = ? AND b.wa_reminder_sent = 1 AND s.tanggal_misa >= ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$user['id'], $today]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $r) {
            $reminders[] = [
                'id' => 'reminder-' . $r['id'],
                'booking_id' => $r['id'],
                'title' => '🔔 Pengingat Tugas: ' . strtoupper($r['kategori']) . ' (' . $r['posisi_tugas'] . ')',
                'message' => 'Pengurus Sakristi mengingatkan tugas altar pada ' . format_indo_date($r['tanggal_misa']) . ' pukul ' . format_indo_time($r['jam_mulai']) . '. Harap hadir di sakristi H-30 menit.',
                'url' => base_url('anggota/index.php'),
                'type' => 'reminder'
            ];
        }
    }

    if (!empty($reminders)) {
        $latest_reminder = $reminders[0];
    }

    // Ambil Pengumuman / Warta Sakristi
    $wartaStmt = $pdo->query("SELECT * FROM announcements ORDER BY is_pinned DESC, created_at DESC LIMIT 5");
    $announcements = $wartaStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'unread_count' => count($reminders),
        'reminders' => $reminders,
        'latest_reminder' => $latest_reminder,
        'announcements' => $announcements
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
