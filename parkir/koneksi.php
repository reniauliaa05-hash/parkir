
<?php

// ============================================
// 1. PENGATURAN SESSION
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// 2. KONFIGURASI DATABASE
// ============================================
$host = 'localhost';
$db   = 'db_parkir';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (PDOException $e) {
    error_log('Koneksi database gagal: ' . $e->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Periksa MySQL dan konfigurasi database.');
}

// ============================================
// 3. DAFTAR ROLE YANG VALID
// ============================================
function daftarRole(): array
{
    return ['admin', 'petugas', 'owner'];
}

// ============================================
// 4. PEMERIKSAAN LOGIN
// ============================================
function wajibLogin(): void
{
    if (
        empty($_SESSION['user']['id_user']) ||
        empty($_SESSION['user']['role'])
    ) {
        header('Location: /parkir/login.php');
        exit;
    }
}

// ============================================
// 5. PEMERIKSAAN HAK AKSES ROLE
// ============================================
function wajibRole(array $roles): void
{
    wajibLogin();

    $role = strtolower(
        trim((string) $_SESSION['user']['role'])
    );

    // Pastikan role yang diizinkan merupakan role valid.
    $rolesDiizinkan = array_intersect($roles, daftarRole());

    if (!in_array($role, $rolesDiizinkan, true)) {
        http_response_code(403);
        exit('403 - Anda tidak memiliki hak akses ke halaman ini.');
    }
}

// ============================================
// 6. MENGAMBIL ROLE PENGGUNA LOGIN
// ============================================
function roleLogin(): string
{
    return strtolower(
        trim((string) ($_SESSION['user']['role'] ?? ''))
    );
}

// ============================================
// 7. MENGAMBIL NAMA PENGGUNA LOGIN
// ============================================
function namaPenggunaLogin(): string
{
    return (string) (
        $_SESSION['user']['nama_lengkap']
        ?? $_SESSION['user']['username']
        ?? 'Pengguna'
    );
}

// ============================================
// 8. MENCATAT AKTIVITAS PENGGUNA
// ============================================
function catatAktivitas(PDO $pdo, string $aktivitas): void
{
    $idUser = $_SESSION['user']['id_user'] ?? null;

    if (!$idUser) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO tb_log_aktivitas
                (id_user, aktivitas, waktu_aktivitas)
             VALUES
                (:id_user, :aktivitas, NOW())'
        );

        $stmt->execute([
            ':id_user'   => (int) $idUser,
            ':aktivitas' => mb_substr($aktivitas, 0, 255)
        ]);
    } catch (PDOException $e) {
        // Kegagalan log tidak boleh menghentikan aplikasi.
        error_log(
            'Gagal mencatat aktivitas: ' . $e->getMessage()
        );
    }
}

// ============================================
// 9. ESCAPE OUTPUT HTML
// ============================================
function e($nilai): string
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}
