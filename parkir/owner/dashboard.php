
<?php
require_once __DIR__ . '/../koneksi.php';
wajibRole(['owner']);

$judul = 'Dashboard Owner';

function formatRupiahOwner($angka): string
{
    return 'Rp ' . number_format((float) ($angka ?? 0), 0, ',', '.');
}

function formatWaktuOwner($waktu): string
{
    if (empty($waktu)) {
        return '-';
    }

    $timestamp = strtotime((string) $waktu);

    return $timestamp !== false
        ? date('d-m-Y H:i', $timestamp)
        : (string) $waktu;
}

$totalTransaksi = 0;
$parkirAktif = 0;
$transaksiSelesai = 0;
$totalPendapatan = 0;
$pendapatanHariIni = 0;
$kapasitasParkir = 0;
$parkirTersedia = 0;
$statusKetersediaan = 'Belum diatur';
$terbaru = [];
$errorDashboard = '';

$namaOwner = $_SESSION['user']['nama_lengkap']
    ?? $_SESSION['user']['username']
    ?? 'Owner';

try {
    // Mengambil statistik transaksi.
    $sqlStatistik = $pdo->query("
        SELECT
            COUNT(*) AS total_transaksi,
            COALESCE(SUM(CASE
                WHEN status = 'masuk' THEN 1 ELSE 0
            END), 0) AS parkir_aktif,
            COALESCE(SUM(CASE
                WHEN status = 'keluar' THEN 1 ELSE 0
            END), 0) AS transaksi_selesai,
            COALESCE(SUM(CASE
                WHEN status = 'keluar'
                THEN COALESCE(biaya_total, 0)
                ELSE 0
            END), 0) AS total_pendapatan,
            COALESCE(SUM(CASE
                WHEN status = 'keluar'
                    AND DATE(waktu_keluar) = CURDATE()
                THEN COALESCE(biaya_total, 0)
                ELSE 0
            END), 0) AS pendapatan_hari_ini
        FROM tb_transaksi
    ");

    $statistik = $sqlStatistik->fetch(PDO::FETCH_ASSOC);

    if ($statistik) {
        $totalTransaksi = (int) $statistik['total_transaksi'];
        $parkirAktif = (int) $statistik['parkir_aktif'];
        $transaksiSelesai = (int) $statistik['transaksi_selesai'];
        $totalPendapatan = (float) $statistik['total_pendapatan'];
        $pendapatanHariIni = (float) $statistik['pendapatan_hari_ini'];
    }

    // Mengambil kapasitas seluruh area parkir.
    $kapasitasParkir = (int) $pdo->query("
        SELECT COALESCE(SUM(kapasitas), 0)
        FROM tb_area_parkir
    ")->fetchColumn();

    if ($kapasitasParkir > 0) {
        $parkirTersedia = max(
            0,
            $kapasitasParkir - $parkirAktif
        );

        if ($parkirTersedia === 0) {
            $statusKetersediaan = 'Penuh';
        } elseif ($parkirTersedia <= $kapasitasParkir * 0.2) {
            $statusKetersediaan = 'Hampir penuh';
        } else {
            $statusKetersediaan = 'Tersedia';
        }
    }

    // Mengambil sepuluh transaksi terbaru.
    $stmt = $pdo->query("
        SELECT
            t.id_parkir,
            k.plat_nomor,
            k.jenis_kendaraan,
            t.waktu_masuk,
            t.waktu_keluar,
            t.status,
            t.biaya_total
        FROM tb_transaksi t
        INNER JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
        ORDER BY t.id_parkir DESC
        LIMIT 10
    ");

    $terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Dashboard Owner: ' . $e->getMessage());
    $errorDashboard = 'Sebagian data gagal dimuat. Periksa struktur database dan koneksi.';
}

$kelasTagTersedia = '';

if ($statusKetersediaan === 'Tersedia') {
    $kelasTagTersedia = 'tag-tersedia';
} elseif ($statusKetersediaan === 'Hampir penuh') {
    $kelasTagTersedia = 'tag-hampir';
} elseif ($statusKetersediaan === 'Penuh') {
    $kelasTagTersedia = 'tag-penuh';
}

require __DIR__ . '/header.php';
?>

<style>
.owner-dashboard {
    --owner-green: #123b2e;
    --owner-green-light: #e8f3ed;
    --owner-yellow: #f3c84b;
    --owner-text: #1f2937;
    --owner-muted: #718096;
    --owner-border: #e7ece8;

    color: var(--owner-text);
    padding-bottom: 25px;
    min-width: 0;
}

.owner-dashboard *,
.owner-dashboard *::before,
.owner-dashboard *::after {
    box-sizing: border-box;
}

.owner-welcome {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 22px;
    padding: 30px;
    margin-bottom: 25px;
    border-radius: 16px;
    color: #fff;
    background: linear-gradient(120deg, #123b2e, #1d5942);
    box-shadow: 0 8px 22px rgba(18, 59, 46, .12);
}

.owner-welcome::after {
    content: "";
    position: absolute;
    width: 230px;
    height: 230px;
    right: 80px;
    top: -145px;
    border: 35px solid rgba(255,255,255,.055);
    border-radius: 50%;
    pointer-events: none;
}

.owner-welcome-content {
    position: relative;
    z-index: 1;
    min-width: 0;
}

.owner-eyebrow {
    display: inline-block;
    margin-bottom: 12px;
    color: var(--owner-yellow);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.8px;
    text-transform: uppercase;
}

.owner-welcome h1 {
    margin: 0 0 10px;
    color: #fff;
    font-size: clamp(23px, 3vw, 30px);
    line-height: 1.3;
    font-weight: 750;
}

.owner-welcome p {
    margin: 0;
    max-width: 560px;
    color: #d7e8df;
    font-size: 14px;
    line-height: 1.8;
}

.owner-profile {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border: 1px solid rgba(255,255,255,.15);
    background: rgba(255,255,255,.08);
    border-radius: 12px;
    flex-shrink: 0;
    max-width: 100%;
}

.owner-avatar {
    display: grid;
    place-items: center;
    width: 43px;
    height: 43px;
    flex-shrink: 0;
    border-radius: 12px;
    background: var(--owner-yellow);
    color: var(--owner-green);
    font-size: 18px;
    font-weight: 800;
}

.owner-profile-name {
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.owner-profile-role {
    margin-top: 4px;
    color: #d7e8df;
    font-size: 11px;
}

.owner-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 0 0 15px;
}

.owner-section-heading h2 {
    margin: 0;
    color: var(--owner-green);
    font-size: 17px;
    font-weight: 750;
}

.owner-section-heading p {
    margin: 5px 0 0;
    color: var(--owner-muted);
    font-size: 12px;
}

.owner-cards {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 17px;
    margin-bottom: 28px;
}

.owner-card {
    min-width: 0;
    padding: 22px;
    background: #fff;
    border: 1px solid var(--owner-border);
    border-radius: 14px;
    box-shadow: 0 4px 14px rgba(15,23,42,.025);
    transition: transform .2s ease, box-shadow .2s ease;
}

.owner-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 9px 23px rgba(15,23,42,.07);
}

.owner-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 22px;
}

.owner-card-icon {
    display: grid;
    place-items: center;
    width: 43px;
    height: 43px;
    flex-shrink: 0;
    border-radius: 12px;
    background: var(--owner-green-light);
    color: var(--owner-green);
}

.owner-card-icon svg {
    width: 22px;
    height: 22px;
}

.owner-card-tag {
    padding: 6px 9px;
    border-radius: 7px;
    background: #f5f7f5;
    color: #6b7c70;
    font-size: 10px;
    font-weight: 700;
    text-align: center;
}

.owner-card-tag.tag-tersedia {
    background: #e5f5e9;
    color: #237442;
}

.owner-card-tag.tag-hampir {
    background: #fff5d6;
    color: #936b00;
}

.owner-card-tag.tag-penuh {
    background: #fee2e2;
    color: #991b1b;
}

.owner-card-label {
    margin-bottom: 9px;
    color: var(--owner-muted);
    font-size: 12px;
    font-weight: 600;
}

.owner-card-value {
    color: var(--owner-green);
    font-size: clamp(23px, 2.4vw, 30px);
    font-weight: 800;
    line-height: 1.4;
    overflow-wrap: anywhere;
    letter-spacing: -.7px;
}

.owner-card-value.money {
    font-size: clamp(18px, 1.8vw, 25px);
    letter-spacing: -.5px;
}

.owner-card-note {
    display: flex;
    align-items: flex-start;
    gap: 7px;
    margin-top: 11px;
    color: #89968e;
    font-size: 11px;
    line-height: 1.7;
}

.owner-card-note::before {
    content: "";
    width: 6px;
    height: 6px;
    margin-top: 5px;
    flex-shrink: 0;
    border-radius: 50%;
    background: var(--owner-yellow);
}

.owner-panel {
    margin-bottom: 24px;
    min-width: 0;
    overflow: hidden;
    background: #fff;
    border: 1px solid var(--owner-border);
    border-radius: 14px;
    box-shadow: 0 4px 14px rgba(15,23,42,.025);
}

.owner-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 22px 24px;
    border-bottom: 1px solid var(--owner-border);
}

.owner-panel-title {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.owner-panel-icon {
    display: grid;
    place-items: center;
    width: 39px;
    height: 39px;
    flex-shrink: 0;
    border-radius: 10px;
    background: var(--owner-green-light);
    color: var(--owner-green);
}

.owner-panel-icon svg {
    width: 20px;
    height: 20px;
}

.owner-panel-header h2 {
    margin: 0;
    color: var(--owner-green);
    font-size: 16px;
    font-weight: 750;
}

.owner-panel-header p {
    margin: 5px 0 0;
    color: var(--owner-muted);
    font-size: 12px;
    line-height: 1.6;
}

.owner-total-label {
    padding: 8px 11px;
    border-radius: 8px;
    background: #fff8dd;
    color: #87650a;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.owner-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.owner-table {
    width: 100%;
    min-width: 780px;
    border-collapse: collapse;
    white-space: nowrap;
}

.owner-table th,
.owner-table td {
    padding: 15px 18px;
    border-bottom: 1px solid #edf0ed;
    text-align: left;
    font-size: 12px;
}

.owner-table th {
    background: #f8faf8;
    color: #718078;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .6px;
}

.owner-table tbody tr {
    transition: background .15s ease;
}

.owner-table tbody tr:hover {
    background: #f8fbf8;
}

.owner-table tbody tr:last-child td {
    border-bottom: none;
}

.owner-number {
    color: #94a39a;
    font-weight: 700;
}

.owner-plate {
    color: var(--owner-green);
    font-weight: 800;
}

.owner-type {
    display: inline-block;
    padding: 5px 9px;
    background: #f3f5f3;
    color: #59665e;
    border-radius: 6px;
    font-size: 11px;
}

.owner-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 800;
}

.owner-badge::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.owner-badge-active {
    background: #fff5d6;
    color: #936b00;
}

.owner-badge-done {
    background: #e5f5e9;
    color: #237442;
}

.owner-badge-other {
    background: #f1f5f9;
    color: #475569;
}

.owner-cost {
    color: var(--owner-green);
    font-weight: 800;
}

.owner-empty {
    padding: 35px 18px !important;
    text-align: center !important;
    color: var(--owner-muted);
    white-space: normal;
}

.owner-empty-mark {
    display: block;
    margin-bottom: 7px;
    color: #a1b2a7;
    font-size: 24px;
}

.owner-report {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 24px;
}

.owner-report-content {
    display: flex;
    align-items: center;
    gap: 15px;
    min-width: 0;
}

.owner-report-icon {
    display: grid;
    place-items: center;
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    background: #fff5d6;
    color: #896600;
    border-radius: 13px;
}

.owner-report-icon svg {
    width: 24px;
    height: 24px;
}

.owner-report h2 {
    margin: 0 0 7px;
    color: var(--owner-green);
    font-size: 16px;
}

.owner-report p {
    margin: 0;
    color: var(--owner-muted);
    font-size: 12px;
    line-height: 1.7;
}

.owner-report-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    flex-shrink: 0;
    padding: 12px 17px;
    border: none;
    border-radius: 9px;
    background: var(--owner-green);
    color: #fff;
    text-decoration: none;
    font-size: 12px;
    font-weight: 750;
    transition: background .2s ease;
}

.owner-report-link:hover {
    background: #205b43;
    color: #fff;
}

.owner-report-link svg {
    width: 16px;
    height: 16px;
}

.owner-error {
    margin-bottom: 20px;
    padding: 14px 17px;
    border: 1px solid #fecaca;
    border-radius: 10px;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 13px;
    line-height: 1.6;
}

@media (max-width: 1100px) {
    .owner-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 650px) {
    .owner-welcome {
        align-items: flex-start;
        flex-direction: column;
        padding: 23px 20px;
    }

    .owner-profile {
        width: 100%;
    }

    .owner-cards {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .owner-card {
        padding: 18px;
    }

    .owner-card-top {
        margin-bottom: 15px;
    }

    .owner-panel-header {
        align-items: flex-start;
        padding: 18px;
    }

    .owner-total-label {
        display: none;
    }

    .owner-table th,
    .owner-table td {
        padding: 12px 14px;
    }

    .owner-report {
        align-items: stretch;
        flex-direction: column;
        padding: 19px;
    }

    .owner-report-link {
        width: 100%;
    }
}
</style>

<div class="owner-dashboard">

    <?php if ($errorDashboard !== ''): ?>
        <div class="owner-error">
            <?= e($errorDashboard) ?>
        </div>
    <?php endif; ?>

    <section class="owner-welcome">
        <div class="owner-welcome-content">
            <span class="owner-eyebrow">ParkirKu Management</span>
            <h1>Dashboard Owner</h1>
            <p>
                Selamat datang, <?= e($namaOwner) ?>.
                Pantau aktivitas, transaksi, dan pendapatan usaha parkir
                melalui ringkasan berikut.
            </p>
        </div>

        <div class="owner-profile">
            <div class="owner-avatar">
                <?= e(strtoupper(substr((string) $namaOwner, 0, 1))) ?>
            </div>
            <div>
                <div class="owner-profile-name">
                    <?= e($namaOwner) ?>
                </div>
                <div class="owner-profile-role">Pemilik Parkir</div>
            </div>
        </div>
    </section>

    <div class="owner-section-heading">
        <div>
            <h2>Ringkasan Parkir</h2>
            <p>Ikhtisar aktivitas dan pendapatan parkir.</p>
        </div>
    </div>

    <section class="owner-cards">

        <div class="owner-card">
            <div class="owner-card-top">
                <div class="owner-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="4" y="3" width="16" height="18" rx="2"/>
                        <path d="M8 8h8M8 12h8M8 16h5"/>
                    </svg>
                </div>
                <span class="owner-card-tag">Keseluruhan</span>
            </div>
            <div class="owner-card-label">Total Transaksi</div>
            <div class="owner-card-value">
                <?= number_format($totalTransaksi, 0, ',', '.') ?>
            </div>
            <div class="owner-card-note">Seluruh transaksi yang tercatat</div>
        </div>

        <div class="owner-card">
            <div class="owner-card-top">
                <div class="owner-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M5 16V8a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v8"/>
                        <path d="M3 16h18v3H3zM7 11h10M7 6l1-3h8l1 3"/>
                        <circle cx="7" cy="19" r="1"/>
                        <circle cx="17" cy="19" r="1"/>
                    </svg>
                </div>
                <span class="owner-card-tag">Berlangsung</span>
            </div>
            <div class="owner-card-label">Parkir Aktif</div>
            <div class="owner-card-value">
                <?= number_format($parkirAktif, 0, ',', '.') ?>
            </div>
            <div class="owner-card-note">Kendaraan masih berada di area parkir</div>
        </div>

        <div class="owner-card">
            <div class="owner-card-top">
                <div class="owner-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="3" y="3" width="18" height="18" rx="5"/>
                        <path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>
                    </svg>
                </div>
                <span class="owner-card-tag <?= e($kelasTagTersedia) ?>">
                    <?= e($statusKetersediaan) ?>
                </span>
            </div>
            <div class="owner-card-label">Parkir Tersedia</div>
            <div class="owner-card-value">
                <?= number_format($parkirTersedia, 0, ',', '.') ?>
            </div>
            <div class="owner-card-note">
                <?php if ($kapasitasParkir > 0): ?>
                    Dari total kapasitas
                    <?= number_format($kapasitasParkir, 0, ',', '.') ?> slot
                <?php else: ?>
                    Kapasitas area parkir belum diatur
                <?php endif; ?>
            </div>
        </div>

        <div class="owner-card">
            <div class="owner-card-top">
                <div class="owner-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="m8 12 3 3 5-6"/>
                    </svg>
                </div>
                <span class="owner-card-tag">Selesai</span>
            </div>
            <div class="owner-card-label">Transaksi Selesai</div>
            <div class="owner-card-value">
                <?= number_format($transaksiSelesai, 0, ',', '.') ?>
            </div>
            <div class="owner-card-note">Kendaraan sudah keluar</div>
        </div>

        <div class="owner-card">
            <div class="owner-card-top">
                <div class="owner-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="3" y="6" width="18" height="13" rx="2"/>
                        <path d="M3 10h18M7 15h4"/>
                    </svg>
                </div>
                <span class="owner-card-tag">Akumulasi</span>
            </div>
            <div class="owner-card-label">Total Pendapatan</div>
            <div class="owner-card-value money">
                <?= formatRupiahOwner($totalPendapatan) ?>
            </div>
            <div class="owner-card-note">Dari seluruh transaksi selesai</div>
        </div>

        <div class="owner-card">
            <div class="owner-card-top">
                <div class="owner-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="3" y="5" width="18" height="16" rx="2"/>
                        <path d="M16 3v4M8 3v4M3 10h18M8 14h3M8 17h6"/>
                    </svg>
                </div>
                <span class="owner-card-tag">Hari ini</span>
            </div>
            <div class="owner-card-label">Pendapatan Hari Ini</div>
            <div class="owner-card-value money">
                <?= formatRupiahOwner($pendapatanHariIni) ?>
            </div>
            <div class="owner-card-note">
                Pendapatan dari kendaraan yang keluar hari ini
            </div>
        </div>

    </section>

    <section class="owner-panel">
        <div class="owner-panel-header">
            <div class="owner-panel-title">
                <div class="owner-panel-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M4 5h16M4 10h16M4 15h10M4 20h10"/>
                        <circle cx="19" cy="18" r="3"/>
                        <path d="M19 16v2l1 1"/>
                    </svg>
                </div>
                <div>
                    <h2>Riwayat Transaksi</h2>
                    <p>Sepuluh transaksi terbaru yang tercatat di sistem.</p>
                </div>
            </div>
            <span class="owner-total-label">
                <?= count($terbaru) ?> Data
            </span>
        </div>

        <div class="owner-table-wrap">
            <table class="owner-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Plat Nomor</th>
                        <th>Jenis Kendaraan</th>
                        <th>Waktu Masuk</th>
                        <th>Waktu Keluar</th>
                        <th>Status</th>
                        <th>Biaya</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($terbaru)): ?>
                        <tr>
                            <td colspan="7" class="owner-empty">
                                <span class="owner-empty-mark">—</span>
                                Belum ada transaksi parkir.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($terbaru as $i => $t): ?>
                            <?php
                            $status = strtolower((string) ($t['status'] ?? ''));
                            $sudahKeluar = $status === 'keluar';

                            if ($sudahKeluar) {
                                $labelStatus = 'Selesai';
                                $kelasStatus = 'owner-badge-done';
                            } elseif ($status === 'masuk') {
                                $labelStatus = 'Parkir Aktif';
                                $kelasStatus = 'owner-badge-active';
                            } else {
                                $labelStatus = $status !== ''
                                    ? ucfirst($status)
                                    : 'Tidak diketahui';
                                $kelasStatus = 'owner-badge-other';
                            }
                            ?>
                            <tr>
                                <td class="owner-number">
                                    <?= $i + 1 ?>
                                </td>
                                <td class="owner-plate">
                                    <?= e($t['plat_nomor'] ?? '-') ?>
                                </td>
                                <td>
                                    <span class="owner-type">
                                        <?= e($t['jenis_kendaraan'] ?? '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <?= e(formatWaktuOwner($t['waktu_masuk'] ?? null)) ?>
                                </td>
                                <td>
                                    <?= e(formatWaktuOwner($t['waktu_keluar'] ?? null)) ?>
                                </td>
                                <td>
                                    <span class="owner-badge <?= e($kelasStatus) ?>">
                                        <?= e($labelStatus) ?>
                                    </span>
                                </td>
                                <td class="owner-cost">
                                    <?= formatRupiahOwner($t['biaya_total'] ?? 0) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="owner-panel">
        <div class="owner-report">
            <div class="owner-report-content">
                <div class="owner-report-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M5 3h10l4 4v14H5z"/>
                        <path d="M14 3v5h5M8 16l3-3 2 2 3-4"/>
                    </svg>
                </div>
                <div>
                    <h2>Laporan Parkir</h2>
                    <p>
                        Lihat rekap transaksi untuk memantau operasional
                        dan pendapatan usaha parkir.
                    </p>
                </div>
            </div>

            <a class="owner-report-link" href="rekap.php">
                Lihat Laporan
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M13 6l6 6-6 6"/>
                </svg>
            </a>
        </div>
    </section>

</div>

<?php require __DIR__ . '/footer.php'; ?>
