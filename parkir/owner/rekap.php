
<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['owner']);

$judul = 'Rekap Transaksi';

function rupiahRekap($nilai): string
{
    return 'Rp ' . number_format((float) ($nilai ?? 0), 0, ',', '.');
}

function tanggalValidRekap($tanggal): bool
{
    if (!is_string($tanggal)) {
        return false;
    }

    $hasil = DateTime::createFromFormat('!Y-m-d', $tanggal);

    return $hasil !== false
        && $hasil->format('Y-m-d') === $tanggal;
}

function formatTanggalRekap($tanggal, $denganWaktu = true): string
{
    if (empty($tanggal)) {
        return '-';
    }

    try {
        $waktu = new DateTime((string) $tanggal);

        return $waktu->format(
            $denganWaktu ? 'd-m-Y H:i' : 'd-m-Y'
        );
    } catch (Throwable $e) {
        return '-';
    }
}

$tanggalHariIni = date('Y-m-d');
$tanggalAwalDefault = date('Y-m-01');

$tanggalAwal = $_GET['awal'] ?? $tanggalAwalDefault;
$tanggalAkhir = $_GET['akhir'] ?? $tanggalHariIni;

if (
    !tanggalValidRekap($tanggalAwal) ||
    !tanggalValidRekap($tanggalAkhir) ||
    $tanggalAwal > $tanggalAkhir ||
    $tanggalAkhir > $tanggalHariIni
) {
    $tanggalAwal = $tanggalAwalDefault;
    $tanggalAkhir = $tanggalHariIni;
}

$data = [];
$totalPendapatan = 0;
$totalSelesai = 0;
$totalAktif = 0;
$pesanError = '';

try {
    /*
     * Mengambil transaksi berdasarkan waktu masuk.
     * Batas akhir menggunakan hari berikutnya agar seluruh
     * transaksi pada tanggal akhir ikut terambil.
     */
    $stmt = $pdo->prepare("
        SELECT
            t.id_parkir,
            t.waktu_masuk,
            t.waktu_keluar,
            t.durasi_jam,
            t.biaya_total,
            t.status,
            k.plat_nomor,
            k.jenis_kendaraan,
            a.nama_area
        FROM tb_transaksi t
        INNER JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
        LEFT JOIN tb_area_parkir a
            ON a.id_area = t.id_area
        WHERE t.waktu_masuk >= ?
          AND t.waktu_masuk < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY t.id_parkir DESC
    ");

    $stmt->execute([$tanggalAwal, $tanggalAkhir]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($data as $transaksi) {
        $status = strtolower((string) ($transaksi['status'] ?? ''));

        if ($status === 'keluar') {
            $totalPendapatan += (float) ($transaksi['biaya_total'] ?? 0);
            $totalSelesai++;
        } elseif ($status === 'masuk') {
            $totalAktif++;
        }
    }
} catch (Throwable $e) {
    error_log('Rekap Transaksi Owner: ' . $e->getMessage());

    $data = [];
    $totalPendapatan = 0;
    $totalSelesai = 0;
    $totalAktif = 0;

    $pesanError = 'Data transaksi gagal dimuat. Periksa koneksi dan struktur tabel database.';
}

$jumlahTransaksi = count($data);

require __DIR__ . '/header.php';
?>

<style>
.rekap-page {
    --rekap-green: #0c3b2e;
    --rekap-green-2: #14513f;
    --rekap-sign: #f0b429;
    --rekap-text: #16241e;
    --rekap-muted: #62736a;
    --rekap-line: #d9e2dc;
    --rekap-soft: #f3f7f4;

    color: var(--rekap-text);
    padding-bottom: 25px;
    min-width: 0;
}

.rekap-page *,
.rekap-page *::before,
.rekap-page *::after {
    box-sizing: border-box;
}

.rekap-page svg {
    display: block;
    flex-shrink: 0;
}

.rekap-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.rekap-heading h1 {
    margin: 0 0 7px;
    color: var(--rekap-green);
    font-family: var(--adm-display, system-ui, sans-serif);
    font-size: 25px;
    font-weight: 700;
    letter-spacing: -.02em;
}

.rekap-heading p {
    margin: 0;
    color: var(--rekap-muted);
    font-size: 13px;
    line-height: 1.7;
}

.rekap-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 13px;
    border: 1px solid #f3dea0;
    border-radius: 20px;
    background: #fdf1cf;
    color: #8a5a00;
    font-size: 12px;
    font-weight: 700;
}

.rekap-label::before {
    content: "";
    width: 7px;
    height: 7px;
    flex-shrink: 0;
    border-radius: 50%;
    background: currentColor;
}

.rekap-alert {
    margin-bottom: 20px;
    padding: 13px 16px;
    border: 1px solid #f3c7c7;
    border-left: 4px solid #c0392b;
    border-radius: 10px;
    background: #fdeeee;
    color: #a12b2b;
    font-size: 13px;
    line-height: 1.6;
}

.rekap-filter {
    margin-bottom: 22px;
    padding: 22px;
    border: 1px solid var(--rekap-line);
    border-radius: 15px;
    background: #fff;
}

.rekap-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 18px;
    color: var(--rekap-green);
    font-size: 16px;
    font-weight: 700;
}

.rekap-section-title::before {
    content: "";
    width: 4px;
    height: 20px;
    flex-shrink: 0;
    border-radius: 4px;
    background: var(--rekap-sign);
}

.rekap-filter-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
    align-items: end;
    gap: 16px;
}

.rekap-field {
    min-width: 0;
}

.rekap-field label {
    display: block;
    margin-bottom: 8px;
    color: var(--rekap-text);
    font-size: 12px;
    font-weight: 700;
}

.rekap-field input {
    display: block;
    width: 100%;
    min-width: 0;
    min-height: 43px;
    padding: 10px 12px;
    border: 1px solid var(--rekap-line);
    border-radius: 9px;
    outline: none;
    background: #f8faf8;
    color: var(--rekap-text);
    font: inherit;
    font-size: 13px;
}

.rekap-field input:focus {
    border-color: var(--rekap-green-2);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(20, 81, 63, .14);
}

.rekap-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
}

.rekap-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 43px;
    padding: 11px 15px;
    border: 0;
    border-radius: 9px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
    transition: background .2s ease;
}

.rekap-btn svg {
    width: 16px;
    height: 16px;
}

.rekap-btn:focus-visible {
    outline: 3px solid var(--rekap-green);
    outline-offset: 2px;
}

.rekap-btn-primary {
    background: var(--rekap-sign);
    color: var(--rekap-green);
}

.rekap-btn-primary:hover {
    background: #f5c24a;
}

.rekap-btn-print {
    border: 1px solid var(--rekap-line);
    background: #e9f2ec;
    color: var(--rekap-green-2);
}

.rekap-btn-print:hover {
    background: #dcebe2;
}

.rekap-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}

.rekap-stat {
    position: relative;
    overflow: hidden;
    min-width: 0;
    padding: 22px;
    border: 1px solid var(--rekap-line);
    border-radius: 15px;
    background: #fff;
}

.rekap-stat::after {
    position: absolute;
    top: -30px;
    right: -28px;
    width: 85px;
    height: 85px;
    border-radius: 50%;
    background: rgba(20, 81, 63, .07);
    content: "";
    pointer-events: none;
}

.rekap-stat.yellow::after {
    background: rgba(240, 180, 41, .16);
}

.rekap-stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 17px;
}

.rekap-stat-label {
    color: var(--rekap-muted);
    font-size: 12px;
    font-weight: 600;
}

.rekap-stat-icon {
    position: relative;
    z-index: 1;
    display: grid;
    place-items: center;
    width: 39px;
    height: 39px;
    flex-shrink: 0;
    border-radius: 11px;
    background: #e3f1e9;
    color: var(--rekap-green-2);
}

.rekap-stat-icon svg {
    width: 20px;
    height: 20px;
}

.rekap-stat.yellow .rekap-stat-icon {
    background: #fdf1cf;
    color: #8a5a00;
}

.rekap-stat-value {
    color: var(--rekap-green);
    font-size: clamp(21px, 2vw, 28px);
    font-weight: 800;
    line-height: 1.4;
    letter-spacing: -.02em;
    overflow-wrap: anywhere;
}

.rekap-stat.yellow .rekap-stat-value {
    color: #7a4e00;
}

.rekap-stat-note {
    margin-top: 7px;
    color: var(--rekap-muted);
    font-size: 11px;
    line-height: 1.6;
}

.rekap-stat.hero {
    border-color: var(--rekap-green);
    background: var(--rekap-green);
}

.rekap-stat.hero::after {
    background: rgba(255, 255, 255, .06);
}

.rekap-stat.hero .rekap-stat-label,
.rekap-stat.hero .rekap-stat-note {
    color: rgba(234, 243, 237, .8);
}

.rekap-stat.hero .rekap-stat-icon {
    background: rgba(240, 180, 41, .18);
    color: var(--rekap-sign);
}

.rekap-stat.hero .rekap-stat-value {
    color: var(--rekap-sign);
    font-size: clamp(19px, 2vw, 25px);
}

.rekap-report {
    margin-bottom: 22px;
    overflow: hidden;
    border: 1px solid var(--rekap-line);
    border-radius: 15px;
    background: #fff;
}

.rekap-report-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 22px;
    border-bottom: 1px solid #edf2ee;
    flex-wrap: wrap;
}

.rekap-report-header h2 {
    margin: 0 0 6px;
    color: var(--rekap-green);
    font-size: 17px;
    font-weight: 700;
}

.rekap-report-header p {
    margin: 0;
    color: var(--rekap-muted);
    font-size: 12px;
    line-height: 1.7;
}

.rekap-count {
    padding: 8px 11px;
    border: 1px solid #cfe2d6;
    border-radius: 8px;
    background: #e9f2ec;
    color: var(--rekap-green-2);
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.rekap-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.rekap-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
    text-align: left;
}

.rekap-table thead {
    background: var(--rekap-soft);
}

.rekap-table th {
    padding: 14px 16px;
    border-bottom: 1px solid var(--rekap-line);
    color: var(--rekap-muted);
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.rekap-table td {
    padding: 15px 16px;
    border-bottom: 1px solid #edf2ee;
    color: var(--rekap-text);
    font-size: 12px;
    vertical-align: middle;
    white-space: nowrap;
}

.rekap-table tbody tr:hover {
    background: #f8fbf9;
}

.rekap-table tbody tr:last-child td {
    border-bottom: 0;
}

.rekap-number {
    color: #8a9a91 !important;
    font-weight: 700;
}

.rekap-plat {
    display: inline-block;
    padding: 4px 8px;
    border: 1.5px solid var(--rekap-green);
    border-radius: 6px;
    background: #fff;
    color: var(--rekap-green);
    font-weight: 700;
    letter-spacing: .04em;
}

.rekap-biaya {
    color: var(--rekap-green-2) !important;
    font-weight: 800;
}

.rekap-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.rekap-status::before {
    content: "";
    width: 6px;
    height: 6px;
    flex-shrink: 0;
    border-radius: 50%;
    background: currentColor;
}

.rekap-status-keluar {
    background: #dcf3e6;
    color: #14653f;
}

.rekap-status-masuk {
    background: #fdf1cf;
    color: #8a5a00;
}

.rekap-status-lain {
    background: #eef2f7;
    color: #475569;
}

.rekap-empty {
    padding: 45px 20px !important;
    color: var(--rekap-muted) !important;
    text-align: center !important;
    white-space: normal !important;
}

.rekap-empty svg {
    width: 28px;
    height: 28px;
    margin: 0 auto 10px;
    color: #a8b8ae;
}

.rekap-empty strong {
    color: var(--rekap-green);
}

.rekap-footnote {
    padding: 14px 22px;
    border-top: 1px solid #edf2ee;
    background: #fafcfa;
    color: var(--rekap-muted);
    font-size: 11px;
    line-height: 1.7;
}

@media (max-width: 1000px) {
    .rekap-filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .rekap-actions {
        grid-column: 1 / -1;
    }
}

@media (max-width: 760px) {
    .rekap-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .rekap-stat.hero {
        grid-column: 1 / -1;
    }
}

@media (max-width: 560px) {
    .rekap-heading h1 {
        font-size: 22px;
    }

    .rekap-filter {
        padding: 17px;
    }

    .rekap-filter-grid,
    .rekap-stats {
        grid-template-columns: 1fr;
    }

    .rekap-actions {
        grid-column: auto;
    }

    .rekap-actions .rekap-btn {
        flex: 1;
    }

    .rekap-stat.hero {
        grid-column: auto;
    }

    .rekap-stat {
        padding: 18px;
    }

    .rekap-report-header {
        padding: 18px;
    }

    .rekap-footnote {
        padding: 14px 18px;
    }
}

@media print {
    @page {
        size: landscape;
        margin: 12mm;
    }

    body {
        background: #fff !important;
        color: #111827 !important;
    }

    .no-print,
    aside,
    nav,
    .sidebar {
        display: none !important;
    }

    .rekap-page {
        margin: 0 !important;
        padding: 0 !important;
    }

    .rekap-heading {
        margin-bottom: 15px;
    }

    .rekap-heading h1 {
        font-size: 20px;
    }

    .rekap-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .rekap-stat,
    .rekap-report {
        box-shadow: none !important;
        break-inside: avoid;
    }

    .rekap-stat.hero {
        border-color: #cbd5d0 !important;
        background: #fff !important;
    }

    .rekap-stat.hero .rekap-stat-label,
    .rekap-stat.hero .rekap-stat-note {
        color: #475569 !important;
    }

    .rekap-stat.hero .rekap-stat-value {
        color: #0c3b2e !important;
    }

    .rekap-table-wrap {
        overflow: visible !important;
    }

    .rekap-table {
        width: 100% !important;
        min-width: 0 !important;
    }

    .rekap-table th,
    .rekap-table td {
        padding: 7px 8px;
        font-size: 9px;
        color: #111827 !important;
    }

    .rekap-table th {
        background: #f1f5f9 !important;
    }

    .rekap-report-header {
        padding: 12px 15px;
    }

    .rekap-footnote {
        background: #fff !important;
    }
}
</style>

<div class="rekap-page">

    <div class="rekap-heading">
        <div>
            <h1>Rekap Transaksi</h1>
            <p>Ringkasan aktivitas dan pendapatan parkir berdasarkan periode.</p>
        </div>

        <div class="rekap-label">Laporan Parkir Utama</div>
    </div>

    <?php if ($pesanError !== ''): ?>
        <div class="rekap-alert" role="alert">
            <?= e($pesanError) ?>
        </div>
    <?php endif; ?>

    <div class="rekap-filter no-print">
        <h2 class="rekap-section-title">Filter Periode Transaksi</h2>

        <form method="GET" class="rekap-filter-grid">
            <div class="rekap-field">
                <label for="awal">Tanggal Awal</label>
                <input
                    type="date"
                    id="awal"
                    name="awal"
                    value="<?= e($tanggalAwal) ?>"
                    max="<?= e($tanggalHariIni) ?>"
                    required
                >
            </div>

            <div class="rekap-field">
                <label for="akhir">Tanggal Akhir</label>
                <input
                    type="date"
                    id="akhir"
                    name="akhir"
                    value="<?= e($tanggalAkhir) ?>"
                    min="<?= e($tanggalAwal) ?>"
                    max="<?= e($tanggalHariIni) ?>"
                    required
                >
            </div>

            <div class="rekap-actions">
                <button class="rekap-btn rekap-btn-primary" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="m20 20-3.5-3.5"/>
                    </svg>
                    Tampilkan
                </button>

                <button
                    class="rekap-btn rekap-btn-print"
                    type="button"
                    onclick="window.print()"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true">
                        <path d="M7 9V3h10v6"/>
                        <path d="M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/>
                        <rect x="7" y="14" width="10" height="7" rx="1"/>
                    </svg>
                    Cetak Rekap
                </button>
            </div>
        </form>
    </div>

    <div class="rekap-stats">
        <div class="rekap-stat">
            <div class="rekap-stat-top">
                <div class="rekap-stat-label">Jumlah Transaksi</div>
                <div class="rekap-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" aria-hidden="true">
                        <rect x="4" y="3" width="16" height="18" rx="2"/>
                        <path d="M8 8h8M8 12h8M8 16h5"/>
                    </svg>
                </div>
            </div>
            <div class="rekap-stat-value">
                <?= number_format($jumlahTransaksi, 0, ',', '.') ?>
            </div>
            <div class="rekap-stat-note">
                Seluruh transaksi pada periode terpilih
            </div>
        </div>

        <div class="rekap-stat yellow">
            <div class="rekap-stat-top">
                <div class="rekap-stat-label">Transaksi Selesai</div>
                <div class="rekap-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="m8 12 3 3 5-6"/>
                    </svg>
                </div>
            </div>
            <div class="rekap-stat-value">
                <?= number_format($totalSelesai, 0, ',', '.') ?>
            </div>
            <div class="rekap-stat-note">
                Kendaraan yang sudah keluar
            </div>
        </div>

        <div class="rekap-stat hero">
            <div class="rekap-stat-top">
                <div class="rekap-stat-label">Total Pendapatan</div>
                <div class="rekap-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" aria-hidden="true">
                        <rect x="3" y="6" width="18" height="13" rx="2"/>
                        <path d="M3 10h18M7 15h4"/>
                    </svg>
                </div>
            </div>
            <div class="rekap-stat-value">
                <?= rupiahRekap($totalPendapatan) ?>
            </div>
            <div class="rekap-stat-note">
                Dari transaksi yang sudah selesai
            </div>
        </div>
    </div>

    <div class="rekap-report">
        <div class="rekap-report-header">
            <div>
                <h2>Laporan Transaksi Parkir</h2>
                <p>
                    Periode
                    <?= e(formatTanggalRekap($tanggalAwal, false)) ?>
                    sampai
                    <?= e(formatTanggalRekap($tanggalAkhir, false)) ?>
                </p>
            </div>

            <div class="rekap-count">
                <?= number_format($jumlahTransaksi, 0, ',', '.') ?>
                transaksi
            </div>
        </div>

        <div class="rekap-table-wrap">
            <table class="rekap-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Plat Nomor</th>
                        <th>Jenis Kendaraan</th>
                        <th>Area Parkir</th>
                        <th>Waktu Masuk</th>
                        <th>Waktu Keluar</th>
                        <th>Durasi</th>
                        <th>Biaya</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($data): ?>
                    <?php foreach ($data as $i => $t): ?>
                        <?php
                        $status = strtolower((string) ($t['status'] ?? ''));

                        if ($status === 'keluar') {
                            $labelStatus = 'Selesai';
                            $kelasStatus = 'rekap-status-keluar';
                        } elseif ($status === 'masuk') {
                            $labelStatus = 'Parkir Aktif';
                            $kelasStatus = 'rekap-status-masuk';
                        } else {
                            $labelStatus = $status !== ''
                                ? ucfirst($status)
                                : 'Tidak diketahui';
                            $kelasStatus = 'rekap-status-lain';
                        }
                        ?>
                        <tr>
                            <td class="rekap-number"><?= $i + 1 ?></td>

                            <td>
                                <span class="rekap-plat">
                                    <?= e($t['plat_nomor'] ?? '-') ?>
                                </span>
                            </td>

                            <td><?= e($t['jenis_kendaraan'] ?? '-') ?></td>
                            <td><?= e($t['nama_area'] ?? '-') ?></td>

                            <td>
                                <?= e(formatTanggalRekap($t['waktu_masuk'] ?? null)) ?>
                            </td>

                            <td>
                                <?= e(formatTanggalRekap($t['waktu_keluar'] ?? null)) ?>
                            </td>

                            <td>
                                <?= (int) ($t['durasi_jam'] ?? 0) ?> jam
                            </td>

                            <td class="rekap-biaya">
                                <?= rupiahRekap($t['biaya_total'] ?? 0) ?>
                            </td>

                            <td>
                                <span class="rekap-status <?= e($kelasStatus) ?>">
                                    <?= e($labelStatus) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="rekap-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.6" aria-hidden="true">
                                <rect x="4" y="3" width="16" height="18" rx="2"/>
                                <path d="M8 8h8M8 12h8M8 16h5"/>
                            </svg>
                            <strong>Tidak ada transaksi</strong>
                            <div style="margin-top:6px">
                                Belum ada transaksi pada periode tanggal yang dipilih.
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="rekap-footnote">
            Laporan dibuat pada <?= e(date('d-m-Y H:i')) ?>.
            Pendapatan hanya menghitung transaksi berstatus selesai.
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const awal = document.getElementById('awal');
    const akhir = document.getElementById('akhir');

    if (!awal || !akhir) {
        return;
    }

    awal.addEventListener('change', function () {
        akhir.min = awal.value;

        if (akhir.value && akhir.value < awal.value) {
            akhir.value = awal.value;
        }
    });

    akhir.addEventListener('change', function () {
        awal.max = akhir.value;

        if (awal.value && awal.value > akhir.value) {
            awal.value = akhir.value;
        }
    });
});
</script>

<?php require __DIR__ . '/footer.php'; ?>
