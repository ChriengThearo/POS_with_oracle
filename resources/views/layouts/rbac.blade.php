<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'RBAC Demo')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f6f8fa;
            --surface: #ffffff;
            --surface-soft: #eef3f8;
            --text: #101820;
            --muted: #5a6b7f;
            --primary: #0055a5;
            --accent: #e36414;
            --success: #1d7a4f;
            --danger: #a32222;
            --border: #d6dfeb;
            --shadow: 0 18px 44px rgba(16, 24, 32, 0.1);
            --appbar: #d9dfe5;
            --sidebar-bg: #2f3b45;
            --sidebar-text: #e6eef5;
            --sidebar-muted: #a7b2bf;
            --sidebar-active: #1e2a33;
            --appbar-height: 56px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--text);
            font-family: "Manrope", sans-serif;
            min-height: 100vh;
            background:
                radial-gradient(circle at 15% 0%, rgba(0, 85, 165, 0.17), transparent 32%),
                radial-gradient(circle at 85% 12%, rgba(227, 100, 20, 0.17), transparent 28%),
                linear-gradient(165deg, #f9fbfd, #ecf1f7 58%, #e8eef6 100%);
        }

        a { color: inherit; text-decoration: none; }

        .shell {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        .layout {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            min-height: calc(100vh - var(--appbar-height));
        }

        .appbar {
            height: var(--appbar-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 18px;
            background: var(--appbar);
            border-bottom: 1px solid #c7d1db;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: "Space Grotesk", sans-serif;
            font-weight: 700;
            font-size: 1.05rem;
            letter-spacing: .02em;
        }

        .brand-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: linear-gradient(140deg, var(--primary), var(--accent));
            box-shadow: 0 0 0 8px rgba(0, 85, 165, 0.08);
        }

        .brand-logo {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid rgba(0, 0, 0, 0.08);
            background: #fff;
        }

        .sidebar {
            position: sticky;
            top: var(--appbar-height);
            align-self: start;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            border-radius: 0;
            box-shadow: none;
            padding: 16px 14px;
            min-height: calc(100vh - var(--appbar-height));
            display: flex;
            flex-direction: column;
        }

        .nav {
            display: grid;
            gap: 8px;
        }

        .nav-section {
            margin-top: 12px;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--sidebar-muted);
        }

        .nav-link {
            font-size: .9rem;
            color: var(--sidebar-text);
            padding: 8px 12px;
            border-radius: 10px;
            transition: all .2s ease;
            border: 1px solid transparent;
            display: block;
        }

        .nav-link:hover {
            color: #fff;
            border-color: transparent;
            background: rgba(255, 255, 255, 0.08);
        }

        .nav-link.active {
            color: #fff;
            border-color: transparent;
            background: var(--sidebar-active);
        }

        .nav-link .nav-ico {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.12);
            color: #c9d6e2;
            font-size: .75rem;
            margin-right: 8px;
        }

        .nav-badge {
            margin-left: auto;
            background: #ba2d2d;
            color: #fff;
            border-radius: 999px;
            padding: 2px 7px;
            font-size: .7rem;
            font-weight: 700;
            line-height: 1;
        }

        .staff-meta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.06);
            color: #e6eef5;
            font-size: .8rem;
            line-height: 1.25;
        }

        .staff-meta strong {
            display: block;
            font-size: .84rem;
            color: #ffffff;
        }

        .logout-btn {
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: transparent;
            color: #e6eef5;
            border-radius: 9px;
            font-size: .8rem;
            font-weight: 700;
            padding: 8px 10px;
            cursor: pointer;
        }

        .content {
            display: grid;
            gap: 16px;
            padding: 20px 20px 40px;
        }

        .flash {
            border-radius: 12px;
            padding: 11px 13px;
            border: 1px solid transparent;
            font-size: .92rem;
            font-weight: 700;
        }

        .flash.success {
            color: #0f5132;
            background: #d1e7dd;
            border-color: #badbcc;
        }

        .flash.error {
            color: #842029;
            background: #f8d7da;
            border-color: #f5c2c7;
        }

        .error-card {
            border-radius: 12px;
            padding: 12px 14px;
            border: 1px solid #f5c2c7;
            background: #f8d7da;
            color: #842029;
        }

        .error-card ul { margin: 6px 0 0; }

        .page-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .page-title {
            margin: 0;
            font-size: clamp(1.6rem, 2.6vw, 2.2rem);
            font-family: "Space Grotesk", sans-serif;
            letter-spacing: .01em;
        }

        .card {
            border: 1px solid var(--border);
            background: var(--surface);
            border-radius: 14px;
            padding: 14px;
            box-shadow: var(--shadow);
        }

        .rbac-form-card {
            display: grid;
            grid-template-columns: minmax(220px, 320px) minmax(0, 1fr);
            gap: 18px;
            padding: 20px;
            position: relative;
            overflow: hidden;
            animation: formIn 650ms ease;
        }

        .form-strip {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 12px;
            background: linear-gradient(120deg, rgba(0, 85, 165, 0.16), rgba(46, 139, 255, 0.1), rgba(227, 100, 20, 0.14));
            background-size: 200% 200%;
            border: 1px solid rgba(0, 0, 0, 0.05);
            animation: shimmer 14s ease infinite;
        }

        .form-strip strong {
            font-size: .92rem;
            letter-spacing: .02em;
        }

        .form-strip span {
            font-size: .8rem;
            color: var(--muted);
        }

        .rbac-form-card::before {
            content: "";
            position: absolute;
            width: 320px;
            height: 320px;
            top: -180px;
            right: -120px;
            background: radial-gradient(circle, rgba(0, 85, 165, 0.25), transparent 65%);
            filter: blur(10px);
            opacity: 0.7;
            animation: halo 12s ease-in-out infinite;
        }

        .form-hero {
            background: var(--surface-soft);
            border-radius: 14px;
            padding: 16px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            display: grid;
            gap: 12px;
            align-content: start;
        }

        .hero-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-weight: 800;
            font-family: "Space Grotesk", sans-serif;
            color: #fff;
            background: conic-gradient(from 120deg, var(--primary), #2e8bff, var(--accent), var(--primary));
            box-shadow: 0 12px 26px rgba(0, 85, 165, 0.25);
            animation: pulse 10s ease-in-out infinite;
        }

        .eyebrow {
            margin: 0;
            font-size: .72rem;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
        }

        .hero-title {
            margin: 2px 0 0;
            font-size: 1.3rem;
            font-family: "Space Grotesk", sans-serif;
        }

        .chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px;
        }

        .form-panel {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            background: #fff;
            display: grid;
            gap: 14px;
        }

        .helper {
            margin-top: 4px;
            font-size: .8rem;
            color: var(--muted);
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 700;
            color: #1d2e4a;
            background: #e6eef7;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .form-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 16px;
            align-items: start;
        }

        .form-grid .full {
            grid-column: 1 / -1;
        }

        .rbac-form-card input:focus,
        .rbac-form-card select:focus,
        .rbac-form-card textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 85, 165, 0.15);
        }

        .rbac-form-card select {
            appearance: none;
            background-image:
                linear-gradient(45deg, transparent 50%, #5a6b7f 50%),
                linear-gradient(135deg, #5a6b7f 50%, transparent 50%);
            background-position: calc(100% - 18px) 50%, calc(100% - 12px) 50%;
            background-size: 6px 6px;
            background-repeat: no-repeat;
            padding-right: 36px;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 880px;
            background: #fff;
        }

        th, td {
            border-bottom: 1px solid #d2d9e4;
            padding: 10px 11px;
            text-align: left;
            vertical-align: top;
            font-size: .95rem;
        }

        th {
            font-size: .78rem;
            font-family: "Space Grotesk", sans-serif;
            color: #33485f;
            background: #f3f7fb;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        tr:last-child td { border-bottom: 0; }

        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 8px;
            padding: 2px 10px;
            font-weight: 800;
            font-size: .84rem;
            line-height: 1.35;
            margin: 2px 4px 2px 0;
            border: 1px solid transparent;
        }

        .badge.role { background: #1766df; color: #fff; }
        .badge.perm { background: #d9f2ff; color: #063247; border-color: #bde5f8; }
        .badge.status.active { background: #e0f3e7; color: var(--success); border-color: rgba(29, 143, 85, 0.2); }
        .badge.status.inactive { background: #f8dee2; color: var(--danger); border-color: rgba(192, 53, 71, 0.18); }
        .badge.count { background: #798491; color: #fff; }

        .btn {
            text-decoration: none;
            border-radius: 11px;
            font-weight: 700;
            font-family: inherit;
            font-size: .9rem;
            padding: 9px 13px;
            border: 0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all .2s ease;
        }

        .btn-primary {
            color: #fff;
            background: var(--accent);
        }

        .btn-muted {
            color: var(--text);
            background: #edf3fa;
            border: 1px solid var(--border);
        }

        .btn-edit {
            background: #f0b429;
            color: #1f242c;
            min-width: 44px;
            padding: 8px 12px;
            font-size: .86rem;
        }

        .btn-delete {
            background: #ba2d2d;
            color: #fff;
            min-width: 44px;
            padding: 8px 12px;
            font-size: .86rem;
        }

        .actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .field-grid {
            display: grid;
            gap: 12px;
        }

        label {
            display: block;
            font-weight: 700;
            color: #1b2a40;
            margin-bottom: 6px;
            font-size: .82rem;
        }

        input, select, textarea {
            width: 100%;
            border: 1px solid #cbd8e6;
            background: #fff;
            border-radius: 10px;
            padding: 9px 10px;
            font-family: inherit;
            font-size: .92rem;
            color: #1b2a40;
        }

        textarea { min-height: 110px; resize: vertical; }

        .muted {
            color: var(--muted);
            font-size: .9rem;
        }

        .permission-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .permission-group {
            border: 1px solid #c7d0de;
            border-radius: 10px;
            background: #fff;
            padding: 10px;
        }

        .permission-group h4 {
            margin: 0 0 8px;
            font-family: "Space Grotesk", sans-serif;
            font-size: 1rem;
            color: #32485f;
        }

        .permission-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 4px 0;
        }

        .permission-item input {
            width: auto;
            margin-top: 4px;
        }

        .permission-item span {
            font-size: .9rem;
            line-height: 1.35;
        }

        .metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .dashboard-hero {
            position: relative;
            border-radius: 18px;
            padding: 22px 24px;
            background: linear-gradient(120deg, rgba(0, 85, 165, 0.18), rgba(46, 139, 255, 0.12), rgba(227, 100, 20, 0.16));
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .dashboard-hero::after {
            content: "";
            position: absolute;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            top: -160px;
            right: -120px;
            background: radial-gradient(circle, rgba(0, 85, 165, 0.35), transparent 65%);
            filter: blur(8px);
            opacity: 0.7;
        }

        .hero-kicker {
            margin: 0 0 6px;
            font-size: .72rem;
            letter-spacing: .26em;
            text-transform: uppercase;
            color: #38506b;
            font-weight: 700;
        }

        .hero-title {
            margin: 0;
            font-size: clamp(1.7rem, 2.8vw, 2.3rem);
            font-family: "Space Grotesk", sans-serif;
        }

        .hero-sub {
            margin: 8px 0 0;
            color: var(--muted);
            max-width: 460px;
        }

        .hero-actions {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .metric {
            border: 1px solid var(--border);
            background: var(--surface-soft);
            border-radius: 12px;
            padding: 12px;
        }

        .metrics.metrics-tiles .metric {
            position: relative;
            background: #fff;
            border: 1px solid rgba(0, 0, 0, 0.06);
            padding: 14px 16px;
            overflow: hidden;
        }

        .metrics.metrics-tiles .metric::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(140deg, rgba(0, 85, 165, 0.12), transparent 40%);
            opacity: 0.6;
            pointer-events: none;
        }

        .metrics.metrics-tiles .metric .value {
            font-size: 1.6rem;
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .data-card {
            border: 1px solid var(--border);
            background: #fff;
            border-radius: 14px;
            padding: 14px;
            box-shadow: var(--shadow);
            display: grid;
            gap: 10px;
        }

        .data-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .data-head h3 {
            margin: 0;
            font-size: 1rem;
            font-family: "Space Grotesk", sans-serif;
        }

        .data-link {
            font-size: .78rem;
            font-weight: 700;
            color: var(--primary);
        }

        .data-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 8px;
        }

        .data-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 10px;
            background: var(--surface-soft);
            border: 1px solid rgba(0, 0, 0, 0.04);
            font-size: .88rem;
        }

        .data-code {
            font-family: "Space Grotesk", sans-serif;
            font-size: .8rem;
            color: #1a3554;
            background: rgba(0, 85, 165, 0.12);
            padding: 2px 8px;
            border-radius: 999px;
        }

        .metric .label {
            font-size: .78rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 6px;
        }

        .metric .value {
            font-size: 1.35rem;
            font-family: "Space Grotesk", sans-serif;
            font-weight: 700;
        }

        @keyframes formIn {
            from { opacity: 0; transform: translateY(12px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes halo {
            0%, 100% { transform: translateY(0); opacity: .55; }
            50% { transform: translateY(16px); opacity: .85; }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes shimmer {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .stock-alert-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(7, 18, 33, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 9999;
            opacity: 0;
            animation: stock-alert-fade 0.25s ease forwards;
        }

        .stock-alert-card {
            width: min(460px, 100%);
            border-radius: 16px;
            border: 1px solid var(--border);
            background: #ffffff;
            box-shadow: var(--shadow);
            padding: 18px;
            animation: stock-alert-rise 0.3s ease;
        }

        .stock-alert-title {
            margin: 0 0 6px;
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.05rem;
            color: #0f2a44;
        }

        .stock-alert-body {
            margin: 0;
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .stock-alert-actions {
            margin-top: 14px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        @keyframes stock-alert-fade {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes stock-alert-rise {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 980px) {
            .metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .permission-grid { grid-template-columns: 1fr; }
            .layout { grid-template-columns: 200px minmax(0, 1fr); }
            .rbac-form-card { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .data-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 760px) {
            .layout { grid-template-columns: 1fr; }
            .sidebar { position: static; min-height: auto; }
            .content { padding: 16px 12px 28px; }
            .page-title { font-size: 1.45rem; }
            .btn { font-size: .84rem; }
            th { font-size: .75rem; }
            td { font-size: .88rem; }
            .metrics { grid-template-columns: 1fr; }
            .dashboard-hero { padding: 18px 16px; flex-direction: column; align-items: flex-start; }
            .hero-actions { width: 100%; justify-content: flex-start; }
        }
    </style>
</head>
<body>
@php
    $storeName = 'Electronic Store';
    $storeLogoPath = 'images/store-logo.png';
    $storeLogoExists = file_exists(public_path($storeLogoPath));
    $staffUser = \App\Support\StaffAuth::user();
    $canDashboardManage = \App\Support\StaffAuth::can('dashboard.manage');
    $canDashboardRead = \App\Support\StaffAuth::can('dashboard.read');
    $canShop = \App\Support\StaffAuth::can('shop.read');
    $canCheckout = \App\Support\StaffAuth::can('checkout.process');
    $canOrders = \App\Support\StaffAuth::can('orders.read');
    $canTotalSales = \App\Support\StaffAuth::can('total-sales.read');
    $canInvoices = \App\Support\StaffAuth::can('invoices.read');
    $canPurchases = \App\Support\StaffAuth::can('purchases.read');
    $canClients = \App\Support\StaffAuth::can('clients.read');
    $canClientDebts = \App\Support\StaffAuth::can('client-depts.read');
    $canCurrencies = \App\Support\StaffAuth::can('currencies.read');
    $canProducts = \App\Support\StaffAuth::can('products.read');
    $canStockStatus = \App\Support\StaffAuth::can('stock-status.read');
    $canFutureStock = \App\Support\StaffAuth::can('future-stock.read');
    $canEmployees = \App\Support\StaffAuth::can('employees.read');
    $canJobs = \App\Support\StaffAuth::can('jobs.read');
    $canUsers = \App\Support\StaffAuth::can('users.read');
    $canRoles = \App\Support\StaffAuth::can('roles.read');
    $canPermissions = \App\Support\StaffAuth::can('permissions.read');
    $canSystemAudit = \App\Support\StaffAuth::can('system.audit');
    $underStockCount = null;
    if ($underStockCount === null && $canStockStatus) {
        $fallbackUnderStockCount = (int) session()->get('understock_last_known', 0);
        try {
            $underStockCount = (int) \Illuminate\Support\Facades\DB::connection('oracle')
                ->table('PRODUCTS')
                ->whereRaw("UPPER(NVL(STATUS, 'UNKNOWN')) = ?", ['UNDERSTOCK'])
                ->count();
            session()->put('understock_last_known', $underStockCount);
        } catch (\Throwable) {
            $underStockCount = $fallbackUnderStockCount;
        }
    }
    $underStockCount = $underStockCount ?? 0;
    $lastUnderStockCount = (int) session()->get('understock_alert_last', 0);
    $showUnderStockAlert = false;
    if (! $canStockStatus || $underStockCount <= 0) {
        session()->forget('understock_alert_last');
    } else {
        if ($underStockCount > $lastUnderStockCount) {
            $showUnderStockAlert = true;
        }
        session()->put('understock_alert_last', $underStockCount);
    }
    $dashboardUrl = $canDashboardManage ? route('admin.dashboard') : route('store.home');
    $dashboardActive = $canDashboardManage ? request()->routeIs('admin.*') : request()->routeIs('store.home');
    $clientDeptCount = null;
    if ($canClientDebts) {
        try {
            $clientDeptCount = (int) \Illuminate\Support\Facades\DB::connection('oracle')
                ->table('INVOICES')
                ->where('INVOICE_STATUS', '=', 'In Debt')
                ->count();
        } catch (\Throwable) {
            $clientDeptCount = 0;
        }
    }
    $clientDeptCount = $clientDeptCount ?? 0;
@endphp
<div class="shell">
    <header class="appbar">
        <a href="{{ route('dashboard.entry') }}" class="brand">
            @if($storeLogoExists)
                <img src="{{ asset($storeLogoPath) }}" alt="{{ $storeName }} logo" class="brand-logo">
            @else
                <span class="brand-dot"></span>
            @endif
            <span>{{ $storeName }}</span>
        </a>
    </header>
    <div class="layout">
        <aside class="sidebar">
            <nav class="nav" style="margin-top: 14px;">
                <div class="nav-section">Main</div>
                @if($canDashboardManage || $canDashboardRead)
                    <a href="{{ $dashboardUrl }}" class="nav-link {{ $dashboardActive ? 'active' : '' }}">
                        <span class="nav-ico">D</span>
                        {{ $canDashboardManage ? 'Admin Dashboard' : 'Dashboard' }}
                    </a>
                @endif
                @if($canShop)
                    <a href="{{ route('store.catalog') }}" class="nav-link {{ request()->routeIs('store.catalog') ? 'active' : '' }}">
                        <span class="nav-ico">S</span>
                        Shop
                    </a>
                @endif
                @if($canCheckout)
                    <a href="{{ route('store.cart') }}" class="nav-link {{ request()->routeIs('store.cart*') || request()->routeIs('store.checkout*') ? 'active' : '' }}">
                        <span class="nav-ico">C</span>
                        Checkout
                    </a>
                @endif
                @if($canOrders)
                    <a href="{{ route('store.orders') }}" class="nav-link {{ request()->routeIs('store.orders*') ? 'active' : '' }}">
                        <span class="nav-ico">O</span>
                        Orders
                    </a>
                @endif
                @if($canTotalSales)
                    <a href="{{ route('total-sales.index') }}" class="nav-link {{ request()->routeIs('total-sales.*') ? 'active' : '' }}">
                        <span class="nav-ico">T</span>
                        Total Sales
                    </a>
                @endif
                @if($canInvoices)
                    <a href="{{ route('invoices.index') }}" class="nav-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
                        <span class="nav-ico">I</span>
                        Invoices
                    </a>
                @endif
                @if($canPurchases)
                    <a href="{{ route('purchases.index') }}" class="nav-link {{ request()->routeIs('purchases.index') || request()->routeIs('purchases.history') || request()->routeIs('purchases.store') || request()->routeIs('purchases.items.*') ? 'active' : '' }}">
                        <span class="nav-ico">P</span>
                        Purchases
                    </a>
                @endif
                @if($canClients)
                    <a href="{{ route('clients.index') }}" class="nav-link {{ request()->routeIs('clients.*') || request()->routeIs('client-types.*') ? 'active' : '' }}">
                        <span class="nav-ico">C</span>
                        Clients
                    </a>
                @endif
                @if($canClientDebts)
                    <a href="{{ route('client-depts.index') }}" class="nav-link {{ request()->routeIs('client-depts.*') ? 'active' : '' }}" style="display: flex; align-items: center; gap: 8px;">
                        <span class="nav-ico">D</span>
                        Client Debts
                        @if($clientDeptCount > 0)
                            <span class="nav-badge">{{ $clientDeptCount }}</span>
                        @endif
                    </a>
                @endif
                @if($canCurrencies)
                    <a href="{{ route('currencies.index') }}" class="nav-link {{ request()->routeIs('currencies.*') ? 'active' : '' }}">
                        <span class="nav-ico">Y</span>
                        Currencies
                    </a>
                @endif
                @if($canProducts)
                    <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.index') || request()->routeIs('products.store') || request()->routeIs('products.update') || request()->routeIs('product-types.*') || request()->routeIs('alert-stocks.*') ? 'active' : '' }}">
                        <span class="nav-ico">P</span>
                        Products
                    </a>
                @endif
                @if($canStockStatus)
                    <a href="{{ route('products.status') }}" class="nav-link {{ request()->routeIs('products.status') ? 'active' : '' }}" style="display: flex; align-items: center; gap: 8px;">
                        <span class="nav-ico">S</span>
                        Product Status
                        @if($underStockCount > 0)
                            <span class="nav-badge">{{ $underStockCount }}</span>
                        @endif
                    </a>
                @endif
                @if($canFutureStock)
                    <a href="{{ route('products.status.future') }}" class="nav-link {{ request()->routeIs('products.status.future') ? 'active' : '' }}">
                        <span class="nav-ico">F</span>
                        Analyst Future
                    </a>
                @endif
                @if($canEmployees)
                    <a href="{{ route('employees.index') }}" class="nav-link {{ request()->routeIs('employees.*') ? 'active' : '' }}">
                        <span class="nav-ico">E</span>
                        Employees
                    </a>
                @endif
                @if($canJobs)
                    <a href="{{ route('jobs.index') }}" class="nav-link {{ request()->routeIs('jobs.*') ? 'active' : '' }}">
                        <span class="nav-ico">J</span>
                        Jobs
                    </a>
                @endif
                @if($canSystemAudit)
                    <a href="{{ route('store.deep-check') }}" class="nav-link {{ request()->routeIs('store.deep-check') ? 'active' : '' }}">
                        <span class="nav-ico">A</span>
                        Deep Check
                    </a>
                @endif

                @if($canUsers || $canRoles || $canPermissions)
                    <div class="nav-section">RBAC</div>
                    @if($canUsers)
                        <a href="{{ route('admin.rbac.users.index') }}" class="nav-link {{ request()->routeIs('admin.rbac.users.*') ? 'active' : '' }}">
                            <span class="nav-ico">U</span>
                            Users
                        </a>
                    @endif
                    @if($canRoles)
                        <a href="{{ route('admin.rbac.roles.index') }}" class="nav-link {{ request()->routeIs('admin.rbac.roles.*') ? 'active' : '' }}">
                            <span class="nav-ico">R</span>
                            Roles
                        </a>
                    @endif
                    @if($canPermissions)
                        <a href="{{ route('admin.rbac.permissions.index') }}" class="nav-link {{ request()->routeIs('admin.rbac.permissions.*') ? 'active' : '' }}">
                            <span class="nav-ico">P</span>
                            Permissions
                        </a>
                    @endif
                @endif
            </nav>

            <div style="margin-top: auto;">
                @if($staffUser)
                    <div class="staff-meta" style="margin-top: 14px;">
                        <div>
                            <strong>{{ $staffUser['employee_name'] }}</strong>
                            {{ $staffUser['job_title'] }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('staff.logout') }}" style="margin-top: 10px;">
                        @csrf
                        <button type="submit" class="logout-btn">Logout</button>
                    </form>
                @endif
            </div>
        </aside>

        <main class="content">
            @if (session('success'))
                <div class="flash success">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="flash error">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="error-card">
                    <strong>Please fix these issues:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
@if($showUnderStockAlert)
    <div class="stock-alert-backdrop" id="stock-alert-modal" role="dialog" aria-modal="true" aria-labelledby="stock-alert-title">
        <div class="stock-alert-card">
            <h2 class="stock-alert-title" id="stock-alert-title">Stock Alert</h2>
            <p class="stock-alert-body">{{ number_format($underStockCount) }} products are running low on stock.</p>
            <div class="stock-alert-actions">
                <button type="button" class="btn btn-muted" data-stock-alert-close>OK</button>
                <a href="{{ route('products.status') }}#under-stock-list" class="btn btn-primary">Manage</a>
            </div>
        </div>
    </div>
    <script>
        (() => {
            const modal = document.getElementById('stock-alert-modal');
            if (!modal) return;
            const closeModal = () => modal.remove();
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });
            modal.querySelectorAll('[data-stock-alert-close]').forEach((btn) => {
                btn.addEventListener('click', closeModal);
            });
        })();
    </script>
@endif
</body>
</html>

