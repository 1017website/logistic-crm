<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CRM') - Logistic Service</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Select2 (local) -->
    <link href="{{ asset('vendor/select2/select2.min.css') }}" rel="stylesheet">
    <!-- Flatpickr (local) -->
    <link href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}" rel="stylesheet">

    <style>
        /* ── Select2 Custom Theme ── */
        .select2-container--default .select2-selection--single {
            height: 38px !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 7px !important;
            background: #fff !important;
            font-size: .82rem;
            display: flex;
            align-items: center;
            transition: border-color .2s;
        }
        .select2-container--default .select2-selection--single:hover {
            border-color: #d1d5db !important;
        }
        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #2563eb !important;
            box-shadow: 0 0 0 3px rgba(37,99,235,.1) !important;
            outline: none !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px !important;
            padding-left: 12px !important;
            padding-right: 30px !important;
            color: #374151 !important;
            font-size: .82rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #9ca3af !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
            right: 8px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: #9ca3af transparent transparent transparent !important;
        }
        .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
            border-color: transparent transparent #2563eb transparent !important;
        }
        /* Multiple select2 */
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #e5e7eb !important;
            border-radius: 7px !important;
            min-height: 38px !important;
            font-size: .82rem;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #2563eb !important;
            box-shadow: 0 0 0 3px rgba(37,99,235,.1) !important;
            outline: none !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background: #eff6ff !important;
            border: 1px solid #bfdbfe !important;
            color: #2563eb !important;
            border-radius: 20px !important;
            font-size: .75rem;
            padding: 1px 8px !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #93c5fd !important;
            margin-right: 4px !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #2563eb !important;
        }
        /* Dropdown */
        .select2-dropdown {
            border: 1px solid #e5e7eb !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,.1) !important;
            font-size: .82rem;
            overflow: hidden;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #e5e7eb !important;
            border-radius: 6px !important;
            font-size: .82rem;
            padding: 6px 10px;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field:focus {
            border-color: #2563eb !important;
            outline: none;
        }
        .select2-container--default .select2-results__option {
            padding: 8px 12px;
            font-size: .82rem;
            color: #374151;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background: #eff6ff !important;
            color: #2563eb !important;
        }
        .select2-container--default .select2-results__option[aria-selected=true] {
            background: #f0fdf4 !important;
            color: #16a34a !important;
        }
        .select2-results__option--group { font-weight: 600; color: #9ca3af; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; }

        /* ── Flatpickr Custom Theme ── */
        .flatpickr-input {
            background: #fff !important;
            cursor: pointer;
        }
        .flatpickr-input:focus {
            border-color: #2563eb !important;
            box-shadow: 0 0 0 3px rgba(37,99,235,.1) !important;
        }
        .flatpickr-calendar {
            border: 1px solid #e5e7eb !important;
            border-radius: 12px !important;
            box-shadow: 0 8px 32px rgba(0,0,0,.12) !important;
            font-family: 'Inter', sans-serif !important;
            overflow: hidden;
        }
        .flatpickr-months { background: #0f1d35; border-radius: 10px 10px 0 0; padding: 4px 0; }
        .flatpickr-month { color: #fff !important; }
        .flatpickr-current-month { font-size: .9rem !important; font-weight: 600 !important; color: #fff !important; }
        .flatpickr-current-month .flatpickr-monthDropdown-months { color: #fff !important; background: transparent; }
        .flatpickr-current-month input.cur-year { color: #fff !important; font-weight: 600 !important; }
        .flatpickr-prev-month, .flatpickr-next-month { color: #fff !important; fill: #fff !important; padding: 8px !important; }
        .flatpickr-prev-month:hover svg, .flatpickr-next-month:hover svg { fill: #93c5fd !important; }
        .flatpickr-weekdays { background: #f9fafb; }
        .flatpickr-weekday { color: #9ca3af !important; font-size: .72rem !important; font-weight: 600 !important; }
        .flatpickr-day {
            border-radius: 8px !important;
            font-size: .8rem !important;
            color: #374151 !important;
            height: 34px !important;
            line-height: 34px !important;
        }
        .flatpickr-day:hover { background: #eff6ff !important; color: #2563eb !important; border-color: transparent !important; }
        .flatpickr-day.selected, .flatpickr-day.selected:hover {
            background: #2563eb !important;
            border-color: #2563eb !important;
            color: #fff !important;
            border-radius: 8px !important;
        }
        .flatpickr-day.today { border-color: #2563eb !important; color: #2563eb !important; font-weight: 600 !important; }
        .flatpickr-day.today:hover { background: #eff6ff !important; }
        .flatpickr-day.today.selected { color: #fff !important; }
        .flatpickr-day.inRange { background: #dbeafe !important; border-color: transparent !important; }
        .flatpickr-day.disabled { color: #d1d5db !important; }
        .flatpickr-time input { font-size: .82rem !important; color: #374151 !important; font-family: 'Inter', sans-serif !important; }
        .flatpickr-time .flatpickr-am-pm { font-size: .82rem !important; color: #374151 !important; }
        .flatpickr-time input:hover, .flatpickr-time .flatpickr-am-pm:hover,
        .flatpickr-time input:focus, .flatpickr-time .flatpickr-am-pm:focus { background: #eff6ff !important; }

        /* Input date wrapper icon */
        .date-input-wrap { position: relative; }
        .date-input-wrap .date-icon {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            color: #9ca3af; font-size: .8rem; pointer-events: none;
        }
        :root {
            --sidebar-bg: #0f1d35;
            --sidebar-width: 220px;
            --sidebar-collapsed: 60px;
            --primary: #2563eb;
            --primary-light: #dbeafe;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --purple-color: #8b5cf6;
            --teal-color: #14b8a6;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --card-shadow: 0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
        }
        * { font-family: 'Inter', sans-serif; }
        body { background: #f3f4f8; overflow-x: hidden; }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            height: 100vh;
            position: fixed;
            left: 0; top: 0;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: width .25s ease;
            overflow: hidden;
        }
        .sidebar-brand {
            padding: 18px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            text-decoration: none;
        }
        .sidebar-brand .brand-icon {
            width: 36px; height: 36px;
            background: var(--primary);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .sidebar-brand .brand-text {
            color: #fff;
            font-weight: 700;
            font-size: .85rem;
            line-height: 1.2;
            white-space: nowrap;
        }
        .sidebar-nav { flex: 1; overflow-y: auto; padding: 12px 0; }
        .sidebar-nav::-webkit-scrollbar { width: 3px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,.2); border-radius: 2px; }

        .sidebar-section {
            padding: 8px 16px 4px;
            font-size: .65rem;
            font-weight: 600;
            color: rgba(255,255,255,.35);
            letter-spacing: .08em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 16px;
            color: rgba(255,255,255,.65);
            text-decoration: none;
            font-size: .82rem;
            font-weight: 500;
            transition: all .15s;
            cursor: pointer;
            white-space: nowrap;
        }
        .sidebar-item:hover { color: #fff; background: rgba(255,255,255,.07); }
        .sidebar-item.active { color: #fff; background: var(--primary); border-radius: 6px; margin: 0 8px; padding: 9px 12px; }
        .sidebar-item .si-icon { width: 18px; text-align: center; flex-shrink: 0; font-size: .85rem; }

        .sidebar-footer {
            padding: 12px 16px;
            border-top: 1px solid rgba(255,255,255,.08);
        }
        .sidebar-collapse-btn {
            width: 100%;
            background: rgba(255,255,255,.07);
            border: none;
            color: rgba(255,255,255,.6);
            padding: 8px 10px;
            border-radius: 6px;
            font-size: .78rem;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: .15s;
            white-space: nowrap;
        }
        .sidebar-collapse-btn:hover { background: rgba(255,255,255,.12); color: #fff; }

        /* MAIN */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left .25s ease;
        }
        .topbar {
            background: #fff;
            border-bottom: 1px solid var(--border-color);
            padding: 0 24px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar-title h5 { font-size: 1.05rem; font-weight: 700; color: #111827; margin: 0; }
        .topbar-title p { font-size: .75rem; color: var(--text-muted); margin: 0; }

        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .topbar-search {
            position: relative;
        }
        .topbar-search input {
            background: #f9fafb;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 7px 12px 7px 34px;
            font-size: .8rem;
            width: 220px;
            outline: none;
            transition: .2s;
        }
        .topbar-search input:focus { border-color: var(--primary); background: #fff; }
        .topbar-search .search-icon {
            position: absolute;
            left: 10px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: .8rem;
        }
        .notif-btn {
            position: relative;
            width: 36px; height: 36px;
            background: #f9fafb;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            color: var(--text-muted);
            transition: .15s;
        }
        .notif-btn:hover { background: #fff; color: #111; }
        .notif-badge {
            position: absolute;
            top: -4px; right: -4px;
            background: var(--danger-color);
            color: #fff;
            font-size: .6rem;
            font-weight: 700;
            width: 16px; height: 16px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
        .user-avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem;
            font-weight: 700;
        }
        .user-info { line-height: 1.2; }
        .user-info .u-name { font-size: .82rem; font-weight: 600; color: #111; }
        .user-info .u-role { font-size: .72rem; color: var(--text-muted); }

        .drop-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 14px; font-size: 13px; color: #374151;
            text-decoration: none; border-radius: 6px; cursor: pointer;
            transition: background .12s;
        }
        .drop-item:hover { background: #f9fafb; color: #111; }
        .drop-item span { color: #374151; }

        .search-result-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 16px; cursor: pointer; border-bottom: 1px solid #f9fafb;
            text-decoration: none;
        }
        .search-result-item:hover { background: #f9fafb; }
        .search-result-item:last-child { border-bottom: none; }
        .sri-icon { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 12px; }
        .sri-title { font-size: 13px; font-weight: 600; color: #0f1d35; }
        .sri-sub   { font-size: 11px; color: #6b7280; }
        .sri-badge { font-size: 10px; padding: 1px 7px; border-radius: 20px; font-weight: 600; flex-shrink: 0; margin-left: auto; }

        .main-content { padding: 24px; flex: 1; }

        /* CARDS */
        .card { border: none; box-shadow: var(--card-shadow); border-radius: 10px; }
        .card-header { background: transparent; border-bottom: 1px solid var(--border-color); padding: 14px 18px; font-weight: 600; font-size: .88rem; }

        /* KPI CARDS */
        .kpi-card {
            background: #fff;
            border-radius: 10px;
            padding: 18px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .kpi-icon {
            width: 52px; height: 52px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .kpi-label { font-size: .72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: .04em; }
        .kpi-value { font-size: 1.3rem; font-weight: 700; color: #111827; line-height: 1.2; }
        .kpi-change { font-size: .72rem; font-weight: 600; }
        .kpi-change.up { color: var(--success-color); }
        .kpi-change.down { color: var(--danger-color); }
        .kpi-vs { font-size: .7rem; color: var(--text-muted); }

        /* BADGES */
        .badge-hot { background: #fee2e2; color: #dc2626; font-size: .68rem; font-weight: 600; padding: 2px 7px; border-radius: 20px; }
        .badge-warm { background: #fef3c7; color: #d97706; font-size: .68rem; font-weight: 600; padding: 2px 7px; border-radius: 20px; }
        .badge-cold { background: #dbeafe; color: #2563eb; font-size: .68rem; font-weight: 600; padding: 2px 7px; border-radius: 20px; }
        .badge-existing { background: #d1fae5; color: #059669; font-size: .7rem; font-weight: 600; padding: 3px 8px; border-radius: 20px; }
        .badge-potential { background: #fef3c7; color: #d97706; font-size: .7rem; font-weight: 600; padding: 3px 8px; border-radius: 20px; }
        .badge-stage {
            font-size: .68rem; font-weight: 600; padding: 2px 8px; border-radius: 20px; display: inline-block;
        }
        .badge-identifying { background: #dbeafe; color: #1d4ed8; }
        .badge-approaching { background: #fef3c7; color: #b45309; }
        .badge-follow-up { background: #ede9fe; color: #7c3aed; }
        .badge-closing { background: #d1fae5; color: #059669; }
        .badge-won { background: #ccfbf1; color: #0d9488; }
        .badge-lost { background: #fee2e2; color: #dc2626; }

        .badge-done { background: #d1fae5; color: #059669; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-planned { background: #dbeafe; color: #1d4ed8; }
        .badge-overdue { background: #fee2e2; color: #dc2626; }
        .badge-today { background: #dbeafe; color: #1d4ed8; }
        .badge-tomorrow { background: #ede9fe; color: #7c3aed; }

        /* PIPELINE KANBAN */
        .kanban-col { min-width: 0; }
        .kanban-header {
            padding: 10px 14px;
            border-radius: 8px 8px 0 0;
            font-size: .8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .kanban-identifying { background: #dbeafe; color: #1d4ed8; }
        .kanban-approaching { background: #fef3c7; color: #b45309; }
        .kanban-follow-up { background: #ede9fe; color: #7c3aed; }
        .kanban-closing { background: #d1fae5; color: #059669; }
        .kanban-won { background: #ccfbf1; color: #0d9488; }
        .kanban-body {
            background: #f9fafb;
            border: 1px solid var(--border-color);
            border-radius: 0 0 8px 8px;
            padding: 10px;
            min-height: 200px;
        }
        .kanban-card {
            background: #fff;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            box-shadow: var(--card-shadow);
            font-size: .78rem;
            cursor: pointer;
            transition: .15s;
        }
        .kanban-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); transform: translateY(-1px); }
        .kanban-card .kc-company { font-weight: 600; color: #111; font-size: .82rem; }
        .kanban-card .kc-pic { color: var(--text-muted); margin-top: 2px; }
        .kanban-card .kc-service { color: #6b7280; margin-top: 4px; font-size: .75rem; }
        .kanban-card .kc-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
        .kanban-card .kc-amount { font-size: .75rem; font-weight: 600; color: var(--primary); }

        /* ACTIVITY TIMELINE */
        .activity-timeline { position: relative; }
        .activity-item {
            display: flex;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-time { min-width: 50px; text-align: right; font-size: .75rem; font-weight: 600; color: #374151; }
        .activity-icon {
            width: 34px; height: 34px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem;
            flex-shrink: 0;
        }
        .activity-body { flex: 1; }
        .activity-subject { font-weight: 600; font-size: .83rem; color: #111; }
        .activity-desc { font-size: .77rem; color: var(--text-muted); margin-top: 2px; }
        .activity-meta { font-size: .72rem; color: var(--text-muted); margin-top: 4px; }

        /* TABLE */
        .crm-table { font-size: .82rem; }
        .crm-table th { font-size: .72rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; background: #f9fafb; border-bottom: 1px solid var(--border-color); padding: 10px 14px; }
        .crm-table td { padding: 12px 14px; vertical-align: middle; border-bottom: 1px solid #f3f4f6; }
        .crm-table tr:hover td { background: #fafafa; }

        /* QUICK ACTION */
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 14px 8px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: .15s;
            text-decoration: none;
        }
        .quick-action-btn:hover { background: var(--primary-light); border-color: var(--primary); }
        .quick-action-btn .qa-icon {
            width: 34px; height: 34px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem;
        }
        .quick-action-btn .qa-label { font-size: .7rem; font-weight: 600; color: #374151; text-align: center; }

        /* REMINDER */
        .reminder-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: .78rem;
        }
        .reminder-item:last-child { border-bottom: none; }
        .reminder-time { min-width: 40px; font-weight: 700; font-size: .8rem; color: #111; }

        /* SIDEBAR COLLAPSED */
        body.sidebar-collapsed .sidebar { width: var(--sidebar-collapsed); }
        body.sidebar-collapsed .main-wrapper { margin-left: var(--sidebar-collapsed); }
        body.sidebar-collapsed .sidebar-brand .brand-text,
        body.sidebar-collapsed .sidebar-section,
        body.sidebar-collapsed .sidebar-item span,
        body.sidebar-collapsed .sidebar-collapse-btn span { display: none; }

        /* MODALS */
        .modal-header { border-bottom: 1px solid var(--border-color); }
        .modal-footer { border-top: 1px solid var(--border-color); }
        .form-label { font-size: .8rem; font-weight: 600; color: #374151; }
        .form-control, .form-select {
            font-size: .82rem;
            border-color: var(--border-color);
            border-radius: 7px;
            padding: 8px 12px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        }

        /* BTN */
        .btn-primary { background: var(--primary); border-color: var(--primary); font-size: .82rem; }
        .btn-primary:hover { background: #1d4ed8; border-color: #1d4ed8; }
        .btn-sm { font-size: .75rem; padding: 5px 10px; }

        /* CHARTS placeholder */
        .chart-placeholder {
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            background: #f9fafb;
            border-radius: 8px;
        }

        /* SCROLLBAR */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: #f3f4f8; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }

        /* ALERTS */
        .alert-success { background: #d1fae5; border-color: #a7f3d0; color: #065f46; font-size: .82rem; }
        .alert-danger  { background: #fee2e2; border-color: #fca5a5; color: #991b1b; font-size: .82rem; }
    </style>

    @stack('styles')
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <a href="{{ route('dashboard') }}" class="sidebar-brand">
        <div class="brand-icon">
            <i class="fas fa-truck-fast text-white" style="font-size:.85rem"></i>
        </div>
        <div class="brand-text">LOGISTIC<br>SERVICE</div>
    </a>

    <nav class="sidebar-nav">
        <div class="sidebar-section">Main Menu</div>
        <a href="{{ route('dashboard') }}" class="sidebar-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fas fa-home si-icon"></i><span>Dashboard</span>
        </a>

        <div class="sidebar-section">Sales</div>
        <a href="{{ route('sales.activity') }}" class="sidebar-item {{ request()->routeIs('sales.*') ? 'active' : '' }}">
            <i class="fas fa-chart-line si-icon"></i><span>Sales Activity</span>
        </a>
        <a href="{{ route('leads.index') }}" class="sidebar-item {{ request()->routeIs('leads.*') ? 'active' : '' }}">
            <i class="fas fa-user-plus si-icon"></i><span>Leads</span>
        </a>
        <a href="{{ route('pipeline.index') }}" class="sidebar-item {{ request()->routeIs('pipeline.*') ? 'active' : '' }}">
            <i class="fas fa-filter si-icon"></i><span>Pipeline</span>
        </a>
        <a href="{{ route('calendar.index') }}" class="sidebar-item {{ request()->routeIs('calendar.*') ? 'active' : '' }}">
            <i class="fas fa-calendar si-icon"></i><span>Calendar</span>
        </a>
        <a href="{{ route('tasks.index') }}" class="sidebar-item {{ request()->routeIs('tasks.*') ? 'active' : '' }}">
            <i class="fas fa-bell si-icon"></i><span>Tasks / Reminder</span>
        </a>

        <div class="sidebar-section">Marketing</div>
        <a href="{{ route('customers.index') }}" class="sidebar-item {{ request()->routeIs('customers.*') ? 'active' : '' }}">
            <i class="fas fa-building si-icon"></i><span>Database Customer</span>
        </a>
        <a href="{{ route('vendors.index') }}" class="sidebar-item {{ request()->routeIs('vendors.*') ? 'active' : '' }}">
            <i class="fas fa-handshake si-icon"></i><span>Database Vendor</span>
        </a>

        <div class="sidebar-section">Analytics</div>
        @if(auth()->user()->canAccess('analytics'))
        <a href="{{ route('analytics.index') }}" class="sidebar-item {{ request()->routeIs('analytics.*') ? 'active' : '' }}">
            <i class="fas fa-chart-bar si-icon"></i><span>Analytics</span>
        </a>
        @endif
        @if(auth()->user()->canAccess('reports'))
        <a href="{{ route('reports.index') }}" class="sidebar-item {{ request()->routeIs('reports.*') ? 'active' : '' }}">
            <i class="fas fa-file-alt si-icon"></i><span>Reports</span>
        </a>
        @endif

        <div class="sidebar-section">System</div>
        @if(auth()->user()->canAccess('users'))
        <a href="{{ route('users.index') }}" class="sidebar-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <i class="fas fa-users si-icon"></i><span>Users</span>
        </a>
        @endif
        @if(auth()->user()->canAccess('settings'))
        <a href="{{ route('settings.index') }}" class="sidebar-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
            <i class="fas fa-cog si-icon"></i><span>Settings</span>
        </a>
        @endif
    </nav>

    <div class="sidebar-footer">
        <button class="sidebar-collapse-btn" onclick="toggleSidebar()">
            <i class="fas fa-chevron-left" id="collapseIcon"></i>
            <span>Collapse</span>
        </button>
    </div>
</aside>

<!-- MAIN -->
<div class="main-wrapper" id="mainWrapper">
    <!-- TOPBAR -->
    <header class="topbar">
        <div class="topbar-title">
            <h5>@yield('page-title', 'Dashboard')</h5>
            <p>@yield('page-subtitle', '')</p>
        </div>
        <div class="topbar-right">

            {{-- ── SEARCH ── --}}
            <div class="topbar-search" id="searchWrap">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="globalSearch" placeholder="Search lead, customer..." autocomplete="off"
                    oninput="handleSearch(this.value)" onfocus="showSearchDrop()" />
                {{-- Search dropdown --}}
                <div id="searchDrop" style="display:none;position:absolute;top:calc(100% + 6px);left:0;width:340px;background:#fff;border-radius:10px;border:1px solid #e5e7eb;box-shadow:0 4px 20px rgba(0,0,0,.12);z-index:999;overflow:hidden">
                    <div id="searchResults" style="max-height:320px;overflow-y:auto">
                        <div style="padding:12px 16px;font-size:12px;color:#9ca3af;text-align:center">Ketik untuk mencari lead, customer, atau vendor...</div>
                    </div>
                    <div style="padding:10px 16px;border-top:1px solid #f0f0f0;display:flex;gap:8px;flex-wrap:wrap">
                        <span style="font-size:11px;color:#9ca3af">Quick:</span>
                        <a href="{{ route('leads.index') }}"   style="font-size:11px;color:#3b82f6;text-decoration:none">Leads</a>
                        <a href="{{ route('customers.index') }}" style="font-size:11px;color:#3b82f6;text-decoration:none">Customers</a>
                        <a href="{{ route('pipeline.index') }}" style="font-size:11px;color:#3b82f6;text-decoration:none">Pipeline</a>
                        <a href="{{ route('tasks.index') }}"   style="font-size:11px;color:#3b82f6;text-decoration:none">Tasks</a>
                    </div>
                </div>
            </div>

            {{-- ── NOTIFICATION ── --}}
            @php
            $notifCount = \App\Models\Activity::where('status','Overdue')
                ->orWhere(function($q){ $q->whereDate('activity_at', today())->where('status','!=','Done'); })
                ->count();
            @endphp
            <div style="position:relative" id="notifWrap">
                <div class="notif-btn" onclick="toggleNotif()">
                    <i class="fas fa-bell" style="font-size:.85rem"></i>
                    @if($notifCount > 0)
                    <span class="notif-badge" id="notifCount">{{ $notifCount > 9 ? '9+' : $notifCount }}</span>
                    @endif
                </div>
                {{-- Notification dropdown --}}
                <div id="notifDrop" style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:320px;background:#fff;border-radius:10px;border:1px solid #e5e7eb;box-shadow:0 4px 20px rgba(0,0,0,.12);z-index:999;overflow:hidden">
                    <div style="padding:14px 16px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between">
                        <span style="font-size:13px;font-weight:700;color:#0f1d35">Notifications</span>
                        <button onclick="markAllRead()" style="font-size:11px;color:#3b82f6;background:none;border:none;cursor:pointer;padding:0">Mark all read</button>
                    </div>
                    <div style="max-height:320px;overflow-y:auto" id="notifList">
                        @php
                        $notifActivities = \App\Models\Activity::with(['lead','customer','salesUser'])
                            ->where(function($q) {
                                $q->where('status', 'Overdue')
                                  ->orWhere(function($q2) {
                                      $q2->whereDate('activity_at', today())
                                         ->where('status','!=','Done');
                                  })
                                  ->orWhere(function($q3) {
                                      $q3->whereDate('next_follow_up', today());
                                  });
                            })
                            ->orderBy('activity_at')
                            ->limit(5)
                            ->get();
                        $recentLeads = \App\Models\Lead::orderBy('created_at','desc')->limit(3)->get();
                        @endphp

                        @if($notifActivities->isEmpty() && $recentLeads->isEmpty())
                        <div style="padding:24px 16px;text-align:center;color:#9ca3af;font-size:13px">
                            <i class="fas fa-bell-slash" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4"></i>
                            Tidak ada notifikasi
                        </div>
                        @else
                            @foreach($notifActivities as $act)
                            @php
                            $isOverdue = $act->status === 'Overdue';
                            $icon  = $isOverdue ? 'exclamation-circle' : 'clock';
                            $color = $isOverdue ? '#ef4444' : '#f59e0b';
                            $bg    = $isOverdue ? '#fee2e2' : '#fef9c3';
                            $title = $isOverdue ? 'Activity Overdue' : 'Reminder Hari Ini';
                            $who   = $act->customer?->company_name ?? $act->lead?->company_name ?? '-';
                            $diff  = $act->activity_at ? $act->activity_at->diffForHumans() : '-';
                            @endphp
                            <div class="notif-item unread" style="display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-bottom:1px solid #f9fafb;cursor:pointer;background:#fafbff" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#fafbff'">
                                <div style="width:34px;height:34px;border-radius:50%;background:{{ $bg }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <i class="fas fa-{{ $icon }}" style="font-size:13px;color:{{ $color }}"></i>
                                </div>
                                <div style="flex:1;min-width:0">
                                    <div style="font-size:12px;font-weight:600;color:#0f1d35">{{ $title }}</div>
                                    <div style="font-size:11px;color:#6b7280;margin-top:2px;line-height:1.4">{{ $act->subject }} — {{ $who }}</div>
                                    <div style="font-size:10px;color:#9ca3af;margin-top:4px">{{ $diff }}</div>
                                </div>
                                <div style="width:7px;height:7px;border-radius:50%;background:{{ $color }};flex-shrink:0;margin-top:4px"></div>
                            </div>
                            @endforeach

                            @foreach($recentLeads as $lead)
                            <div class="notif-item" style="display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-bottom:1px solid #f9fafb;cursor:pointer;background:#fff" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#fff'">
                                <div style="width:34px;height:34px;border-radius:50%;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <i class="fas fa-user-plus" style="font-size:13px;color:#2563eb"></i>
                                </div>
                                <div style="flex:1;min-width:0">
                                    <div style="font-size:12px;font-weight:500;color:#0f1d35">Lead Baru</div>
                                    <div style="font-size:11px;color:#6b7280;margin-top:2px">{{ $lead->company_name }} — {{ $lead->pipeline_stage }}</div>
                                    <div style="font-size:10px;color:#9ca3af;margin-top:4px">{{ $lead->created_at->diffForHumans() }}</div>
                                </div>
                            </div>
                            @endforeach
                        @endif
                    </div>
                    <a href="{{ route('tasks.index') }}" style="display:block;text-align:center;padding:12px;font-size:12px;color:#3b82f6;text-decoration:none;border-top:1px solid #f0f0f0" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                        Lihat semua notifikasi →
                    </a>
                </div>
            </div>

            {{-- ── USER MENU ── --}}
            <div style="position:relative" id="userDropWrap">
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:4px 8px;border-radius:8px;transition:.15s" onclick="toggleUserDrop()" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                    <div class="user-avatar">{{ auth()->user()->avatar_initials }}</div>
                    <div class="user-info">
                        <div class="u-name">{{ auth()->user()->name }}</div>
                        <div class="u-role">{{ auth()->user()->role }}</div>
                    </div>
                    <i class="fas fa-chevron-down" style="font-size:.7rem;color:#9ca3af"></i>
                </div>
                {{-- User dropdown --}}
                <div id="userDrop" style="display:none;position:absolute;top:calc(100% + 6px);right:0;background:#fff;border-radius:10px;border:1px solid #e5e7eb;box-shadow:0 4px 20px rgba(0,0,0,.12);min-width:210px;z-index:999;padding:6px;overflow:hidden">
                    {{-- Profile header --}}
                    <div style="padding:12px 14px 10px;border-bottom:1px solid #f0f0f0;margin-bottom:4px">
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="user-avatar" style="width:38px;height:38px;font-size:.8rem">{{ auth()->user()->avatar_initials }}</div>
                            <div>
                                <div style="font-size:13px;font-weight:600;color:#0f1d35">{{ auth()->user()->name }}</div>
                                <div style="font-size:11px;color:#6b7280">{{ auth()->user()->email }}</div>
                                <span style="font-size:10px;font-weight:600;padding:1px 8px;border-radius:20px;background:#eff6ff;color:#2563eb;display:inline-block;margin-top:3px">{{ auth()->user()->role }}</span>
                            </div>
                        </div>
                    </div>
                    {{-- Menu items --}}
                    @php
                    $dropMenus = [
                        ['icon'=>'tachometer-alt','label'=>'Dashboard','route'=>'dashboard'],
                        ['icon'=>'tasks','label'=>'Tasks & Reminder','route'=>'tasks.index'],
                        ['icon'=>'calendar','label'=>'Calendar','route'=>'calendar.index'],
                    ];
                    @endphp
                    @foreach($dropMenus as $m)
                    <a href="{{ route($m['route']) }}" class="drop-item">
                        <i class="fas fa-{{ $m['icon'] }}" style="width:16px;color:#9ca3af;font-size:13px"></i>
                        <span>{{ $m['label'] }}</span>
                    </a>
                    @endforeach
                    @if(auth()->user()->canAccess('settings'))
                    <a href="{{ route('settings.index') }}" class="drop-item">
                        <i class="fas fa-cog" style="width:16px;color:#9ca3af;font-size:13px"></i>
                        <span>Settings</span>
                    </a>
                    @endif
                    <div style="border-top:1px solid #f0f0f0;margin:4px 0"></div>
                    <form action="{{ route('logout') }}" method="POST" style="margin:0">
                        @csrf
                        <button type="submit" class="drop-item" style="width:100%;background:none;border:none;color:#dc2626;text-align:left">
                            <i class="fas fa-sign-out-alt" style="width:16px;font-size:13px"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </header>

    <!-- CONTENT -->
    <main class="main-content">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>
</div>

<!-- jQuery (harus sebelum Bootstrap & Select2) -->
<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- Select2 (local) -->
<script src="{{ asset('vendor/select2/select2.min.js') }}"></script>
<!-- Flatpickr (local) -->
<script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}"></script>

<script>
function toggleSidebar() {
    document.body.classList.toggle('sidebar-collapsed');
    const icon = document.getElementById('collapseIcon');
    icon.className = document.body.classList.contains('sidebar-collapsed') ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
}

// ── Close all dropdowns when click outside ──
document.addEventListener('click', function(e) {
    if (!document.getElementById('notifWrap')?.contains(e.target))
        document.getElementById('notifDrop').style.display = 'none';
    if (!document.getElementById('userDropWrap')?.contains(e.target))
        document.getElementById('userDrop').style.display = 'none';
    if (!document.getElementById('searchWrap')?.contains(e.target))
        document.getElementById('searchDrop').style.display = 'none';
});

// ── Notification ──
function toggleNotif() {
    const d = document.getElementById('notifDrop');
    const u = document.getElementById('userDrop');
    const s = document.getElementById('searchDrop');
    u.style.display = 'none'; s.style.display = 'none';
    d.style.display = d.style.display === 'none' ? 'block' : 'none';
}
function markAllRead() {
    document.querySelectorAll('.notif-item.unread').forEach(el => {
        el.classList.remove('unread');
        el.style.background = '#fff';
        const dot = el.querySelector('div[style*="border-radius:50%;background:#3b82f6"]');
        if (dot) dot.remove();
    });
    document.getElementById('notifCount').style.display = 'none';
}

// ── User dropdown ──
function toggleUserDrop() {
    const d = document.getElementById('userDrop');
    const n = document.getElementById('notifDrop');
    const s = document.getElementById('searchDrop');
    n.style.display = 'none'; s.style.display = 'none';
    d.style.display = d.style.display === 'none' ? 'block' : 'none';
}

// ── Search ──
let searchTimeout;
function showSearchDrop() {
    document.getElementById('notifDrop').style.display = 'none';
    document.getElementById('userDrop').style.display = 'none';
    document.getElementById('searchDrop').style.display = 'block';
}
function handleSearch(q) {
    clearTimeout(searchTimeout);
    if (q.length < 2) {
        document.getElementById('searchResults').innerHTML =
            '<div style="padding:12px 16px;font-size:12px;color:#9ca3af;text-align:center">Ketik minimal 2 karakter...</div>';
        return;
    }
    document.getElementById('searchResults').innerHTML =
        '<div style="padding:12px 16px;font-size:12px;color:#9ca3af;text-align:center"><i class="fas fa-spinner fa-spin me-1"></i>Mencari...</div>';
    searchTimeout = setTimeout(() => fetchSearch(q), 350);
}
function fetchSearch(q) {
    fetch(`/search?q=${encodeURIComponent(q)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(data => renderSearchResults(data, q))
    .catch(() => {
        document.getElementById('searchResults').innerHTML =
            '<div style="padding:12px 16px;font-size:12px;color:#9ca3af;text-align:center">Tidak dapat memuat hasil.</div>';
    });
}
function renderSearchResults(data, q) {
    const container = document.getElementById('searchResults');
    if (!data.length) {
        container.innerHTML = `<div style="padding:16px;font-size:12px;color:#9ca3af;text-align:center">
            <i class="fas fa-search" style="font-size:20px;display:block;margin-bottom:6px;opacity:.4"></i>
            Tidak ada hasil untuk "<strong>${q}</strong>"</div>`;
        return;
    }
    const icons  = { lead:'user-plus', customer:'building', vendor:'handshake' };
    const colors = { lead:'#eff6ff:#2563eb', customer:'#f0fdf4:#16a34a', vendor:'#faf5ff:#7c3aed' };
    const labels = { lead:'Lead', customer:'Customer', vendor:'Vendor' };
    container.innerHTML = data.map(item => {
        const [bg, color] = (colors[item.type] || '#f9fafb:#6b7280').split(':');
        return `<a href="${item.url}" class="search-result-item">
            <div class="sri-icon" style="background:${bg}">
                <i class="fas fa-${icons[item.type] || 'file'}" style="color:${color}"></i>
            </div>
            <div style="flex:1;min-width:0">
                <div class="sri-title">${item.title}</div>
                <div class="sri-sub">${item.subtitle || ''}</div>
            </div>
            <span class="sri-badge" style="background:${bg};color:${color}">${labels[item.type] || item.type}</span>
        </a>`;
    }).join('');
}
</script>

<script>
// ── Global Init: Select2 & Flatpickr ──
function initSelect2(scope) {
    const ctx = scope ? $(scope) : $(document);
    ctx.find('select').filter('.form-select, .form-select-sm, .select2').not('.no-select2').each(function() {
        if ($(this).data('select2')) return;
        $(this).select2({
            width: '100%',
            placeholder: $(this).data('placeholder') || $(this).find('option[value=""]').text() || 'Pilih...',
            allowClear: true,
            minimumResultsForSearch: 0,
            dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : $('body'),
            language: {
                noResults: () => '<span style="font-size:13px;color:#9ca3af">Tidak ada hasil</span>',
                searching: () => '<span style="font-size:13px;color:#9ca3af">Mencari...</span>',
            }
        });
    });
}

function initFlatpickr(scope) {
    const ctx = scope || document;

    // Date only
    $(ctx).find('input[type="date"]').not('.flatpickr-input').each(function() {
        if (this._flatpickr) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'date-input-wrap';
        this.parentNode.insertBefore(wrapper, this);
        wrapper.appendChild(this);
        const icon = document.createElement('i');
        icon.className = 'fas fa-calendar-alt date-icon';
        wrapper.appendChild(icon);
        flatpickr(this, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd M Y',
            locale: { firstDayOfWeek: 1 },
            disableMobile: true,
            allowInput: true,
        });
    });

    // DateTime
    $(ctx).find('input[type="datetime-local"]').not('.flatpickr-input').each(function() {
        if (this._flatpickr) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'date-input-wrap';
        this.parentNode.insertBefore(wrapper, this);
        wrapper.appendChild(this);
        const icon = document.createElement('i');
        icon.className = 'fas fa-clock date-icon';
        wrapper.appendChild(icon);
        flatpickr(this, {
            dateFormat: 'Y-m-d H:i',
            altInput: true,
            altFormat: 'd M Y H:i',
            enableTime: true,
            time_24hr: true,
            minuteIncrement: 15,
            locale: { firstDayOfWeek: 1 },
            disableMobile: true,
            allowInput: true,
        });
    });
}

// Init saat DOM ready
$(document).ready(function() {
    initSelect2();
    initFlatpickr();

    // Re-init setiap kali modal Bootstrap dibuka
    $(document).on('shown.bs.modal', '.modal', function() {
        initSelect2(this);
        initFlatpickr(this);
    });

    // ── Format input IDR ──
    $(document).on('input', '.idr-input', function() {
        let pos = this.selectionStart;
        let raw = $(this).val().replace(/\D/g, '');
        if (raw === '') { $(this).val(''); return; }
        let formatted = parseInt(raw, 10).toLocaleString('id-ID');
        $(this).val(formatted);
    });
    $(document).on('focus', '.idr-input', function() {
        // Saat focus, strip format untuk mudah edit
        let raw = $(this).val().replace(/\D/g, '');
        $(this).val(raw);
    });
    $(document).on('blur', '.idr-input', function() {
        let raw = $(this).val().replace(/\D/g, '');
        if (raw === '') return;
        $(this).val(parseInt(raw, 10).toLocaleString('id-ID'));
    });

    // Strip separator titik dari .idr-input sebelum form submit
    $(document).on('submit', 'form', function() {
        $(this).find('.idr-input').each(function() {
            let raw = $(this).val().replace(/\./g, '').replace(/,/g, '');
            $(this).val(raw);
        });
    });
});
</script>

@stack('scripts')
</body>
</html>
