<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['admin']);

$pesan = '';
$error = '';

/*
 * Menyimpan tarif baru atau memperbarui tarif.
 * Diasumsikan tb_tarif memiliki kolom:
 * id_tarif, jenis_kendaraan, tarif_per_jam.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $jenis = trim($_POST['jenis_kendaraan'] ?? '');
    $tarif = filter_var(
        $_POST['tarif_per_jam'] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($aksi === 'simpan') {
        if ($jenis === '' || $tarif === false || $tarif < 0) {
            $error = 'Jenis kendaraan dan tarif yang valid wajib diisi.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO tb_tarif (jenis_kendaraan, tarif_per_jam)
                     VALUES (?, ?)"
                );
                $stmt->execute([$jenis, $tarif]);
                header('Location: tarif.php?status=tambah');
                exit;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = 'Tarif gagal disimpan. Periksa struktur tabel tb_tarif.';
            }
        }
    }

    if ($aksi === 'ubah') {
        $id = filter_var(
            $_POST['id_tarif'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            !$id ||
            $jenis === '' ||
            $tarif === false ||
            $tarif < 0
        ) {
            $error = 'Data tarif untuk diperbarui tidak valid.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE tb_tarif
                     SET jenis_kendaraan = ?, tarif_per_jam = ?
                     WHERE id_tarif = ?"
                );
                $stmt->execute([$jenis, $tarif, $id]);
                header('Location: tarif.php?status=ubah');
                exit;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = 'Tarif gagal diperbarui. Periksa struktur tabel tb_tarif.';
            }
        }
    }
}

if (($_GET['status'] ?? '') === 'tambah') {
    $pesan = 'Tarif baru berhasil ditambahkan.';
} elseif (($_GET['status'] ?? '') === 'ubah') {
    $pesan = 'Tarif berhasil diperbarui.';
}

$daftarTarif = [];
try {
    $stmt = $pdo->query(
        "SELECT id_tarif, jenis_kendaraan, tarif_per_jam
         FROM tb_tarif
         ORDER BY id_tarif ASC"
    );
    $daftarTarif = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Data tarif gagal dibaca. Periksa kolom tabel tb_tarif.';
}

require_once __DIR__ . '/header.php';
?>

<style>
/* Hanya WARNA yang diubah. Ukuran, jarak, dan tata letak sama seperti semula. */

.tarif-page {
    padding: 24px;
    color: #16241e;
}

.tarif-heading {
    margin-bottom: 22px;
}

.tarif-heading h2 {
    margin: 0 0 8px;
    font-size: 24px;
    color: #0c3b2e;
}

.tarif-heading p {
    margin: 0;
    color: #62736a;
    font-size: 14px;
}

.tarif-card {
    margin-bottom: 22px;
    padding: 24px;
    background: #fff;
    border: 1px solid #d9e2dc;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(12, 59, 46, .05);
}

.tarif-card h3 {
    margin: 0 0 20px;
    font-size: 17px;
    color: #0c3b2e;
}

.tarif-form {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    align-items: end;
    gap: 16px;
}

.tarif-field label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #16241e;
}

.tarif-field input,
.tarif-field select {
    width: 100%;
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid #d9e2dc;
    border-radius: 7px;
    background: #f8faf8;
    color: #16241e;
    font-size: 14px;
    box-sizing: border-box;
}

.tarif-field input:focus,
.tarif-field select:focus,
.tarif-edit input:focus {
    outline: 2px solid rgba(20, 81, 63, .3);
    border-color: #14513f;
    background: #fff;
}

.tarif-btn {
    min-height: 42px;
    padding: 0 18px;
    border: none;
    border-radius: 7px;
    background: #f0b429;
    color: #0c3b2e;
    font-weight: 600;
    cursor: pointer;
}

.tarif-btn:hover {
    background: #f5c24a;
}

.tarif-btn:focus-visible {
    outline: 2px solid #0c3b2e;
    outline-offset: 2px;
}

.tarif-alert {
    margin-bottom: 18px;
    padding: 12px 15px;
    border-radius: 8px;
    background: #e9f7ef;
    color: #14653f;
    font-size: 14px;
}

.tarif-alert.error {
    background: #fdeeee;
    color: #a12b2b;
}

.tarif-table-wrap {
    overflow-x: auto;
}

.tarif-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    text-align: left;
}

.tarif-table th {
    padding: 14px 12px;
    background: #f3f7f4;
    color: #62736a;
    border-bottom: 1px solid #d9e2dc;
    white-space: nowrap;
}

.tarif-table td {
    padding: 14px 12px;
    border-bottom: 1px solid #edf2ee;
}

.tarif-table tbody tr:hover {
    background: #f8fbf9;
}

.tarif-price {
    color: #14513f;
    font-weight: 700;
}

.tarif-edit {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 8px;
    min-width: 380px;
}

.tarif-edit input {
    min-width: 0;
    width: 100%;
    padding: 8px;
    border: 1px solid #d9e2dc;
    border-radius: 6px;
    background: #f8faf8;
    color: #16241e;
    box-sizing: border-box;
}

.tarif-empty {
    padding: 24px;
    text-align: center;
    color: #62736a;
}

@media (max-width: 800px) {
    .tarif-page {
        padding: 16px;
    }

    .tarif-form {
        grid-template-columns: 1fr;
    }

    .tarif-form .tarif-btn {
        width: 100%;
    }
}
</style>

<div class="tarif-page">
    <div class="tarif-heading">
        <h2>Tarif Parkir</h2>
        <p>Kelola tarif parkir berdasarkan jenis kendaraan.</p>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="tarif-alert">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="tarif-alert error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <section class="tarif-card">
        <h3>Tambah Tarif Baru</h3>

        <form method="POST" action="tarif.php" class="tarif-form">
            <input type="hidden" name="aksi" value="simpan">

            <div class="tarif-field">
                <label for="jenis_kendaraan">Jenis Kendaraan</label>
                <select id="jenis_kendaraan" name="jenis_kendaraan" required>
                    <option value="">Pilih jenis kendaraan</option>
                    <option value="motor">Motor</option>
                    <option value="mobil">Mobil</option>
                </select>
            </div>

            <div class="tarif-field">
                <label for="tarif_per_jam">Tarif per Jam (Rp)</label>
                <input
                    type="number"
                    id="tarif_per_jam"
                    name="tarif_per_jam"
                    min="0"
                    step="1"
                    placeholder="Contoh: 3000"
                    required
                >
            </div>

            <button type="submit" class="tarif-btn">
                Simpan Tarif
            </button>
        </form>
    </section>

    <section class="tarif-card">
        <h3>Daftar Tarif Kendaraan</h3>

        <div class="tarif-table-wrap">
            <table class="tarif-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Jenis Kendaraan</th>
                        <th>Tarif per Jam</th>
                        <th>Ubah Tarif</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($daftarTarif)): ?>
                        <tr>
                            <td colspan="4" class="tarif-empty">
                                Belum ada data tarif parkir.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daftarTarif as $i => $tarifItem): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e(ucfirst($tarifItem['jenis_kendaraan'])) ?></td>
                                <td class="tarif-price">
                                    Rp <?= number_format((float) $tarifItem['tarif_per_jam'], 0, ',', '.') ?>
                                </td>
                                <td>
                                    <form method="POST" action="tarif.php" class="tarif-edit">
                                        <input type="hidden" name="aksi" value="ubah">
                                        <input type="hidden" name="id_tarif"
                                               value="<?= (int) $tarifItem['id_tarif'] ?>">

                                        <input
                                            type="text"
                                            name="jenis_kendaraan"
                                            value="<?= e($tarifItem['jenis_kendaraan']) ?>"
                                            aria-label="Jenis kendaraan"
                                            required
                                        >

                                        <input
                                            type="number"
                                            name="tarif_per_jam"
                                            value="<?= (int) $tarifItem['tarif_per_jam'] ?>"
                                            min="0"
                                            step="1"
                                            aria-label="Tarif per jam"
                                            required
                                        >

                                        <button type="submit" class="tarif-btn">
                                            Simpan
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