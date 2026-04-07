<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Oracle Commerce')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
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

        .appbar .brand {
            font-size: 1rem;
        }

        .appbar-search {
            display: inline-flex;
            align-items: center;
            background: #fff;
            border: 1px solid #c7d1db;
            border-radius: 6px;
            overflow: hidden;
        }

        .appbar-search input {
            border: 0;
            padding: 7px 10px;
            width: 220px;
            font-size: .9rem;
            outline: none;
        }

        .appbar-search button {
            border: 0;
            padding: 7px 10px;
            background: #ffffff;
            cursor: pointer;
            color: #2f3b45;
            font-weight: 700;
            border-left: 1px solid #c7d1db;
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

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: "Space Grotesk", sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
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
            gap: 14px;
            padding: 18px 20px 40px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 18px;
        }

        .page-hero {
            position: relative;
            padding: 22px 24px;
            border-radius: 20px;
            background: linear-gradient(120deg, rgba(0, 85, 165, 0.2), rgba(46, 139, 255, 0.12), rgba(227, 100, 20, 0.18));
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            animation: hero-rise .15s ease;
        }

        .page-hero::after {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            top: -150px;
            right: -110px;
            background: radial-gradient(circle, rgba(0, 85, 165, 0.35), transparent 65%);
            filter: blur(10px);
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

        .hero-actions {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
            align-items: center;
        }

        .chip-strong {
            background: rgba(255, 255, 255, 0.8);
            border-color: rgba(0, 0, 0, 0.08);
            color: #1b2a40;
            font-weight: 700;
        }

        .headline {
            margin: 0;
            font-family: "Space Grotesk", sans-serif;
            font-size: clamp(1.4rem, 2.2vw, 2rem);
            letter-spacing: .01em;
        }

        .subtle {
            color: var(--muted);
            font-size: .95rem;
            line-height: 1.55;
        }

        .grid {
            display: grid;
            gap: 14px;
        }

        .grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }

        .search-card {
            display: grid;
            gap: 12px;
        }

        .search-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: end;
        }

        .tab-switch {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid var(--border);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }

        .tab-btn {
            border: 0;
            border-radius: 999px;
            padding: 8px 16px;
            font-weight: 700;
            font-size: .86rem;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            transition: all .2s ease;
        }

        .tab-btn.active {
            color: #fff;
            background: linear-gradient(130deg, var(--primary), #2e8bff);
            box-shadow: 0 10px 20px rgba(0, 85, 165, 0.25);
        }

        .stat {
            border: 1px solid var(--border);
            background: var(--surface-soft);
            border-radius: 14px;
            padding: 14px;
        }

        .stat .label {
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .stat .value {
            font-size: 1.45rem;
            font-weight: 800;
            font-family: "Space Grotesk", sans-serif;
        }

        .flash {
            border-radius: 12px;
            padding: 11px 13px;
            border: 1px solid transparent;
            font-size: .92rem;
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

        .flash.toast {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            animation: toast-pop 0.18s ease-out;
        }

        .flash.toast .toast-ico {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #1d7a4f;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        @keyframes toast-pop {
            0% { opacity: 0; transform: translateY(-6px) scale(0.98); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        .error-list {
            margin: 0;
            padding-left: 18px;
            color: var(--danger);
            font-size: .9rem;
        }

        .btn {
            border: 0;
            border-radius: 11px;
            padding: 9px 13px;
            font-family: inherit;
            font-weight: 700;
            font-size: .9rem;
            cursor: pointer;
            transition: background-color .12s, opacity .12s;
        }

        .btn:disabled {
            cursor: not-allowed;
            opacity: .6;
        }

        .btn-primary {
            color: #fff;
            background: var(--accent);
        }

        .btn-primary:hover { opacity: .88; }

        .btn-muted {
            color: var(--text);
            background: #edf3fa;
            border: 1px solid var(--border);
        }

        .btn-danger {
            color: #fff;
            background: linear-gradient(140deg, #ba2d2d, #932323);
        }

        .chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            font-size: .75rem;
            padding: 4px 10px;
            border: 1px solid var(--border);
            background: #f5f8fc;
            color: var(--muted);
        }

        .table-wrap {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .table-wrap.soft {
            background: #fff;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
        }

        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid #e7edf5;
            text-align: left;
            font-size: .9rem;
        }

        th {
            background: #f3f7fb;
            color: #33485f;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        tr:last-child td { border-bottom: 0; }

        .employee-table tbody tr:hover {
            background: #f6f9ff;
        }

        .employee-photo {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.12);
        }

        .field-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        label {
            display: block;
            font-size: .82rem;
            color: #3c4f64;
            margin-bottom: 6px;
            font-weight: 700;
        }

        input, select, textarea {
            width: 100%;
            border: 1px solid #cbd8e6;
            border-radius: 10px;
            padding: 9px 10px;
            font-size: .92rem;
            background: #fff;
            color: var(--text);
            font-family: inherit;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 85, 165, 0.14);
        }

        textarea { min-height: 88px; resize: vertical; }

        .actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pager {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .pager .pager-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .pager .pager-links {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .pager .pager-btn,
        .pager .pager-page,
        .pager .pager-ellipsis {
            padding: 6px 10px;
            font-size: .86rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
        }

        .pager .pager-btn,
        .pager .pager-page {
            color: var(--text);
            transition: all .15s ease;
        }

        .pager .pager-btn:hover,
        .pager .pager-page:hover {
            background: #edf3fa;
        }

        .pager .pager-btn.disabled,
        .pager .pager-page.disabled {
            color: var(--muted);
            background: #f3f7fb;
            cursor: not-allowed;
        }

        .pager .pager-page.active {
            background: var(--primary);
            color: #fff;
            border-color: transparent;
        }

        .pager .pager-ellipsis {
            color: var(--muted);
            border-style: dashed;
            background: #f8fafc;
        }

        .pager .pager-meta {
            font-size: .86rem;
            color: var(--muted);
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
            animation: stock-alert-fade 0.15s ease forwards;
        }

        .stock-alert-card {
            width: min(460px, 100%);
            border-radius: 16px;
            border: 1px solid var(--border);
            background: #ffffff;
            box-shadow: var(--shadow);
            padding: 18px;
            animation: stock-alert-rise 0.15s ease;
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

        @keyframes hero-rise {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 980px) {
            .grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 980px) {
            .layout { grid-template-columns: 200px minmax(0, 1fr); }
        }

        @media (max-width: 760px) {
            .shell { width: 100%; }
            .layout { grid-template-columns: 1fr; }
            .sidebar { position: static; }
            .brand { justify-content: flex-start; }
            .appbar-search input { width: 140px; }
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
            .field-grid { grid-template-columns: 1fr; }
            .headline { font-size: 1.35rem; }
            .page-hero { flex-direction: column; align-items: flex-start; }
            .search-grid { grid-template-columns: 1fr; }
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
    $canEmployees = \App\Support\StaffAuth::can('employees.read');
    $canJobs = \App\Support\StaffAuth::can('jobs.read');
    $canClients = \App\Support\StaffAuth::can('clients.read');
    $canClientDebts = \App\Support\StaffAuth::can('client-depts.read');
    $canCurrencies = \App\Support\StaffAuth::can('currencies.read');
    $canProducts = \App\Support\StaffAuth::can('products.read');
    $canStockStatus = \App\Support\StaffAuth::can('stock-status.read');
    $canFutureStock = \App\Support\StaffAuth::can('future-stock.read');
    $canSystemAudit = \App\Support\StaffAuth::can('system.audit');
    $dashboardUrl = $canDashboardManage ? route('admin.dashboard') : route('store.home');
    $dashboardActive = $canDashboardManage ? request()->routeIs('admin.*') : request()->routeIs('store.home');
    $inProcessCount = isset($inProcessCount) ? (int) $inProcessCount : null;
    if ($inProcessCount === null && $canOrders) {
        try {
            $inProcessCount = (int) \Illuminate\Support\Facades\DB::connection('oracle')
                ->table('INVOICES')
                ->where('INVOICE_STATUS', '=', 'In Process')
                ->count();
        } catch (\Throwable) {
            $inProcessCount = 0;
        }
    }
    $inProcessCount = $inProcessCount ?? 0;

    $clientDeptCount = isset($clientDeptCount) ? (int) $clientDeptCount : null;
    if ($clientDeptCount === null && $canClientDebts) {
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

    $underStockCount = isset($underStockCount) ? (int) $underStockCount : null;
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
        <!-- search removed -->
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
            <div class="flash success toast">
                <span class="toast-ico" aria-hidden="true">
                    <svg width="14" height="14" viewBox="0 0 24 24" focusable="false">
                        <path fill="currentColor" d="M9.55 17.55L4.5 12.5l1.4-1.4 3.65 3.65 8.05-8.05 1.4 1.4z"/>
                    </svg>
                </span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="flash error">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="card">
                <h3 style="margin-top: 0;">Please fix these issues</h3>
                <ul class="error-list">
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

