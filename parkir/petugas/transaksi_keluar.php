
<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['petugas']);

$judul = 'Kendaraan Keluar';
$error = '';
$sukses = isset($_GET['sukses']);

// ========================================
// PROSES TRANSAKSI KENDARAAN KELUAR
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idParkir = (int) ($_POST['id_parkir'] ?? 0);

    try {
        if ($idParkir <= 0) {
            throw new Exception('Transaksi parkir tidak valid.');
        }

        $pdo->beginTransaction();

        // Ambil transaksi yang masih aktif
        $stmt = $pdo->prepare(
            "SELECT
                t.*,
                k.plat_nomor,
                tr.tarif_per_jam
             FROM tb_transaksi t
             INNER JOIN tb_kendaraan k
                ON k.id_kendaraan = t.id_kendaraan
             INNER JOIN tb_tarif tr
                ON tr.id_tarif = t.id_tarif
             WHERE t.id_parkir = ?
               AND t.status = 'masuk'
             FOR UPDATE"
        );

        $stmt->execute([$idParkir]);
        $transaksi = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaksi) {
            throw new Exception(
                'Transaksi tidak ditemukan atau sudah selesai.'
            );
        }

        // Hitung durasi parkir
        $waktuMasuk = strtotime($transaksi['waktu_masuk']);

        if ($waktuMasuk === false) {
            throw new Exception('Waktu masuk kendaraan tidak valid.');
        }

        $waktuKeluar = time();
        $selisihDetik = max(0, $waktuKeluar - $waktuMasuk);

        // Minimal biaya satu jam, pembulatan ke atas
        $durasiJam = max(
            1,
            (int) ceil($selisihDetik / 3600)
        );

        $tarifPerJam = (float) $transaksi['tarif_per_jam'];
        $biayaTotal = $durasiJam * $tarifPerJam;

        // Perbarui transaksi menjadi keluar
        $stmt = $pdo->prepare(
            "UPDATE tb_transaksi
             SET waktu_keluar = NOW(),
                 durasi_jam = ?,
                 biaya_total = ?,
                 status = 'keluar'
             WHERE id_parkir = ?
               AND status = 'masuk'"
        );

        $stmt->execute([
            $durasiJam,
            $biayaTotal,
            $idParkir
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception(
                'Transaksi gagal diperbarui atau sudah diproses.'
            );
        }

        // Kurangi kapasitas area jika transaksi memiliki area
        if (!empty($transaksi['id_area'])) {
            $stmt = $pdo->prepare(
                'UPDATE tb_area_parkir
                 SET terisi = GREATEST(0, terisi - 1)
                 WHERE id_area = ?'
            );

            $stmt->execute([
                $transaksi['id_area']
            ]);
        }

        // Catat aktivitas
        catatAktivitas(
            $pdo,
            'Kendaraan keluar: ' . $transaksi['plat_nomor']
        );

        $pdo->commit();

        header('Location: transaksi_keluar.php?sukses=1');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}

// ========================================
// AMBIL DAFTAR KENDARAAN YANG MASIH PARKIR
// ========================================
$aktif = [];

try {
    $stmt = $pdo->query(
        "SELECT
            t.id_parkir,
            t.waktu_masuk,
            t.id_area,
            k.plat_nomor,
            k.jenis_kendaraan,
            a.nama_area
         FROM tb_transaksi t
         INNER JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
         LEFT JOIN tb_area_parkir a
            ON a.id_area = t.id_area
         WHERE t.status = 'masuk'
         ORDER BY t.waktu_masuk ASC"
    );

    $hasil = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $aktif = is_array($hasil) ? $hasil : [];

} catch (Throwable $e) {
    $error = 'Gagal mengambil daftar kendaraan aktif. Periksa struktur tabel dan query database.';
}

// ========================================
// AMBIL RIWAYAT TRANSAKSI KELUAR
// ========================================
$selesai = [];

try {
    $stmt = $pdo->query(
        "SELECT
            t.id_parkir,
            t.waktu_keluar,
            t.durasi_jam,
            t.biaya_total,
            k.plat_nomor
         FROM tb_transaksi t
         INNER JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
         WHERE t.status = 'keluar'
         ORDER BY t.id_parkir DESC
         LIMIT 10"
    );

    $hasil = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $selesai = is_array($hasil) ? $hasil : [];

} catch (Throwable $e) {
    $error = 'Gagal mengambil riwayat transaksi keluar. Periksa struktur tabel dan query database.';
}

require __DIR__ . '/header.php';
?>

<?php if ($error !== ''): ?>
    <div class="alert">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($sukses): ?>
    <div class="alert success">
        Kendaraan keluar berhasil diproses.
    </div>
<?php endif; ?>

<!-- DAFTAR KENDARAAN AKTIF -->
<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Daftar Kendaraan Aktif</h2>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Plat Nomor</th>
                    <th>Jenis</th>
                    <th>Area Parkir</th>
                    <th>Waktu Masuk</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!empty($aktif)): ?>
                    <?php foreach ($aktif as $i => $t): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $t['plat_nomor'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $t['jenis_kendaraan'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $t['nama_area'] ?? 'Tidak ditentukan',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $t['waktu_masuk'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <form
                                    method="POST"
                                    onsubmit="return confirm('Proses kendaraan keluar dan hitung biaya parkir?')"
                                >
                                    <input
                                        type="hidden"
                                        name="id_parkir"
                                        value="<?= (int) $t['id_parkir'] ?>"
                                    >

                                    <button
                                        class="btn btn-primary"
                                        type="submit"
                                    >
                                        Proses Keluar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">
                            Tidak ada kendaraan yang sedang parkir.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- RIWAYAT TRANSAKSI KELUAR -->
<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Transaksi Keluar Terakhir</h2>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Plat Nomor</th>
                    <th>Waktu Keluar</th>
                    <th>Durasi</th>
                    <th>Total Biaya</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!empty($selesai)): ?>
                    <?php foreach ($selesai as $i => $t): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>

                            <td>
                                <?= htmlspecialchars(
                                    $t['plat_nomor'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $t['waktu_keluar'] ?? '-',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= (int) $t['durasi_jam'] ?> jam
                            </td>

                            <td>
                                Rp <?= number_format(
                                    (float) $t['biaya_total'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            Belum ada transaksi selesai.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
