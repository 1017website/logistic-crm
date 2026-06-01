<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>403 – Akses Ditolak</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f3f4f8; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>
<div class="text-center" style="max-width:420px;padding:20px">
    <div style="width:80px;height:80px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
        <i class="fas fa-lock" style="font-size:32px;color:#dc2626"></i>
    </div>
    <h4 style="font-weight:700;color:#111827;margin-bottom:8px">Akses Ditolak</h4>
    <p style="color:#6b7280;font-size:14px;margin-bottom:24px">
        {{ $exception->getMessage() ?: 'Anda tidak memiliki izin untuk mengakses halaman ini.' }}
    </p>
    <a href="{{ url()->previous() }}" class="btn btn-light btn-sm me-2" style="border-radius:8px;border:1px solid #e5e7eb;font-size:13px">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>
    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-size:13px">
        <i class="fas fa-home me-1"></i> Dashboard
    </a>
</div>
</body>
</html>
