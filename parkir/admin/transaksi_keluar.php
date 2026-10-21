
<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['admin']);

$judul = 'Kendaraan Keluar';
$error = '';
$daftarParkir = [];
$selesai = [];

/*
|--------------------------------------------------------------------------
| Proses kendaraan keluar
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idParkir = filter_var(
        $_POST['id_parkir'] ?? null,
        FILTER_VALIDATE_INT
    );

    try {
        if (!$idParkir || $idParkir <= 0) {
            throw new Exception('ID transaksi tidak valid.');
        }

        $pdo->beginTransaction();

        // Ambil transaksi aktif beserta kendaraan dan tarifnya.
        $stmt = $pdo->prepare(
            "SELECT
                t.id_parkir,
                t.id_area,
                t.waktu_masuk,
                t.status,
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
                'Transaksi tidak ditemukan atau kendaraan sudah keluar.'
            );
        }

        if (empty($transaksi['id_area'])) {
            throw new Exception(
                'Area parkir kendaraan tidak ditemukan.'
            );
        }

        $waktuMasuk = strtotime($transaksi['waktu_masuk']);

        if ($waktuMasuk === false) {
            throw new Exception('Waktu masuk kendaraan tidak valid.');
        }

        // Durasi dibulatkan per jam, minimal 1 jam.
        $selisihDetik = max(0, time() - $waktuMasuk);
        $durasi = max(1, (int) ceil($selisihDetik / 3600));

        $tarif = (float) $transaksi['tarif_per_jam'];

        if ($tarif < 0) {
            throw new Exception('Tarif parkir tidak valid.');
        }

        $biaya = $durasi * $tarif;

        // Tandai transaksi sebagai kendaraan keluar.
        $stmt = $pdo->prepare(
            "UPDATE tb_transaksi
             SET waktu_keluar = NOW(),
                 durasi_jam = ?,
                 biaya_total = ?,
                 status = 'keluar'
             WHERE id_parkir = ?
               AND status = 'masuk'"
        );

        $stmt->execute([$durasi, $biaya, $idParkir]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception('Transaksi gagal diperbarui.');
        }

        // Kurangi kapasitas terisi pada Area A atau Area B.
        $stmt = $pdo->prepare(
            "UPDATE tb_area_parkir
             SET terisi = GREATEST(0, terisi - 1)
             WHERE id_area = ?"
        );

        $stmt->execute([(int) $transaksi['id_area']]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception(
                'Area parkir gagal diperbarui. Periksa data area.'
            );
        }

        // Catat aktivitas admin.
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

        if ($e instanceof PDOException) {
            error_log('Transaksi keluar: ' . $e->getMessage());
            $error = 'Transaksi gagal diproses. Periksa struktur tabel database.';
        } else {
            $error = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Daftar kendaraan yang masih parkir
|--------------------------------------------------------------------------
*/
try {
    $stmt = $pdo->query(
        "SELECT
            t.id_parkir,
            t.waktu_masuk,
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

    $daftarParkir = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Kendaraan aktif: ' . $e->getMessage());
    $error = 'Data kendaraan aktif gagal dimuat. Periksa struktur tabel database.';
}

/*
|--------------------------------------------------------------------------
| Riwayat transaksi keluar
|--------------------------------------------------------------------------
*/
try {
    $stmt = $pdo->query(
        "SELECT
            t.id_parkir,
            t.waktu_keluar,
            t.durasi_jam,
            t.biaya_total,
            k.plat_nomor,
            a.nama_area
         FROM tb_transaksi t
         INNER JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
         LEFT JOIN tb_area_parkir a
            ON a.id_area = t.id_area
         WHERE t.status = 'keluar'
         ORDER BY t.id_parkir DESC
         LIMIT 10"
    );

    $selesai = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Riwayat keluar: ' . $e->getMessage());
    $error = 'Riwayat transaksi gagal dimuat. Periksa struktur tabel database.';
}

require_once __DIR__ . '/header.php';
?>

<style>
.transaksi-page {
    padding: 24px;
    color: #16241e;
}

.transaksi-heading {
    margin: 0 0 24px;
}

.transaksi-heading h1 {
    margin: 0 0 8px;
    font-size: 26px;
    font-weight: 700;
    line-height: 1.4;
    color: #0c3b2e;
}

.transaksi-heading p {
    margin: 0;
    color: #62736a;
    font-size: 14px;
    line-height: 1.7;
}

.transaksi-alert {
    padding: 13px 16px;
    margin: 0 0 20px;
    border: 1px solid #f3c7c7;
    border-radius: 8px;
    background: #fdeeee;
    color: #a12b2b;
    font-size: 14px;
    line-height: 1.6;
}

.transaksi-alert.success {
    border-color: #bfe3cf;
    background: #e9f7ef;
    color: #14653f;
}

.transaksi-panel {
    margin-bottom: 24px;
    overflow: hidden;
    border: 1px solid #d9e2dc;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(12, 59, 46, 0.05);
}

.transaksi-panel-header {
    padding: 19px 22px;
    border-bottom: 1px solid #d9e2dc;
}

.transaksi-panel-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    line-height: 1.5;
    color: #0c3b2e;
}

.transaksi-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.transaksi-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: auto;
}

.transaksi-table th,
.transaksi-table td {
    padding: 15px 18px;
    border-bottom: 1px solid #edf2ee;
    text-align: left;
    vertical-align: middle;
    font-size: 14px;
    line-height: 1.6;
}

.transaksi-table th {
    background: #f3f7f4;
    color: #62736a;
    font-weight: 600;
    white-space: nowrap;
    border-bottom-color: #d9e2dc;
}

.transaksi-table td {
    color: #16241e;
}

.transaksi-table tbody tr:last-child td {
    border-bottom: none;
}

.transaksi-table tbody tr:hover {
    background: #f8fbf9;
}

.transaksi-table .plat {
    font-weight: 600;
    color: #0c3b2e;
    white-space: nowrap;
}

.transaksi-table .waktu {
    min-width: 150px;
    white-space: nowrap;
}

.transaksi-table .aksi {
    min-width: 145px;
}

.btn-keluar {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    padding: 10px 15px;
    border: none;
    border-radius: 7px;
    background: #f0b429;
    color: #0c3b2e;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.4;
    white-space: nowrap;
    cursor: pointer;
    transition: background 0.2s ease;
}

.btn-keluar:hover {
    background: #f5c24a;
}

.btn-keluar:focus-visible {
    outline: 2px solid #0c3b2e;
    outline-offset: 2px;
}

.form-keluar {
    margin: 0;
}

.transaksi-kosong {
    padding: 24px !important;
    text-align: center !important;
    color: #62736a !important;
}

@media (max-width: 768px) {
    .transaksi-page {
        padding: 16px;
    }

    .transaksi-heading h1 {
        font-size: 22px;
    }

    .transaksi-panel-header {
        padding: 16px;
    }

    .transaksi-table th,
    .transaksi-table td {
        padding: 12px;
        font-size: 13px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .btn-keluar {
        transition: none;
    }
}
</style>

<div class="transaksi-page">

    <div class="transaksi-heading">
        <h1>Kendaraan Keluar</h1>
        <p>
            Kelola kendaraan yang selesai parkir dan lihat riwayat transaksi.
        </p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="transaksi-alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['sukses'])): ?>
        <div class="transaksi-alert success">
            Kendaraan keluar berhasil diproses.
        </div>
    <?php endif; ?>

    <section class="transaksi-panel">
        <div class="transaksi-panel-header">
            <h2 class="transaksi-panel-title">
                Daftar Kendaraan Aktif
            </h2>
        </div>

        <div class="transaksi-table-wrap">
            <table class="transaksi-table">
                <thead>
                    <tr>
                        <th>Plat Nomor</th>
                        <th>Jenis Kendaraan</th>
                        <th>Area Parkir</th>
                        <th>Waktu Masuk</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($daftarParkir)): ?>
                        <?php foreach ($daftarParkir as $t): ?>
                            <tr>
                                <td class="plat">
                                    <?= htmlspecialchars(
                                        $t['plat_nomor'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $t['jenis_kendaraan'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $t['nama_area'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td class="waktu">
                                    <?= htmlspecialchars(
                                        $t['waktu_masuk'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td class="aksi">
                                    <form
                                        class="form-keluar"
                                        method="POST"
                                        action="transaksi_keluar.php"
                                        onsubmit="return confirm('Proses kendaraan ini keluar dari parkir?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="id_parkir"
                                            value="<?= (int) $t['id_parkir'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn-keluar"
                                        >
                                            Proses Keluar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="transaksi-kosong">
                                Tidak ada kendaraan yang sedang parkir.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="transaksi-panel">
        <div class="transaksi-panel-header">
            <h2 class="transaksi-panel-title">
                Transaksi Keluar Terakhir
            </h2>
        </div>

        <div class="transaksi-table-wrap">
            <table class="transaksi-table">
                <thead>
                    <tr>
                        <th>Plat Nomor</th>
                        <th>Area Parkir</th>
                        <th>Waktu Keluar</th>
                        <th>Durasi Parkir</th>
                        <th>Total Biaya</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($selesai)): ?>
                        <?php foreach ($selesai as $t): ?>
                            <tr>
                                <td class="plat">
                                    <?= htmlspecialchars(
                                        $t['plat_nomor'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $t['nama_area'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td class="waktu">
                                    <?= htmlspecialchars(
                                        $t['waktu_keluar'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int) ($t['durasi_jam'] ?? 0) ?> jam
                                </td>

                                <td>
                                    Rp <?= number_format(
                                        (float) ($t['biaya_total'] ?? 0),
                                        0,
                                        ',',
                                        '.'
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="transaksi-kosong">
                                Belum ada transaksi keluar.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
