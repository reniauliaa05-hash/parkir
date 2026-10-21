<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['admin']);

$judul = 'Backup Database';
$pesan = '';
$berhasil = false;

$folderBackup = 'C:/backup-parkir';
$database = 'db_parkir';
$mysqlBin = 'C:/xampp/mysql/bin/mysqldump.exe';

// Membuat folder backup jika belum tersedia
if (!is_dir($folderBackup)) {
    if (!mkdir($folderBackup, 0775, true) && !is_dir($folderBackup)) {
        $pesan = 'Folder backup tidak dapat dibuat. Periksa izin folder C:/backup-parkir.';
    }
}

// Proses backup ketika tombol ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pesan === '') {
    $tujuan = '';

    try {
        if (!is_file($mysqlBin)) {
            throw new RuntimeException(
                'mysqldump.exe tidak ditemukan. Periksa lokasi instalasi XAMPP.'
            );
        }

        if (!is_writable($folderBackup)) {
            throw new RuntimeException(
                'Folder backup tidak dapat ditulisi. Periksa izin folder.'
            );
        }

        // Membuat nama file unik
        $namaFile = 'db_parkir_' . date('Ymd_His') . '.sql';
        $tujuan = $folderBackup . '/' . $namaFile;

        // Backup database menggunakan mysqldump
        $perintah = '"' . $mysqlBin . '"'
            . ' --host=localhost'
            . ' --port=3306'
            . ' --user=root'
            . ' --default-character-set=utf8mb4'
            . ' --single-transaction'
            . ' --routines'
            . ' --triggers'
            . ' --events'
            . ' ' . escapeshellarg($database)
            . ' > ' . escapeshellarg($tujuan)
            . ' 2>&1';

        $output = [];
        $kodeKeluar = 1;

        exec($perintah, $output, $kodeKeluar);

        clearstatcache(true, $tujuan);

        if (
            $kodeKeluar !== 0 ||
            !is_file($tujuan) ||
            filesize($tujuan) < 20
        ) {
            $detail = trim(implode("\n", $output));

            throw new RuntimeException(
                'Backup gagal. Pastikan MySQL aktif dan database db_parkir tersedia.'
                . ($detail !== '' ? ' Detail: ' . $detail : '')
            );
        }

        // Catat aktivitas menggunakan koneksi PDO
        if (function_exists('catatAktivitas')) {
            catatAktivitas($pdo, 'Backup database db_parkir berhasil');
        }

        // Siapkan file untuk diunduh
        if (headers_sent()) {
            throw new RuntimeException(
                'File backup berhasil dibuat, tetapi pengunduhan tidak dapat dimulai karena header HTTP sudah terkirim.'
            );
        }

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $namaFile . '"');
        header('Content-Length: ' . filesize($tujuan));
        header('Cache-Control: no-store, no-cache, must-revalidate');

        readfile($tujuan);
        exit;

    } catch (Throwable $e) {
        if ($tujuan !== '' && is_file($tujuan) && filesize($tujuan) < 20) {
            @unlink($tujuan);
        }

        $pesan = $e->getMessage();
    }
}
?>

<?php require_once __DIR__ . '/header.php'; ?>

<section class="page-header">
    <h1>Backup Database</h1>
    <p>Buat salinan database ParkirKu dan unduh file SQL.</p>
</section>

<section class="card" style="max-width:760px;background:#fff;padding:24px;border-radius:14px;margin-top:20px;">

    <?php if ($pesan !== ''): ?>
        <div style="padding:12px 14px;border-radius:8px;background:#fff1f0;color:#9f241d;margin-bottom:16px;white-space:pre-wrap;">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <p>
        File backup disimpan di
        <strong>C:\backup-parkir</strong>
        dan otomatis diunduh setelah proses berhasil.
    </p>

    <form method="post" onsubmit="return confirm('Buat backup database sekarang?')">
        <button
            type="submit"
            style="border:0;border-radius:8px;padding:11px 18px;background:#14513f;color:#fff;cursor:pointer;font-weight:600;"
        >
            Backup Sekarang
        </button>
    </form>

</section>

</main>
</div>
</body>
</html>