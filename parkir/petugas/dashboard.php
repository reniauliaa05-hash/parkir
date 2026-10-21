
<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['petugas']);

$judul = 'Dashboard Petugas';

/*
|--------------------------------------------------------------------------
| Fungsi bantuan
|--------------------------------------------------------------------------
*/
function formatRupiahPetugas($angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function tanggalIndonesia(): string
{
    $hari = [
        'Minggu', 'Senin', 'Selasa', 'Rabu',
        'Kamis', 'Jumat', 'Sabtu'
    ];

    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April',
        'Mei', 'Juni', 'Juli', 'Agustus',
        'September', 'Oktober', 'November', 'Desember'
    ];

    $sekarang = new DateTime();

    return $hari[(int) $sekarang->format('w')] . ', '
        . $sekarang->format('j') . ' '
        . $bulan[(int) $sekarang->format('n')] . ' '
        . $sekarang->format('Y');
}

function durasiParkir(?string $masuk, ?string $keluar = null): string
{
    if (empty($masuk)) {
        return '-';
    }

    try {
        $awal = new DateTime($masuk);
        $akhir = $keluar ? new DateTime($keluar) : new DateTime();

        $menit = max(
            0,
            (int) floor(
                ($akhir->getTimestamp() - $awal->getTimestamp()) / 60
            )
        );
    } catch (Throwable $e) {
        return '-';
    }

    if ($menit < 60) {
        return $menit . ' menit';
    }

    $jam = intdiv($menit, 60);
    $sisaMenit = $menit % 60;

    if ($jam >= 24) {
        $hari = intdiv($jam, 24);
        $sisaJam = $jam % 24;

        return $hari . ' hari ' . $sisaJam . ' jam';
    }

    return $jam . ' jam ' . $sisaMenit . ' menit';
}

/*
|--------------------------------------------------------------------------
| Inisialisasi data dashboard
|--------------------------------------------------------------------------
*/
$totalKendaraan = 0;
$parkirAktif = 0;
$transaksiMasukHariIni = 0;
$transaksiKeluarHariIni = 0;
$pendapatanHariIni = 0;
$kapasitas = 0;
$terisiAreaMotor = 0;
$terisiAreaMobil = 0;
$kapasitasMotor = 0;
$kapasitasMobil = 0;
$terbaru = [];
$pesanError = '';

/*
|--------------------------------------------------------------------------
| Ambil statistik dan transaksi
|--------------------------------------------------------------------------
*/
try {
    $totalKendaraan = (int) $pdo->query(
        "SELECT COUNT(*) FROM tb_kendaraan"
    )->fetchColumn();

    $parkirAktif = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM tb_transaksi
         WHERE status = 'masuk'"
    )->fetchColumn();

    $transaksiMasukHariIni = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM tb_transaksi
         WHERE waktu_masuk >= CURDATE()
           AND waktu_masuk < CURDATE() + INTERVAL 1 DAY"
    )->fetchColumn();

    $transaksiKeluarHariIni = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM tb_transaksi
         WHERE status = 'keluar'
           AND waktu_keluar >= CURDATE()
           AND waktu_keluar < CURDATE() + INTERVAL 1 DAY"
    )->fetchColumn();

    $pendapatanHariIni = (float) $pdo->query(
        "SELECT COALESCE(SUM(biaya_total), 0)
         FROM tb_transaksi
         WHERE status = 'keluar'
           AND waktu_keluar >= CURDATE()
           AND waktu_keluar < CURDATE() + INTERVAL 1 DAY"
    )->fetchColumn();

    $stmt = $pdo->query(
        "SELECT
            t.id_parkir,
            k.plat_nomor,
            k.jenis_kendaraan,
            t.waktu_masuk,
            t.waktu_keluar,
            t.status,
            t.biaya_total,
            a.nama_area
         FROM tb_transaksi t
         INNER JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
         LEFT JOIN tb_area_parkir a
            ON a.id_area = t.id_area
         ORDER BY t.id_parkir DESC
         LIMIT 8"
    );

    $terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Dashboard Petugas: ' . $e->getMessage());

    $pesanError = 'Sebagian data dashboard gagal dimuat. Periksa tabel database.';
}

/*
|--------------------------------------------------------------------------
| Kapasitas dan jumlah kendaraan per area
|--------------------------------------------------------------------------
*/
try {
    $kapasitas = (int) $pdo->query(
        "SELECT COALESCE(SUM(kapasitas), 0)
         FROM tb_area_parkir"
    )->fetchColumn();

    $stmt = $pdo->query(
        "SELECT
            a.id_area,
            a.nama_area,
            a.kapasitas,
            (
                SELECT COUNT(*)
                FROM tb_transaksi t
                WHERE t.id_area = a.id_area
                  AND t.status = 'masuk'
            ) AS terisi
         FROM tb_area_parkir a"
    );

    $areaParkir = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($areaParkir as $area) {
        $namaArea = strtolower(trim($area['nama_area']));
        $jumlahTerisi = (int) $area['terisi'];
        $jumlahKapasitas = (int) $area['kapasitas'];

        if (in_array($namaArea, ['area a', 'area a - motor'], true)) {
            $kapasitasMotor = $jumlahKapasitas;
            $terisiAreaMotor = $jumlahTerisi;
        }

        if (in_array($namaArea, ['area b', 'area b - mobil'], true)) {
            $kapasitasMobil = $jumlahKapasitas;
            $terisiAreaMobil = $jumlahTerisi;
        }
    }

} catch (PDOException $e) {
    error_log('Kapasitas Dashboard Petugas: ' . $e->getMessage());
}

$slotTersedia = max(0, $kapasitas - $parkirAktif);

$persenTerisi = $kapasitas > 0
    ? min(100, ($parkirAktif / $kapasitas) * 100)
    : 0;

$lebarBar = number_format($persenTerisi, 1, '.', '');

if ($kapasitas <= 0) {
    $kelasKapasitas = 'pd-cap-unknown';
    $statusKapasitas = 'Kapasitas belum diatur';
} elseif ($slotTersedia === 0) {
    $kelasKapasitas = 'pd-cap-full';
    $statusKapasitas = 'Parkir penuh';
} elseif ($slotTersedia <= $kapasitas * 0.2) {
    $kelasKapasitas = 'pd-cap-warn';
    $statusKapasitas = 'Hampir penuh';
} else {
    $kelasKapasitas = 'pd-cap-ok';
    $statusKapasitas = 'Slot tersedia';
}

/*
|--------------------------------------------------------------------------
| Sapaan petugas
|--------------------------------------------------------------------------
*/
$namaPetugas = $_SESSION['user']['nama_lengkap']
    ?? $_SESSION['user']['username']
    ?? 'Petugas Parkir';

$jam = (int) date('G');

if ($jam < 11) {
    $sapaan = 'Selamat pagi';
} elseif ($jam < 15) {
    $sapaan = 'Selamat siang';
} elseif ($jam < 18) {
    $sapaan = 'Selamat sore';
} else {
    $sapaan = 'Selamat malam';
}

require_once __DIR__ . '/header.php';
?>

<style>
.pd {
    --green: #0c3b2e;
    --green-light: #14513f;
    --yellow: #f0b429;
    --text: #24352c;
    --muted: #6b7c72;
    --line: #e0e8e2;
    --soft: #f4f7f4;

    width: 100%;
    min-width: 0;
    color: var(--text);
    font-family: 'Figtree', 'Segoe UI', sans-serif;
}

.pd *,
.pd *::before,
.pd *::after {
    box-sizing: border-box;
}

/* Header dashboard */
.pd-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(240px, .8fr);
    align-items: center;
    gap: 24px;
    margin-bottom: 22px;
    padding: 28px;
    overflow: hidden;
    border-radius: 15px;
    background: var(--green);
    color: white;
}

.pd-date {
    margin: 0 0 10px;
    color: #f5cd68;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .5px;
    text-transform: uppercase;
}

.pd-hero h1 {
    margin: 0 0 10px;
    color: #fff;
    font-size: clamp(23px, 2.5vw, 31px);
    font-weight: 700;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.pd-hero-lead {
    max-width: 440px;
    margin: 0;
    color: #d5e5da;
    font-size: 14px;
    line-height: 1.7;
}

.pd-cta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 22px;
}

.pd-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: background .2s ease;
}

.pd-btn svg {
    flex-shrink: 0;
}

.pd-btn-primary {
    background: var(--yellow);
    color: var(--green);
}

.pd-btn-primary:hover {
    background: #f7c94d;
}

.pd-btn-ghost {
    border: 1px solid #6b8a7d;
    color: white;
}

.pd-btn-ghost:hover {
    background: #194c3b;
}

.pd-btn:focus-visible {
    outline: 3px solid white;
    outline-offset: 3px;
}

/* Kapasitas */
.pd-cap {
    padding: 22px;
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 12px;
    background: rgba(255,255,255,.07);
}

.pd-cap-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    color: #d5e5da;
    font-size: 12px;
}

.pd-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.pd-pill::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.pd-cap-ok .pd-pill {
    background: #d9f5e3;
    color: #17683a;
}

.pd-cap-warn .pd-pill {
    background: #fff0bd;
    color: #865800;
}

.pd-cap-full .pd-pill {
    background: #ffe0e0;
    color: #a32929;
}

.pd-cap-unknown .pd-pill {
    background: #e6e9e7;
    color: #47564d;
}

.pd-cap-number {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 8px;
    margin-top: 18px;
    color: #f5cd68;
    font-size: clamp(42px, 5vw, 58px);
    font-weight: 700;
    line-height: 1.1;
}

.pd-cap-number small {
    color: #d5e5da;
    font-size: 13px;
    font-weight: 500;
}

.pd-cap-sub {
    margin: 8px 0 0;
    color: #d5e5da;
    font-size: 12px;
}

.pd-bar {
    height: 7px;
    margin-top: 18px;
    overflow: hidden;
    border-radius: 10px;
    background: rgba(255,255,255,.15);
}

.pd-bar div {
    height: 100%;
    border-radius: inherit;
    background: #a3efbf;
    transition: width .3s ease;
}

.pd-cap-warn .pd-bar div {
    background: var(--yellow);
}

.pd-cap-full .pd-bar div {
    background: #f87171;
}

/* Kartu statistik */
.pd-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}

.pd-stat {
    min-width: 0;
    padding: 18px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(12,59,46,.035);
}

.pd-stat-top {
    display: flex;
    align-items: center;
    gap: 10px;
}

.pd-icon {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 9px;
    background: #e8f2eb;
    color: var(--green);
}

.pd-icon-gold {
    background: #fff3d0;
    color: #8c650b;
}

.pd-stat-label {
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
    line-height: 1.5;
}

.pd-stat-value {
    margin-top: 17px;
    color: var(--green);
    font-size: clamp(23px, 2.2vw, 30px);
    font-weight: 700;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.pd-stat-value.pd-money {
    font-size: clamp(17px, 1.8vw, 23px);
}

.pd-stat-note {
    margin-top: 7px;
    color: #87958c;
    font-size: 11px;
    line-height: 1.5;
}

/* Ringkasan area */
.pd-area-section {
    margin-bottom: 22px;
}

.pd-section-title {
    margin: 0 0 14px;
    color: var(--green);
    font-size: 19px;
    font-weight: 700;
}

.pd-area-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.pd-area-card {
    padding: 19px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #fff;
}

.pd-area-card h3 {
    margin: 0 0 16px;
    color: var(--green);
    font-size: 15px;
}

.pd-area-numbers {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.pd-area-numbers span {
    display: block;
    margin-bottom: 7px;
    color: var(--muted);
    font-size: 11px;
}

.pd-area-numbers strong {
    color: var(--green);
    font-size: 22px;
}

/* Panel transaksi */
.pd-panel {
    min-width: 0;
    padding: 22px;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(12,59,46,.035);
}

.pd-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}

.pd-panel-head h2 {
    margin: 0;
    color: var(--green);
    font-size: 20px;
    font-weight: 700;
}

.pd-panel-head p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 12px;
}

.pd-panel-count {
    padding: 7px 11px;
    border-radius: 7px;
    background: var(--soft);
    color: var(--green);
    font-size: 11px;
    font-weight: 700;
}

.pd-table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--line);
    border-radius: 10px;
}

.pd-table {
    width: 100%;
    min-width: 800px;
    border-collapse: collapse;
    white-space: nowrap;
}

.pd-table th {
    padding: 13px 14px;
    background: #f2f6f3;
    color: #64766b;
    font-size: 11px;
    font-weight: 700;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: .35px;
}

.pd-table td {
    padding: 13px 14px;
    border-top: 1px solid #edf1ee;
    color: var(--text);
    font-size: 12px;
    vertical-align: middle;
}

.pd-table tbody tr:hover td {
    background: #f9fbf9;
}

.pd-num {
    color: #89988f;
    font-variant-numeric: tabular-nums;
}

.pd-right {
    text-align: right !important;
}

.pd-plat {
    display: inline-block;
    padding: 5px 9px;
    border: 1px solid #cbd8ce;
    border-radius: 5px;
    background: #f7faf7;
    color: var(--text);
    font-family: 'Courier New', monospace;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .4px;
}

.pd-jenis {
    color: var(--muted);
    text-transform: capitalize;
}

.pd-time {
    font-variant-numeric: tabular-nums;
}

.pd-time small {
    display: block;
    margin-top: 4px;
    color: #8a9b92;
    font-size: 10px;
}

.pd-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.pd-badge::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.pd-badge-masuk {
    background: #fff1c9;
    color: #896000;
}

.pd-badge-keluar {
    background: #e0f4e6;
    color: #216b3c;
}

.pd-biaya {
    color: var(--green);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pd-biaya-kosong {
    color: #98a49c;
    font-weight: 500;
}

.pd-empty {
    padding: 36px 18px !important;
    color: var(--muted) !important;
    text-align: center !important;
    white-space: normal;
    line-height: 1.6;
}

.pd-empty strong {
    display: block;
    margin-bottom: 5px;
    color: var(--green);
    font-size: 14px;
}

.pd-error {
    margin-bottom: 18px;
    padding: 12px 15px;
    border: 1px solid #f0caca;
    border-radius: 8px;
    background: #fff0f0;
    color: #9c2929;
    font-size: 13px;
    line-height: 1.6;
}

/* Responsif */
@media (max-width: 1100px) {
    .pd-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .pd-hero {
        grid-template-columns: minmax(0, 1fr);
        gap: 22px;
        padding: 22px;
    }

    .pd-area-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .pd-panel {
        padding: 16px;
    }
}

@media (max-width: 480px) {
    .pd-stats {
        grid-template-columns: minmax(0, 1fr);
    }

    .pd-hero {
        padding: 20px 16px;
    }

    .pd-cta {
        flex-direction: column;
    }

    .pd-btn {
        width: 100%;
    }

    .pd-stat {
        padding: 16px;
    }

    .pd-panel-head h2 {
        font-size: 18px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .pd-bar div,
    .pd-btn {
        transition: none;
    }
}
</style>

<div class="pd">

    <?php if ($pesanError !== ''): ?>
        <div class="pd-error" role="alert">
            <?= e($pesanError) ?>
        </div>
    <?php endif; ?>

    <!-- Banner sambutan -->
    <section class="pd-hero">
        <div>
            <p class="pd-date"><?= e(tanggalIndonesia()) ?></p>

            <h1>
                <?= e($sapaan) ?>, <?= e($namaPetugas) ?>
            </h1>

            <p class="pd-hero-lead">
                Pantau kendaraan di area parkir dan kelola
                transaksi masuk maupun keluar dengan mudah.
            </p>

            <div class="pd-cta">
                <a class="pd-btn pd-btn-primary" href="transaksi_masuk.php">
                    <svg width="17" height="17" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true">
                        <path d="M5 12h14M13 6l6 6-6 6"/>
                    </svg>
                    Kendaraan Masuk
                </a>

                <a class="pd-btn pd-btn-ghost" href="transaksi_keluar.php">
                    <svg width="17" height="17" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true">
                        <path d="M19 12H5M11 6l-6 6 6 6"/>
                    </svg>
                    Kendaraan Keluar
                </a>
            </div>
        </div>

        <div class="pd-cap <?= e($kelasKapasitas) ?>">
            <div class="pd-cap-head">
                <span>Kapasitas Parkir</span>
                <span class="pd-pill">
                    <?= e($statusKapasitas) ?>
                </span>
            </div>

            <div class="pd-cap-number">
                <?= number_format($parkirAktif, 0, ',', '.') ?>
                <small>kendaraan</small>
            </div>

            <?php if ($kapasitas > 0): ?>
                <p class="pd-cap-sub">
                    <?= number_format($slotTersedia, 0, ',', '.') ?>
                    slot tersedia dari
                    <?= number_format($kapasitas, 0, ',', '.') ?> slot
                </p>

                <div
                    class="pd-bar"
                    role="progressbar"
                    aria-label="Persentase kapasitas parkir terisi"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="<?= (int) round($persenTerisi) ?>"
                >
                    <div style="width: <?= e($lebarBar) ?>%"></div>
                </div>
            <?php else: ?>
                <p class="pd-cap-sub">
                    Kapasitas area parkir belum tersedia.
                </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Statistik -->
    <section class="pd-stats">

        <article class="pd-stat">
            <div class="pd-stat-top">
                <span class="pd-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14M13 6l6 6-6 6"/>
                    </svg>
                </span>
                <span class="pd-stat-label">Kendaraan Masuk Hari Ini</span>
            </div>
            <div class="pd-stat-value">
                <?= number_format($transaksiMasukHariIni, 0, ',', '.') ?>
            </div>
            <div class="pd-stat-note">Total pencatatan masuk hari ini</div>
        </article>

        <article class="pd-stat">
            <div class="pd-stat-top">
                <span class="pd-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M11 6l-6 6 6 6"/>
                    </svg>
                </span>
                <span class="pd-stat-label">Kendaraan Keluar Hari Ini</span>
            </div>
            <div class="pd-stat-value">
                <?= number_format($transaksiKeluarHariIni, 0, ',', '.') ?>
            </div>
            <div class="pd-stat-note">Total transaksi selesai hari ini</div>
        </article>

        <article class="pd-stat">
            <div class="pd-stat-top">
                <span class="pd-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 17h14l-2-6H7l-2 6Z"/>
                        <path d="M7 11l1.5-4h7L17 11"/>
                        <circle cx="8" cy="17" r="1.5"/>
                        <circle cx="16" cy="17" r="1.5"/>
                    </svg>
                </span>
                <span class="pd-stat-label">Kendaraan Terdaftar</span>
            </div>
            <div class="pd-stat-value">
                <?= number_format($totalKendaraan, 0, ',', '.') ?>
            </div>
            <div class="pd-stat-note">Total data kendaraan</div>
        </article>

        <article class="pd-stat">
            <div class="pd-stat-top">
                <span class="pd-icon pd-icon-gold" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="6" width="18" height="12" rx="2"/>
                        <circle cx="12" cy="12" r="2.5"/>
                        <path d="M6.5 9.5v.01M17.5 14.5v.01"/>
                    </svg>
                </span>
                <span class="pd-stat-label">Pendapatan Hari Ini</span>
            </div>
            <div class="pd-stat-value pd-money">
                <?= e(formatRupiahPetugas($pendapatanHariIni)) ?>
            </div>
            <div class="pd-stat-note">Pendapatan dari transaksi keluar</div>
        </article>

    </section>

    <!-- Kapasitas tiap area -->
    <section class="pd-area-section">
        <h2 class="pd-section-title">Kondisi Area Parkir</h2>

        <div class="pd-area-grid">
            <article class="pd-area-card">
                <h3>Area A - Motor</h3>

                <div class="pd-area-numbers">
                    <div>
                        <span>Kapasitas</span>
                        <strong><?= number_format($kapasitasMotor) ?></strong>
                    </div>
                    <div>
                        <span>Terisi</span>
                        <strong><?= number_format($terisiAreaMotor) ?></strong>
                    </div>
                    <div>
                        <span>Tersedia</span>
                        <strong><?= number_format(max(0, $kapasitasMotor - $terisiAreaMotor)) ?></strong>
                    </div>
                </div>
            </article>

            <article class="pd-area-card">
                <h3>Area B - Mobil</h3>

                <div class="pd-area-numbers">
                    <div>
                        <span>Kapasitas</span>
                        <strong><?= number_format($kapasitasMobil) ?></strong>
                    </div>
                    <div>
                        <span>Terisi</span>
                        <strong><?= number_format($terisiAreaMobil) ?></strong>
                    </div>
                    <div>
                        <span>Tersedia</span>
                        <strong><?= number_format(max(0, $kapasitasMobil - $terisiAreaMobil)) ?></strong>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <!-- Tabel transaksi terbaru -->
    <section class="pd-panel">
        <div class="pd-panel-head">
            <div>
                <h2>Transaksi Terbaru</h2>
                <p>Delapan transaksi parkir terakhir.</p>
            </div>

            <span class="pd-panel-count">
                <?= count($terbaru) ?> transaksi
            </span>
        </div>

        <div class="pd-table-wrap">
            <table class="pd-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Plat Nomor</th>
                        <th>Jenis</th>
                        <th>Area</th>
                        <th>Waktu Masuk</th>
                        <th>Durasi</th>
                        <th>Status</th>
                        <th class="pd-right">Biaya</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($terbaru)): ?>
                        <tr>
                            <td colspan="8" class="pd-empty">
                                <strong>Belum ada transaksi</strong>
                                Data transaksi akan muncul setelah kendaraan dicatat.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($terbaru as $i => $t): ?>
                            <?php
                            $sudahKeluar = $t['status'] === 'keluar';
                            $waktuMasuk = strtotime((string) $t['waktu_masuk']);
                            ?>
                            <tr>
                                <td class="pd-num">
                                    <?= $i + 1 ?>
                                </td>

                                <td>
                                    <span class="pd-plat">
                                        <?= e($t['plat_nomor']) ?>
                                    </span>
                                </td>

                                <td class="pd-jenis">
                                    <?= e(ucfirst($t['jenis_kendaraan'])) ?>
                                </td>

                                <td>
                                    <?= e($t['nama_area'] ?? '-') ?>
                                </td>

                                <td class="pd-time">
                                    <?= $waktuMasuk ? e(date('H:i', $waktuMasuk)) : '-' ?>
                                    <small>
                                        <?= $waktuMasuk ? e(date('d/m/Y', $waktuMasuk)) : '' ?>
                                    </small>
                                </td>

                                <td class="pd-time">
                                    <?= e(durasiParkir(
                                        $t['waktu_masuk'] ?? null,
                                        $sudahKeluar
                                            ? ($t['waktu_keluar'] ?? null)
                                            : null
                                    )) ?>
                                </td>

                                <td>
                                    <span class="pd-badge <?= $sudahKeluar ? 'pd-badge-keluar' : 'pd-badge-masuk' ?>">
                                        <?= $sudahKeluar ? 'Selesai' : 'Parkir' ?>
                                    </span>
                                </td>

                                <td class="pd-right">
                                    <?php if ($sudahKeluar): ?>
                                        <span class="pd-biaya">
                                            <?= e(formatRupiahPetugas($t['biaya_total'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="pd-biaya pd-biaya-kosong">
                                            Belum dihitung
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
