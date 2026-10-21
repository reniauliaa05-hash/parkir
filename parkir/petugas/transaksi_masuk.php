
<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['petugas']);

$judul = 'Kendaraan Masuk';
$error = '';
$sukses = isset($_GET['sukses']);
$kendaraanBaru = ($_GET['sukses'] ?? '') === 'baru';

$platInput = '';
$jenisInput = '';
$areaInput = 0;
$daftarJenis = [];
$kendaraan = [];
$petaPlat = [];
$area = [];
$daftarParkir = [];
$adaAreaTersedia = false;

/*
|--------------------------------------------------------------------------
| FUNGSI BANTU PLAT NOMOR
|--------------------------------------------------------------------------
*/
if (!function_exists('normalisasiPlat')) {
    function normalisasiPlat(string $input): ?string
    {
        $bersih = strtoupper(
            preg_replace('/[^A-Za-z0-9]/', '', $input)
        );

        if (!preg_match('/^([A-Z]{1,2})(\d{1,4})([A-Z]{0,3})$/', $bersih, $m)) {
            return null;
        }

        return trim($m[1] . ' ' . $m[2] . ' ' . $m[3]);
    }
}

if (!function_exists('kunciPlat')) {
    function kunciPlat(string $plat): string
    {
        return strtoupper(
            preg_replace('/[^A-Za-z0-9]/', '', $plat)
        );
    }
}

/*
|--------------------------------------------------------------------------
| AMBIL DAFTAR TARIF
|--------------------------------------------------------------------------
*/
try {
    $stmt = $pdo->query(
        'SELECT DISTINCT jenis_kendaraan
         FROM tb_tarif
         ORDER BY jenis_kendaraan ASC'
    );

    $daftarJenis = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $error = 'Gagal mengambil data tarif. Periksa tabel tb_tarif.';
}

/*
|--------------------------------------------------------------------------
| PROSES KENDARAAN MASUK
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $platInput = trim((string) ($_POST['plat_nomor'] ?? ''));
    $jenisInput = trim((string) ($_POST['jenis_kendaraan'] ?? ''));
    $areaInput = (int) ($_POST['id_area'] ?? 0);

    try {
        if ($platInput === '') {
            throw new Exception('Plat nomor wajib diisi.');
        }

        $platFormat = normalisasiPlat($platInput);

        if ($platFormat === null) {
            throw new Exception(
                'Format plat nomor tidak valid. Contoh: L 1234 AB.'
            );
        }

        if ($areaInput <= 0) {
            throw new Exception('Silakan pilih area parkir.');
        }

        if (empty($daftarJenis)) {
            throw new Exception('Data tarif belum tersedia.');
        }

        $platKunci = kunciPlat($platFormat);

        $pdo->beginTransaction();

        /*
         * Cari kendaraan berdasarkan plat nomor.
         */
        $stmt = $pdo->prepare(
            "SELECT id_kendaraan, plat_nomor, jenis_kendaraan
             FROM tb_kendaraan
             WHERE REPLACE(
                 REPLACE(UPPER(plat_nomor), ' ', ''),
                 '-', ''
             ) = ?
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->execute([$platKunci]);
        $dataKendaraan = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dataKendaraan) {
            $idKendaraan = (int) $dataKendaraan['id_kendaraan'];
            $jenisKendaraan = trim(
                (string) $dataKendaraan['jenis_kendaraan']
            );
            $platTercatat = $dataKendaraan['plat_nomor'];
            $baru = false;
        } else {
            if ($jenisInput === '') {
                throw new Exception(
                    'Kendaraan belum terdaftar. Pilih jenis kendaraan.'
                );
            }

            if (!in_array($jenisInput, $daftarJenis, true)) {
                throw new Exception('Jenis kendaraan tidak valid.');
            }

            $jenisKendaraan = $jenisInput;

            $stmt = $pdo->prepare(
                'INSERT INTO tb_kendaraan
                    (plat_nomor, jenis_kendaraan)
                 VALUES (?, ?)'
            );

            $stmt->execute([$platFormat, $jenisKendaraan]);

            $idKendaraan = (int) $pdo->lastInsertId();
            $platTercatat = $platFormat;
            $baru = true;
        }

        /*
         * Periksa apakah kendaraan masih parkir.
         */
        $stmt = $pdo->prepare(
            "SELECT id_parkir
             FROM tb_transaksi
             WHERE id_kendaraan = ?
               AND status = 'masuk'
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->execute([$idKendaraan]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception(
                'Kendaraan ini masih tercatat sedang parkir.'
            );
        }

        /*
         * Ambil tarif kendaraan.
         */
        $stmt = $pdo->prepare(
            'SELECT id_tarif
             FROM tb_tarif
             WHERE jenis_kendaraan = ?
             LIMIT 1'
        );

        $stmt->execute([$jenisKendaraan]);
        $idTarif = $stmt->fetchColumn();

        if (!$idTarif) {
            throw new Exception(
                'Tarif untuk jenis kendaraan ini belum tersedia.'
            );
        }

        /*
         * Ambil area dan kunci barisnya.
         */
        $stmt = $pdo->prepare(
            'SELECT id_area, nama_area, kapasitas, terisi
             FROM tb_area_parkir
             WHERE id_area = ?
             FOR UPDATE'
        );

        $stmt->execute([$areaInput]);
        $dataArea = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dataArea) {
            throw new Exception('Area parkir tidak ditemukan.');
        }

        $kapasitas = (int) $dataArea['kapasitas'];
        $terisi = (int) $dataArea['terisi'];

        if ($kapasitas <= 0 || $terisi >= $kapasitas) {
            throw new Exception('Area parkir sudah penuh.');
        }

        /*
         * Validasi jenis kendaraan dengan area parkir.
         */
        $namaArea = strtolower(
            trim((string) $dataArea['nama_area'])
        );

        $jenisLower = strtolower(trim($jenisKendaraan));

        $jenisMotor = str_contains($jenisLower, 'motor');
        $jenisMobil = str_contains($jenisLower, 'mobil');

        $areaMotor = preg_match('/\barea\s*a\b/', $namaArea)
            || str_contains($namaArea, 'motor');

        $areaMobil = preg_match('/\barea\s*b\b/', $namaArea)
            || str_contains($namaArea, 'mobil');

        if ($jenisMotor && !$areaMotor) {
            throw new Exception(
                'Motor harus ditempatkan di Area A atau area motor.'
            );
        }

        if ($jenisMobil && !$areaMobil) {
            throw new Exception(
                'Mobil harus ditempatkan di Area B atau area mobil.'
            );
        }

        /*
         * Periksa sesi petugas.
         */
        $idUser = (int) ($_SESSION['user']['id_user'] ?? 0);

        if ($idUser <= 0) {
            throw new Exception(
                'Data login petugas tidak ditemukan. Silakan login kembali.'
            );
        }

        /*
         * Simpan transaksi masuk.
         */
        $stmt = $pdo->prepare(
            "INSERT INTO tb_transaksi (
                id_kendaraan,
                waktu_masuk,
                id_tarif,
                durasi_jam,
                biaya_total,
                status,
                id_user,
                id_area
            ) VALUES (?, NOW(), ?, 0, 0, 'masuk', ?, ?)"
        );

        $stmt->execute([
            $idKendaraan,
            $idTarif,
            $idUser,
            $areaInput
        ]);

        /*
         * Tambah jumlah kendaraan di area.
         */
        $stmt = $pdo->prepare(
            'UPDATE tb_area_parkir
             SET terisi = terisi + 1
             WHERE id_area = ?
               AND terisi < kapasitas'
        );

        $stmt->execute([$areaInput]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception(
                'Gagal memperbarui kapasitas area parkir.'
            );
        }

        /*
         * Catat aktivitas dan simpan transaksi.
         */
        catatAktivitas(
            $pdo,
            'Kendaraan masuk: ' . $platTercatat
        );

        $pdo->commit();

        header(
            'Location: transaksi_masuk.php?sukses=' .
            ($baru ? 'baru' : '1')
        );
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| AMBIL KENDARAAN YANG BELUM PARKIR
|--------------------------------------------------------------------------
*/
try {
    $stmt = $pdo->query(
        "SELECT k.id_kendaraan, k.plat_nomor, k.jenis_kendaraan
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
} catch (Throwable $e) {
    if ($error === '') {
        $error = 'Gagal mengambil data kendaraan.';
    }
}

/*
|--------------------------------------------------------------------------
| PETA PLAT NOMOR
|--------------------------------------------------------------------------
*/
foreach ($kendaraan as $k) {
    $petaPlat[kunciPlat($k['plat_nomor'])] =
        $k['jenis_kendaraan'];
}

/*
|--------------------------------------------------------------------------
| AMBIL DAN KELOMPOKKAN AREA PARKIR
|--------------------------------------------------------------------------
*/
try {
    $stmt = $pdo->query(
        'SELECT id_area, nama_area, kapasitas, terisi
         FROM tb_area_parkir
         ORDER BY id_area ASC'
    );

    $semuaArea = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $kelompokMotor = [];
    $kelompokMobil = [];
    $kelompokLain = [];

    foreach ($semuaArea as $a) {
        $nama = strtolower(trim((string) $a['nama_area']));

        $adalahMotor = str_contains($nama, 'motor')
            || preg_match('/\barea\s*a\b/', $nama);

        $adalahMobil = str_contains($nama, 'mobil')
            || preg_match('/\barea\s*b\b/', $nama);

        if ($adalahMotor) {
            $kelompokMotor[] = $a;
        } elseif ($adalahMobil) {
            $kelompokMobil[] = $a;
        } else {
            /*
             * Hilangkan perbedaan spasi dan tanda baca
             * untuk mencegah nama area yang sama berulang.
             */
            $kunci = preg_replace('/[^a-z0-9]/', '', $nama);

            if ($kunci !== '' && !isset($kelompokLain[$kunci])) {
                $kelompokLain[$kunci] = $a;
            }
        }
    }

    /*
     * Pilih satu baris area motor.
     * Area yang masih tersedia lebih diprioritaskan.
     */
    if (!empty($kelompokMotor)) {
        $pilihan = $kelompokMotor[0];

        foreach ($kelompokMotor as $a) {
            if (
                (int) $a['kapasitas'] > 0
                && (int) $a['terisi'] < (int) $a['kapasitas']
            ) {
                $pilihan = $a;
                break;
            }
        }

        $pilihan['nama_area'] = 'Area A - Motor';
        $area[] = $pilihan;
    }

    /*
     * Pilih satu baris area mobil.
     */
    if (!empty($kelompokMobil)) {
        $pilihan = $kelompokMobil[0];

        foreach ($kelompokMobil as $a) {
            if (
                (int) $a['kapasitas'] > 0
                && (int) $a['terisi'] < (int) $a['kapasitas']
            ) {
                $pilihan = $a;
                break;
            }
        }

        $pilihan['nama_area'] = 'Area B - Mobil';
        $area[] = $pilihan;
    }

    /*
     * Tambahkan area lain yang tidak termasuk kelompok motor/mobil.
     */
    foreach ($kelompokLain as $a) {
        $area[] = $a;
    }

    foreach ($area as $a) {
        if (
            (int) $a['kapasitas'] > 0
            && (int) $a['terisi'] < (int) $a['kapasitas']
        ) {
            $adaAreaTersedia = true;
            break;
        }
    }

} catch (Throwable $e) {
    $error = 'Gagal mengambil data area parkir.';
}

/*
|--------------------------------------------------------------------------
| AMBIL KENDARAAN YANG SEDANG PARKIR
|--------------------------------------------------------------------------
*/
try {
    $stmt = $pdo->query(
        "SELECT
            t.id_parkir,
            k.plat_nomor,
            k.jenis_kendaraan,
            a.nama_area,
            t.waktu_masuk
         FROM tb_transaksi t
         INNER JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
         LEFT JOIN tb_area_parkir a
            ON a.id_area = t.id_area
         WHERE t.status = 'masuk'
         ORDER BY t.waktu_masuk DESC"
    );

    $daftarParkir = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if ($error === '') {
        $error = 'Gagal mengambil daftar kendaraan yang sedang parkir.';
    }
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
        Transaksi kendaraan masuk berhasil disimpan.
        <?= $kendaraanBaru
            ? 'Kendaraan baru juga sudah didaftarkan.'
            : '' ?>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Catat Kendaraan Masuk</h2>
    </div>

    <?php if (empty($area)): ?>
        <div class="alert">
            Belum ada data area parkir. Tambahkan area parkir terlebih dahulu.
        </div>
    <?php endif; ?>

    <?php if (empty($daftarJenis)): ?>
        <div class="alert">
            Belum ada data tarif. Tambahkan tarif terlebih dahulu.
        </div>
    <?php endif; ?>

    <?php if (!empty($area) && !$adaAreaTersedia): ?>
        <div class="alert">
            Semua area parkir sedang penuh.
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="form-grid">
            <div class="form-group">
                <label for="plat_nomor">Plat Nomor</label>

                <input
                    type="text"
                    name="plat_nomor"
                    id="plat_nomor"
                    list="daftar_plat"
                    placeholder="Contoh: L 1234 AB"
                    value="<?= htmlspecialchars(
                        $platInput,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    maxlength="12"
                    autocapitalize="characters"
                    spellcheck="false"
                    style="text-transform: uppercase;"
                    required
                    autofocus
                >

                <datalist id="daftar_plat">
                    <?php foreach ($kendaraan as $k): ?>
                        <option value="<?= htmlspecialchars(
                            $k['plat_nomor'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"></option>
                    <?php endforeach; ?>
                </datalist>

                <small
                    id="infoPlat"
                    style="display:block;margin-top:6px;color:#62736a;"
                >
                    Ketik plat nomor. Jika belum terdaftar, kendaraan
                    akan didaftarkan otomatis.
                </small>
            </div>

            <div class="form-group">
                <label for="jenis_kendaraan">Jenis Kendaraan</label>

                <select name="jenis_kendaraan" id="jenis_kendaraan">
                    <option value="">Pilih jenis kendaraan</option>

                    <?php foreach ($daftarJenis as $j): ?>
                        <option
                            value="<?= htmlspecialchars(
                                $j,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            <?= $jenisInput === $j ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars(
                                $j,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_area">Area Parkir</label>

                <select name="id_area" id="id_area" required>
                    <option value="">Pilih area parkir</option>

                    <?php foreach ($area as $a): ?>
                        <?php
                        $kapasitas = (int) $a['kapasitas'];
                        $terisi = (int) $a['terisi'];
                        $penuh = $kapasitas <= 0 || $terisi >= $kapasitas;
                        ?>

                        <option
                            value="<?= (int) $a['id_area'] ?>"
                            <?= $penuh ? 'disabled' : '' ?>
                            <?= (int) $a['id_area'] === $areaInput && !$penuh
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars(
                                $a['nama_area'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                            (<?= $terisi ?>/<?= $kapasitas ?>)
                            <?= $penuh ? '- PENUH' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button
            class="btn btn-primary"
            type="submit"
            <?= empty($daftarJenis) || !$adaAreaTersedia
                ? 'disabled'
                : '' ?>
        >
            Simpan Kendaraan Masuk
        </button>
    </form>
</div>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Kendaraan Sedang Parkir</h2>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Plat Nomor</th>
                    <th>Jenis</th>
                    <th>Area</th>
                    <th>Waktu Masuk</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!empty($daftarParkir)): ?>
                    <?php foreach ($daftarParkir as $i => $t): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>

                            <td><?= htmlspecialchars(
                                $t['plat_nomor'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></td>

                            <td><?= htmlspecialchars(
                                $t['jenis_kendaraan'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></td>

                            <td><?= htmlspecialchars(
                                $t['nama_area'] ?? '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></td>

                            <td><?= htmlspecialchars(
                                $t['waktu_masuk'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            Tidak ada kendaraan yang sedang parkir.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const peta = <?= json_encode(
        $petaPlat,
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_UNESCAPED_UNICODE
    ) ?>;

    const plat = document.getElementById('plat_nomor');
    const jenis = document.getElementById('jenis_kendaraan');
    const info = document.getElementById('infoPlat');

    if (!plat || !jenis || !info) {
        return;
    }

    const teksAwal = info.textContent;

    function periksaPlat() {
        const kunci = plat.value
            .toUpperCase()
            .replace(/[^A-Z0-9]/g, '');

        if (
            kunci !== '' &&
            Object.prototype.hasOwnProperty.call(peta, kunci)
        ) {
            jenis.value = peta[kunci];
            jenis.disabled = true;

            info.textContent =
                'Kendaraan terdaftar. Jenis: ' + peta[kunci] + '.';
        } else {
            jenis.disabled = false;
            info.textContent = teksAwal;
        }
    }

    /*
     * Aktifkan select sebelum submit agar nilainya terkirim.
     */
    const form = plat.closest('form');

    if (form) {
        form.addEventListener('submit', function () {
            jenis.disabled = false;
        });
    }

    plat.addEventListener('input', periksaPlat);
    plat.addEventListener('change', periksaPlat);

    periksaPlat();
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
