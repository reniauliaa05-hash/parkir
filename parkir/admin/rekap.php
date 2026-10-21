
<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['admin']);

$judul = 'Rekap Transaksi';

$tanggalAwal = $_GET['awal'] ?? date('Y-m-01');
$tanggalAkhir = $_GET['akhir'] ?? date('Y-m-d');

$error = '';
$data = [];
$total = 0;
$totalSelesai = 0;

/*
|--------------------------------------------------------------------------
| Validasi tanggal
|--------------------------------------------------------------------------
*/
$validAwal = DateTimeImmutable::createFromFormat('!Y-m-d', $tanggalAwal);
$validAkhir = DateTimeImmutable::createFromFormat('!Y-m-d', $tanggalAkhir);

if (
    !$validAwal ||
    !$validAkhir ||
    $validAwal->format('Y-m-d') !== $tanggalAwal ||
    $validAkhir->format('Y-m-d') !== $tanggalAkhir ||
    $tanggalAwal > $tanggalAkhir
) {
    $tanggalAwal = date('Y-m-01');
    $tanggalAkhir = date('Y-m-d');
}

/*
|--------------------------------------------------------------------------
| Ambil data transaksi
|--------------------------------------------------------------------------
| Area A dan Area B ditampilkan sesuai relasi id_area.
*/
try {
    $stmt = $pdo->prepare(
        "SELECT
            t.id_parkir,
            t.waktu_masuk,
            t.waktu_keluar,
            t.durasi_jam,
            t.biaya_total,
            t.status,
            k.plat_nomor,
            k.jenis_kendaraan,
            u.nama_lengkap,
            a.nama_area
         FROM tb_transaksi t
         INNER JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
         LEFT JOIN tb_user u
            ON u.id_user = t.id_user
         LEFT JOIN tb_area_parkir a
            ON a.id_area = t.id_area
         WHERE t.waktu_masuk >= ?
           AND t.waktu_masuk < DATE_ADD(?, INTERVAL 1 DAY)
         ORDER BY t.id_parkir DESC"
    );

    $stmt->execute([$tanggalAwal, $tanggalAkhir]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($data as $t) {
        if (($t['status'] ?? '') === 'keluar') {
            $total += (float) ($t['biaya_total'] ?? 0);
            $totalSelesai++;
        }
    }
} catch (PDOException $e) {
    error_log('Rekap transaksi: ' . $e->getMessage());
    $error = 'Data rekap gagal dimuat. Periksa struktur tabel database.';
}

/*
|--------------------------------------------------------------------------
| Format output aman
|--------------------------------------------------------------------------
*/
function amanRekap($nilai): string
{
    return htmlspecialchars(
        (string) ($nilai ?? '-'),
        ENT_QUOTES,
        'UTF-8'
    );
}

require_once __DIR__ . '/header.php';
?>

<style>
.admin-content .panel,
.admin-content .card {
    background-color: #ffffff !important;
    border-color: #d9e2dc !important;
}

.admin-content .panel-title,
.admin-content .panel-header h2 {
    color: #0c3b2e !important;
}

.admin-content .card-label,
.admin-content .subtitle {
    color: #62736a !important;
}

.admin-content .card-value {
    color: #0c3b2e !important;
}

.admin-content .form-group label {
    color: #16241e !important;
}

.admin-content .form-group input,
.admin-content .form-group select {
    background-color: #f8faf8 !important;
    border-color: #d9e2dc !important;
    color: #16241e !important;
}

.admin-content .form-group input:focus,
.admin-content .form-group select:focus {
    border-color: #14513f !important;
    box-shadow: 0 0 0 3px rgba(20, 81, 63, 0.14) !important;
}

.admin-content .btn-primary {
    background-color: #f0b429 !important;
    border-color: #f0b429 !important;
    color: #0c3b2e !important;
}

.admin-content .btn-primary:hover {
    background-color: #f5c24a !important;
    border-color: #f5c24a !important;
}

.admin-content .btn-light {
    background-color: #e9f2ec !important;
    border-color: #d9e2dc !important;
    color: #14513f !important;
}

.admin-content .btn-light:hover {
    background-color: #dcebe2 !important;
}

.admin-content table th {
    background-color: #f3f7f4 !important;
    border-bottom-color: #d9e2dc !important;
    color: #62736a !important;
}

.admin-content table td {
    border-bottom-color: #edf2ee !important;
    color: #16241e !important;
}

.admin-content table tbody tr:hover {
    background-color: #f8fbf9 !important;
}

.rekap-alert {
    margin-bottom: 20px;
    padding: 13px 16px;
    border: 1px solid #f3c7c7;
    border-radius: 8px;
    background: #fdeeee;
    color: #a12b2b;
    font-size: 14px;
}

.rekap-status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.rekap-status.keluar {
    background: #e9f7ef;
    color: #14653f;
}

.rekap-status.masuk {
    background: #fff4d6;
    color: #875d00;
}

.rekap-status.lain {
    background: #edf2f7;
    color: #475569;
}

.rekap-nominal {
    font-weight: 600;
    white-space: nowrap;
}

.rekap-area {
    white-space: nowrap;
}

@media print {
    .no-print,
    .sidebar,
    .admin-sidebar,
    .topbar,
    .admin-header {
        display: none !important;
    }

    .admin-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }

    .panel,
    .card {
        box-shadow: none !important;
        break-inside: avoid;
    }

    .table-wrap {
        overflow: visible !important;
    }

    table {
        width: 100% !important;
        font-size: 10px !important;
    }

    th, td {
        padding: 6px !important;
    }

    body {
        background: #fff !important;
    }
}
</style>

<div class="panel no-print">
    <h2 class="panel-title" style="margin-bottom:20px">
        Filter Periode Transaksi
    </h2>

    <form method="GET" class="filter-form">
        <div class="form-group">
            <label for="awal">Tanggal Awal</label>
            <input
                type="date"
                id="awal"
                name="awal"
                value="<?= amanRekap($tanggalAwal) ?>"
                max="<?= date('Y-m-d') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="akhir">Tanggal Akhir</label>
            <input
                type="date"
                id="akhir"
                name="akhir"
                value="<?= amanRekap($tanggalAkhir) ?>"
                max="<?= date('Y-m-d') ?>"
                required
            >
        </div>

        <button class="btn btn-primary" type="submit">
            Tampilkan
        </button>

        <button
            class="btn btn-light"
            type="button"
            onclick="window.print()"
        >
            Cetak Rekap
        </button>
    </form>
</div>

<?php if ($error !== ''): ?>
    <div class="rekap-alert">
        <?= amanRekap($error) ?>
    </div>
<?php endif; ?>

<div class="cards">
    <div class="card">
        <div class="card-label">Jumlah Transaksi</div>
        <div class="card-value">
            <?= number_format(count($data), 0, ',', '.') ?>
        </div>
    </div>

    <div class="card">
        <div class="card-label">Transaksi Selesai</div>
        <div class="card-value">
            <?= number_format($totalSelesai, 0, ',', '.') ?>
        </div>
    </div>

    <div class="card">
        <div class="card-label">Pendapatan Selesai</div>
        <div class="card-value" style="font-size:23px">
            Rp <?= number_format($total, 0, ',', '.') ?>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Laporan Transaksi Parkir</h2>
    </div>

    <p class="subtitle" style="margin-bottom:18px">
        Periode <?= amanRekap($tanggalAwal) ?>
        sampai <?= amanRekap($tanggalAkhir) ?>
    </p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Plat Nomor</th>
                    <th>Jenis</th>
                    <th>Area</th>
                    <th>Masuk</th>
                    <th>Keluar</th>
                    <th>Durasi</th>
                    <th>Biaya</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!empty($data)): ?>
                    <?php foreach ($data as $i => $t): ?>
                        <?php
                        $status = $t['status'] ?? '';
                        $kelasStatus = match ($status) {
                            'keluar' => 'keluar',
                            'masuk' => 'masuk',
                            default => 'lain'
                        };
                        ?>

                        <tr>
                            <td><?= $i + 1 ?></td>

                            <td>
                                <?= amanRekap($t['plat_nomor']) ?>
                            </td>

                            <td>
                                <?= amanRekap($t['jenis_kendaraan']) ?>
                            </td>

                            <td class="rekap-area">
                                <?= amanRekap($t['nama_area']) ?>
                            </td>

                            <td>
                                <?= amanRekap($t['waktu_masuk']) ?>
                            </td>

                            <td>
                                <?= amanRekap($t['waktu_keluar']) ?>
                            </td>

                            <td>
                                <?= (int) ($t['durasi_jam'] ?? 0) ?> jam
                            </td>

                            <td class="rekap-nominal">
                                Rp <?= number_format(
                                    (float) ($t['biaya_total'] ?? 0),
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </td>

                            <td>
                                <span class="rekap-status <?= $kelasStatus ?>">
                                    <?= amanRekap(ucfirst($status ?: 'Tidak diketahui')) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:24px">
                            Tidak ada transaksi pada periode ini.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
