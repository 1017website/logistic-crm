<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – CRM Logistic Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f3f4f8; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-wrap { width: 100%; max-width: 420px; padding: 20px; }
        .login-card { background: #fff; border-radius: 16px; padding: 40px 36px; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .brand-icon { width: 52px; height: 52px; background: #2563eb; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
        .login-title { font-size: 20px; font-weight: 700; color: #0f1d35; text-align: center; margin-bottom: 4px; }
        .login-sub   { font-size: 13px; color: #6b7280; text-align: center; margin-bottom: 28px; }
        .form-label  { font-size: 13px; font-weight: 600; color: #374151; }
        .form-control { font-size: 13px; border-color: #e5e7eb; border-radius: 8px; padding: 10px 14px; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
        .input-group-text { background: #fff; border-color: #e5e7eb; cursor: pointer; border-radius: 0 8px 8px 0; }
        .btn-login { background: #2563eb; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; padding: 11px; width: 100%; color: #fff; transition: .2s; }
        .btn-login:hover { background: #1d4ed8; }
        .role-badge { display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600; margin: 2px; }
        .role-admin   { background: #fee2e2; color: #dc2626; }
        .role-manager { background: #faf5ff; color: #7c3aed; }
        .role-sales   { background: #eff6ff; color: #2563eb; }
        .demo-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; margin-top: 20px; }
        .demo-box-title { font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .demo-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; font-size: 12px; }
        .demo-row:last-child { margin-bottom: 0; }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <!-- Brand -->
        <div class="brand-icon">
            <i class="fas fa-truck-fast text-white" style="font-size: 22px"></i>
        </div>
        <div class="login-title">CRM Logistic Service</div>
        <div class="login-sub">Masuk ke dashboard manajemen penjualan</div>

        <!-- Alert Error -->
        @if($errors->any())
        <div class="alert alert-danger py-2 mb-3" style="font-size:13px;border-radius:8px">
            <i class="fas fa-exclamation-circle me-1"></i>
            {{ $errors->first() }}
        </div>
        @endif

        @if(session('success'))
        <div class="alert alert-success py-2 mb-3" style="font-size:13px;border-radius:8px">
            {{ session('success') }}
        </div>
        @endif

        <!-- Form -->
        <form action="{{ route('login') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}"
                    placeholder="email@perusahaan.com" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" name="password" class="form-control" id="passwordInput"
                        placeholder="Password" required style="border-right:0;border-radius:8px 0 0 8px">
                    <span class="input-group-text" onclick="togglePass()">
                        <i class="fas fa-eye" id="passIcon" style="font-size:13px;color:#9ca3af"></i>
                    </span>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label" for="remember" style="font-size:13px;color:#6b7280">Ingat saya</label>
                </div>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Masuk
            </button>
        </form>

        <!-- Demo Credentials -->
        <div class="demo-box">
            <div class="demo-box-title">Demo Credentials</div>
            <div class="demo-row">
                <span><span class="role-badge role-admin">Admin</span> admin@crm.com</span>
                <span style="color:#6b7280">password: <strong>password</strong></span>
            </div>
            <div class="demo-row">
                <span><span class="role-badge role-manager">Manager</span> manager@crm.com</span>
                <span style="color:#6b7280">password: <strong>password</strong></span>
            </div>
            <div class="demo-row">
                <span><span class="role-badge role-sales">Sales</span> sales@crm.com</span>
                <span style="color:#6b7280">password: <strong>password</strong></span>
            </div>
        </div>
    </div>

    <p class="text-center mt-4" style="font-size:12px;color:#9ca3af">
        &copy; {{ date('Y') }} CRM Logistic Service. All rights reserved.
    </p>
</div>

<script>
function togglePass() {
    const input = document.getElementById('passwordInput');
    const icon  = document.getElementById('passIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}
</script>
</body>
</html>
