<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – {{ \App\Models\Setting::get('company_name', 'Chemical CRM') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; box-sizing: border-box; margin: 0; padding: 0; }
        body { min-height: 100vh; display: flex; background: #0f1d35; }

        /* ── LEFT PANEL ── */
        .left-panel {
            flex: 1; display: flex; flex-direction: column;
            justify-content: space-between; padding: 48px;
            position: relative; overflow: hidden;
        }
        .left-panel::before {
            content: ''; position: absolute;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(37,99,235,0.3) 0%, transparent 70%);
            top: -100px; left: -100px; border-radius: 50%;
            animation: float1 8s ease-in-out infinite;
        }
        .left-panel::after {
            content: ''; position: absolute;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(16,185,129,0.2) 0%, transparent 70%);
            bottom: -50px; right: -50px; border-radius: 50%;
            animation: float2 10s ease-in-out infinite;
        }
        @keyframes float1 { 0%,100%{transform:translate(0,0)} 50%{transform:translate(30px,20px)} }
        @keyframes float2 { 0%,100%{transform:translate(0,0)} 50%{transform:translate(-20px,-30px)} }

        .left-brand { position: relative; z-index: 2; }
        .brand-logo { width:44px;height:44px;background:#2563eb;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:16px; }
        .brand-name { font-size:18px;font-weight:700;color:#fff;letter-spacing:.5px; }
        .brand-sub  { font-size:13px;color:rgba(255,255,255,.45);margin-top:2px; }

        .left-hero { position: relative; z-index: 2; }
        .left-hero h1 { font-size:38px;font-weight:800;color:#fff;line-height:1.2;margin-bottom:16px; }
        .left-hero h1 span { color: #3b82f6; }
        .left-hero p { font-size:15px;color:rgba(255,255,255,.5);line-height:1.7;max-width:380px; }

        .stats-row { display:flex;gap:24px;margin-top:36px;position:relative;z-index:2; }
        .stat-num   { font-size:26px;font-weight:800;color:#fff; }
        .stat-label { font-size:12px;color:rgba(255,255,255,.4);margin-top:2px; }

        .feature-list { display:flex;flex-direction:column;gap:12px;margin-top:40px;position:relative;z-index:2; }
        .feature-item { display:flex;align-items:center;gap:12px; }
        .feature-icon { width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0; }
        .feature-text { font-size:13px;color:rgba(255,255,255,.6); }
        .left-footer { position:relative;z-index:2;font-size:12px;color:rgba(255,255,255,.25); }

        /* ── RIGHT PANEL ── */
        .right-panel { width:480px;background:#f8fafc;display:flex;align-items:center;justify-content:center;padding:40px; }
        .login-box { width:100%;max-width:380px; }
        .login-heading    { font-size:26px;font-weight:800;color:#0f1d35;margin-bottom:6px; }
        .login-subheading { font-size:14px;color:#6b7280;margin-bottom:32px; }

        .field-label { font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;display:block;letter-spacing:.02em; }
        .field-wrap  { position:relative;margin-bottom:20px; }
        .field-icon  { position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:14px;pointer-events:none; }
        .field-wrap input {
            width:100%;padding:12px 14px 12px 42px;
            border:1.5px solid #e5e7eb;border-radius:10px;
            font-size:14px;color:#111827;background:#fff;
            outline:none;transition:border-color .2s,box-shadow .2s;
        }
        .field-wrap input:focus { border-color:#2563eb;box-shadow:0 0 0 4px rgba(37,99,235,.08); }
        .field-wrap input::placeholder { color:#c4c9d4; }
        .toggle-pass { position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:#9ca3af;font-size:14px;background:none;border:none;padding:0; }
        .toggle-pass:hover { color:#6b7280; }

        .remember-row { display:flex;align-items:center;margin-bottom:24px; }
        .remember-label { display:flex;align-items:center;gap:8px;font-size:13px;color:#6b7280;cursor:pointer; }
        .remember-label input[type=checkbox] { width:15px;height:15px;accent-color:#2563eb;cursor:pointer; }

        .btn-login {
            width:100%;padding:13px;background:#2563eb;color:#fff;
            border:none;border-radius:10px;font-size:14px;font-weight:600;
            cursor:pointer;transition:.2s;
            display:flex;align-items:center;justify-content:center;gap:8px;
        }
        .btn-login:hover { background:#1d4ed8;transform:translateY(-1px);box-shadow:0 4px 16px rgba(37,99,235,.35); }
        .btn-login:active { transform:translateY(0); }
        .btn-login .spinner { display:none; }
        .btn-login.loading .spinner { display:inline-block; }

        .divider { display:flex;align-items:center;gap:12px;margin:24px 0;font-size:12px;color:#d1d5db; }
        .divider::before,.divider::after { content:'';flex:1;height:1px;background:#e5e7eb; }

        .demo-box   { background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;padding:14px 16px; }
        .demo-title { font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px; }
        .demo-item  { display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:8px;cursor:pointer;transition:background .15s;margin-bottom:4px; }
        .demo-item:last-child { margin-bottom:0; }
        .demo-item:hover { background:#f9fafb; }
        .demo-badge { font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;flex-shrink:0;min-width:58px;text-align:center; }
        .badge-admin   { background:#fee2e2;color:#dc2626; }
        .badge-manager { background:#faf5ff;color:#7c3aed; }
        .badge-sales   { background:#eff6ff;color:#2563eb; }
        .demo-email { font-size:12px;color:#374151;flex:1; }
        .demo-copy  { font-size:10px;color:#9ca3af;background:#f3f4f6;border-radius:4px;padding:2px 6px; }

        .alert-box     { display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:10px;margin-bottom:20px;font-size:13px; }
        .alert-error   { background:#fef2f2;border:1px solid #fecaca;color:#dc2626; }
        .alert-success { background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a; }

        @media (max-width:900px) { .left-panel { display:none; } .right-panel { width:100%; } }
    </style>
</head>
<body>

<!-- LEFT -->
<div class="left-panel">
    <div class="left-brand">
        @php
            $loginLogo = \App\Models\Setting::get('company_login_logo');
            $loginName = \App\Models\Setting::get('company_name', 'CHEMICAL CRM');
        @endphp
        @if($loginLogo)
            <img src="{{ Storage::url($loginLogo) }}" alt="{{ $loginName }}"
                 style="max-height:200px;max-width:480px;width:100%;object-fit:contain;object-position:left center;margin-bottom:24px;display:block">
        @else
            <div class="brand-logo"><i class="fas fa-flask" style="color:#fff;font-size:20px"></i></div>
            <div class="brand-name">{{ $loginName }}</div>
        @endif
        <div class="brand-sub">Customer Relationship Management</div>
    </div>

    <div class="left-hero">
        <h1>Kelola Sales &<br><span>Pipeline</span> dengan<br>Lebih Efisien</h1>
        <p>Platform CRM khusus untuk bisnis trading chemical — dari tracking leads, pipeline penjualan, hingga analitik revenue dan profit dalam satu dashboard.</p>
        <div class="stats-row">
            <div><div class="stat-num">98%</div><div class="stat-label">Uptime System</div></div>
            <div><div class="stat-num">3x</div><div class="stat-label">Closing Lebih Cepat</div></div>
            <div><div class="stat-num">5+</div><div class="stat-label">Modul Terintegrasi</div></div>
        </div>
        <div class="feature-list">
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-filter" style="color:#3b82f6;font-size:13px"></i></div>
                <span class="feature-text">Pipeline visual dengan kanban board real-time</span>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-chart-bar" style="color:#10b981;font-size:13px"></i></div>
                <span class="feature-text">Analytics dan laporan performa sales otomatis</span>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-bell" style="color:#f59e0b;font-size:13px"></i></div>
                <span class="feature-text">Reminder follow up dan task management</span>
            </div>
        </div>
    </div>

    <div class="left-footer">&copy; {{ date('Y') }} {{ \App\Models\Setting::get('company_name', 'Chemical CRM') }}. All rights reserved.</div>
</div>

<!-- RIGHT -->
<div class="right-panel">
    <div class="login-box">
        <div class="login-heading">Selamat Datang</div>
        <div class="login-subheading">Masuk ke akun Anda untuk melanjutkan</div>

        @if($errors->any())
        <div class="alert-box alert-error">
            <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
        </div>
        @endif
        @if(session('success'))
        <div class="alert-box alert-success">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
        @endif

        <form action="{{ route('login') }}" method="POST" id="loginForm">
            @csrf
            <div>
                <label class="field-label">Alamat Email</label>
                <div class="field-wrap">
                    <i class="fas fa-envelope field-icon"></i>
                    <input type="email" name="email" id="emailInput"
                        value="{{ old('email') }}" placeholder="email@perusahaan.com" required autofocus>
                </div>
            </div>
            <div>
                <label class="field-label">Password</label>
                <div class="field-wrap">
                    <i class="fas fa-lock field-icon"></i>
                    <input type="password" name="password" id="passwordInput"
                        placeholder="Masukkan password" required>
                    <button type="button" class="toggle-pass" onclick="togglePass()">
                        <i class="fas fa-eye" id="passIcon"></i>
                    </button>
                </div>
            </div>
            <div class="remember-row">
                <label class="remember-label">
                    <input type="checkbox" name="remember"> Ingat saya selama 30 hari
                </label>
            </div>
            <button type="submit" class="btn-login" id="loginBtn">
                <i class="fas fa-spinner fa-spin spinner"></i>
                <span class="btn-text"><i class="fas fa-sign-in-alt"></i> &nbsp;Masuk ke Dashboard</span>
            </button>
        </form>

        <div class="divider">atau gunakan demo credentials</div>

        <div class="demo-box">
            <div class="demo-title">Demo Credentials — klik untuk autofill</div>
            <div class="demo-item" onclick="fillCreds(this, 'admin@crm.com')">
                <span class="demo-badge badge-admin">Admin</span>
                <span class="demo-email">admin@crm.com</span>
                <span class="demo-copy">password</span>
            </div>
            <div class="demo-item" onclick="fillCreds(this, 'manager@crm.com')">
                <span class="demo-badge badge-manager">Manager</span>
                <span class="demo-email">manager@crm.com</span>
                <span class="demo-copy">password</span>
            </div>
            <div class="demo-item" onclick="fillCreds(this, 'sales@crm.com')">
                <span class="demo-badge badge-sales">Sales</span>
                <span class="demo-email">sales@crm.com</span>
                <span class="demo-copy">password</span>
            </div>
        </div>
    </div>
</div>

<script>
function togglePass() {
    const input = document.getElementById('passwordInput');
    const icon  = document.getElementById('passIcon');
    input.type = input.type === 'password' ? 'text' : 'password';
    icon.className = input.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
function fillCreds(el, email) {
    document.getElementById('emailInput').value = email;
    document.getElementById('passwordInput').value = 'password';
    el.style.background = '#eff6ff';
    setTimeout(() => el.style.background = '', 500);
}
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.classList.add('loading');
    btn.disabled = true;
});
</script>
</body>
</html>