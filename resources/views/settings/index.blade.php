@extends('layouts.app')

@section('title', 'Settings')

@push('styles')
<style>
    .settings-nav { background:#fff;border-radius:12px;border:1px solid #f0f0f0;padding:8px; }
    .settings-nav-item { display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:8px;color:#6b7280;font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;transition:.15s; }
    .settings-nav-item:hover { background:#f9fafb;color:#374151; }
    .settings-nav-item.active { background:#eff6ff;color:#2563eb;font-weight:600; }
    .settings-nav-item i { width:18px;text-align:center;font-size:13px; }

    .settings-card { background:#fff;border-radius:12px;border:1px solid #f0f0f0;padding:28px; }
    .settings-section-title { font-size:15px;font-weight:700;color:#0f1d35;margin-bottom:4px; }
    .settings-section-desc { font-size:12px;color:#6b7280;margin-bottom:24px; }
    .settings-divider { border-top:1px solid #f0f0f0;margin:24px 0; }

    .form-label { font-size:13px;font-weight:600;color:#374151; }
    .form-hint { font-size:11px;color:#9ca3af;margin-top:3px; }
    .input-group-text { font-size:13px;background:#f9fafb;border-color:#e5e7eb; }

    .avatar-upload { width:80px;height:80px;border-radius:50%;background:#f3f4f6;border:2px dashed #d1d5db;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:24px;color:#9ca3af;transition:.15s; }
    .avatar-upload:hover { border-color:#3b82f6;background:#eff6ff;color:#3b82f6; }

    .toggle-switch { position:relative;display:inline-block;width:44px;height:24px; }
    .toggle-switch input { opacity:0;width:0;height:0; }
    .toggle-slider { position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#d1d5db;border-radius:24px;transition:.2s; }
    .toggle-slider:before { position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s; }
    input:checked + .toggle-slider { background:#3b82f6; }
    input:checked + .toggle-slider:before { transform:translateX(20px); }

    .notification-row { display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f9fafb; }
    .notification-row:last-child { border-bottom:none; }
    .notification-label { font-size:13px;color:#374151; }
    .notification-desc  { font-size:11px;color:#9ca3af; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#0f1d35">Settings</h4>
        <p class="text-muted mb-0" style="font-size:13px">Konfigurasi sistem CRM Logistic Service</p>
    </div>
</div>

<div class="row g-3">
    <!-- Nav Tabs -->
    <div class="col-md-3">
        <div class="settings-nav">
            <a href="#company"  class="settings-nav-item active" onclick="switchTab(this,'company')"><i class="fas fa-building"></i> Company Profile</a>
            <a href="#general"  class="settings-nav-item" onclick="switchTab(this,'general')"><i class="fas fa-sliders-h"></i> General</a>
            <a href="#notif"    class="settings-nav-item" onclick="switchTab(this,'notif')"><i class="fas fa-bell"></i> Notifications</a>
            <a href="#pipeline" class="settings-nav-item" onclick="switchTab(this,'pipeline')"><i class="fas fa-filter"></i> Pipeline Stages</a>
            <a href="#service"  class="settings-nav-item" onclick="switchTab(this,'service')"><i class="fas fa-truck"></i> Service Types</a>
            <a href="#about"    class="settings-nav-item" onclick="switchTab(this,'about')"><i class="fas fa-info-circle"></i> About</a>
        </div>
    </div>

    <!-- Content Area -->
    <div class="col-md-9">

        <!-- Company Profile -->
        <div class="settings-card" id="tab-company">
            <div class="settings-section-title">Company Profile</div>
            <div class="settings-section-desc">Informasi dasar perusahaan yang tampil di laporan dan dokumen.</div>
            <form action="{{ route('settings.update') }}" method="POST">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-12 d-flex align-items-center gap-4 mb-2">
                        <div class="avatar-upload">
                            <i class="fas fa-camera"></i>
                        </div>
                        <div>
                            <div style="font-size:13px;font-weight:600;color:#374151">Logo Perusahaan</div>
                            <div class="form-hint">PNG, JPG, max 2MB. Rekomendasi 200x200px</div>
                            <button type="button" class="btn btn-light btn-sm mt-2" style="font-size:12px;border-radius:6px;border:1px solid #e5e7eb">Upload Logo</button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Perusahaan</label>
                        <input type="text" name="company_name" class="form-control" value="{{ $settings['company_name'] }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Perusahaan</label>
                        <input type="email" name="company_email" class="form-control" value="{{ $settings['company_email'] }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">No. Telepon</label>
                        <input type="text" name="company_phone" class="form-control" value="{{ $settings['company_phone'] }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Website</label>
                        <input type="text" class="form-control" placeholder="https://example.com">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" rows="2">{{ $settings['company_address'] }}</textarea>
                    </div>
                </div>
                <div class="settings-divider"></div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light btn-sm" style="border-radius:8px;font-size:13px">Reset</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="border-radius:8px;font-size:13px">Simpan Perubahan</button>
                </div>
            </form>
        </div>

        <!-- General -->
        <div class="settings-card d-none" id="tab-general">
            <div class="settings-section-title">General Settings</div>
            <div class="settings-section-desc">Konfigurasi umum aplikasi CRM.</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Currency</label>
                    <select class="form-select">
                        <option {{ $settings['currency'] === 'IDR' ? 'selected' : '' }}>IDR</option>
                        <option>USD</option><option>SGD</option><option>EUR</option>
                    </select>
                    <div class="form-hint">Mata uang default untuk revenue dan deal value</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Timezone</label>
                    <select class="form-select">
                        <option {{ $settings['timezone'] === 'Asia/Jakarta' ? 'selected' : '' }}>Asia/Jakarta (WIB)</option>
                        <option>Asia/Makassar (WITA)</option>
                        <option>Asia/Jayapura (WIT)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Language</label>
                    <select class="form-select">
                        <option selected>Bahasa Indonesia</option>
                        <option>English</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date Format</label>
                    <select class="form-select">
                        <option selected>DD/MM/YYYY</option>
                        <option>MM/DD/YYYY</option>
                        <option>YYYY-MM-DD</option>
                    </select>
                </div>
            </div>
            <div class="settings-divider"></div>
            <button class="btn btn-primary btn-sm" style="border-radius:8px;font-size:13px">Simpan Perubahan</button>
        </div>

        <!-- Notifications -->
        <div class="settings-card d-none" id="tab-notif">
            <div class="settings-section-title">Notification Settings</div>
            <div class="settings-section-desc">Atur kapan dan bagaimana sistem mengirim notifikasi.</div>
            @php
            $notifs = [
                ['label'=>'Activity Overdue','desc'=>'Notifikasi saat activity melewati batas waktu','default'=>true],
                ['label'=>'Lead Baru Masuk','desc'=>'Notifikasi saat ada lead baru ditambahkan','default'=>true],
                ['label'=>'Deal Closed (Won)','desc'=>'Notifikasi saat deal berhasil di-close','default'=>true],
                ['label'=>'Follow Up Reminder','desc'=>'Pengingat H-1 sebelum jadwal follow up','default'=>true],
                ['label'=>'Pipeline Stage Change','desc'=>'Notifikasi saat lead pindah stage','default'=>false],
                ['label'=>'Weekly Summary Report','desc'=>'Laporan ringkasan mingguan via email','default'=>false],
                ['label'=>'Target Warning','desc'=>'Peringatan saat target sales di bawah 50%','default'=>true],
            ];
            @endphp
            @foreach($notifs as $n)
            <div class="notification-row">
                <div>
                    <div class="notification-label">{{ $n['label'] }}</div>
                    <div class="notification-desc">{{ $n['desc'] }}</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" {{ $n['default'] ? 'checked' : '' }}>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            @endforeach
            <div class="settings-divider"></div>
            <button class="btn btn-primary btn-sm" style="border-radius:8px;font-size:13px">Simpan Perubahan</button>
        </div>

        <!-- Pipeline Stages -->
        <div class="settings-card d-none" id="tab-pipeline">
            <div class="settings-section-title">Pipeline Stages</div>
            <div class="settings-section-desc">Konfigurasi tahapan pipeline penjualan.</div>
            @php
            $stages = [
                ['name'=>'Identifying','color'=>'#3b82f6','prob'=>'10%','order'=>1],
                ['name'=>'Approaching','color'=>'#f59e0b','prob'=>'30%','order'=>2],
                ['name'=>'Follow Up','color'=>'#8b5cf6','prob'=>'50%','order'=>3],
                ['name'=>'Closing','color'=>'#f97316','prob'=>'80%','order'=>4],
                ['name'=>'Won','color'=>'#10b981','prob'=>'100%','order'=>5],
            ];
            @endphp
            @foreach($stages as $s)
            <div class="d-flex align-items-center gap-3 mb-3 p-3" style="background:#f9fafb;border-radius:8px;border:1px solid #f0f0f0">
                <div style="width:6px;height:36px;border-radius:3px;background:{{ $s['color'] }};flex-shrink:0"></div>
                <input type="text" class="form-control form-control-sm" value="{{ $s['name'] }}" style="max-width:160px">
                <div class="d-flex align-items-center gap-2">
                    <span style="font-size:12px;color:#6b7280">Probability:</span>
                    <input type="text" class="form-control form-control-sm" value="{{ $s['prob'] }}" style="max-width:70px">
                </div>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <button class="btn btn-sm" style="border:1px solid #e5e7eb;border-radius:6px;font-size:11px;color:#6b7280"><i class="fas fa-arrows-alt-v"></i></button>
                    <button class="btn btn-sm" style="border:1px solid #fecaca;border-radius:6px;font-size:11px;color:#dc2626"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            @endforeach
            <button class="btn btn-light btn-sm mt-2" style="border-radius:8px;font-size:13px;border:1px dashed #d1d5db;width:100%">
                <i class="fas fa-plus me-1"></i> Tambah Stage
            </button>
            <div class="settings-divider"></div>
            <button class="btn btn-primary btn-sm" style="border-radius:8px;font-size:13px">Simpan Perubahan</button>
        </div>

        <!-- Service Types -->
        <div class="settings-card d-none" id="tab-service">
            <div class="settings-section-title">Service Types</div>
            <div class="settings-section-desc">Jenis layanan logistik yang tersedia dalam sistem.</div>
            @php
            $serviceTypes = [
                ['name'=>'Sea Freight Import','icon'=>'ship','color'=>'#3b82f6'],
                ['name'=>'Sea Freight Export','icon'=>'ship','color'=>'#10b981'],
                ['name'=>'Air Freight Import','icon'=>'plane','color'=>'#f59e0b'],
                ['name'=>'Air Freight Export','icon'=>'plane','color'=>'#f97316'],
                ['name'=>'Trucking Domestic','icon'=>'truck','color'=>'#8b5cf6'],
                ['name'=>'Custom Clearance','icon'=>'file-contract','color'=>'#6b7280'],
            ];
            @endphp
            @foreach($serviceTypes as $st)
            <div class="d-flex align-items-center gap-3 mb-2 p-3" style="background:#f9fafb;border-radius:8px;border:1px solid #f0f0f0">
                <div style="width:32px;height:32px;border-radius:8px;background:{{ $st['color'] }}20;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="fas fa-{{ $st['icon'] }}" style="color:{{ $st['color'] }};font-size:13px"></i>
                </div>
                <input type="text" class="form-control form-control-sm" value="{{ $st['name'] }}" style="max-width:240px">
                <button class="btn btn-sm ms-auto" style="border:1px solid #fecaca;border-radius:6px;font-size:11px;color:#dc2626"><i class="fas fa-trash"></i></button>
            </div>
            @endforeach
            <button class="btn btn-light btn-sm mt-2" style="border-radius:8px;font-size:13px;border:1px dashed #d1d5db;width:100%">
                <i class="fas fa-plus me-1"></i> Tambah Service Type
            </button>
            <div class="settings-divider"></div>
            <button class="btn btn-primary btn-sm" style="border-radius:8px;font-size:13px">Simpan Perubahan</button>
        </div>

        <!-- About -->
        <div class="settings-card d-none" id="tab-about">
            <div class="settings-section-title">About</div>
            <div class="settings-section-desc">Informasi versi sistem CRM.</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <div style="background:#f9fafb;border-radius:8px;padding:16px">
                        <div style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Versi Aplikasi</div>
                        <div style="font-size:20px;font-weight:700;color:#0f1d35;margin-top:4px">v1.0.0</div>
                        <div style="font-size:12px;color:#6b7280">CRM Logistic Service</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div style="background:#f9fafb;border-radius:8px;padding:16px">
                        <div style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Framework</div>
                        <div style="font-size:20px;font-weight:700;color:#0f1d35;margin-top:4px">Laravel 12</div>
                        <div style="font-size:12px;color:#6b7280">PHP 8.2 · Bootstrap 5.3</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
function switchTab(el, tab) {
    event.preventDefault();
    document.querySelectorAll('.settings-nav-item').forEach(a => a.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('[id^="tab-"]').forEach(d => d.classList.add('d-none'));
    document.getElementById('tab-' + tab).classList.remove('d-none');
}
</script>
@endpush
