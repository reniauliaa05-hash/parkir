<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['admin']);

$namaAdmin = $_SESSION['user']['nama_lengkap']
    ?? $_SESSION['user']['username']
    ?? 'Administrator';

$halamanAktif = basename($_SERVER['PHP_SELF']);

// Huruf awal nama untuk avatar.
$inisial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($namaAdmin, 0, 1))
    : strtoupper(substr($namaAdmin, 0, 1));

/*
|--------------------------------------------------------------------------
| Struktur menu sidebar: [file, label, ikon SVG]
|--------------------------------------------------------------------------
*/
$menu = [
    'Menu utama' => [
        ['dashboard.php', 'Dashboard',
            '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>'],
    ],
    'Data master' => [
        ['kendaraan.php', 'Data Kendaraan',
            '<path d="M5 17H3v-4l2-5h14l2 5v4h-2"/><circle cx="7.5" cy="17" r="1.8"/><circle cx="16.5" cy="17" r="1.8"/><path d="M9.5 17h5M3 13h18"/>'],
        ['tarif.php', 'Tarif Parkir',
            '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6.5 9.5v.01M17.5 14.5v.01"/>'],
        ['area_parkir.php', 'Area Parkir',
            '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>'],
    ],
    'Transaksi' => [
        ['transaksi_masuk.php', 'Transaksi Masuk',
            '<path d="M15 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5M15 12H3"/>'],
        ['transaksi_keluar.php', 'Transaksi Keluar',
            '<path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>'],
    ],
    'Laporan dan sistem' => [
        ['rekap.php', 'Laporan Parkir',
            '<path d="M5 20V11M12 20V4M19 20v-6"/>'],
        ['log.php', 'Log Aktivitas',
            '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        ['backup.php', 'Backup Database',
            '<path d="M12 3v12m-5-5 5 5 5-5"/><path d="M5 17v4h14v-4"/>'],
        ['restore.php', 'Restore Database',
            '<path d="M12 21V9m-5 5 5-5 5 5"/><path d="M5 7V3h14v4"/>'],
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($judul ?? 'Dashboard Admin') . ' - ParkirKu') ?></title>
    <link rel="stylesheet" href="../style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

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
        .admin-sidebar {
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

        .admin-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 26px;
            font-family: var(--adm-display);
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        .admin-brand-badge {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 11px;
            background: var(--adm-sign);
            font-size: 20px;
            font-weight: 700;
            color: var(--adm-green);
        }

        .admin-nav {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding-right: 2px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }

        .admin-menu-title {
            margin: 18px 12px 8px;
            font-size: 12px;
            font-weight: 600;
            color: rgba(234, 243, 237, 0.48);
        }

        .admin-nav .admin-menu-title:first-child {
            margin-top: 0;
        }

        .admin-sidebar a {
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

        .admin-sidebar a:hover {
            background: rgba(255, 255, 255, 0.07);
            color: #fff;
        }

        .admin-sidebar a.active {
            background: rgba(240, 180, 41, 0.14);
            color: var(--adm-sign-soft);
            font-weight: 600;
        }

        .admin-sidebar a.active::before {
            content: "";
            position: absolute;
            left: -14px;
            top: 9px;
            bottom: 9px;
            width: 4px;
            border-radius: 0 4px 4px 0;
            background: var(--adm-sign);
        }

        .admin-sidebar a:focus-visible,
        .admin-toggle:focus-visible {
            outline: 2px solid var(--adm-sign);
            outline-offset: 2px;
        }

        .nav-ikon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .admin-sidebar-foot {
            margin-top: 12px;
            padding-top: 14px;
            border-top: 1px solid rgba(234, 243, 237, 0.14);
        }

        .admin-sidebar-foot a {
            margin-bottom: 0;
        }

        .admin-sidebar-foot a:hover {
            background: rgba(248, 113, 113, 0.16);
            color: #fda4a4;
        }

        .admin-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 30;
            background: rgba(8, 28, 21, 0.5);
        }

        /* ---------- Area utama ---------- */
        .admin-main {
            min-height: 100vh;
            margin-left: var(--adm-sidebar);
        }

        .admin-topbar {
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

        .admin-topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .admin-toggle {
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

        .admin-toggle svg {
            width: 22px;
            height: 22px;
        }

        .admin-topbar strong {
            font-family: var(--adm-display);
            font-size: 18px;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--adm-green);
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 50%;
            background: var(--adm-green);
            font-family: var(--adm-display);
            font-size: 17px;
            font-weight: 600;
            color: var(--adm-sign);
        }

        .admin-user-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
            line-height: 1.3;
        }

        .admin-user-name {
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 14px;
            font-weight: 600;
            color: var(--adm-text);
        }

        .admin-user-role {
            font-size: 12px;
            color: var(--adm-muted);
        }

        .admin-content {
            padding: 32px;
        }

        /* ---------- Responsif ---------- */
        @media (max-width: 900px) {
            .admin-sidebar {
                transform: translateX(-100%);
                box-shadow: none;
            }

            body.sidebar-open .admin-sidebar {
                transform: none;
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.3);
            }

            body.sidebar-open .admin-overlay {
                display: block;
            }

            body.sidebar-open {
                overflow: hidden;
            }

            .admin-main {
                margin-left: 0;
            }

            .admin-toggle {
                display: grid;
            }

            .admin-topbar {
                padding: 12px 16px;
            }

            .admin-content {
                padding: 18px 16px 28px;
            }
        }

        @media (max-width: 520px) {
            .admin-user-info {
                display: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .admin-sidebar,
            .admin-sidebar a {
                transition: none;
            }
        }
    </style>
</head>
<body>

<aside class="admin-sidebar" id="adminSidebar" aria-label="Menu utama">
    <div class="admin-brand">
        <span class="admin-brand-badge" aria-hidden="true">P</span>
        ParkirKu
    </div>

    <nav class="admin-nav">
        <?php foreach ($menu as $judulGrup => $daftar): ?>
            <div class="admin-menu-title"><?= e($judulGrup) ?></div>

            <?php foreach ($daftar as [$berkas, $label, $ikon]): ?>
                <?php $aktif = $halamanAktif === $berkas; ?>
                <a href="<?= e($berkas) ?>"
                   class="<?= $aktif ? 'active' : '' ?>"
                   <?= $aktif ? 'aria-current="page"' : '' ?>>
                    <svg class="nav-ikon" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true"><?= $ikon ?></svg>
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="admin-sidebar-foot">
        <a href="../logout.php"
           onclick="return confirm('Apakah Anda yakin ingin logout?')">
            <svg class="nav-ikon" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/>
                <path d="M16 17l5-5-5-5M21 12H9"/>
            </svg>
            Logout
        </a>
    </div>
</aside>

<div class="admin-overlay" id="adminOverlay"></div>

<div class="admin-main">
    <header class="admin-topbar">
        <div class="admin-topbar-left">
            <button type="button"
                    class="admin-toggle"
                    id="adminToggle"
                    aria-controls="adminSidebar"
                    aria-expanded="false"
                    aria-label="Buka menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </button>

            <strong>Sistem Informasi Parkir</strong>
        </div>

        <div class="admin-user">
            <span class="admin-avatar" aria-hidden="true"><?= e($inisial) ?></span>

            <div class="admin-user-info">
                <span class="admin-user-name"><?= e($namaAdmin) ?></span>
                <span class="admin-user-role">Administrator</span>
            </div>
        </div>
    </header>

    <main class="admin-content">

<script>
    (function () {
        var body = document.body;
        var tombol = document.getElementById('adminToggle');
        var overlay = document.getElementById('adminOverlay');
        var sidebar = document.getElementById('adminSidebar');
        if (!tombol || !overlay || !sidebar) return;

        function atur(buka) {
            body.classList.toggle('sidebar-open', buka);
            tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
        }

        tombol.addEventListener('click', function () {
            atur(!body.classList.contains('sidebar-open'));
        });

        overlay.addEventListener('click', function () { atur(false); });

        sidebar.addEventListener('click', function (e) {
            if (e.target.closest('a')) atur(false);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') atur(false);
        });
    })();
</script>