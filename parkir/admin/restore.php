<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['admin']);

$judul = 'Restore Database';
$pesan = '';
$berhasil = false;

$database = 'db_parkir';
$mysqlBin = 'C:/xampp/mysql/bin/mysql.exe';
$folderTemp = __DIR__ . '/../storage/restore-temp';
$batasUkuran = 100 * 1024 * 1024;

// Proses restore
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fileTemp = '';

    try {
        // Validasi upload
        if (!isset($_FILES['file_sql'])) {
            throw new RuntimeException('Pilih file backup SQL terlebih dahulu.');
        }

        $file = $_FILES['file_sql'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'Upload file gagal. Kode error: ' . $file['error']
            );
        }

        if (
            strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'sql'
        ) {
            throw new RuntimeException('File harus berformat .sql.');
        }

        if (
            $file['size'] <= 0 ||
            $file['size'] > $batasUkuran
        ) {
            throw new RuntimeException(
                'Ukuran file kosong atau melebihi batas 100 MB.'
            );
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('File upload tidak valid.');
        }

        if (!is_file($mysqlBin)) {
            throw new RuntimeException(
                'Program mysql.exe tidak ditemukan: ' . $mysqlBin
            );
        }

        // Buat folder sementara
        if (
            !is_dir($folderTemp) &&
            !mkdir($folderTemp, 0775, true) &&
            !is_dir($folderTemp)
        ) {
            throw new RuntimeException(
                'Folder sementara restore gagal dibuat.'
            );
        }

        if (!is_writable($folderTemp)) {
            throw new RuntimeException(
                'Folder sementara restore tidak dapat ditulisi.'
            );
        }

        $fileTemp = $folderTemp . '/restore_' .
            bin2hex(random_bytes(8)) . '.sql';

        if (!move_uploaded_file($file['tmp_name'], $fileTemp)) {
            throw new RuntimeException(
                'File SQL gagal dipindahkan ke folder sementara.'
            );
        }

        // Baca isi awal untuk mendeteksi pembungkus Markdown.
        $isiAwal = file_get_contents($fileTemp, false, null, 0, 4096);

        if ($isiAwal === false || trim($isiAwal) === '') {
            throw new RuntimeException('File SQL kosong atau tidak dapat dibaca.');
        }

        // Hapus BOM UTF-8 jika ada.
        if (strncmp($isiAwal, "\xEF\xBB\xBF", 3) === 0) {
            $isiAwal = substr($isiAwal, 3);
        }

        // Deteksi file yang diawali ```sql, ```php, atau ``` lainnya.
        if (preg_match('/^\s*```[a-zA-Z0-9_-]*\s*(?:\r?\n)/', $isiAwal)) {
            $isiLengkap = file_get_contents($fileTemp);

            if ($isiLengkap === false) {
                throw new RuntimeException('File SQL gagal dibaca.');
            }

            $isiLengkap = preg_replace(
                '/^\xEF\xBB\xBF/',
                '',
                $isiLengkap
            );

            $isiLengkap = preg_replace(
                '/^\s*```[a-zA-Z0-9_-]*[ \t]*\r?\n/',
                '',
                $isiLengkap,
                1
            );

            // Hapus penutup Markdown hanya jika berada di akhir file.
            $isiLengkap = preg_replace(
                '/\r?\n[ \t]*```[ \t]*\r?\n?[ \t]*$/',
                "\n",
                $isiLengkap,
                1
            );

            if (file_put_contents($fileTemp, $isiLengkap, LOCK_EX) === false) {
                throw new RuntimeException(
                    'File SQL gagal diperbaiki dari pembungkus Markdown.'
                );
            }
        }

        // Pastikan file mengandung struktur SQL yang masuk akal.
        $handle = fopen($fileTemp, 'rb');

        if ($handle === false) {
            throw new RuntimeException('File SQL tidak dapat diperiksa.');
        }

        $awal = fread($handle, 8192);
        fclose($handle);

        if (
            $awal === false ||
            !preg_match(
                '/(?:--|\/\*|CREATE\s+|INSERT\s+|REPLACE\s+|SET\s+|USE\s+|START\s+TRANSACTION|LOCK\s+TABLES)/i',
                $awal
            )
        ) {
            throw new RuntimeException(
                'Isi file tidak terlihat seperti dump SQL yang valid. Periksa file backup.'
            );
        }

        /*
         * PERINGATAN:
         * Restore dapat mengubah atau menimpa data.
         * Sesuaikan user, password, dan port dengan konfigurasi MySQL.
         * Password tidak ditulis di command line pada konfigurasi default root tanpa password.
         */
        $perintah = '"' . $mysqlBin . '"'
            . ' --host=localhost'
            . ' --port=3306'
            . ' --user=root'
            . ' --default-character-set=utf8mb4'
            . ' ' . escapeshellarg($database)
            . ' < ' . escapeshellarg($fileTemp)
            . ' 2>&1';

        $output = [];
        $kodeKeluar = 1;

        exec($perintah, $output, $kodeKeluar);

        if ($kodeKeluar !== 0) {
            $detail = trim(implode("\n", $output));

            throw new RuntimeException(
                'Restore gagal. Periksa format SQL, koneksi MySQL, dan hak akses.'
                . ($detail !== '' ? ' Detail: ' . $detail : '')
            );
        }

        // Catat aktivitas setelah restore berhasil.
        if (function_exists('catatAktivitas')) {
            catatAktivitas($pdo, 'Restore database db_parkir berhasil');
        }

        $berhasil = true;
        $pesan = 'Restore selesai. Database db_parkir berhasil dipulihkan.';

    } catch (Throwable $e) {
        $pesan = $e->getMessage();

    } finally {
        // Hapus file sementara setelah proses selesai.
        if ($fileTemp !== '' && is_file($fileTemp)) {
            @unlink($fileTemp);
        }
    }
}
?>

<?php require_once __DIR__ . '/header.php'; ?>

<section class="page-header">
    <h1>Restore Database</h1>
    <p>Pulihkan database ParkirKu menggunakan file backup SQL.</p>
</section>

<section class="card" style="max-width:760px;background:#fff;padding:24px;border-radius:14px;margin-top:20px;">

    <?php if ($pesan !== ''): ?>
        <div style="padding:12px 14px;border-radius:8px;background:<?= $berhasil ? '#e8f7ee' : '#fff1f0' ?>;color:<?= $berhasil ? '#17633b' : '#9f241d' ?>;margin-bottom:16px;white-space:pre-wrap;">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <p>
        <strong>Perhatian:</strong>
        proses restore dapat mengubah atau menimpa data yang ada.
        Pastikan file backup benar sebelum melanjutkan.
    </p>

    <form
        method="post"
        enctype="multipart/form-data"
        onsubmit="return confirm('Yakin ingin restore database? Data yang ada dapat berubah atau tertimpa.')"
    >
        <label
            for="file_sql"
            style="display:block;margin:14px 0 8px;font-weight:600;"
        >
            Pilih file backup (.sql)
        </label>

        <input
            id="file_sql"
            type="file"
            name="file_sql"
            accept=".sql"
            required
            style="display:block;margin-bottom:18px;"
        >

        <button
            type="submit"
            style="border:0;border-radius:8px;padding:11px 18px;background:#14513f;color:#fff;cursor:pointer;font-weight:600;"
        >
            Restore Sekarang
        </button>
    </form>
</section>

</main>
</div>
</body>
</html>
