
<?php
require 'koneksi.php';
wajibRole(['admin', 'owner']);

$judul = 'Log Aktivitas';

$data = $pdo->query(
    'SELECT l.id_log, l.aktivitas, l.waktu_aktivitas,
            u.nama_lengkap, u.username, u.role
     FROM tb_log_aktivitas l
     JOIN tb_user u ON u.id_user = l.id_user
     ORDER BY l.id_log DESC
     LIMIT 200'
)->fetchAll();

require 'header.php';
?>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Riwayat Aktivitas Pengguna</h2>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Pengguna</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Aktivitas</th>
                    <th>Waktu</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $i => $l): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($l['nama_lengkap']) ?></td>
                    <td><?= htmlspecialchars($l['username']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($l['role'])) ?></td>
                    <td><?= htmlspecialchars($l['aktivitas']) ?></td>
                    <td><?= htmlspecialchars($l['waktu_aktivitas']) ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$data): ?>
                <tr><td colspan="6">Belum ada aktivitas tercatat.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'footer.php'; ?>
