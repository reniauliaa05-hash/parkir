<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['owner']);

$namaOwner = $_SESSION['user']['nama_lengkap']
    ?? $_SESSION['user']['username']
    ?? 'Owner';

$halamanAktif = basename($_SERVER['PHP_SELF']);

$inisialOwner = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($namaOwner, 0, 1))
    : strtoupper(substr($namaOwner, 0, 1));

/*
|--------------------------------------------------------------------------
| Struktur menu sidebar: [file, label, ikon SVG]
|--------------------------------------------------------------------------
*/
$menu = [
    'Menu utama' => [
        [
            'dashboard.php',
            'Dashboard',
            '<rect x="3" y="3" width="8" height="8" rx="2"/>
             <rect x="13" y="3" width="8" height="5" rx="2"/>
             <rect x="13" y="10" width="8" height="11" rx="2"/>
             <rect x="3" y="13" width="8" height="8" rx="2"/>'
        ],
        [
            'rekap.php',
            'Laporan Parkir',
            '<path d="M5 3h10l4 4v14H5z"/>
             <path d="M14 3v5h5M8 16l2.5-3 2 2 3-4"/>'
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($judul ?? 'Dashboard Owner') ?> - ParkirKu</title>

    <link rel="stylesheet" href="../style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Nama variabel lama dipertahankan agar halaman owner lain tetap jalan. */
            --owner-green: #0c3b2e;
            --owner-green-light: #14513f;
            --owner-yellow: #f0b429;
            --owner-bg: #f1f5f2;
            --owner-white: #ffffff;
            --owner-text: #16241e;
            --owner-muted: #62736a;
            --owner-border: #d9e2dc;
            --owner-sidebar-width: 264px;

            /* Variabel yang sama dengan header admin dan petugas. */
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
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--adm-sans);
            background: var(--owner-bg);
            color: var(--owner-text);
            -webkit-font-smoothing: antialiased;
        }

        body.owner-menu-open {
            overflow: hidden;
        }

        a {
            -webkit-tap-highlight-color: transparent;
        }

        /* ---------- Sidebar ---------- */
        .owner-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            width: var(--owner-sidebar-width);
            padding: 24px 14px 18px;
            background:
                radial-gradient(120% 60% at 0% 0%, #14513f 0%, var(--owner-green) 55%);
            color: #eaf3ed;
            transition: transform 0.25s ease;
        }

        .owner-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 26px;
        }

        .owner-brand-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 11px;
            background: var(--owner-yellow);
            font-family: var(--adm-display);
            font-size: 20px;
            font-weight: 700;
            color: var(--owner-green);
        }

        .owner-brand-name {
            font-family: var(--adm-display);
            font-size: 24px;
            font-weight: 600;
            line-height: 1.1;
            letter-spacing: -0.01em;
            color: #fff;
        }

        .owner-brand-caption {
            margin-top: 3px;
            font-size: 12px;
            color: rgba(234, 243, 237, 0.55);
        }

        .owner-nav-area {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding-right: 2px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }

        .owner-menu-title {
            margin: 18px 12px 8px;
            font-size: 12px;
            font-weight: 600;
            color: rgba(234, 243, 237, 0.48);
        }

        .owner-nav-area .owner-menu-title:first-child {
            margin-top: 0;
        }

        .owner-nav {
            display: flex;
            flex-direction: column;
        }

        .owner-sidebar a.owner-link {
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

        .owner-link-icon {
            width: 20px;
            height: 20px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }

        .owner-link-icon svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
        }

        .owner-sidebar a.owner-link:hover {
            background: rgba(255, 255, 255, 0.07);
            color: #fff;
        }

        .owner-sidebar a.owner-link.active {
            background: rgba(240, 180, 41, 0.14);
            color: var(--adm-sign-soft);
            font-weight: 600;
        }

        .owner-sidebar a.owner-link.active::before {
            content: "";
            position: absolute;
            left: -14px;
            top: 9px;
            bottom: 9px;
            width: 4px;
            border-radius: 0 4px 4px 0;
            background: var(--owner-yellow);
        }

        .owner-sidebar a.owner-link:focus-visible,
        .owner-menu-toggle:focus-visible {
            outline: 2px solid var(--owner-yellow);
            outline-offset: 2px;
        }

        .owner-sidebar-bottom {
            margin-top: 12px;
            padding-top: 14px;
            border-top: 1px solid rgba(234, 243, 237, 0.14);
        }

        .owner-sidebar-bottom a.owner-link {
            margin-bottom: 0;
        }

        .owner-sidebar a.owner-link.owner-logout-link:hover {
            background: rgba(248, 113, 113, 0.16);
            color: #fda4a4;
        }

        /* ---------- Area utama ---------- */
        .owner-main {
            min-height: 100vh;
            margin-left: var(--owner-sidebar-width);
            transition: margin-left 0.25s ease;
        }

        .owner-topbar {
            position: sticky;
            top: 0;
            z-index: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-height: 68px;
            padding: 12px 32px;
            border-bottom: 1px solid var(--owner-border);
            background: rgba(255, 255, 255, 0.92);
            -webkit-backdrop-filter: blur(8px);
                    backdrop-filter: blur(8px);
        }

        .owner-topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .owner-page-heading {
            min-width: 0;
        }

        .owner-topbar-title {
            font-family: var(--adm-display);
            font-size: 18px;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--owner-green);
        }

        .owner-topbar-subtitle {
            margin-top: 2px;
            font-size: 12px;
            color: var(--owner-muted);
        }

        .owner-user {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .owner-topbar-avatar {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 50%;
            background: var(--owner-green);
            font-family: var(--adm-display);
            font-size: 17px;
            font-weight: 600;
            color: var(--owner-yellow);
        }

        .owner-user-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
            line-height: 1.3;
        }

        .owner-user-name {
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 14px;
            font-weight: 600;
            color: var(--owner-text);
        }

        .owner-user-role {
            font-size: 12px;
            color: var(--owner-muted);
        }

        .owner-content {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
            padding: 32px;
        }

        /* ---------- Warna komponen halaman (hanya warna, bukan ukuran) ---------- */
        .owner-content .panel,
        .owner-content .card {
            background-color: #ffffff !important;
            border-color: var(--owner-border) !important;
        }

        .owner-content .panel-title,
        .owner-content .panel-header h2,
        .owner-content .card-value {
            color: var(--owner-green) !important;
        }

        .owner-content .card-label,
        .owner-content .subtitle {
            color: var(--owner-muted) !important;
        }

        .owner-content .form-group label {
            color: var(--owner-text) !important;
        }

        .owner-content .form-group input,
        .owner-content .form-group select {
            background-color: #f8faf8 !important;
            border-color: var(--owner-border) !important;
            color: var(--owner-text) !important;
        }

        .owner-content .form-group input:focus,
        .owner-content .form-group select:focus {
            border-color: var(--owner-green-light) !important;
            box-shadow: 0 0 0 3px rgba(20, 81, 63, 0.14) !important;
            outline: none;
        }

        .owner-content .btn-primary {
            background-color: var(--owner-yellow) !important;
            border-color: var(--owner-yellow) !important;
            color: var(--owner-green) !important;
        }

        .owner-content .btn-primary:hover {
            background-color: #f5c24a !important;
            border-color: #f5c24a !important;
        }

        .owner-content .btn-light {
            background-color: #e9f2ec !important;
            border-color: var(--owner-border) !important;
            color: var(--owner-green-light) !important;
        }

        .owner-content .btn-light:hover {
            background-color: #dcebe2 !important;
        }

        .owner-content table th {
            background-color: #f3f7f4 !important;
            border-bottom-color: var(--owner-border) !important;
            color: var(--owner-muted) !important;
        }

        .owner-content table td {
            border-bottom-color: #edf2ee !important;
            color: var(--owner-text) !important;
        }

        .owner-content table tbody tr:hover {
            background-color: #f8fbf9 !important;
        }

        .owner-content table td strong {
            color: var(--owner-green) !important;
        }

        /* ---------- Tombol menu (HP) ---------- */
        .owner-menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            padding: 0;
            border: 1px solid var(--owner-border);
            border-radius: 10px;
            background: #fff;
            color: var(--owner-green);
            cursor: pointer;
        }

        .owner-menu-toggle svg {
            width: 22px;
            height: 22px;
        }

        .owner-sidebar-overlay {
            display: none;
        }

        /* ---------- Responsif ---------- */
        @media (max-width: 900px) {
            .owner-sidebar {
                transform: translateX(-100%);
            }

            .owner-sidebar.is-open {
                transform: translateX(0);
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.3);
            }

            .owner-sidebar-overlay.is-visible {
                position: fixed;
                inset: 0;
                z-index: 900;
                display: block;
                background: rgba(8, 28, 21, 0.5);
            }

            .owner-main {
                margin-left: 0;
            }

            .owner-menu-toggle {
                display: inline-flex;
            }

            .owner-topbar {
                padding: 12px 16px;
            }

            .owner-content {
                padding: 18px 16px 28px;
            }
        }

        @media (max-width: 520px) {
            .owner-user-info,
            .owner-topbar-subtitle {
                display: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .owner-sidebar,
            .owner-main,
            .owner-sidebar a.owner-link {
                transition: none;
            }
        }

        /* ---------- Cetak: sembunyikan sidebar dan bar atas ---------- */
        @media print {
            .owner-sidebar,
            .owner-sidebar-overlay,
            .owner-topbar {
                display: none !important;
            }

            .owner-main {
                margin-left: 0;
            }

            .owner-content {
                padding: 0;
            }

            body {
                background: #fff;
            }
        }
    </style>
</head>

<body>

<aside class="owner-sidebar" id="ownerSidebar" aria-label="Menu owner">
    <div class="owner-brand">
        <div class="owner-brand-icon" aria-hidden="true">P</div>

        <div>
            <div class="owner-brand-name">ParkirKu</div>
            <div class="owner-brand-caption">Panel pemilik</div>
        </div>
    </div>

    <div class="owner-nav-area">
        <?php foreach ($menu as $judulGrup => $daftar): ?>
            <div class="owner-menu-title"><?= e($judulGrup) ?></div>

            <nav class="owner-nav">
                <?php foreach ($daftar as [$berkas, $label, $ikon]): ?>
                    <?php $aktif = $halamanAktif === $berkas; ?>

                    <a href="<?= e($berkas) ?>"
                       class="owner-link <?= $aktif ? 'active' : '' ?>"
                       <?= $aktif ? 'aria-current="page"' : '' ?>>
                        <span class="owner-link-icon">
                            <svg viewBox="0 0 24 24" fill="none"
                                 stroke-width="1.8"
                                 stroke-linecap="round"
                                 stroke-linejoin="round"
                                 aria-hidden="true">
                                <?= $ikon ?>
                            </svg>
                        </span>
                        <?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endforeach; ?>
    </div>

    <div class="owner-sidebar-bottom">
        <nav class="owner-nav">
            <a href="../logout.php"
               class="owner-link owner-logout-link"
               onclick="return confirm('Apakah Anda yakin ingin logout?')">
                <span class="owner-link-icon">
                    <svg viewBox="0 0 24 24" fill="none"
                         stroke-width="1.8"
                         stroke-linecap="round"
                         stroke-linejoin="round"
                         aria-hidden="true">
                        <path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/>
                        <path d="M16 17l5-5-5-5M21 12H9"/>
                    </svg>
                </span>
                Logout
            </a>
        </nav>
    </div>
</aside>

<div class="owner-sidebar-overlay" id="ownerSidebarOverlay"></div>

<div class="owner-main">
    <header class="owner-topbar">
        <div class="owner-topbar-left">
            <button
                type="button"
                class="owner-menu-toggle"
                id="ownerMenuToggle"
                aria-label="Buka menu navigasi"
                aria-controls="ownerSidebar"
                aria-expanded="false"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div class="owner-page-heading">
                <strong class="owner-topbar-title">Sistem Informasi Parkir</strong>
                <div class="owner-topbar-subtitle">
                    Panel pemantauan dan laporan usaha parkir
                </div>
            </div>
        </div>

        <div class="owner-user">
            <div class="owner-topbar-avatar" aria-hidden="true">
                <?= e($inisialOwner) ?>
            </div>

            <div class="owner-user-info">
                <span class="owner-user-name"><?= e($namaOwner) ?></span>
                <span class="owner-user-role">Pemilik Parkir</span>
            </div>
        </div>
    </header>

    <main class="owner-content">

<script>
(function () {
    const sidebar = document.getElementById('ownerSidebar');
    const toggle = document.getElementById('ownerMenuToggle');
    const overlay = document.getElementById('ownerSidebarOverlay');

    if (!sidebar || !toggle || !overlay) return;

    function bukaMenu() {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-visible');
        document.body.classList.add('owner-menu-open');
        toggle.setAttribute('aria-expanded', 'true');
    }

    function tutupMenu() {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-visible');
        document.body.classList.remove('owner-menu-open');
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', function () {
        if (sidebar.classList.contains('is-open')) {
            tutupMenu();
        } else {
            bukaMenu();
        }
    });

    overlay.addEventListener('click', tutupMenu);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            tutupMenu();
        }
    });

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 900) tutupMenu();
        });
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) tutupMenu();
    });
})();
</script>