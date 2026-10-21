<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['admin']);

$judul = 'Dashboard Admin';

function rupiah($angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function dashRupiahRingkas(float $nilai): string
{
    if ($nilai >= 1000000000) {
        $n = $nilai / 1000000000;
        $satuan = ' M';
    } elseif ($nilai >= 1000000) {
        $n = $nilai / 1000000;
        $satuan = ' jt';
    } elseif ($nilai >= 1000) {
        $n = $nilai / 1000;
        $satuan = ' rb';
    } else {
        return number_format($nilai, 0, ',', '.');
    }

    $teks = number_format($n, 2, ',', '.');
    $teks = rtrim(rtrim($teks, '0'), ',');

    return $teks . $satuan;
}

function dashSkalaAtas(float $nilai): float
{
    if ($nilai <= 0) {
        return 1.0;
    }

    $pangkat = pow(10, floor(log10($nilai)));
    $relatif = $nilai / $pangkat;

    foreach ([1, 2, 4, 5, 8, 10] as $langkah) {
        if ($relatif <= $langkah) {
            return (float) ($langkah * $pangkat);
        }
    }

    return (float) (10 * $pangkat);
}

function dashAngka(float $nilai, int $desimal = 2): string
{
    return number_format($nilai, $desimal, '.', '');
}

/*
|--------------------------------------------------------------------------
| Inisialisasi data dashboard
|--------------------------------------------------------------------------
*/

$totalKendaraan = 0;
$parkirAktif = 0;
$totalArea = 0;
$pendapatan = 0;
$totalPengguna = 0;
$kapasitasTotal = 0;

$terbaru = [];
$jenisKendaraan = [];
$pendapatanPerTanggal = [];
$dataArea = [];
$pesanError = '';

/*
|--------------------------------------------------------------------------
| Statistik utama
|--------------------------------------------------------------------------
*/

try {
    $totalKendaraan = (int) $pdo->query(
        'SELECT COUNT(*) FROM tb_kendaraan'
    )->fetchColumn();

    $parkirAktif = (int) $pdo->query(
        "SELECT COUNT(*) FROM tb_transaksi WHERE status = 'masuk'"
    )->fetchColumn();

    $totalArea = (int) $pdo->query(
        'SELECT COUNT(*) FROM tb_area_parkir'
    )->fetchColumn();

    $pendapatan = (float) $pdo->query(
        "SELECT COALESCE(SUM(biaya_total), 0)
         FROM tb_transaksi
         WHERE status = 'keluar'"
    )->fetchColumn();

    $totalPengguna = (int) $pdo->query(
        'SELECT COUNT(*) FROM tb_user'
    )->fetchColumn();

    $kapasitasTotal = (int) $pdo->query(
        'SELECT COALESCE(SUM(kapasitas), 0) FROM tb_area_parkir'
    )->fetchColumn();

} catch (PDOException $e) {
    error_log('Dashboard Admin Statistik: ' . $e->getMessage());
    $pesanError = 'Sebagian statistik gagal dimuat. Periksa struktur database.';
}

/*
|--------------------------------------------------------------------------
| Area A untuk motor dan Area B untuk mobil
|--------------------------------------------------------------------------
*/

try {
    $stmtArea = $pdo->query("
        SELECT
            a.id_area,
            a.nama_area,
            a.kapasitas,
            a.terisi,
            COALESCE(SUM(
                CASE
                    WHEN t.status = 'masuk'
                    THEN 1 ELSE 0
                END
            ), 0) AS kendaraan_aktif,
            COALESCE(SUM(
                CASE
                    WHEN t.status = 'masuk'
                    AND LOWER(TRIM(k.jenis_kendaraan)) = 'motor'
                    THEN 1 ELSE 0
                END
            ), 0) AS jumlah_motor,
            COALESCE(SUM(
                CASE
                    WHEN t.status = 'masuk'
                    AND LOWER(TRIM(k.jenis_kendaraan)) = 'mobil'
                    THEN 1 ELSE 0
                END
            ), 0) AS jumlah_mobil
        FROM tb_area_parkir a
        LEFT JOIN tb_transaksi t
            ON t.id_area = a.id_area
            AND t.status = 'masuk'
        LEFT JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
        GROUP BY
            a.id_area,
            a.nama_area,
            a.kapasitas,
            a.terisi
        ORDER BY a.id_area ASC
    ");

    foreach ($stmtArea->fetchAll(PDO::FETCH_ASSOC) as $area) {
        $dataArea[$area['nama_area']] = $area;
    }

} catch (PDOException $e) {
    error_log('Dashboard Admin Area: ' . $e->getMessage());
    $pesanError = 'Data area parkir gagal dimuat.';
}

/*
|--------------------------------------------------------------------------
| Lima transaksi terbaru
|--------------------------------------------------------------------------
*/

try {
    $terbaru = $pdo->query("
        SELECT
            t.id_parkir,
            t.id_area,
            t.waktu_masuk,
            t.waktu_keluar,
            t.status,
            t.biaya_total,
            k.plat_nomor,
            k.jenis_kendaraan,
            a.nama_area
        FROM tb_transaksi t
        LEFT JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
        LEFT JOIN tb_area_parkir a
            ON a.id_area = t.id_area
        ORDER BY t.id_parkir DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Dashboard Admin Transaksi: ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| Pendapatan tujuh hari terakhir
|--------------------------------------------------------------------------
*/

try {
    $stmtPendapatan = $pdo->prepare("
        SELECT
            DATE(waktu_keluar) AS tgl,
            COALESCE(SUM(biaya_total), 0) AS total
        FROM tb_transaksi
        WHERE status = 'keluar'
          AND waktu_keluar IS NOT NULL
          AND waktu_keluar >= ?
          AND waktu_keluar < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        GROUP BY DATE(waktu_keluar)
    ");

    $stmtPendapatan->execute([
        date('Y-m-d', strtotime('-6 days')) . ' 00:00:00'
    ]);

    foreach ($stmtPendapatan->fetchAll(PDO::FETCH_ASSOC) as $baris) {
        $pendapatanPerTanggal[$baris['tgl']] = (float) $baris['total'];
    }

} catch (PDOException $e) {
    error_log('Dashboard Admin Grafik Pendapatan: ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| Komposisi kendaraan terdaftar
|--------------------------------------------------------------------------
*/

try {
    $jenisKendaraan = $pdo->query("
        SELECT
            jenis_kendaraan,
            COUNT(*) AS jumlah
        FROM tb_kendaraan
        GROUP BY jenis_kendaraan
        ORDER BY jumlah DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Dashboard Admin Jenis Kendaraan: ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| Data tanggal dan grafik
|--------------------------------------------------------------------------
*/

$namaHari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

$namaHariPanjang = [
    'Minggu', 'Senin', 'Selasa', 'Rabu',
    'Kamis', 'Jumat', 'Sabtu'
];

$namaBulan = [
    1 => 'Januari', 'Februari', 'Maret', 'April',
    'Mei', 'Juni', 'Juli', 'Agustus',
    'September', 'Oktober', 'November', 'Desember'
];

$tanggalHariIni =
    $namaHariPanjang[(int) date('w')] . ', ' .
    date('j') . ' ' .
    $namaBulan[(int) date('n')] . ' ' .
    date('Y');

$namaAdmin = $_SESSION['user']['nama_lengkap']
    ?? $_SESSION['user']['username']
    ?? 'Administrator';

$seriPendapatan = [];

for ($i = 6; $i >= 0; $i--) {
    $ts = strtotime("-{$i} days");
    $tgl = date('Y-m-d', $ts);

    $seriPendapatan[] = [
        'label' => $namaHari[(int) date('w', $ts)],
        'tanggal' => date('j', $ts) . ' ' .
            $namaBulan[(int) date('n', $ts)],
        'nilai' => $pendapatanPerTanggal[$tgl] ?? 0.0,
    ];
}

$nilaiTertinggi = max(array_column($seriPendapatan, 'nilai'));
$adaPendapatan = $nilaiTertinggi > 0;
$skalaMaks = dashSkalaAtas((float) $nilaiTertinggi);
$totalTujuhHari = array_sum(array_column($seriPendapatan, 'nilai'));

$svgLebar = 640;
$svgTinggi = 280;
$margKiri = 65;
$margKanan = 12;
$margAtas = 25;
$margBawah = 36;
$areaLebar = $svgLebar - $margKiri - $margKanan;
$areaTinggi = $svgTinggi - $margAtas - $margBawah;
$garisDasar = $margAtas + $areaTinggi;
$lebarSlot = $areaLebar / 7;
$lebarBar = $lebarSlot * 0.5;

/*
|--------------------------------------------------------------------------
| Data grafik donat
|--------------------------------------------------------------------------
*/

$warnaDonut = [
    '#0c3b2e',
    '#f0b429',
    '#2f8f6b',
    '#8fb9a5',
    '#c8d5ce'
];

$segmenJenis = [];
$totalJenis = 0;

foreach ($jenisKendaraan as $baris) {
    $totalJenis += (int) $baris['jumlah'];
}

if ($totalJenis > 0) {
    $lainnya = 0;

    foreach ($jenisKendaraan as $idx => $baris) {
        $nama = trim((string) $baris['jenis_kendaraan']);
        $nama = $nama === '' ? 'Tidak diketahui' : $nama;
        $jumlah = (int) $baris['jumlah'];

        if ($idx < 4) {
            $segmenJenis[] = [
                'nama' => $nama,
                'jumlah' => $jumlah
            ];
        } else {
            $lainnya += $jumlah;
        }
    }

    if ($lainnya > 0) {
        $segmenJenis[] = [
            'nama' => 'Lainnya',
            'jumlah' => $lainnya
        ];
    }

    foreach ($segmenJenis as $idx => $sg) {
        $segmenJenis[$idx]['persen'] =
            ($sg['jumlah'] / $totalJenis) * 100;

        $segmenJenis[$idx]['warna'] =
            $warnaDonut[$idx % count($warnaDonut)];
    }
}

$persenOkupansi = $kapasitasTotal > 0
    ? min(100, ($parkirAktif / $kapasitasTotal) * 100)
    : null;

$kelasOkupansi = '';

if ($persenOkupansi !== null) {
    if ($persenOkupansi >= 90) {
        $kelasOkupansi = 'is-penuh';
    } elseif ($persenOkupansi >= 70) {
        $kelasOkupansi = 'is-ramai';
    }
}

require __DIR__ . '/header.php';
?>

<style>
.admin-dashboard {
    --ad-green: #0c3b2e;
    --ad-green-2: #14513f;
    --ad-yellow: #f0b429;
    --ad-text: #16241e;
    --ad-muted: #62736a;
    --ad-line: #d9e2dc;
    --ad-soft: #f3f7f4;

    font-family: "Segoe UI", Arial, sans-serif;
    color: var(--ad-text);
}

.admin-dashboard *,
.admin-dashboard *::before,
.admin-dashboard *::after {
    box-sizing: border-box;
}

.admin-welcome {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 26px;
}

.admin-welcome h1 {
    margin: 0 0 8px;
    color: var(--ad-green);
    font-size: 30px;
    font-weight: 750;
}

.admin-welcome p {
    margin: 0;
    color: var(--ad-muted);
    font-size: 14px;
    line-height: 1.6;
}

.admin-date {
    padding: 10px 15px;
    border: 1px solid var(--ad-line);
    border-radius: 30px;
    background: #fff;
    color: var(--ad-green);
    font-size: 12px;
    font-weight: 700;
}

.admin-cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.admin-stat {
    min-width: 0;
    padding: 22px;
    background: #fff;
    border: 1px solid var(--ad-line);
    border-radius: 15px;
}

.admin-stat-label {
    margin-bottom: 12px;
    color: var(--ad-muted);
    font-size: 13px;
}

.admin-stat-value {
    color: var(--ad-green);
    font-size: 30px;
    font-weight: 750;
    overflow-wrap: anywhere;
}

.admin-stat-note {
    margin-top: 9px;
    color: var(--ad-muted);
    font-size: 12px;
}

.admin-stat-hero {
    background: var(--ad-green);
    border-color: var(--ad-green);
}

.admin-stat-hero .admin-stat-label,
.admin-stat-hero .admin-stat-note {
    color: #d1e4d9;
}

.admin-stat-hero .admin-stat-value {
    color: var(--ad-yellow);
    font-size: 25px;
}

.admin-meter,
.admin-area-progress {
    height: 7px;
    margin-top: 12px;
    overflow: hidden;
    border-radius: 20px;
    background: #e5ece7;
}

.admin-meter div,
.admin-area-progress div {
    height: 100%;
    border-radius: 20px;
    background: var(--ad-green-2);
}

.admin-meter.is-ramai div { background: var(--ad-yellow); }
.admin-meter.is-penuh div { background: #dc2626; }

.admin-area-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.admin-area-card {
    padding: 22px;
    background: #fff;
    border: 1px solid var(--ad-line);
    border-radius: 15px;
}

.admin-area-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.admin-area-card h2 {
    margin: 0 0 6px;
    color: var(--ad-green);
    font-size: 20px;
}

.admin-area-card p {
    margin: 0;
    color: var(--ad-muted);
    font-size: 13px;
}

.admin-area-tag {
    padding: 7px 10px;
    border-radius: 8px;
    background: #fef3c7;
    color: #854d0e;
    font-size: 11px;
    font-weight: 750;
}

.admin-area-value {
    margin-top: 23px;
    color: var(--ad-green);
    font-size: 29px;
    font-weight: 750;
}

.admin-area-value span {
    color: var(--ad-muted);
    font-size: 13px;
    font-weight: 500;
}

.admin-area-detail {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-top: 10px;
    color: var(--ad-muted);
    font-size: 12px;
}

.admin-area-detail strong { color: var(--ad-green); }

.admin-panel {
    min-width: 0;
    margin-bottom: 22px;
    padding: 22px;
    border: 1px solid var(--ad-line);
    border-radius: 15px;
    background: #fff;
}

.admin-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}

.admin-panel-header h2 {
    margin: 0;
    color: var(--ad-green);
    font-size: 19px;
}

.admin-panel-header p {
    margin: 6px 0 0;
    color: var(--ad-muted);
    font-size: 13px;
}

.admin-chip {
    padding: 7px 11px;
    border-radius: 8px;
    background: var(--ad-soft);
    color: var(--ad-green);
    font-size: 12px;
    font-weight: 700;
}

.admin-charts {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.admin-charts .admin-panel { margin-bottom: 0; }

.admin-chart-scroll { overflow-x: auto; }

.admin-chart {
    display: block;
    width: 100%;
    min-width: 480px;
    height: auto;
}

.admin-chart text {
    font-family: "Segoe UI", Arial, sans-serif;
    fill: var(--ad-muted);
    font-size: 12px;
}

.admin-chart .grid-line {
    stroke: #e6ede8;
    stroke-width: 1;
}

.admin-chart .base-line {
    stroke: #c5d2ca;
    stroke-width: 1;
}

.admin-chart .bar { fill: #14513f; }
.admin-chart .bar.is-today { fill: #f0b429; }
.admin-chart .bar-value {
    fill: var(--ad-text);
    font-size: 11px;
    font-weight: 700;
}

.admin-chart .label-today {
    fill: var(--ad-text);
    font-weight: 700;
}

.admin-chart-empty {
    padding: 35px 12px;
    text-align: center;
    color: var(--ad-muted);
    font-size: 13px;
    line-height: 1.7;
}

.admin-donut-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 18px;
}

.admin-donut {
    display: block;
    width: 100%;
    max-width: 190px;
}

.admin-donut .track {
    fill: none;
    stroke: #e6ede8;
    stroke-width: 26;
}

.admin-donut .segment {
    fill: none;
    stroke-width: 26;
}

.admin-donut .donut-angka {
    font-size: 29px;
    font-weight: 750;
    fill: var(--ad-green);
}

.admin-donut .donut-label {
    font-size: 11px;
    fill: var(--ad-muted);
}

.admin-legend {
    width: 100%;
    margin: 0;
    padding: 0;
    list-style: none;
}

.admin-legend li {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 10px 0;
    border-bottom: 1px solid #edf2ee;
    font-size: 12px;
}

.admin-legend li:last-child { border-bottom: 0; }
.admin-legend-dot {
    width: 10px;
    height: 10px;
    flex-shrink: 0;
    border-radius: 3px;
}

.admin-legend-name {
    flex: 1;
    min-width: 0;
    overflow-wrap: anywhere;
}

.admin-legend-count { font-weight: 700; }
.admin-legend-persen {
    min-width: 44px;
    text-align: right;
    color: var(--ad-muted);
}

.admin-table-wrap { width: 100%; overflow-x: auto; }

.admin-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

.admin-table th {
    background: var(--ad-soft);
    color: var(--ad-muted);
    font-size: 12px;
    font-weight: 700;
}

.admin-table th,
.admin-table td {
    padding: 13px;
    border-bottom: 1px solid #edf2ee;
    text-align: left;
    font-size: 12px;
}

.admin-table tbody tr:last-child td { border-bottom: 0; }
.admin-table tbody tr:hover { background: #f8fbf9; }

.admin-plate {
    display: inline-block;
    padding: 4px 8px;
    border: 1px solid var(--ad-green);
    border-radius: 6px;
    color: var(--ad-green);
    font-weight: 750;
}

.admin-badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 750;
}

.admin-badge-active {
    background: #fef3c7;
    color: #854d0e;
}

.admin-badge-done {
    background: #dcfce7;
    color: #166534;
}

.admin-empty {
    padding: 28px !important;
    text-align: center !important;
    color: var(--ad-muted);
}

@media (max-width: 1100px) {
    .admin-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .admin-charts { grid-template-columns: 1fr; }
}

@media (max-width: 600px) {
    .admin-welcome h1 { font-size: 25px; }
    .admin-cards,
    .admin-area-grid { grid-template-columns: 1fr; }
    .admin-stat,
    .admin-area-card,
    .admin-panel { padding: 17px; }
    .admin-stat-value { font-size: 27px; }
}
</style>

<div class="admin-dashboard">

    <section class="admin-welcome">
        <div>
            <h1>Dashboard Admin</h1>
            <p>
                Selamat datang, <?= e($namaAdmin) ?>.
                Berikut ringkasan sistem parkir hari ini.
            </p>
        </div>

        <div class="admin-date"><?= e($tanggalHariIni) ?></div>
    </section>

    <!-- Statistik utama -->
    <section class="admin-cards">

        <div class="admin-stat admin-stat-hero">
            <div class="admin-stat-label">Total Pendapatan</div>
            <div class="admin-stat-value"><?= rupiah($pendapatan) ?></div>
            <div class="admin-stat-note">Transaksi parkir yang sudah selesai</div>
        </div>

        <div class="admin-stat">
            <div class="admin-stat-label">Parkir Aktif</div>
            <div class="admin-stat-value">
                <?= number_format($parkirAktif, 0, ',', '.') ?>
            </div>

            <?php if ($persenOkupansi !== null): ?>
                <div class="admin-meter <?= e($kelasOkupansi) ?>">
                    <div style="width:<?= dashAngka($persenOkupansi, 1) ?>%"></div>
                </div>
                <div class="admin-stat-note">
                    <?= number_format($persenOkupansi, 0, ',', '.') ?>%
                    dari <?= $kapasitasTotal ?> slot
                </div>
            <?php else: ?>
                <div class="admin-stat-note">Kendaraan yang belum keluar</div>
            <?php endif; ?>
        </div>

        <div class="admin-stat">
            <div class="admin-stat-label">Total Kendaraan</div>
            <div class="admin-stat-value">
                <?= number_format($totalKendaraan, 0, ',', '.') ?>
            </div>
            <div class="admin-stat-note">Kendaraan terdaftar</div>
        </div>

        <div class="admin-stat">
            <div class="admin-stat-label">Total Pengguna</div>
            <div class="admin-stat-value">
                <?= number_format($totalPengguna, 0, ',', '.') ?>
            </div>
            <div class="admin-stat-note">Akun pengguna sistem</div>
        </div>

    </section>

    <!-- Area parkir -->
    <section class="admin-area-grid">

        <?php
        $daftarArea = [
            [
                'nama' => 'Area A',
                'jenis' => 'Khusus Motor',
                'kolom' => 'jumlah_motor'
            ],
            [
                'nama' => 'Area B',
                'jenis' => 'Khusus Mobil',
                'kolom' => 'jumlah_mobil'
            ]
        ];
        ?>

        <?php foreach ($daftarArea as $item): ?>
            <?php
            $area = $dataArea[$item['nama']] ?? [];
            $kapasitas = (int) ($area['kapasitas'] ?? 0);

            /*
             * Jumlah kendaraan aktif dihitung dari transaksi yang
             * memiliki status masuk dan jenis yang sesuai area.
             */
            $jumlah = (int) ($area[$item['kolom']] ?? 0);

            $persen = $kapasitas > 0
                ? min(100, ($jumlah / $kapasitas) * 100)
                : 0;
            ?>

            <div class="admin-area-card">
                <div class="admin-area-top">
                    <div>
                        <h2><?= e($item['nama']) ?></h2>
                        <p><?= e($item['jenis']) ?></p>
                    </div>

                    <span class="admin-area-tag">
                        <?= $area ? 'Area Terdaftar' : 'Area Belum Ada' ?>
                    </span>
                </div>

                <div class="admin-area-value">
                    <?= number_format($jumlah, 0, ',', '.') ?>
                    <span>
                        / <?= number_format($kapasitas, 0, ',', '.') ?> kendaraan
                    </span>
                </div>

                <div class="admin-area-progress">
                    <div style="width:<?= dashAngka($persen, 1) ?>%"></div>
                </div>

                <div class="admin-area-detail">
                    <span>Kapasitas terisi</span>
                    <strong><?= number_format($persen, 0, ',', '.') ?>%</strong>
                </div>
            </div>
        <?php endforeach; ?>

    </section>

    <!-- Grafik -->
    <section class="admin-charts">

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2>Pendapatan 7 Hari Terakhir</h2>
                    <p>Pendapatan berdasarkan waktu kendaraan keluar.</p>
                </div>

                <div class="admin-chip">
                    <?= rupiah($totalTujuhHari) ?>
                </div>
            </div>

            <?php if (!$adaPendapatan): ?>
                <div class="admin-chart-empty">
                    Belum ada pendapatan dalam tujuh hari terakhir.
                </div>
            <?php else: ?>
                <div class="admin-chart-scroll">
                    <svg class="admin-chart"
                         viewBox="0 0 <?= $svgLebar ?> <?= $svgTinggi ?>"
                         role="img"
                         aria-label="Grafik pendapatan parkir selama tujuh hari">

                        <?php for ($k = 0; $k <= 4; $k++):
                            $yGaris = $garisDasar - ($areaTinggi * $k / 4);
                            $nilaiGaris = $skalaMaks * $k / 4;
                        ?>
                            <line
                                class="<?= $k === 0 ? 'base-line' : 'grid-line' ?>"
                                x1="<?= $margKiri ?>"
                                x2="<?= $svgLebar - $margKanan ?>"
                                y1="<?= dashAngka($yGaris) ?>"
                                y2="<?= dashAngka($yGaris) ?>"
                            />

                            <text
                                x="<?= $margKiri - 9 ?>"
                                y="<?= dashAngka($yGaris + 4) ?>"
                                text-anchor="end"
                            ><?= e(dashRupiahRingkas($nilaiGaris)) ?></text>
                        <?php endfor; ?>

                        <?php foreach ($seriPendapatan as $i => $s):
                            $hariIniBar = ($i === count($seriPendapatan) - 1);

                            $tinggiBar = $s['nilai'] > 0
                                ? max(3, ($s['nilai'] / $skalaMaks) * $areaTinggi)
                                : 0;

                            $x = $margKiri
                                + ($lebarSlot * $i)
                                + (($lebarSlot - $lebarBar) / 2);

                            $y = $garisDasar - $tinggiBar;
                            $tengah = $x + ($lebarBar / 2);
                        ?>
                            <?php if ($tinggiBar > 0): ?>
                                <rect
                                    class="bar<?= $hariIniBar ? ' is-today' : '' ?>"
                                    x="<?= dashAngka($x) ?>"
                                    y="<?= dashAngka($y) ?>"
                                    width="<?= dashAngka($lebarBar) ?>"
                                    height="<?= dashAngka($tinggiBar) ?>"
                                    rx="5"
                                >
                                    <title>
                                        <?= e($s['label'] . ' ' . $s['tanggal'] . ': ' . rupiah($s['nilai'])) ?>
                                    </title>
                                </rect>

                                <text
                                    class="bar-value"
                                    x="<?= dashAngka($tengah) ?>"
                                    y="<?= dashAngka($y - 7) ?>"
                                    text-anchor="middle"
                                ><?= e(dashRupiahRingkas($s['nilai'])) ?></text>
                            <?php endif; ?>

                            <text
                                class="<?= $hariIniBar ? 'label-today' : '' ?>"
                                x="<?= dashAngka($tengah) ?>"
                                y="<?= $svgTinggi - 12 ?>"
                                text-anchor="middle"
                            ><?= e($s['label']) ?></text>
                        <?php endforeach; ?>
                    </svg>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2>Jenis Kendaraan</h2>
                    <p>Komposisi kendaraan yang terdaftar.</p>
                </div>
            </div>

            <?php if ($totalJenis === 0): ?>
                <div class="admin-chart-empty">
                    Belum ada data kendaraan terdaftar.
                </div>
            <?php else: ?>
                <div class="admin-donut-wrap">

                    <svg class="admin-donut"
                         viewBox="0 0 200 200"
                         role="img"
                         aria-label="Grafik komposisi kendaraan">

                        <circle class="track" cx="100" cy="100" r="70"/>

                        <?php
                        $akum = 0.0;
                        $banyakSegmen = count($segmenJenis);

                        foreach ($segmenJenis as $sg):
                            $panjang = max(
                                0,
                                $sg['persen'] - ($banyakSegmen > 1 ? 0.8 : 0)
                            );
                        ?>
                            <circle
                                class="segment"
                                cx="100"
                                cy="100"
                                r="70"
                                pathLength="100"
                                stroke="<?= e($sg['warna']) ?>"
                                stroke-dasharray="<?= dashAngka($panjang, 3) ?> <?= dashAngka(100 - $panjang, 3) ?>"
                                stroke-dashoffset="<?= dashAngka(-$akum, 3) ?>"
                                transform="rotate(-90 100 100)"
                            >
                                <title>
                                    <?= e($sg['nama'] . ': ' . $sg['jumlah'] . ' kendaraan') ?>
                                </title>
                            </circle>
                        <?php
                            $akum += $sg['persen'];
                        endforeach;
                        ?>

                        <text class="donut-angka"
                              x="100" y="102"
                              text-anchor="middle">
                            <?= number_format($totalJenis, 0, ',', '.') ?>
                        </text>

                        <text class="donut-label"
                              x="100" y="120"
                              text-anchor="middle">kendaraan</text>
                    </svg>

                    <ul class="admin-legend">
                        <?php foreach ($segmenJenis as $sg): ?>
                            <li>
                                <span
                                    class="admin-legend-dot"
                                    style="background:<?= e($sg['warna']) ?>"
                                ></span>

                                <span class="admin-legend-name">
                                    <?= e($sg['nama']) ?>
                                </span>

                                <span class="admin-legend-count">
                                    <?= number_format($sg['jumlah'], 0, ',', '.') ?>
                                </span>

                                <span class="admin-legend-persen">
                                    <?= number_format($sg['persen'], 1, ',', '.') ?>%
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                </div>
            <?php endif; ?>
        </div>

    </section>

    <!-- Transaksi terbaru -->
    <section class="admin-panel">
        <div class="admin-panel-header">
            <div>
                <h2>Transaksi Terbaru</h2>
                <p>Lima transaksi parkir terakhir yang tercatat.</p>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Plat Nomor</th>
                        <th>Jenis Kendaraan</th>
                        <th>Area</th>
                        <th>Waktu Masuk</th>
                        <th>Waktu Keluar</th>
                        <th>Status</th>
                        <th>Biaya</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($terbaru)): ?>
                    <tr>
                        <td colspan="8" class="admin-empty">
                            Belum ada transaksi parkir.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($terbaru as $i => $t): ?>
                        <?php $sudahKeluar = ($t['status'] === 'keluar'); ?>

                        <tr>
                            <td><?= $i + 1 ?></td>

                            <td>
                                <span class="admin-plate">
                                    <?= e($t['plat_nomor'] ?? '-') ?>
                                </span>
                            </td>

                            <td>
                                <?= e($t['jenis_kendaraan'] ?? '-') ?>
                            </td>

                            <td><?= e($t['nama_area'] ?? '-') ?></td>

                            <td><?= e($t['waktu_masuk'] ?? '-') ?></td>

                            <td>
                                <?= !empty($t['waktu_keluar'])
                                    ? e($t['waktu_keluar'])
                                    : '-' ?>
                            </td>

                            <td>
                                <span class="admin-badge <?= $sudahKeluar
                                    ? 'admin-badge-done'
                                    : 'admin-badge-active' ?>">
                                    <?= $sudahKeluar ? 'Selesai' : 'Parkir' ?>
                                </span>
                            </td>

                            <td>
                                <?= $sudahKeluar
                                    ? rupiah($t['biaya_total'] ?? 0)
                                    : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<?php require __DIR__ . '/footer.php'; ?>
