
<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['petugas']);

$judul = 'Data Kendaraan';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plat = strtoupper(trim($_POST['plat_nomor'] ?? ''));
    $jenis = $_POST['jenis_kendaraan'] ?? '';
    $warna = trim($_POST['warna'] ?? '');
    $pemilik = trim($_POST['pemilik'] ?? '');

    if ($plat === '' || $pemilik === '' ||
        !in_array($jenis, ['motor', 'mobil', 'lainnya'], true)) {
        $error = 'Lengkapi data kendaraan dengan benar.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO tb_kendaraan
                (plat_nomor, jenis_kendaraan, warna, pemilik, id_user)
                VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $plat, $jenis, $warna, $pemilik,
                $_SESSION['user']['id_user']
            ]);

            catatAktivitas($pdo, 'Menambahkan kendaraan ' . $plat);
            header('Location: kendaraan.php?sukses=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Gagal menyimpan. Plat nomor mungkin sudah terdaftar.';
        }
    }
}

$data = $pdo->query(
    'SELECT k.*, u.nama_lengkap
     FROM tb_kendaraan k
     LEFT JOIN tb_user u ON u.id_user = k.id_user
     ORDER BY k.id_kendaraan DESC'
)->fetchAll();

require 'header.php';
?>

<?php if ($error): ?>
    <div class="alert"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (isset($_GET['sukses'])): ?>
    <div class="alert success">Data kendaraan berhasil ditambahkan.</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Tambah Kendaraan</h2>
    </div>

    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>Plat Nomor</label>
                <input name="plat_nomor" placeholder="Contoh: M 1234 AB"
                       maxlength="20" required>
            </div>

            <div class="form-group">
                <label>Jenis Kendaraan</label>
                <select name="jenis_kendaraan" required>
                    <option value="">Pilih jenis</option>
                    <option value="motor">Motor</option>
                    <option value="mobil">Mobil</option>
                    <option value="lainnya">Lainnya</option>
                </select>
            </div>

            <div class="form-group">
                <label>Warna</label>
                <input name="warna" placeholder="Warna kendaraan">
            </div>

            <div class="form-group">
                <label>Nama Pemilik</label>
                <input name="pemilik" placeholder="Nama pemilik" required>
            </div>
        </div>

        <button class="btn btn-primary" type="submit">Simpan Kendaraan</button>
    </form>
</div>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Daftar Kendaraan</h2>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Plat Nomor</th>
                    <th>Jenis</th>
                    <th>Warna</th>
                    <th>Pemilik</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $i => $k): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($k['plat_nomor']) ?></strong></td>
                    <td><?= htmlspecialchars(ucfirst($k['jenis_kendaraan'])) ?></td>
                    <td><?= htmlspecialchars($k['warna'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($k['pemilik']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$data): ?>
                <tr><td colspan="5">Belum ada data kendaraan.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'footer.php'; ?>
