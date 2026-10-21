
<?php
require_once __DIR__ . '/koneksi.php';

echo "<h3>Hasil Pemeriksaan Login ParkirKu</h3>";

echo "Database aktif: ";
echo htmlspecialchars($pdo->query('SELECT DATABASE()')->fetchColumn());
echo "<br><br>";

$stmt = $pdo->prepare(
    "SELECT id_user, username, role, status_aktif, password
     FROM tb_user
     WHERE username = ?"
);
$stmt->execute(['admin1']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    exit("Akun admin1 tidak ditemukan oleh PHP.");
}

echo "Username: " . htmlspecialchars($user['username']) . "<br>";
echo "Role: " . htmlspecialchars($user['role']) . "<br>";
echo "Status aktif: " . (int)$user['status_aktif'] . "<br>";

$cocok = hash_equals(
    strtolower((string)$user['password']),
    hash('sha256', 'admin123')
);

echo "Password admin123 cocok: " . ($cocok ? 'YA' : 'TIDAK');
?>
