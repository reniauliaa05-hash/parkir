
<?php
require_once __DIR__ . '/koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('e')) {
    function e($nilai): string
    {
        return htmlspecialchars(
            (string) $nilai,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function arahkanDashboardLogin(string $role): void
{
    $halaman = [
        'admin'   => 'admin/dashboard.php',
        'petugas' => 'petugas/dashboard.php',
        'owner'   => 'owner/dashboard.php',
    ];

    $role = strtolower(trim($role));

    if (isset($halaman[$role])) {
        header('Location: ' . $halaman[$role]);
        exit;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    header('Location: login.php');
    exit;
}

if (!empty($_SESSION['user']['id_user'])) {
    arahkanDashboardLogin(
        (string) ($_SESSION['user']['role'] ?? '')
    );
}

if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

const BATAS_GAGAL = 5;
const DURASI_BLOKIR = 300;

$error = '';
$usernameInput = '';
$roleDipilih = '';

$rolesValid = ['admin', 'petugas', 'owner'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = trim(
        (string) ($_POST['username'] ?? '')
    );

    $passwordInput = (string) ($_POST['password'] ?? '');

    $roleDipilih = strtolower(
        trim((string) ($_POST['role'] ?? ''))
    );

    $tokenKiriman = (string) ($_POST['csrf'] ?? '');
    $tokenSesi = (string) ($_SESSION['csrf_login'] ?? '');

    $blokirSampai = (int) (
        $_SESSION['login_blokir_sampai'] ?? 0
    );

    if (
        $tokenSesi === '' ||
        !hash_equals($tokenSesi, $tokenKiriman)
    ) {
        $error = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif ($blokirSampai > time()) {
        $sisa = (int) ceil(
            ($blokirSampai - time()) / 60
        );

        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam '
            . $sisa . ' menit.';
    } elseif (
        $usernameInput === '' ||
        $passwordInput === '' ||
        $roleDipilih === ''
    ) {
        $error = 'Username, password, dan peran wajib diisi.';
    } elseif (!in_array($roleDipilih, $rolesValid, true)) {
        $error = 'Peran yang dipilih tidak valid.';
    } else {
        $pesanGagal = 'Username, password, atau peran tidak sesuai.';

        try {
            $stmt = $pdo->prepare(
                "SELECT id_user, nama_lengkap, username,
                        password, role, status_aktif
                 FROM tb_user
                 WHERE username = ?
                 LIMIT 1"
            );

            $stmt->execute([$usernameInput]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $passwordCocok = false;
            $perluRehash = false;

            if ($user) {
                $passwordDB = trim(
                    (string) $user['password']
                );

                if (preg_match('/^[a-f0-9]{64}$/i', $passwordDB)) {
                    $passwordCocok = hash_equals(
                        strtolower($passwordDB),
                        hash('sha256', $passwordInput)
                    );

                    $perluRehash = $passwordCocok;
                } else {
                    $passwordCocok = password_verify(
                        $passwordInput,
                        $passwordDB
                    );

                    $perluRehash = $passwordCocok
                        && password_needs_rehash(
                            $passwordDB,
                            PASSWORD_DEFAULT
                        );
                }
            } else {
                password_hash($passwordInput, PASSWORD_DEFAULT);
            }

            $roleDB = $user
                ? strtolower(trim((string) $user['role']))
                : '';

            if (!$user || !$passwordCocok) {
                $error = $pesanGagal;
            } elseif ((int) $user['status_aktif'] !== 1) {
                $error = 'Akun tidak aktif. Hubungi administrator.';
            } elseif (
                !in_array($roleDB, $rolesValid, true) ||
                $roleDB !== $roleDipilih
            ) {
                $error = $pesanGagal;
            } else {
                if ($perluRehash) {
                    try {
                        $upd = $pdo->prepare(
                            "UPDATE tb_user
                             SET password = ?
                             WHERE id_user = ?"
                        );

                        $upd->execute([
                            password_hash(
                                $passwordInput,
                                PASSWORD_DEFAULT
                            ),
                            (int) $user['id_user'],
                        ]);
                    } catch (Throwable $ex) {
                        error_log(
                            'Rehash password gagal: '
                            . $ex->getMessage()
                        );
                    }
                }

                session_regenerate_id(true);

                unset(
                    $_SESSION['login_gagal'],
                    $_SESSION['login_blokir_sampai'],
                    $_SESSION['csrf_login']
                );

                $_SESSION['user'] = [
                    'id_user' => (int) $user['id_user'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'username' => $user['username'],
                    'role' => $roleDB,
                ];

                try {
                    if (function_exists('catatAktivitas')) {
                        catatAktivitas($pdo, 'Login ke sistem');
                    }
                } catch (Throwable $ex) {
                    error_log(
                        'Pencatatan login gagal: '
                        . $ex->getMessage()
                    );
                }

                arahkanDashboardLogin($roleDB);
            }

            if ($error === $pesanGagal) {
                $_SESSION['login_gagal'] = (int) (
                    $_SESSION['login_gagal'] ?? 0
                ) + 1;

                if ($_SESSION['login_gagal'] >= BATAS_GAGAL) {
                    $_SESSION['login_blokir_sampai'] =
                        time() + DURASI_BLOKIR;

                    $_SESSION['login_gagal'] = 0;
                }
            }
        } catch (PDOException $ex) {
            error_log(
                'Kesalahan database login: '
                . $ex->getMessage()
            );

            $error = 'Terjadi kesalahan pada server. Silakan coba lagi nanti.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Kapasitas dan ketersediaan parkir
|--------------------------------------------------------------------------
*/

$kapasitasMotor = 100;
$kapasitasMobil = 100;
$kapasitasParkir = $kapasitasMotor + $kapasitasMobil;

$motorParkir = 0;
$mobilParkir = 0;

$slotMotorTersedia = $kapasitasMotor;
$slotMobilTersedia = $kapasitasMobil;
$slotTersedia = $kapasitasParkir;

$kendaraanParkir = 0;
$persentaseTerisi = 0;

$kodeStatus = 'unknown';
$labelStatus = 'Data belum tersedia';
$dataParkirBerhasil = false;

try {
    $stmt = $pdo->query(
        "SELECT
            LOWER(TRIM(k.jenis)) AS jenis,
            COUNT(DISTINCT t.id_transaksi) AS jumlah
         FROM tb_transaksi t
         INNER JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
         WHERE LOWER(TRIM(t.status)) = 'masuk'
         GROUP BY LOWER(TRIM(k.jenis))"
    );

    $dataKendaraan = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dataKendaraan as $data) {
        $jenis = strtolower(trim((string) $data['jenis']));
        $jumlah = (int) $data['jumlah'];

        if ($jenis === 'motor') {
            $motorParkir = $jumlah;
        } elseif ($jenis === 'mobil') {
            $mobilParkir = $jumlah;
        }
    }

    $motorParkir = min($kapasitasMotor, $motorParkir);
    $mobilParkir = min($kapasitasMobil, $mobilParkir);

    $slotMotorTersedia = $kapasitasMotor - $motorParkir;
    $slotMobilTersedia = $kapasitasMobil - $mobilParkir;

    $kendaraanParkir = $motorParkir + $mobilParkir;
    $slotTersedia = $slotMotorTersedia + $slotMobilTersedia;

    $persentaseTerisi = (
        $kendaraanParkir / $kapasitasParkir
    ) * 100;

    $dataParkirBerhasil = true;

    if ($slotTersedia === 0) {
        $kodeStatus = 'full';
        $labelStatus = 'Parkir Penuh';
    } elseif ($slotTersedia <= 40) {
        $kodeStatus = 'warning';
        $labelStatus = 'Hampir Penuh';
    } else {
        $kodeStatus = 'available';
        $labelStatus = 'Tersedia';
    }
} catch (PDOException $ex) {
    error_log(
        'Gagal mengambil informasi parkir: '
        . $ex->getMessage()
    );
}

$statusClass = 'status-' . $kodeStatus;
$persenBulat = (int) round($persentaseTerisi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ParkirKu</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --pk-bg: #eaf0eb;
            --pk-green: #0c3b2e;
            --pk-green-2: #14513f;
            --pk-sign: #f0b429;
            --pk-paper: #ffffff;
            --pk-text: #16241e;
            --pk-muted: #62736a;
            --pk-line: #d9e2dc;
            --pk-danger: #a12b2b;
            --pk-danger-bg: #fdeeee;
            --pk-display: 'Bricolage Grotesque', 'Segoe UI', sans-serif;
            --pk-sans: 'Figtree', system-ui, sans-serif;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body.login-page {
            min-height: 100vh;
            margin: 0;
            padding: 32px 20px;
            display: grid;
            place-items: center;
            font-family: var(--pk-sans);
            color: var(--pk-text);
            -webkit-font-smoothing: antialiased;
            background-color: var(--pk-bg);
            background-image:
                radial-gradient(
                    ellipse at center,
                    rgba(234, 240, 235, .92) 0%,
                    rgba(234, 240, 235, .55) 55%,
                    transparent 100%
                ),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='360' height='320' viewBox='0 0 360 320'%3E%3Cg stroke='%23c9d8ce' stroke-width='3' fill='none'%3E%3Cpath d='M0 0V110M60 0V110M120 0V110M180 0V110M240 0V110M300 0V110M360 0V110M0 210V320M60 210V320M120 210V320M180 210V320M240 210V320M300 210V320M360 210V320'/%3E%3Cpath d='M0 160H360' stroke-dasharray='18 14'/%3E%3C/g%3E%3Cg fill='%23d3e1d8'%3E%3Crect x='12' y='22' width='36' height='72' rx='10'/%3E%3Crect x='132' y='30' width='36' height='72' rx='10'/%3E%3Crect x='192' y='18' width='36' height='72' rx='10'/%3E%3Crect x='312' y='26' width='36' height='72' rx='10'/%3E%3Crect x='72' y='226' width='36' height='72' rx='10'/%3E%3Crect x='252' y='234' width='36' height='72' rx='10'/%3E%3Crect x='312' y='222' width='36' height='72' rx='10'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 100% 100%, 360px 320px;
            background-repeat: no-repeat, repeat;
        }

        .pk-layout {
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, .95fr);
            gap: 28px;
            align-items: stretch;
        }

        .pk-card, .pk-lot {
            min-width: 0;
            border-radius: 22px;
            animation: pk-masuk .6s ease both;
        }

        .pk-card {
            padding: 44px 44px 36px;
            border: 1px solid var(--pk-line);
            background: var(--pk-paper);
            box-shadow: 0 14px 36px rgba(12, 59, 46, .08);
        }

        @keyframes pk-masuk {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: none; }
        }

        .pk-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 36px;
        }

        .pk-brand-badge {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: var(--pk-green);
            font-family: var(--pk-display);
            font-size: 22px;
            font-weight: 700;
            color: var(--pk-sign);
        }

        .pk-brand-name {
            font-family: var(--pk-display);
            font-size: 24px;
            font-weight: 600;
            color: var(--pk-green);
        }

        .pk-card h1 {
            margin: 0 0 8px;
            font-family: var(--pk-display);
            font-size: 32px;
            font-weight: 600;
        }

        .pk-lead {
            margin: 0 0 28px;
            font-size: 15px;
            line-height: 1.6;
            color: var(--pk-muted);
        }

        .pk-alert {
            margin-bottom: 20px;
            padding: 12px 14px;
            border: 1px solid #f3c7c7;
            border-radius: 10px;
            background: var(--pk-danger-bg);
            color: var(--pk-danger);
            font-size: 14px;
            line-height: 1.5;
        }

        .pk-field { margin-bottom: 18px; }

        .pk-field label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: 600;
        }

        .pk-input, .pk-select {
            width: 100%;
            height: 50px;
            padding: 0 16px;
            border: 1.5px solid var(--pk-line);
            border-radius: 12px;
            background-color: #f8faf8;
            font-family: inherit;
            font-size: 15px;
            color: var(--pk-text);
            transition: border-color .2s, background-color .2s, box-shadow .2s;
        }

        .pk-input::placeholder { color: #9aa9a0; }

        .pk-select {
            appearance: none;
            padding-right: 44px;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2362736a' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
        }

        .pk-input:hover, .pk-select:hover { border-color: #bccac1; }

        .pk-input:focus-visible, .pk-select:focus-visible {
            outline: none;
            border-color: var(--pk-green-2);
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(20, 81, 63, .14);
        }

        .pk-password { position: relative; }
        .pk-password .pk-input { padding-right: 112px; }

        .pk-toggle {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            padding: 8px 10px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            color: var(--pk-green-2);
            cursor: pointer;
        }

        .pk-toggle:hover { background: #e7efe9; }

        .pk-toggle:focus-visible {
            outline: 2px solid var(--pk-green-2);
            outline-offset: 1px;
        }

        .pk-submit {
            width: 100%;
            min-height: 52px;
            margin-top: 6px;
            border: 0;
            border-radius: 12px;
            background: var(--pk-sign);
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            color: var(--pk-green);
            cursor: pointer;
            transition: background .2s, transform .1s;
        }

        .pk-submit:hover { background: #f5c24a; }
        .pk-submit:active { transform: translateY(1px); }
        .pk-submit:disabled { opacity: .7; cursor: wait; }

        .pk-submit:focus-visible {
            outline: 3px solid var(--pk-green);
            outline-offset: 3px;
        }

        .pk-foot {
            margin: 24px 0 0;
            font-size: 13px;
            line-height: 1.6;
            color: var(--pk-muted);
        }

        .pk-lot {
            display: flex;
            flex-direction: column;
            gap: 26px;
            padding: 36px;
            background: var(--pk-green);
            color: #eaf3ed;
            box-shadow: 0 14px 36px rgba(12, 59, 46, .22);
            animation-delay: .1s;
        }

        .pk-lot-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .pk-lot-head h2 {
            margin: 0;
            font-family: var(--pk-display);
            font-size: 21px;
            font-weight: 600;
        }

        .pk-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 13px 6px 11px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
        }

        .pk-dot {
            width: 8px;
            height: 8px;
            flex-shrink: 0;
            border-radius: 50%;
            background: currentColor;
        }

        .status-available {
            background: rgba(134, 239, 172, .16);
            color: #a3efbf;
        }

        .status-warning {
            background: rgba(240, 180, 41, .20);
            color: #f7cf6a;
        }

        .status-full {
            background: rgba(248, 113, 113, .20);
            color: #fda4a4;
        }

        .status-unknown {
            background: rgba(255, 255, 255, .12);
            color: rgba(234, 243, 237, .8);
        }

        .pk-lot-count strong {
            display: block;
            font-family: var(--pk-display);
            font-size: clamp(64px, 8vw, 96px);
            font-weight: 700;
            line-height: 1;
            color: var(--pk-sign);
        }

        .pk-lot-count span {
            display: block;
            margin-top: 8px;
            font-size: 15px;
            color: rgba(234, 243, 237, .72);
        }

        .lot-full .pk-lot-count strong { color: #fda4a4; }
        .lot-unknown .pk-lot-count strong { color: rgba(234, 243, 237, .55); }

        .pk-lot-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            padding-top: 22px;
            border-top: 1px solid rgba(234, 243, 237, .16);
        }

        .pk-stat { min-width: 0; }

        .pk-stat span {
            display: block;
            font-size: 13px;
            color: rgba(234, 243, 237, .68);
        }

        .pk-stat strong {
            display: block;
            margin-top: 4px;
            font-family: var(--pk-display);
            font-size: 30px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .pk-stat small {
            display: block;
            margin-top: 3px;
            font-size: 12px;
            line-height: 1.5;
            color: rgba(234, 243, 237, .68);
        }

        .pk-lot-note {
            margin: 0;
            font-size: 13px;
            line-height: 1.7;
            color: rgba(234, 243, 237, .72);
        }

        @media (max-width: 820px) {
            body.login-page {
                align-items: start;
                padding: 20px 14px 32px;
            }

            .pk-layout {
                max-width: 480px;
                grid-template-columns: minmax(0, 1fr);
                gap: 20px;
            }

            .pk-card { padding: 30px 24px 28px; }
            .pk-lot { padding: 28px 22px; }
        }

        @media (max-width: 360px) {
            .pk-card { padding: 24px 18px; }
            .pk-lot { padding: 24px 16px; }
            .pk-lot-stats { gap: 10px; }
            .pk-stat strong { font-size: 25px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .pk-card, .pk-lot { animation: none; }
            .pk-input, .pk-select, .pk-submit { transition: none; }
        }
    </style>
</head>
<body class="login-page">

<div class="pk-layout">

    <main class="pk-card">
        <div class="pk-brand">
            <span class="pk-brand-badge" aria-hidden="true">P</span>
            <span class="pk-brand-name">ParkirKu</span>
        </div>

        <h1>Selamat datang</h1>

        <p class="pk-lead">
            Sistem Informasi Manajemen Parkir. Pilih peran Anda,
            lalu masuk dengan akun yang terdaftar.
        </p>

        <?php if ($error !== ''): ?>
            <div class="pk-alert" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" id="formLogin">
            <input
                type="hidden"
                name="csrf"
                value="<?= e($_SESSION['csrf_login'] ?? '') ?>"
            >

            <div class="pk-field">
                <label for="role">Peran</label>
                <select name="role" id="role" class="pk-select" required>
                    <option value="">Pilih peran</option>
                    <option value="admin" <?= $roleDipilih === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="petugas" <?= $roleDipilih === 'petugas' ? 'selected' : '' ?>>Petugas Parkir</option>
                    <option value="owner" <?= $roleDipilih === 'owner' ? 'selected' : '' ?>>Owner</option>
                </select>
            </div>

            <div class="pk-field">
                <label for="username">Username</label>
                <input
                    type="text"
                    name="username"
                    id="username"
                    class="pk-input"
                    placeholder="Masukkan username"
                    value="<?= e($usernameInput) ?>"
                    maxlength="100"
                    autocomplete="username"
                    autocapitalize="off"
                    spellcheck="false"
                    required
                    autofocus
                >
            </div>

            <div class="pk-field">
                <label for="password">Password</label>
                <div class="pk-password">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="pk-input"
                        placeholder="Masukkan password"
                        maxlength="200"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="pk-toggle"
                        id="togglePassword"
                        aria-controls="password"
                        aria-pressed="false"
                    >Tampilkan</button>
                </div>
            </div>

            <button
                type="submit"
                class="pk-submit"
                id="tombolMasuk"
            >Masuk ke dashboard</button>
        </form>

        <p class="pk-foot">
            Lupa password atau akun tidak aktif? Hubungi administrator.
        </p>
    </main>

    <aside
        class="pk-lot lot-<?= e($kodeStatus) ?>"
        aria-label="Ketersediaan parkir"
    >
        <div class="pk-lot-head">
            <h2>Ketersediaan parkir</h2>

            <span class="pk-pill <?= e($statusClass) ?>">
                <span class="pk-dot"></span>
                <?= e($labelStatus) ?>
            </span>
        </div>

        <div class="pk-lot-count">
            <strong><?= number_format($slotTersedia) ?></strong>
            <span>slot kosong dari <?= number_format($kapasitasParkir) ?> slot</span>
        </div>

        <!-- Informasi slot tanpa denah kotak-kotak -->
        <div class="pk-lot-stats">
            <div class="pk-stat">
                <span>Kapasitas Motor</span>
                <strong><?= number_format($kapasitasMotor) ?></strong>
                <small>Terisi: <?= number_format($motorParkir) ?> slot</small>
                <small>Kosong: <?= number_format($slotMotorTersedia) ?> slot</small>
            </div>

            <div class="pk-stat">
                <span>Kapasitas Mobil</span>
                <strong><?= number_format($kapasitasMobil) ?></strong>
                <small>Terisi: <?= number_format($mobilParkir) ?> slot</small>
                <small>Kosong: <?= number_format($slotMobilTersedia) ?> slot</small>
            </div>
        </div>

        <div class="pk-lot-stats">
            <div class="pk-stat">
                <span>Total kendaraan terisi</span>
                <strong><?= number_format($kendaraanParkir) ?></strong>
                <small><?= $persenBulat ?>% dari kapasitas</small>
            </div>

            <div class="pk-stat">
                <span>Total slot kosong</span>
                <strong><?= number_format($slotTersedia) ?></strong>
                <small>dari <?= number_format($kapasitasParkir) ?> slot parkir</small>
            </div>
        </div>

        <p class="pk-lot-note">
            <?php if (!$dataParkirBerhasil): ?>
                Data kendaraan belum berhasil dimuat. Periksa tabel transaksi
                dan kendaraan di database.
            <?php elseif ($kodeStatus === 'full'): ?>
                Parkir penuh. Seluruh slot motor dan mobil sedang terisi.
            <?php elseif ($kodeStatus === 'warning'): ?>
                Slot parkir hampir habis. Periksa ketersediaan sebelum masuk.
            <?php else: ?>
                Slot parkir masih tersedia. Jumlah kendaraan dihitung
                berdasarkan transaksi yang berstatus masuk.
            <?php endif; ?>
        </p>
    </aside>

</div>

<script>
(function () {
    const tombol = document.getElementById('togglePassword');
    const input = document.getElementById('password');

    if (tombol && input) {
        tombol.addEventListener('click', function () {
            const tampil = input.type === 'password';

            input.type = tampil ? 'text' : 'password';
            tombol.textContent = tampil ? 'Sembunyikan' : 'Tampilkan';
            tombol.setAttribute(
                'aria-pressed',
                tampil ? 'true' : 'false'
            );
        });
    }

    const form = document.getElementById('formLogin');
    const submit = document.getElementById('tombolMasuk');

    if (form && submit) {
        const labelAsli = submit.textContent;

        form.addEventListener('submit', function () {
            if (!form.checkValidity()) {
                return;
            }

            submit.disabled = true;
            submit.textContent = 'Memproses...';
        });

        window.addEventListener('pageshow', function () {
            submit.disabled = false;
            submit.textContent = labelAsli;
        });
    }
})();
</script>

</body>
</html>
