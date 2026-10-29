<?php
/**
 * Konfigurasi & Integrasi Supabase untuk PHP Native
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */

// Load .env lokal jika ada (khusus project PHP Native)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($k, $v) = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            if (getenv($k) === false) {
                putenv("$k=$v");
                $_ENV[$k] = $v;
            }
        }
    }
}

// Konfigurasi Project Supabase Khusus PHP Native (sidinar-pro)
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://lestwehpmtlvynoylyey.supabase.co');
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imxlc3R3ZWhwbXRsdnlub3lseWV5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTMyMTU1NDUsImV4cCI6MjEwODc5MTU0NX0.LwLO38G3HyIhsSrq3mDmYsLH9oPb-w71IrMGQZcM17I');
define('SUPABASE_SERVICE_ROLE_KEY', getenv('SUPABASE_SERVICE_ROLE_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imxlc3R3ZWhwbXRsdnlub3lseWV5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTMyMTU1NDUsImV4cCI6MjEwODc5MTU0NX0.LwLO38G3HyIhsSrq3mDmYsLH9oPb-w71IrMGQZcM17I');

class SupabaseClient {

    /**
     * Kirim request HTTP ke REST API Supabase (PostgREST)
     */
    public static function request(string $endpoint, string $method = 'GET', ?array $data = null, bool $useServiceRole = true): array {
        $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
        $apiKey = $useServiceRole ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY;

        $headers = [
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif (in_array($method, ['PATCH', 'PUT', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'error'  => $error,
            'data'   => json_decode($response, true) ?? $response
        ];
    }

    /**
     * Ambil data dari tabel Supabase
     */
    public static function get(string $table, string $query = ''): array {
        $endpoint = $table . ($query ? '?' . $query : '');
        return self::request($endpoint, 'GET');
    }

    /**
     * Tambah data ke tabel Supabase
     */
    public static function insert(string $table, array $data): array {
        return self::request($table, 'POST', $data, true);
    }

    /**
     * Perbarui data di tabel Supabase
     */
    public static function update(string $table, string $filter, array $data): array {
        $endpoint = $table . '?' . $filter;
        return self::request($endpoint, 'PATCH', $data, true);
    }

    /**
     * Hapus data dari tabel Supabase
     */
    public static function delete(string $table, string $filter): array {
        $endpoint = $table . '?' . $filter;
        return self::request($endpoint, 'DELETE', null, true);
    }

    /**
     * Eksekusi Remote Procedure Call (RPC) / PostgreSQL Function
     */
    public static function rpc(string $functionName, array $params = []): array {
        $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/rpc/' . $functionName;
        return self::request('/rpc/' . $functionName, 'POST', $params, true);
    }
}

