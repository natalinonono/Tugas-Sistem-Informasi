<?php
/**
 * API Presensi Digital / QR Check-in
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Sesi login tidak valid.']);
    exit;
}

$user = current_user();
$booking_id = $_POST['booking_id'] ?? null;
$qr_token = trim($_POST['qr_token'] ?? '');

if (!$booking_id) {
    echo json_encode(['success' => false, 'error' => 'ID Penugasan wajib diisi.']);
    exit;
}

try {
    $pdo = get_db();

    // Auto-update seluruh tugas lampau yang belum absen menjadi alpa
    auto_update_expired_attendance($pdo);

    // Ambil booking beserta jadwal
    $stmt = $pdo->prepare("
        SELECT b.*, s.judul_misa, s.tanggal_misa, s.jam_mulai, s.qr_token AS valid_qr_token
        FROM bookings b
        JOIN mass_schedules s ON b.schedule_id = s.id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        echo json_encode(['success' => false, 'error' => 'Penugasan tidak ditemukan.']);
        exit;
    }

    // Hanya pemilik tugas atau pengurus yang boleh melakukan check-in
    if ($user['role'] !== 'pengurus' && (int)$booking['user_id'] !== (int)$user['id']) {
        echo json_encode(['success' => false, 'error' => 'Akses ditolak. Anda tidak berhak melakukan presensi tugas ini.']);
        exit;
    }

    // Jika sudah alpa karena melewati batas toleransi
    if ($booking['status_kehadiran'] === 'alpa') {
        echo json_encode([
            'success' => false,
            'status' => 'alpa',
            'error' => 'Batas toleransi presensi telah berakhir (maksimal 45 menit setelah misa). Status Anda adalah ALPA dan tidak dapat presensi mandiri.'
        ]);
        exit;
    }

    // Jika sudah presensi sebelumnya
    if ($booking['status_kehadiran'] === 'hadir' || $booking['status_kehadiran'] === 'terlambat') {
        echo json_encode([
            'success' => true,
            'status' => $booking['status_kehadiran'],
            'message' => 'Anda sudah melakukan presensi sebelumnya (' . strtoupper($booking['status_kehadiran']) . ').'
        ]);
        exit;
    }

    // Evaluasi waktu checkin (Tepat Waktu vs Terlambat vs Alpa)
    $status = evaluate_checkin_status($booking['tanggal_misa'], $booking['jam_mulai']);
    $waktu_checkin = date('Y-m-d H:i:s');

    if ($status === 'alpa') {
        $update = $pdo->prepare("UPDATE bookings SET status_kehadiran = 'alpa' WHERE id = ?");
        $update->execute([$booking_id]);

        echo json_encode([
            'success' => false,
            'status' => 'alpa',
            'error' => 'Batas waktu toleransi presensi telah berakhir (45 menit pasca misa). Status tugas otomatis tercatat Alpa.'
        ]);
        exit;
    }

    $update = $pdo->prepare("UPDATE bookings SET status_kehadiran = ?, waktu_checkin = ? WHERE id = ?");
    $update->execute([$status, $waktu_checkin, $booking_id]);

    echo json_encode([
        'success' => true,
        'status' => $status,
        'waktu_checkin' => $waktu_checkin,
        'message' => $status === 'hadir' 
            ? 'Presensi Berhasil: Hadir Tepat Waktu! Berkah Dalem. 🙏' 
            : 'Presensi Tercatat: Terlambat. Harap segera bersiap di sakristi.'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
