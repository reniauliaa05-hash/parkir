<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['petugas']);

$namaPetugas = $_SESSION['user']['nama_lengkap']
    ?? $_SESSION['user']['username']
    ?? 'Petugas';

$halamanAktif = basename($_SERVER['PHP_SELF']);

$inisial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($namaPetugas, 0, 1))
    : strtoupper(substr($namaPetugas, 0, 1));

/*
|--------------------------------------------------------------------------
| Struktur menu sidebar: [file, label, ikon SVG]
|--------------------------------------------------------------------------
*/
$menuSidebar = [
    'Menu utama' => [
        [
            'dashboard.php',
            'Dashboard',
            '<rect x="3" y="3" width="7" height="7" rx="1.5"/>
             <rect x="14" y="3" width="7" height="7" rx="1.5"/>
             <rect x="14" y="14" width="7" height="7" rx="1.5"/>
             <rect x="3" y="14" width="7" height="7" rx="1.5"/>'
        ],
    ],
    'Transaksi parkir' => [
        [
            'transaksi_masuk.php',
            'Transaksi Masuk',
            '<path d="M15 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/>
             <path d="M10 17l5-5-5-5M15 12H3"/>'
        ],
        [
            'transaksi_keluar.php',
            'Transaksi Keluar',
            '<path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/>
             <path d="M16 17l5-5-5-5M21 12H9"/>'
        ],
    ],
    'Data master' => [
        [
            'kendaraan.php',
            'Data Kendaraan',
            '<path d="M5 17H3v-4l2-5h14l2 5v4h-2"/>
             <circle cx="7.5" cy="17" r="1.8"/>
             <circle cx="16.5" cy="17" r="1.8"/>
             <path d="M9.5 17h5M3 13h18"/>'
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e(($judul ?? 'Dashboard Petugas') . ' - ParkirKu') ?></title>

    <link rel="stylesheet" href="../style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    <style>
        :root {
            --adm-green: #0c3b2e;
            --adm-green-2: #14513f;
            --adm-sign: #f0b429;
            --adm-sign-soft: #f7cf6a;
            --adm-bg: #f1f5f2;
            --adm-text: #16241e;
            --adm-muted: #62736a;
            --adm-line: #d9e2dc;
            --adm-display: 'Bricolage Grotesque', 'Segoe UI', system-ui, sans-serif;
            --adm-sans: 'Figtree', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            --adm-sidebar: 264px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: var(--adm-sans);
            background: var(--adm-bg);
            color: var(--adm-text);
            -webkit-font-smoothing: antialiased;
        }

        /* ---------- Sidebar ---------- */
        .petugas-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 40;
            width: var(--adm-sidebar);
            display: flex;
            flex-direction: column;
            padding: 24px 14px 18px;
            background:
                radial-gradient(120% 60% at 0% 0%, #14513f 0%, var(--adm-green) 55%);
            color: #eaf3ed;
            transition: transform 0.25s ease;
        }

        .petugas-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 26px;
            font-family: var(--adm-display);
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        .petugas-brand-badge {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 11px;
            background: var(--adm-sign);
            color: var(--adm-green);
            font-size: 20px;
            font-weight: 700;
        }

        .petugas-nav {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding-right: 2px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }

        .petugas-menu-title {
            margin: 18px 12px 8px;
            color: rgba(234, 243, 237, 0.48);
            font-size: 12px;
            font-weight: 600;
        }

        .petugas-nav .petugas-menu-title:first-child {
            margin-top: 0;
        }

        .petugas-sidebar a {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 3px;
            padding: 11px 12px;
            border-radius: 10px;
            color: rgba(234, 243, 237, 0.78);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s, color 0.2s;
        }

        .petugas-sidebar a:hover {
            background: rgba(255, 255, 255, 0.07);
            color: #fff;
        }

        .petugas-sidebar a.active {
            background: rgba(240, 180, 41, 0.14);
            color: var(--adm-sign-soft);
            font-weight: 600;
        }

        .petugas-sidebar a.active::before {
            content: "";
            position: absolute;
            left: -14px;
            top: 9px;
            bottom: 9px;
            width: 4px;
            border-radius: 0 4px 4px 0;
            background: var(--adm-sign);
        }

        .petugas-sidebar a:focus-visible,
        .petugas-toggle:focus-visible {
            outline: 2px solid var(--adm-sign);
            outline-offset: 2px;
        }

        .petugas-ikon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .petugas-sidebar-foot {
            margin-top: 12px;
            padding-top: 14px;
            border-top: 1px solid rgba(234, 243, 237, 0.14);
        }

        .petugas-sidebar-foot a {
            margin-bottom: 0;
        }

        .petugas-sidebar-foot a:hover {
            background: rgba(248, 113, 113, 0.16);
            color: #fda4a4;
        }

        .petugas-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 30;
            background: rgba(8, 28, 21, 0.5);
        }

        /* ---------- Area utama ---------- */
        .petugas-main {
            min-height: 100vh;
            margin-left: var(--adm-sidebar);
        }

        .petugas-topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            min-height: 68px;
            padding: 12px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid var(--adm-line);
            -webkit-backdrop-filter: blur(8px);
                    backdrop-filter: blur(8px);
        }

        .petugas-topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .petugas-topbar strong {
            font-family: var(--adm-display);
            font-size: 18px;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--adm-green);
        }

        .petugas-toggle {
            display: none;
            width: 42px;
            height: 42px;
            padding: 0;
            place-items: center;
            border: 1px solid var(--adm-line);
            border-radius: 10px;
            background: #fff;
            color: var(--adm-green);
            cursor: pointer;
        }

        .petugas-toggle svg {
            width: 22px;
            height: 22px;
        }

        .petugas-user {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .petugas-avatar {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 50%;
            background: var(--adm-green);
            color: var(--adm-sign);
            font-family: var(--adm-display);
            font-size: 17px;
            font-weight: 600;
        }

        .petugas-user-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
            line-height: 1.3;
        }

        .petugas-user-name {
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--adm-text);
            font-size: 14px;
            font-weight: 600;
        }

        .petugas-user-role {
            color: var(--adm-muted);
            font-size: 12px;
        }

        .petugas-content {
            padding: 32px;
        }

        /* ---------- Warna komponen halaman (hanya warna, bukan ukuran) ---------- */
        .petugas-content .panel,
        .petugas-content .card {
            background-color: #ffffff !important;
            border-color: var(--adm-line) !important;
        }

        .petugas-content .panel-title,
        .petugas-content .panel-header h2,
        .petugas-content .card-value {
            color: var(--adm-green) !important;
        }

        .petugas-content .card-label,
        .petugas-content .subtitle {
            color: var(--adm-muted) !important;
        }

        .petugas-content .form-group label {
            color: var(--adm-text) !important;
        }

        .petugas-content .form-group input,
        .petugas-content .form-group select {
            background-color: #f8faf8 !important;
            border-color: var(--adm-line) !important;
            color: var(--adm-text) !important;
        }

        .petugas-content .form-group input:focus,
        .petugas-content .form-group select:focus {
            border-color: var(--adm-green-2) !important;
            box-shadow: 0 0 0 3px rgba(20, 81, 63, 0.14) !important;
            outline: none;
        }

        .petugas-content .btn-primary {
            background-color: var(--adm-sign) !important;
            border-color: var(--adm-sign) !important;
            color: var(--adm-green) !important;
        }

        .petugas-content .btn-primary:hover {
            background-color: #f5c24a !important;
            border-color: #f5c24a !important;
        }

        .petugas-content .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .petugas-content .btn-light {
            background-color: #e9f2ec !important;
            border-color: var(--adm-line) !important;
            color: var(--adm-green-2) !important;
        }

        .petugas-content .btn-light:hover {
            background-color: #dcebe2 !important;
        }

        .petugas-content table th {
            background-color: #f3f7f4 !important;
            border-bottom-color: var(--adm-line) !important;
            color: var(--adm-muted) !important;
        }

        .petugas-content table td {
            border-bottom-color: #edf2ee !important;
            color: var(--adm-text) !important;
        }

        .petugas-content table tbody tr:hover {
            background-color: #f8fbf9 !important;
        }

        .petugas-content table td strong {
            color: var(--adm-green) !important;
        }

        .petugas-content .alert {
            background-color: #fdeeee !important;
            border-color: #f3c7c7 !important;
            color: #a12b2b !important;
        }

        .petugas-content .alert.success {
            background-color: #e9f7ef !important;
            border-color: #bfe3cf !important;
            color: #14653f !important;
        }

        /* ---------- Responsif ---------- */
        @media (max-width: 900px) {
            .petugas-sidebar {
                transform: translateX(-100%);
                box-shadow: none;
            }

            body.petugas-sidebar-open .petugas-sidebar {
                transform: none;
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.3);
            }

            body.petugas-sidebar-open .petugas-overlay {
                display: block;
            }

            body.petugas-sidebar-open {
                overflow: hidden;
            }

            .petugas-main {
                margin-left: 0;
            }

            .petugas-toggle {
                display: grid;
            }

            .petugas-topbar {
                padding: 12px 16px;
            }

            .petugas-content {
                padding: 18px 16px 28px;
            }
        }

        @media (max-width: 520px) {
            .petugas-user-info {
                display: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .petugas-sidebar,
            .petugas-sidebar a {
                transition: none;
            }
        }

        /* ---------- Cetak: sembunyikan sidebar dan bar atas ---------- */
        @media print {
            .petugas-sidebar,
            .petugas-overlay,
            .petugas-topbar {
                display: none !important;
            }

            .petugas-main {
                margin-left: 0;
            }

            .petugas-content {
                padding: 0;
            }

            body {
                background: #fff;
            }
        }
    </style>
</head>
<body>

<aside class="petugas-sidebar" id="petugasSidebar" aria-label="Menu petugas">
    <div class="petugas-brand">
        <span class="petugas-brand-badge" aria-hidden="true">P</span>
        ParkirKu
    </div>

    <nav class="petugas-nav">
        <?php foreach ($menuSidebar as $grupMenu => $daftarMenu): ?>
            <div class="petugas-menu-title">
                <?= e($grupMenu) ?>
            </div>

            <?php foreach ($daftarMenu as [$berkasMenu, $labelMenu, $ikonMenu]): ?>
                <?php $menuAktif = $halamanAktif === $berkasMenu; ?>

                <a href="<?= e($berkasMenu) ?>"
                   class="<?= $menuAktif ? 'active' : '' ?>"
                   <?= $menuAktif ? 'aria-current="page"' : '' ?>>
                    <svg class="petugas-ikon"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="1.8"
                         stroke-linecap="round"
                         stroke-linejoin="round"
                         aria-hidden="true">
                        <?= $ikonMenu ?>
                    </svg>
                    <?= e($labelMenu) ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="petugas-sidebar-foot">
        <a href="../logout.php"
           onclick="return confirm('Apakah Anda yakin ingin logout?')">
            <svg class="petugas-ikon"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="1.8"
                 stroke-linecap="round"
                 stroke-linejoin="round"
                 aria-hidden="true">
                <path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/>
                <path d="M16 17l5-5-5-5M21 12H9"/>
            </svg>
            Logout
        </a>
    </div>
</aside>

<div class="petugas-overlay" id="petugasOverlay"></div>

<div class="petugas-main">
    <header class="petugas-topbar">
        <div class="petugas-topbar-left">
            <button type="button"
                    class="petugas-toggle"
                    id="petugasToggle"
                    aria-controls="petugasSidebar"
                    aria-expanded="false"
                    aria-label="Buka menu">
                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </button>

            <strong>Sistem Informasi Parkir</strong>
        </div>

        <div class="petugas-user">
            <span class="petugas-avatar" aria-hidden="true">
                <?= e($inisial) ?>
            </span>

            <div class="petugas-user-info">
                <span class="petugas-user-name">
                    <?= e($namaPetugas) ?>
                </span>
                <span class="petugas-user-role">Petugas Parkir</span>
            </div>
        </div>
    </header>

    <main class="petugas-content">

<script>
(function () {
    var body = document.body;
    var tombol = document.getElementById('petugasToggle');
    var overlay = document.getElementById('petugasOverlay');
    var sidebar = document.getElementById('petugasSidebar');

    if (!tombol || !overlay || !sidebar) return;

    function aturSidebar(buka) {
        body.classList.toggle('petugas-sidebar-open', buka);
        tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
    }

    tombol.addEventListener('click', function () {
        aturSidebar(!body.classList.contains('petugas-sidebar-open'));
    });

    overlay.addEventListener('click', function () {
        aturSidebar(false);
    });

    sidebar.addEventListener('click', function (event) {
        if (event.target.closest('a')) {
            aturSidebar(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            aturSidebar(false);
        }
    });
})();
</script>