<?php
/**
 * API Otomatisasi Generate Jadwal Misa 30 Hari
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in() || !is_pengurus()) {
    echo json_encode(['success' => false, 'error' => 'Akses ditolak.']);
    exit;
}

try {
    $pdo = get_db();
    $days = (int)($_POST['days'] ?? 30);
    if ($days < 1) $days = 30;

    $startDate = new DateTime();
    $insertedCount = 0;

    $checkStmt = $pdo->prepare("SELECT id FROM mass_schedules WHERE tanggal_misa = ? AND jam_mulai = ?");
    $insertStmt = $pdo->prepare("
        INSERT INTO mass_schedules 
        (judul_misa, kategori, tanggal_misa, jam_mulai, kuota_maksimal, minggu_ke, tahun, tahun_liturgi, warna_liturgi, nama_selebran, qr_token)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    for ($i = 0; $i < $days; $i++) {
        $current = clone $startDate;
        $current->modify("+$i days");
        $dateStr = $current->format('Y-m-d');
        $dayOfWeek = (int)$current->format('N'); // 1 (Senin) .. 7 (Minggu)
        $weekNum = (int)$current->format('W');
        $year = (int)$current->format('Y');

        // 1. Misa Harian: Senin - Sabtu (05.30 WIB)
        if ($dayOfWeek >= 1 && $dayOfWeek <= 6) {
            $time = '05:30:00';
            $checkStmt->execute([$dateStr, $time]);
            if (!$checkStmt->fetch()) {
                $token = md5("misa-harian-$dateStr-$time");
                $insertStmt->execute([
                    'Misa Harian Pagi',
                    'harian',
                    $dateStr,
                    $time,
                    6,
                    $weekNum,
                    $year,
                    'Tahun B / Liturgi Biasa',
                    'Hijau',
                    '',
                    $token
                ]);
                $insertedCount++;
            }
        }

        // 2. Misa Mingguan Sabtu Sore (17.00 WIB)
        if ($dayOfWeek === 6) {
            $time = '17:00:00';
            $checkStmt->execute([$dateStr, $time]);
            if (!$checkStmt->fetch()) {
                $token = md5("misa-sabtu-$dateStr-$time");
                $insertStmt->execute([
                    'Misa Mingguan Sabtu Sore',
                    'mingguan',
                    $dateStr,
                    $time,
                    12,
                    $weekNum,
                    $year,
                    'Tahun B / Liturgi Biasa',
                    'Hijau',
                    '',
                    $token
                ]);
                $insertedCount++;
            }
        }

        // 3. Misa Mingguan Hari Minggu: 06.30, 08.30, 17.00 WIB
        if ($dayOfWeek === 7) {
            $sundaySlots = [
                ['jam' => '06:30:00', 'judul' => 'Misa Mingguan Pagi I', 'warna' => 'Putih'],
                ['jam' => '08:30:00', 'judul' => 'Misa Mingguan Pagi II', 'warna' => 'Hijau'],
                ['jam' => '17:00:00', 'judul' => 'Misa Mingguan Sore', 'warna' => 'Hijau'],
            ];

            foreach ($sundaySlots as $slot) {
                $checkStmt->execute([$dateStr, $slot['jam']]);
                if (!$checkStmt->fetch()) {
                    $token = md5("misa-minggu-$dateStr-{$slot['jam']}");
                    $insertStmt->execute([
                        $slot['judul'],
                        'mingguan',
                        $dateStr,
                        $slot['jam'],
                        12,
                        $weekNum,
                        $year,
                        'Tahun B / Liturgi Biasa',
                        $slot['warna'],
                        '',
                        $token
                    ]);
                    $insertedCount++;
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Berhasil menghasilkan $insertedCount jadwal misa baru untuk $days hari ke depan!",
        'inserted_count' => $insertedCount
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
