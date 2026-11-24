<?php
/**
 * API Notifikasi Real-time
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = get_db();
$today = date('Y-m-d');
$user = current_user();

$reminders = [];
$latest_reminder = null;

try {
    if ($user) {
        if ($user['role'] === 'pengurus') {
            // Pengurus melihat jadwal mendatang & penugasan misdinar
            $stmt = $pdo->prepare("
                SELECT b.*, s.judul_misa, s.tanggal_misa, s.jam_mulai, s.kategori,
                       p.nama_lengkap, p.nama_panggilan
                FROM bookings b
                JOIN mass_schedules s ON b.schedule_id = s.id
                JOIN profiles p ON b.user_id = p.id
                WHERE s.tanggal_misa >= ?
                ORDER BY s.tanggal_misa ASC, s.jam_mulai ASC LIMIT 10
            ");
            $stmt->execute([$today]);
            $rows = $stmt->fetchAll();

            foreach ($rows as $r) {
                $petugas = $r['nama_panggilan'] ?: $r['nama_lengkap'];
                $statusWA = $r['wa_reminder_sent'] ? ' [WA Terkirim]' : ' [Belum Dikirim WA]';
                $reminders[] = [
                    'id' => 'reminder-' . $r['id'],
                    'booking_id' => $r['id'],
                    'title' => '🔔 Jadwal Tugas: ' . strtoupper($r['kategori']) . ' (' . $petugas . ')' . $statusWA,
                    'message' => 'Tugas ' . $r['posisi_tugas'] . ' pada ' . format_indo_date($r['tanggal_misa']) . ' (' . format_indo_time($r['jam_mulai']) . ') oleh ' . $petugas . '.',
                    'url' => base_url('pengurus/jadwal.php?schedule_id=' . $r['schedule_id']),
                    'type' => 'reminder'
                ];
            }
        } else {
            // Anggota melihat seluruh jadwal tugas altar miliknya yang akan datang
            $stmt = $pdo->prepare("
                SELECT b.*, s.judul_misa, s.tanggal_misa, s.jam_mulai, s.kategori
                FROM bookings b
                JOIN mass_schedules s ON b.schedule_id = s.id
                WHERE b.user_id = ? AND s.tanggal_misa >= ?
                ORDER BY s.tanggal_misa ASC, s.jam_mulai ASC
            ");
            $stmt->execute([$user['id'], $today]);
            $rows = $stmt->fetchAll();

            foreach ($rows as $r) {
                $reminders[] = [
                    'id' => 'reminder-' . $r['id'],
                    'booking_id' => $r['id'],
                    'title' => '🔔 Pengingat Tugas: ' . strtoupper($r['kategori']) . ' (' . $r['posisi_tugas'] . ')',
                    'message' => 'Anda bertugas pada ' . format_indo_date($r['tanggal_misa']) . ' pukul ' . format_indo_time($r['jam_mulai']) . '. Harap hadir di sakristi paling lambat H-30 menit.',
                    'url' => base_url('anggota/index.php'),
                    'type' => 'reminder'
                ];
            }
        }
    }

    if (!empty($reminders)) {
        $latest_reminder = $reminders[0];
    }

    // Ambil Pengumuman / Warta Sakristi
    $wartaStmt = $pdo->query("SELECT * FROM announcements ORDER BY is_pinned DESC, created_at DESC LIMIT 5");
    $announcements = $wartaStmt->fetchAll();

    $unread_count = count($reminders);
    if ($unread_count === 0 && !empty($announcements)) {
        $pinnedCount = 0;
        foreach ($announcements as $a) {
            if (!empty($a['is_pinned'])) $pinnedCount++;
        }
        $unread_count = $pinnedCount;
    }

    echo json_encode([
        'success' => true,
        'is_logged_in' => !empty($user),
        'unread_count' => $unread_count,
        'reminders' => $reminders,
        'latest_reminder' => $latest_reminder,
        'announcements' => $announcements
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
