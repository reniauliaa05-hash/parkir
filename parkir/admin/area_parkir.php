<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['admin']);

$judul = 'Area Parkir';
$pesan = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Pastikan Area A dan Area B tersedia
|--------------------------------------------------------------------------
*/
try {
    $pdo->beginTransaction();

    $cekArea = $pdo->prepare(
        "SELECT id_area
         FROM tb_area_parkir
         WHERE nama_area = ?
         LIMIT 1
         FOR UPDATE"
    );

    $cekArea->execute(['Area A']);
    $areaAId = $cekArea->fetchColumn();

    if (!$areaAId) {
        $tambah = $pdo->prepare(
            "INSERT INTO tb_area_parkir
                (nama_area, kapasitas, terisi)
             VALUES (?, ?, ?)"
        );
        $tambah->execute(['Area A', 50, 0]);
    }

    $cekArea->execute(['Area B']);
    $areaBId = $cekArea->fetchColumn();

    if (!$areaBId) {
        $tambah = $pdo->prepare(
            "INSERT INTO tb_area_parkir
                (nama_area, kapasitas, terisi)
             VALUES (?, ?, ?)"
        );
        $tambah->execute(['Area B', 50, 0]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Inisialisasi area parkir: ' . $e->getMessage());
    $error = 'Area parkir gagal disiapkan. Periksa struktur tabel tb_area_parkir.';
}

/*
|--------------------------------------------------------------------------
| Simpan kapasitas Area A dan Area B
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $kapasitasA = filter_input(INPUT_POST, 'kapasitas_a', FILTER_VALIDATE_INT);
    $kapasitasB = filter_input(INPUT_POST, 'kapasitas_b', FILTER_VALIDATE_INT);

    try {
        if (
            $kapasitasA === false || $kapasitasA === null || $kapasitasA < 1 ||
            $kapasitasB === false || $kapasitasB === null || $kapasitasB < 1
        ) {
            throw new Exception('Kapasitas kedua area harus minimal 1.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->query(
            "SELECT id_area, nama_area, kapasitas
             FROM tb_area_parkir
             WHERE nama_area IN ('Area A', 'Area B')
             FOR UPDATE"
        );

        $daftarArea = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $areaA = null;
        $areaB = null;

        foreach ($daftarArea as $area) {
            if ($area['nama_area'] === 'Area A') {
                $areaA = $area;
            } elseif ($area['nama_area'] === 'Area B') {
                $areaB = $area;
            }
        }

        if (!$areaA || !$areaB) {
            throw new Exception('Area A atau Area B belum tersedia.');
        }

        // Hitung kendaraan aktif berdasarkan area transaksi.
        $stmtTerisi = $pdo->prepare(
            "SELECT COUNT(*)
             FROM tb_transaksi
             WHERE status = 'masuk'
               AND id_area = ?"
        );

        $stmtTerisi->execute([$areaA['id_area']]);
        $terisiA = (int) $stmtTerisi->fetchColumn();

        $stmtTerisi->execute([$areaB['id_area']]);
        $terisiB = (int) $stmtTerisi->fetchColumn();

        if ($kapasitasA < $terisiA || $kapasitasB < $terisiB) {
            throw new Exception(
                'Kapasitas tidak boleh lebih kecil dari jumlah kendaraan yang sedang parkir.'
            );
        }

        $update = $pdo->prepare(
            "UPDATE tb_area_parkir
             SET kapasitas = ?, terisi = ?
             WHERE id_area = ?"
        );

        $update->execute([
            $kapasitasA,
            $terisiA,
            $areaA['id_area']
        ]);

        $update->execute([
            $kapasitasB,
            $terisiB,
            $areaB['id_area']
        ]);

        $pdo->commit();

        if (function_exists('catatAktivitas')) {
            try {
                catatAktivitas(
                    $pdo,
                    'Mengubah kapasitas Area A (Motor) dan Area B (Mobil)'
                );
            } catch (Throwable $logError) {
                error_log('Log aktivitas area parkir: ' . $logError->getMessage());
            }
        }

        $pesan = 'Kapasitas Area A (Motor) dan Area B (Mobil) berhasil diperbarui.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Simpan kapasitas area: ' . $e->getMessage());

        $error = $e instanceof PDOException
            ? 'Gagal menyimpan kapasitas. Periksa struktur tabel database.'
            : $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Ambil data area parkir
|--------------------------------------------------------------------------
*/
$areas = [];

try {
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
         FROM tb_area_parkir a
         WHERE a.nama_area IN ('Area A', 'Area B')
         ORDER BY
            CASE
                WHEN a.nama_area = 'Area A' THEN 1
                WHEN a.nama_area = 'Area B' THEN 2
                ELSE 3
            END"
    );

    $hasil = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($hasil as $a) {
        $a['kapasitas'] = (int) $a['kapasitas'];
        $a['terisi'] = (int) $a['terisi'];
        $a['sisa'] = max(0, $a['kapasitas'] - $a['terisi']);

        $a['persentase'] = $a['kapasitas'] > 0
            ? min(100, (int) round(
                $a['terisi'] / $a['kapasitas'] * 100
            ))
            : 0;

        $a['jenis'] = $a['nama_area'] === 'Area A' ? 'Motor' : 'Mobil';

        $a['kelas'] = $a['persentase'] >= 90
            ? 'penuh'
            : ($a['persentase'] >= 70 ? 'ramai' : '');

        $areas[] = $a;
    }

    if (
        count($areas) !== 2 ||
        !in_array('Area A', array_column($areas, 'nama_area'), true) ||
        !in_array('Area B', array_column($areas, 'nama_area'), true)
    ) {
        $error = 'Pastikan tb_area_parkir memiliki Area A (Motor) dan Area B (Mobil).';
    }
} catch (PDOException $e) {
    error_log('Area Parkir: ' . $e->getMessage());
    $error = 'Data area gagal ditampilkan. Periksa tabel dan kolom database.';
}

require_once __DIR__ . '/header.php';
?>

<style>
.area-container {
    padding: 24px;
    color: #16241e;
}

.area-heading {
    margin-bottom: 22px;
}

.area-heading h2 {
    margin: 0 0 7px;
    font-size: 26px;
    color: #0c3b2e;
}

.area-heading p {
    margin: 0;
    color: #62736a;
    font-size: 14px;
}

.area-alert {
    padding: 13px 16px;
    margin-bottom: 18px;
    border-radius: 9px;
    font-size: 14px;
}

.area-success {
    color: #14653f;
    background: #e9f7ef;
}

.area-error {
    color: #a12b2b;
    background: #fdeeee;
}

.area-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 22px;
}

.area-card {
    padding: 22px;
    border: 1px solid #d9e2dc;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 3px 12px rgba(12, 59, 46, .05);
    min-width: 0;
}

.area-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 20px;
}

.area-card h3 {
    margin: 0 0 5px;
    font-size: 21px;
    color: #0c3b2e;
}

.area-card-subtitle {
    color: #62736a;
    font-size: 13px;
}

.area-tag {
    padding: 6px 11px;
    border-radius: 20px;
    background: #e9f7ef;
    color: #14653f;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.area-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.area-stat {
    padding: 13px 10px;
    border-radius: 9px;
    background: #f3f7f4;
    min-width: 0;
}

.area-stat span {
    display: block;
    margin-bottom: 7px;
    color: #62736a;
    font-size: 12px;
}

.area-stat strong {
    display: block;
    color: #0c3b2e;
    font-size: 23px;
    overflow-wrap: anywhere;
}

.area-progress {
    height: 9px;
    margin: 18px 0 8px;
    overflow: hidden;
    border-radius: 20px;
    background: #e3ebe6;
}

.area-progress div {
    height: 100%;
    border-radius: 20px;
    background: #14513f;
}

.area-progress.ramai div {
    background: #f0b429;
}

.area-progress.penuh div {
    background: #d9534f;
}

.area-status {
    margin: 0;
    color: #62736a;
    font-size: 13px;
}

.area-panel {
    padding: 23px;
    margin-bottom: 20px;
    border: 1px solid #d9e2dc;
    border-radius: 14px;
    background: #fff;
}

.area-panel h3 {
    margin: 0 0 8px;
    color: #0c3b2e;
    font-size: 19px;
}

.area-panel > p {
    margin: 0 0 20px;
    color: #62736a;
    font-size: 14px;
}

.area-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-top: 20px;
}

.area-form-group label {
    display: block;
    margin-bottom: 8px;
    color: #16241e;
    font-size: 14px;
    font-weight: 600;
}

.area-form-group input {
    box-sizing: border-box;
    width: 100%;
    padding: 12px;
    border: 1px solid #d9e2dc;
    border-radius: 8px;
    background: #f8faf8;
    color: #16241e;
    font-size: 14px;
}

.area-form-group input:focus {
    outline: 2px solid rgba(20, 81, 63, .25);
    border-color: #14513f;
    background: #fff;
}

.area-submit {
    margin-top: 20px;
    padding: 12px 18px;
    border: 0;
    border-radius: 8px;
    background: #f0b429;
    color: #0c3b2e;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

.area-submit:hover {
    background: #f5c24a;
}

@media (max-width: 800px) {
    .area-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 520px) {
    .area-container {
        padding: 15px;
    }

    .area-stats,
    .area-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="area-container">
    <div class="area-heading">
        <h2>Area Parkir</h2>
        <p>Kelola kapasitas dan pantau kendaraan di setiap area parkir.</p>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="area-alert area-success">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="area-alert area-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (count($areas) === 2): ?>
        <div class="area-grid">
            <?php foreach ($areas as $a): ?>
                <section class="area-card">
                    <div class="area-card-top">
                        <div>
                            <h3><?= e($a['nama_area']) ?></h3>
                            <div class="area-card-subtitle">
                                Area parkir khusus <?= e($a['jenis']) ?>
                            </div>
                        </div>

                        <span class="area-tag">
                            <?= e($a['jenis']) ?>
                        </span>
                    </div>

                    <div class="area-stats">
                        <div class="area-stat">
                            <span>Kapasitas</span>
                            <strong><?= number_format($a['kapasitas'], 0, ',', '.') ?></strong>
                        </div>

                        <div class="area-stat">
                            <span>Terisi</span>
                            <strong><?= number_format($a['terisi'], 0, ',', '.') ?></strong>
                        </div>

                        <div class="area-stat">
                            <span>Tersedia</span>
                            <strong><?= number_format($a['sisa'], 0, ',', '.') ?></strong>
                        </div>
                    </div>

                    <div class="area-progress <?= e($a['kelas']) ?>">
                        <div style="width: <?= $a['persentase'] ?>%"></div>
                    </div>

                    <p class="area-status">
                        <?= $a['persentase'] ?>% kapasitas digunakan.
                    </p>
                </section>
            <?php endforeach; ?>
        </div>

        <?php
        $areaA = null;
        $areaB = null;

        foreach ($areas as $a) {
            if ($a['nama_area'] === 'Area A') {
                $areaA = $a;
            } elseif ($a['nama_area'] === 'Area B') {
                $areaB = $a;
            }
        }
        ?>

        <?php if ($areaA && $areaB): ?>
            <section class="area-panel">
                <h3>Pengaturan Kapasitas</h3>
                <p>Atur kapasitas maksimal area motor dan mobil.</p>

                <form method="POST">
                    <div class="area-form-grid">
                        <div class="area-form-group">
                            <label for="kapasitas_a">Area A - Motor</label>
                            <input
                                type="number"
                                id="kapasitas_a"
                                name="kapasitas_a"
                                min="<?= max(1, $areaA['terisi']) ?>"
                                value="<?= $areaA['kapasitas'] ?>"
                                required
                            >
                        </div>

                        <div class="area-form-group">
                            <label for="kapasitas_b">Area B - Mobil</label>
                            <input
                                type="number"
                                id="kapasitas_b"
                                name="kapasitas_b"
                                min="<?= max(1, $areaB['terisi']) ?>"
                                value="<?= $areaB['kapasitas'] ?>"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit" class="area-submit">
                        Simpan Perubahan
                    </button>
                </form>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
