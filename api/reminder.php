<?php
/**
 * API Kirim Pengingat Penugasan
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in() || !is_pengurus()) {
    echo json_encode(['success' => false, 'error' => 'Akses ditolak.']);
    exit;
}

$booking_id = $_POST['booking_id'] ?? $_GET['booking_id'] ?? null;
$schedule_id = $_POST['schedule_id'] ?? $_GET['schedule_id'] ?? null;

if (!$booking_id && !$schedule_id) {
    echo json_encode(['success' => false, 'error' => 'ID Booking atau ID Jadwal wajib disertakan.']);
    exit;
}

try {
    $pdo = get_db();
    $now = date('Y-m-d H:i:s');

    if ($schedule_id) {
        // Ambil semua booking yang terdaftar di jadwal ini
        $stmt = $pdo->prepare("SELECT id, user_id FROM bookings WHERE schedule_id = ?");
        $stmt->execute([$schedule_id]);
        $allBookings = $stmt->fetchAll();

        if (empty($allBookings)) {
            echo json_encode(['success' => false, 'error' => 'Belum ada misdinar yang terdaftar di jadwal misa ini.']);
            exit;
        }

        $updateStmt = $pdo->prepare("UPDATE bookings SET wa_reminder_sent = 1 WHERE schedule_id = ?");
        $updateStmt->execute([$schedule_id]);

        $userIds = array_unique(array_column($allBookings, 'user_id'));
        if (!empty($userIds)) {
            $inClause = implode(',', array_map('intval', $userIds));
            $pdo->query("UPDATE profiles SET updated_at = '{$now}' WHERE id IN ({$inClause})");
        }

        echo json_encode([
            'success' => true,
            'count' => count($allBookings),
            'message' => 'Pengingat berhasil disiarkan ke ' . count($allBookings) . ' petugas misdinar!',
            'timestamp' => time()
        ]);
        exit;
    }

    // Ambil single booking & user
    $stmt = $pdo->prepare("SELECT user_id FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        echo json_encode(['success' => false, 'error' => 'Data penugasan tidak ditemukan.']);
        exit;
    }

    // Update status reminder
    $updateStmt = $pdo->prepare("UPDATE bookings SET wa_reminder_sent = 1 WHERE id = ?");
    $updateStmt->execute([$booking_id]);

    // Update timestamp profile user
    $pStmt = $pdo->prepare("UPDATE profiles SET updated_at = ? WHERE id = ?");
    $pStmt->execute([$now, $booking['user_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Pengingat tugas berhasil disiarkan ke misdinar!',
        'timestamp' => time()
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
