
<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['admin']);

$judul = 'Kendaraan Masuk';
$error = '';
$kendaraan = [];
$daftarArea = [];
$daftarParkir = [];
$gagalMemuat = false;

/*
|--------------------------------------------------------------------------
| Token CSRF
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_transaksi_masuk'])) {
    $_SESSION['csrf_transaksi_masuk'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_transaksi_masuk'];

/*
|--------------------------------------------------------------------------
| Fungsi area berdasarkan jenis kendaraan
|--------------------------------------------------------------------------
*/
function namaAreaKendaraan(string $jenis): string
{
    $jenis = strtolower(trim($jenis));

    if ($jenis === 'motor') {
        return 'Area A - Motor';
    }

    if ($jenis === 'mobil') {
        return 'Area B - Mobil';
    }

    return '';
}

function namaAlternatifArea(string $namaArea): array
{
    if ($namaArea === 'Area A - Motor') {
        return ['Area A - Motor', 'Area A'];
    }

    if ($namaArea === 'Area B - Mobil') {
        return ['Area B - Mobil', 'Area B'];
    }

    return [];
}

/*
|--------------------------------------------------------------------------
| Proses hapus kendaraan yang sedang parkir
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['hapus_parkir'])
) {
    $idParkir = filter_var(
        $_POST['id_parkir'] ?? null,
        FILTER_VALIDATE_INT
    );

    $tokenKirim = (string) ($_POST['csrf_token'] ?? '');
    $tokenSesi = (string) ($_SESSION['csrf_transaksi_masuk'] ?? '');

    try {
        if (
            !$idParkir ||
            $idParkir < 1 ||
            $tokenSesi === '' ||
            !hash_equals($tokenSesi, $tokenKirim)
        ) {
            throw new Exception(
                'Permintaan hapus tidak valid. Silakan muat ulang halaman.'
            );
        }

        $pdo->beginTransaction();

        // Ambil transaksi aktif beserta kendaraan dan area.
        $stmt = $pdo->prepare(
            "SELECT
                t.id_parkir,
                t.id_area,
                k.plat_nomor
             FROM tb_transaksi t
             INNER JOIN tb_kendaraan k
                ON k.id_kendaraan = t.id_kendaraan
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

        // Hapus transaksi kendaraan yang masih berstatus masuk.
        $stmt = $pdo->prepare(
            "DELETE FROM tb_transaksi
             WHERE id_parkir = ?
               AND status = 'masuk'"
        );

        $stmt->execute([$idParkir]);

        if ($stmt->rowCount() < 1) {
            throw new Exception(
                'Transaksi gagal dihapus.'
            );
        }

        // Hitung ulang jumlah kendaraan di area terkait.
        if (!empty($transaksi['id_area'])) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM tb_transaksi
                 WHERE id_area = ?
                   AND status = 'masuk'"
            );

            $stmt->execute([(int) $transaksi['id_area']]);
            $jumlahTerisi = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "UPDATE tb_area_parkir
                 SET terisi = ?
                 WHERE id_area = ?"
            );

            $stmt->execute([
                $jumlahTerisi,
                (int) $transaksi['id_area']
            ]);
        }

        catatAktivitas(
            $pdo,
            'Menghapus transaksi parkir kendaraan: '
            . $transaksi['plat_nomor']
        );

        $pdo->commit();

        header('Location: transaksi_masuk.php?hapus=1');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException) {
            error_log('Hapus transaksi parkir gagal: ' . $e->getMessage());

            $error = 'Transaksi gagal dihapus. Periksa relasi tabel database.';
        } else {
            $error = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Proses kendaraan masuk
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !isset($_POST['hapus_parkir'])
) {
    $idKendaraan = filter_var(
        $_POST['id_kendaraan'] ?? null,
        FILTER_VALIDATE_INT
    );

    $tokenKirim = (string) ($_POST['csrf_token'] ?? '');
    $tokenSesi = (string) ($_SESSION['csrf_transaksi_masuk'] ?? '');

    try {
        if (
            $tokenSesi === '' ||
            !hash_equals($tokenSesi, $tokenKirim)
        ) {
            throw new Exception(
                'Permintaan tidak valid. Silakan muat ulang halaman.'
            );
        }

        if (!$idKendaraan || $idKendaraan < 1) {
            throw new Exception('Silakan pilih kendaraan.');
        }

        $pdo->beginTransaction();

        // Kunci data kendaraan yang dipilih.
        $stmt = $pdo->prepare(
            "SELECT
                id_kendaraan,
                plat_nomor,
                jenis_kendaraan
             FROM tb_kendaraan
             WHERE id_kendaraan = ?
             FOR UPDATE"
        );

        $stmt->execute([$idKendaraan]);
        $kendaraanTerpilih = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$kendaraanTerpilih) {
            throw new Exception('Kendaraan tidak ditemukan.');
        }

        $jenis = strtolower(
            trim($kendaraanTerpilih['jenis_kendaraan'])
        );

        $namaAreaTujuan = namaAreaKendaraan($jenis);

        if ($namaAreaTujuan === '') {
            throw new Exception(
                'Jenis kendaraan harus motor atau mobil.'
            );
        }

        // Pastikan kendaraan belum memiliki transaksi aktif.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM tb_transaksi
             WHERE id_kendaraan = ?
               AND status = 'masuk'"
        );

        $stmt->execute([$idKendaraan]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new Exception(
                'Kendaraan ini masih tercatat sedang parkir.'
            );
        }

        // Cari area berdasarkan jenis kendaraan.
        $namaAlternatif = namaAlternatifArea($namaAreaTujuan);

        $stmt = $pdo->prepare(
            "SELECT id_area, nama_area, kapasitas, terisi
             FROM tb_area_parkir
             WHERE LOWER(TRIM(nama_area)) IN (?, ?)
             ORDER BY
                CASE
                    WHEN LOWER(TRIM(nama_area)) = LOWER(?) THEN 0
                    ELSE 1
                END
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->execute([
            strtolower($namaAlternatif[0]),
            strtolower($namaAlternatif[1]),
            $namaAreaTujuan
        ]);

        $area = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$area) {
            throw new Exception(
                "Area {$namaAreaTujuan} belum tersedia. Periksa data area parkir."
            );
        }

        $idArea = (int) $area['id_area'];
        $kapasitas = (int) $area['kapasitas'];

        if ($kapasitas < 1) {
            throw new Exception(
                "Kapasitas {$namaAreaTujuan} belum diatur."
            );
        }

        // Hitung jumlah kendaraan aktif pada area.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM tb_transaksi
             WHERE id_area = ?
               AND status = 'masuk'"
        );

        $stmt->execute([$idArea]);
        $terisi = (int) $stmt->fetchColumn();

        if ($terisi >= $kapasitas) {
            throw new Exception(
                "{$namaAreaTujuan} penuh. Kendaraan tidak dapat masuk."
            );
        }

        // Ambil tarif sesuai jenis kendaraan.
        $stmt = $pdo->prepare(
            "SELECT id_tarif
             FROM tb_tarif
             WHERE LOWER(TRIM(jenis_kendaraan)) = ?
             LIMIT 1"
        );

        $stmt->execute([$jenis]);
        $idTarif = $stmt->fetchColumn();

        if (!$idTarif) {
            throw new Exception(
                "Tarif untuk kendaraan {$jenis} belum tersedia."
            );
        }

        // Validasi sesi admin.
        $idUser = filter_var(
            $_SESSION['user']['id_user'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$idUser || $idUser < 1) {
            throw new Exception(
                'Sesi pengguna tidak valid. Silakan login kembali.'
            );
        }

        // Simpan transaksi masuk.
        $stmt = $pdo->prepare(
            "INSERT INTO tb_transaksi
                (
                    id_kendaraan,
                    waktu_masuk,
                    id_tarif,
                    durasi_jam,
                    biaya_total,
                    status,
                    id_user,
                    id_area
                )
             VALUES (?, NOW(), ?, 0, 0, 'masuk', ?, ?)"
        );

        $stmt->execute([
            $idKendaraan,
            $idTarif,
            $idUser,
            $idArea
        ]);

        // Perbarui jumlah kendaraan di area.
        $stmt = $pdo->prepare(
            "UPDATE tb_area_parkir
             SET terisi = ?
             WHERE id_area = ?"
        );

        $stmt->execute([
            $terisi + 1,
            $idArea
        ]);

        // Catat aktivitas.
        catatAktivitas(
            $pdo,
            'Kendaraan masuk: '
            . $kendaraanTerpilih['plat_nomor']
            . ' (' . $namaAreaTujuan . ')'
        );

        $pdo->commit();

        header('Location: transaksi_masuk.php?sukses=1');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException) {
            error_log('Transaksi masuk gagal: ' . $e->getMessage());

            $error = 'Transaksi gagal disimpan. Periksa struktur tabel database.';
        } else {
            $error = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Ambil data kendaraan dan area parkir
|--------------------------------------------------------------------------
*/
$areaMotor = null;
$areaMobil = null;

try {
    // Kendaraan yang belum memiliki transaksi aktif.
    $stmt = $pdo->query(
        "SELECT
            k.id_kendaraan,
            k.plat_nomor,
            k.jenis_kendaraan
         FROM tb_kendaraan k
         WHERE NOT EXISTS (
             SELECT 1
             FROM tb_transaksi t
             WHERE t.id_kendaraan = k.id_kendaraan
               AND t.status = 'masuk'
         )
         ORDER BY k.plat_nomor ASC"
    );

    $kendaraan = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil semua area parkir.
    $stmt = $pdo->query(
        "SELECT
            a.id_area,
            a.nama_area,
            a.kapasitas
         FROM tb_area_parkir a
         ORDER BY a.id_area ASC"
    );

    $semuaArea = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($semuaArea as $a) {
        $nama = strtolower(trim($a['nama_area']));

        if (
            in_array($nama, ['area a - motor', 'area a'], true)
            && (
                $areaMotor === null
                || $nama === 'area a - motor'
            )
        ) {
            $areaMotor = $a;
        }

        if (
            in_array($nama, ['area b - mobil', 'area b'], true)
            && (
                $areaMobil === null
                || $nama === 'area b - mobil'
            )
        ) {
            $areaMobil = $a;
        }
    }

    $daftarArea = [];

    foreach ([
        ['nama' => 'Area A - Motor', 'data' => $areaMotor],
        ['nama' => 'Area B - Mobil', 'data' => $areaMobil]
    ] as $item) {
        if ($item['data'] === null) {
            $daftarArea[] = [
                'nama_area' => $item['nama'],
                'kapasitas' => 0,
                'terisi' => 0,
                'tersedia' => 0
            ];

            continue;
        }

        $idArea = (int) $item['data']['id_area'];
        $kapasitas = (int) $item['data']['kapasitas'];

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM tb_transaksi
             WHERE id_area = ?
               AND status = 'masuk'"
        );

        $stmt->execute([$idArea]);
        $terisi = (int) $stmt->fetchColumn();

        // Sinkronkan kolom terisi dengan transaksi aktif.
        $stmt = $pdo->prepare(
            "UPDATE tb_area_parkir
             SET terisi = ?
             WHERE id_area = ?"
        );

        $stmt->execute([$terisi, $idArea]);

        $daftarArea[] = [
            'nama_area' => $item['nama'],
            'kapasitas' => $kapasitas,
            'terisi' => $terisi,
            'tersedia' => max(0, $kapasitas - $terisi)
        ];
    }

    // Daftar kendaraan yang sedang parkir.
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
         ORDER BY t.id_parkir DESC"
    );

    $daftarParkir = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log(
        'Gagal memuat kendaraan masuk: ' . $e->getMessage()
    );

    $error = 'Data gagal dimuat. Periksa struktur tabel database.';
    $gagalMemuat = true;

    $kendaraan = [];
    $daftarParkir = [];
    $daftarArea = [];
    $areaMotor = null;
    $areaMobil = null;
}

/*
|--------------------------------------------------------------------------
| Pisahkan kendaraan berdasarkan jenis
|--------------------------------------------------------------------------
*/
$kendaraanMotor = [];
$kendaraanMobil = [];

foreach ($kendaraan as $k) {
    $jenis = strtolower(trim($k['jenis_kendaraan']));

    if ($jenis === 'motor') {
        $kendaraanMotor[] = $k;
    } elseif ($jenis === 'mobil') {
        $kendaraanMobil[] = $k;
    }
}

$areaMotorTersedia = 0;
$areaMobilTersedia = 0;

foreach ($daftarArea as $a) {
    if ($a['nama_area'] === 'Area A - Motor') {
        $areaMotorTersedia = (int) $a['tersedia'];
    } elseif ($a['nama_area'] === 'Area B - Mobil') {
        $areaMobilTersedia = (int) $a['tersedia'];
    }
}

require_once __DIR__ . '/header.php';
?>

<style>
.masuk-page {
    padding: 24px;
    color: #16241e;
}

.masuk-heading {
    margin-bottom: 22px;
}

.masuk-heading h2 {
    margin: 0 0 8px;
    font-size: 24px;
    color: #0c3b2e;
}

.masuk-heading p {
    margin: 0;
    color: #62736a;
    font-size: 14px;
    line-height: 1.6;
}

.masuk-alert {
    margin-bottom: 18px;
    padding: 13px 15px;
    border: 1px solid #f3c7c7;
    border-radius: 8px;
    background: #fdeeee;
    color: #a12b2b;
    font-size: 14px;
    line-height: 1.5;
}

.masuk-alert.success {
    border-color: #bfe3cf;
    background: #e9f7ef;
    color: #14653f;
}

.masuk-areas {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.masuk-area-card,
.masuk-panel {
    min-width: 0;
    background: #fff;
    border: 1px solid #d9e2dc;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(12, 59, 46, .05);
}

.masuk-area-card {
    padding: 20px;
}

.masuk-area-card h3,
.masuk-panel h3 {
    margin: 0 0 18px;
    color: #0c3b2e;
    font-size: 17px;
}

.masuk-area-numbers {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.masuk-area-numbers span {
    display: block;
    margin-bottom: 6px;
    color: #62736a;
    font-size: 12px;
}

.masuk-area-numbers strong {
    font-size: 22px;
    color: #0c3b2e;
}

.masuk-panel {
    margin-bottom: 22px;
    padding: 24px;
}

.masuk-field label {
    display: block;
    margin-bottom: 8px;
    color: #16241e;
    font-size: 13px;
    font-weight: 600;
}

.masuk-field select {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px;
    border: 1px solid #d9e2dc;
    border-radius: 7px;
    background: #f8faf8;
    color: #16241e;
    box-sizing: border-box;
}

.masuk-field select:focus {
    outline: none;
    border-color: #14513f;
    box-shadow: 0 0 0 3px rgba(20, 81, 63, .12);
}

.masuk-field select:disabled {
    opacity: .6;
    cursor: not-allowed;
}

.masuk-btn {
    width: 100%;
    min-height: 43px;
    margin-top: 16px;
    padding: 10px 14px;
    border: none;
    border-radius: 7px;
    background: #f0b429;
    color: #0c3b2e;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s ease;
}

.masuk-btn:hover:not(:disabled) {
    background: #f5c24a;
}

.masuk-btn:disabled {
    opacity: .55;
    cursor: not-allowed;
}

.masuk-keterangan {
    margin: 12px 0 0;
    color: #62736a;
    font-size: 13px;
    line-height: 1.5;
}

.masuk-table-wrap {
    overflow-x: auto;
}

.masuk-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 13px;
}

.masuk-table th {
    padding: 14px 12px;
    background: #f3f7f4;
    color: #62736a;
    border-bottom: 1px solid #d9e2dc;
    white-space: nowrap;
}

.masuk-table td {
    padding: 14px 12px;
    color: #16241e;
    border-bottom: 1px solid #edf2ee;
    white-space: nowrap;
}

.masuk-table tbody tr:hover {
    background: #f8fbf9;
}

.masuk-empty {
    padding: 24px !important;
    text-align: center;
    color: #62736a !important;
}

.masuk-btn-hapus {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    background: #dc3545;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.masuk-btn-hapus:hover {
    background: #bd2635;
}

.masuk-form-hapus {
    margin: 0;
}

@media (max-width: 800px) {
    .masuk-page {
        padding: 16px;
    }

    .masuk-areas {
        grid-template-columns: 1fr;
    }

    .masuk-area-numbers strong {
        font-size: 19px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .masuk-btn {
        transition: none;
    }
}
</style>

<div class="masuk-page">

    <div class="masuk-heading">
        <h2>Kendaraan Masuk</h2>
        <p>
            Pilih kendaraan sesuai area parkirnya.
            Motor masuk Area A, sedangkan mobil masuk Area B.
        </p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="masuk-alert" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['sukses'])): ?>
        <div class="masuk-alert success" role="status">
            Transaksi kendaraan masuk berhasil disimpan.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['hapus'])): ?>
        <div class="masuk-alert success" role="status">
            Transaksi kendaraan berhasil dihapus.
        </div>
    <?php endif; ?>

    <!-- Informasi kapasitas area -->
    <div class="masuk-areas">
        <?php foreach ($daftarArea as $a): ?>
            <div class="masuk-area-card">
                <h3><?= e($a['nama_area']) ?></h3>

                <div class="masuk-area-numbers">
                    <div>
                        <span>Kapasitas</span>
                        <strong><?= number_format($a['kapasitas']) ?></strong>
                    </div>

                    <div>
                        <span>Terisi</span>
                        <strong><?= number_format($a['terisi']) ?></strong>
                    </div>

                    <div>
                        <span>Tersedia</span>
                        <strong><?= number_format($a['tersedia']) ?></strong>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Form kendaraan masuk -->
    <div class="masuk-areas">

        <!-- Area A: Motor -->
        <section class="masuk-area-card">
            <h3>Form Kendaraan Masuk - Area A</h3>

            <p class="masuk-keterangan" style="margin:0 0 18px">
                Area A khusus kendaraan motor.
            </p>

            <?php if ($gagalMemuat): ?>
                <div class="masuk-alert">
                    Data kendaraan gagal dimuat.
                </div>
            <?php elseif ($areaMotor === null): ?>
                <div class="masuk-alert">
                    Area A - Motor belum tersedia di database.
                </div>
            <?php else: ?>
                <form method="POST" action="transaksi_masuk.php">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >

                    <div class="masuk-field">
                        <label for="kendaraan_motor">
                            Pilih Kendaraan Motor
                        </label>

                        <select
                            name="id_kendaraan"
                            id="kendaraan_motor"
                            required
                            <?= empty($kendaraanMotor) || $areaMotorTersedia <= 0
                                ? 'disabled' : '' ?>
                        >
                            <option value="">Pilih kendaraan motor</option>

                            <?php foreach ($kendaraanMotor as $k): ?>
                                <option value="<?= (int) $k['id_kendaraan'] ?>">
                                    <?= e($k['plat_nomor']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button
                        type="submit"
                        class="masuk-btn"
                        <?= empty($kendaraanMotor) || $areaMotorTersedia <= 0
                            ? 'disabled' : '' ?>
                    >
                        Simpan Motor Masuk
                    </button>

                    <?php if ($areaMotorTersedia <= 0): ?>
                        <p class="masuk-keterangan">
                            Area A penuh atau kapasitas belum tersedia.
                        </p>
                    <?php elseif (empty($kendaraanMotor)): ?>
                        <p class="masuk-keterangan">
                            Tidak ada motor yang tersedia untuk dipilih.
                        </p>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </section>

        <!-- Area B: Mobil -->
        <section class="masuk-area-card">
            <h3>Form Kendaraan Masuk - Area B</h3>

            <p class="masuk-keterangan" style="margin:0 0 18px">
                Area B khusus kendaraan mobil.
            </p>

            <?php if ($gagalMemuat): ?>
                <div class="masuk-alert">
                    Data kendaraan gagal dimuat.
                </div>
            <?php elseif ($areaMobil === null): ?>
                <div class="masuk-alert">
                    Area B - Mobil belum tersedia di database.
                </div>
            <?php else: ?>
                <form method="POST" action="transaksi_masuk.php">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >

                    <div class="masuk-field">
                        <label for="kendaraan_mobil">
                            Pilih Kendaraan Mobil
                        </label>

                        <select
                            name="id_kendaraan"
                            id="kendaraan_mobil"
                            required
                            <?= empty($kendaraanMobil) || $areaMobilTersedia <= 0
                                ? 'disabled' : '' ?>
                        >
                            <option value="">Pilih kendaraan mobil</option>

                            <?php foreach ($kendaraanMobil as $k): ?>
                                <option value="<?= (int) $k['id_kendaraan'] ?>">
                                    <?= e($k['plat_nomor']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button
                        type="submit"
                        class="masuk-btn"
                        <?= empty($kendaraanMobil) || $areaMobilTersedia <= 0
                            ? 'disabled' : '' ?>
                    >
                        Simpan Mobil Masuk
                    </button>

                    <?php if ($areaMobilTersedia <= 0): ?>
                        <p class="masuk-keterangan">
                            Area B penuh atau kapasitas belum tersedia.
                        </p>
                    <?php elseif (empty($kendaraanMobil)): ?>
                        <p class="masuk-keterangan">
                            Tidak ada mobil yang tersedia untuk dipilih.
                        </p>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </section>

    </div>

    <!-- Daftar kendaraan sedang parkir -->
    <section class="masuk-panel">
        <h3>Kendaraan Sedang Parkir</h3>

        <div class="masuk-table-wrap">
            <table class="masuk-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Plat Nomor</th>
                        <th>Jenis Kendaraan</th>
                        <th>Area Parkir</th>
                        <th>Waktu Masuk</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($daftarParkir)): ?>
                        <tr>
                            <td colspan="6" class="masuk-empty">
                                Tidak ada kendaraan yang sedang parkir.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daftarParkir as $i => $t): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($t['plat_nomor']) ?></td>
                                <td>
                                    <?= e(ucfirst($t['jenis_kendaraan'])) ?>
                                </td>
                                <td><?= e($t['nama_area'] ?? '-') ?></td>
                                <td><?= e($t['waktu_masuk']) ?></td>
                                <td>
                                    <form
                                        method="POST"
                                        action="transaksi_masuk.php"
                                        class="masuk-form-hapus"
                                        onsubmit="return confirm('Yakin ingin menghapus transaksi kendaraan ini? Data transaksi akan dihapus permanen.');"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($csrfToken) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id_parkir"
                                            value="<?= (int) $t['id_parkir'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="hapus_parkir"
                                            value="1"
                                            class="masuk-btn-hapus"
                                        >
                                            Hapus
                                        </button>
                                    </form>
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
