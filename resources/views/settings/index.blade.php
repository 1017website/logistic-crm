@extends('layouts.app')
@section('title', 'Settings')
@section('page-title', 'Settings')
@section('page-subtitle', 'Konfigurasi sistem Logistic CRM')

@push('styles')
    <style>
        .settings-nav {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #f0f0f0;
            padding: 8px;
        }

        .sn-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 8px;
            color: #6b7280;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: .15s;
        }

        .sn-item:hover {
            background: #f9fafb;
            color: #374151;
        }

        .sn-item.active {
            background: #eff6ff;
            color: #2563eb;
            font-weight: 600;
        }

        .sn-item i {
            width: 18px;
            text-align: center;
            font-size: 13px;
        }

        .settings-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #f0f0f0;
            padding: 28px;
        }

        .s-title {
            font-size: 15px;
            font-weight: 700;
            color: #0f1d35;
            margin-bottom: 4px;
        }

        .s-desc {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 20px;
        }

        .s-divider {
            border-top: 1px solid #f0f0f0;
            margin: 20px 0;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #d1d5db;
            border-radius: 24px;
            transition: .2s;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: .2s;
        }

        input:checked+.toggle-slider {
            background: #3b82f6;
        }

        input:checked+.toggle-slider:before {
            transform: translateX(20px);
        }

        .notif-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f9fafb;
        }

        .notif-row:last-child {
            border-bottom: none;
        }
    </style>
@endpush

@section('content')
    <div class="row g-3">
        {{-- Nav --}}
        <div class="col-md-3">
            <div class="settings-nav">
                <a href="#" class="sn-item active" onclick="showSection('company',this);return false"><i
                        class="fas fa-building"></i> Company Profile</a>
                <a href="#" class="sn-item" onclick="showSection('general',this);return false"><i
                        class="fas fa-sliders-h"></i> General</a>
                <a href="#" class="sn-item" onclick="showSection('notif',this);return false"><i class="fas fa-bell"></i>
                    Notifications</a>
                <a href="#" class="sn-item" onclick="showSection('pipeline',this);return false"><i
                        class="fas fa-filter"></i> Pipeline Stages</a>
                <a href="#" class="sn-item" onclick="showSection('about',this);return false"><i
                        class="fas fa-info-circle"></i> About</a>
            </div>
        </div>

        {{-- Content --}}
        <div class="col-md-9">

            {{-- Company Profile --}}
            <div class="settings-card" id="sec-company">
                <div class="s-title">Company Profile</div>
                <div class="s-desc">Informasi dasar perusahaan yang tampil di laporan dan dokumen.</div>
                <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Perusahaan <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control"
                                value="{{ $settings['company_name'] ?? '' }}" required>
                            <div style="font-size:11px;color:#6b7280;margin-top:4px">Ditampilkan di sidebar dan browser tab
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Perusahaan</label>
                            <input type="email" name="company_email" class="form-control"
                                value="{{ $settings['company_email'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No. Telepon</label>
                            <input type="text" name="company_phone" class="form-control"
                                value="{{ $settings['company_phone'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Website</label>
                            <input type="text" name="company_website" class="form-control"
                                value="{{ $settings['company_website'] ?? '' }}" placeholder="https://example.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alamat</label>
                            <textarea name="company_address" class="form-control"
                                rows="2">{{ $settings['company_address'] ?? '' }}</textarea>
                        </div>
                    </div>

                    {{-- Branding --}}
                    <div class="s-divider"></div>
                    <div class="s-title" style="margin-bottom:16px">Branding</div>
                    <div class="row g-4">
                        {{-- Logo --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Logo Sidebar</label>
                            <div style="font-size:11px;color:#6b7280;margin-bottom:8px">Ditampilkan di
                                <strong>sidebar</strong> (full logo, tanpa teks). Format: PNG, WebP, SVG. Maks 2MB. Gunakan
                                format landscape atau square.</div>
                            @if(!empty($settings['company_logo']))
                                <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded"
                                    style="background:#f8f9fa;border:1px solid #e5e7eb">
                                    <img src="{{ Storage::url($settings['company_logo']) }}" alt="Logo"
                                        style="height:48px;object-fit:contain;border-radius:6px">
                                    <div>
                                        <div style="font-size:12px;font-weight:600;color:#374151">Logo aktif</div>
                                        <button type="button" class="btn btn-sm btn-outline-danger mt-1"
                                            style="font-size:11px;padding:2px 8px" onclick="deleteImage('logo')">
                                            <i class="fas fa-trash me-1"></i> Hapus Logo
                                        </button>
                                    </div>
                                </div>
                            @endif
                            <div class="upload-area" id="logoUploadArea"
                                onclick="document.getElementById('logoInput').click()"
                                style="border:2px dashed #cbd5e1;border-radius:8px;padding:24px;text-align:center;cursor:pointer;transition:all .2s"
                                onmouseover="this.style.borderColor='#2563eb'"
                                onmouseout="this.style.borderColor='#cbd5e1'">
                                <i class="fas fa-image" style="font-size:1.5rem;color:#9ca3af"></i>
                                <div style="font-size:12px;color:#6b7280;margin-top:8px">Klik untuk upload logo baru</div>
                                <div id="logoFileName" style="font-size:11px;color:#2563eb;margin-top:4px"></div>
                            </div>
                            <input type="file" id="logoInput" name="company_logo" accept="image/*" style="display:none"
                                onchange="previewFile(this,'logoFileName','logoPreview')">
                            <img id="logoPreview" src="" alt=""
                                style="display:none;margin-top:8px;max-height:60px;border-radius:6px;border:1px solid #e5e7eb">
                        </div>

                        {{-- Logo Login --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Logo Halaman Login</label>
                            <div style="font-size:11px;color:#6b7280;margin-bottom:8px">Ditampilkan di <strong>halaman
                                    login</strong> (kiri atas, lebih besar). Bisa berbeda dari logo sidebar. Format: PNG,
                                WebP, SVG. Maks 2MB.</div>
                            @if(!empty($settings['company_login_logo']))
                                <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded"
                                    style="background:#f8f9fa;border:1px solid #e5e7eb">
                                    <img src="{{ Storage::url($settings['company_login_logo']) }}" alt="Logo Login"
                                        style="max-height:48px;max-width:120px;object-fit:contain;border-radius:6px">
                                    <div>
                                        <div style="font-size:12px;font-weight:600;color:#374151">Logo login aktif</div>
                                        <button type="button" class="btn btn-sm btn-outline-danger mt-1"
                                            style="font-size:11px;padding:2px 8px" onclick="deleteImage('login_logo')">
                                            <i class="fas fa-trash me-1"></i> Hapus
                                        </button>
                                    </div>
                                </div>
                            @endif
                            <div class="upload-area" onclick="document.getElementById('loginLogoInput').click()"
                                style="border:2px dashed #cbd5e1;border-radius:8px;padding:24px;text-align:center;cursor:pointer;transition:all .2s"
                                onmouseover="this.style.borderColor='#2563eb'"
                                onmouseout="this.style.borderColor='#cbd5e1'">
                                <i class="fas fa-sign-in-alt" style="font-size:1.5rem;color:#9ca3af"></i>
                                <div style="font-size:12px;color:#6b7280;margin-top:8px">Klik untuk upload logo login</div>
                                <div id="loginLogoFileName" style="font-size:11px;color:#2563eb;margin-top:4px"></div>
                            </div>
                            <input type="file" id="loginLogoInput" name="company_login_logo" accept="image/*"
                                style="display:none" onchange="previewFile(this,'loginLogoFileName','loginLogoPreview')">
                            <img id="loginLogoPreview" src="" alt=""
                                style="display:none;margin-top:8px;max-height:48px;border-radius:6px;border:1px solid #e5e7eb">
                        </div>

                        {{-- Favicon --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Favicon</label>
                            <div style="font-size:11px;color:#6b7280;margin-bottom:8px">Ikon di browser tab. Format: PNG,
                                ICO, JPG. Maks 512KB. Ideal: 32×32px atau 64×64px.</div>
                            @if(!empty($settings['company_favicon']))
                                <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded"
                                    style="background:#f8f9fa;border:1px solid #e5e7eb">
                                    <img src="{{ Storage::url($settings['company_favicon']) }}" alt="Favicon"
                                        style="width:32px;height:32px;object-fit:contain">
                                    <div>
                                        <div style="font-size:12px;font-weight:600;color:#374151">Favicon aktif</div>
                                        <button type="button" class="btn btn-sm btn-outline-danger mt-1"
                                            style="font-size:11px;padding:2px 8px" onclick="deleteImage('favicon')">
                                            <i class="fas fa-trash me-1"></i> Hapus Favicon
                                        </button>
                                    </div>
                                </div>
                            @endif
                            <div class="upload-area" id="faviconUploadArea"
                                onclick="document.getElementById('faviconInput').click()"
                                style="border:2px dashed #cbd5e1;border-radius:8px;padding:24px;text-align:center;cursor:pointer;transition:all .2s"
                                onmouseover="this.style.borderColor='#2563eb'"
                                onmouseout="this.style.borderColor='#cbd5e1'">
                                <i class="fas fa-star" style="font-size:1.5rem;color:#9ca3af"></i>
                                <div style="font-size:12px;color:#6b7280;margin-top:8px">Klik untuk upload favicon baru
                                </div>
                                <div id="faviconFileName" style="font-size:11px;color:#2563eb;margin-top:4px"></div>
                            </div>
                            <input type="file" id="faviconInput" name="company_favicon" accept="image/*,.ico"
                                style="display:none" onchange="previewFile(this,'faviconFileName','faviconPreview')">
                            <img id="faviconPreview" src="" alt=""
                                style="display:none;margin-top:8px;width:32px;height:32px;object-fit:contain;border:1px solid #e5e7eb">
                        </div>
                    </div>

                    <div class="s-divider"></div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

            <script>
                function previewFile(input, fileNameId, previewId) {
                    const file = input.files[0];
                    if (!file) return;
                    document.getElementById(fileNameId).textContent = file.name;
                    const reader = new FileReader();
                    reader.onload = e => {
                        const preview = document.getElementById(previewId);
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }

                function deleteImage(type) {
                    const labels = { logo: 'logo sidebar', login_logo: 'logo login', favicon: 'favicon' };
                    if (!confirm('Hapus ' + (labels[type] || type) + '?')) return;
                    document.getElementById('deleteImageType').value = type;
                    document.getElementById('deleteImageForm').submit();
                }
            </script>

            {{-- Form hapus image — di luar form utama agar tidak nested --}}
            <form id="deleteImageForm" method="POST" action="{{ route('settings.delete-image') }}" style="display:none">
                @csrf
                <input type="hidden" name="type" id="deleteImageType">
            </form>


            {{-- General --}}
            <div class="settings-card d-none" id="sec-general">
                <div class="s-title">General Settings</div>
                <div class="s-desc">Konfigurasi umum aplikasi CRM.</div>
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf @method('PUT')
                    {{-- Hidden fields untuk company agar tidak ter-reset --}}
                    <input type="hidden" name="company_name" value="{{ $settings['company_name'] ?? '' }}">
                    <input type="hidden" name="company_email" value="{{ $settings['company_email'] ?? '' }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Currency</label>
                            <select name="currency" class="form-select">
                                @foreach(['IDR', 'USD', 'SGD', 'EUR'] as $c)
                                    <option value="{{ $c }}" @selected(($settings['currency'] ?? 'IDR') == $c)>{{ $c }}</option>
                                @endforeach
                            </select>
                            <div style="font-size:11px;color:#9ca3af;margin-top:3px">Mata uang default untuk revenue dan
                                deal value</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Timezone</label>
                            <select name="timezone" class="form-select">
                                @foreach(['Asia/Jakarta' => 'WIB (UTC+7)', 'Asia/Makassar' => 'WITA (UTC+8)', 'Asia/Jayapura' => 'WIT (UTC+9)'] as $tz => $label)
                                    <option value="{{ $tz }}" @selected(($settings['timezone'] ?? 'Asia/Jakarta') == $tz)>
                                        {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Language</label>
                            <select name="language" class="form-select">
                                <option value="id" @selected(($settings['language'] ?? 'id') == 'id')>Bahasa Indonesia</option>
                                <option value="en" @selected(($settings['language'] ?? 'id') == 'en')>English</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date Format</label>
                            <select name="date_format" class="form-select">
                                @foreach(['DD/MM/YYYY', 'MM/DD/YYYY', 'YYYY-MM-DD'] as $fmt)
                                    <option @selected(($settings['date_format'] ?? 'DD/MM/YYYY') == $fmt)>{{ $fmt }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="s-divider"></div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>
                            Simpan</button>
                    </div>
                </form>
            </div>

            {{-- Notifications --}}
            <div class="settings-card d-none" id="sec-notif">
                <div class="s-title">Notification Settings</div>
                <div class="s-desc">Atur kapan sistem mengirim notifikasi.</div>
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf @method('PUT')
                    <input type="hidden" name="company_name" value="{{ $settings['company_name'] ?? '' }}">
                    <input type="hidden" name="company_email" value="{{ $settings['company_email'] ?? '' }}">
                    @php
                        $notifs = [
                            ['key' => 'notif_overdue', 'label' => 'Activity Overdue', 'desc' => 'Notifikasi saat activity melewati batas waktu'],
                            ['key' => 'notif_new_lead', 'label' => 'Lead Baru Masuk', 'desc' => 'Notifikasi saat ada lead baru ditambahkan'],
                            ['key' => 'notif_deal_won', 'label' => 'Deal Closed (Won)', 'desc' => 'Notifikasi saat deal berhasil di-close'],
                            ['key' => 'notif_followup', 'label' => 'Follow Up Reminder', 'desc' => 'Pengingat H-1 sebelum jadwal follow up'],
                            ['key' => 'notif_stage', 'label' => 'Pipeline Stage Change', 'desc' => 'Notifikasi saat lead pindah stage'],
                            ['key' => 'notif_weekly', 'label' => 'Weekly Summary Report', 'desc' => 'Laporan ringkasan mingguan via email'],
                            ['key' => 'notif_target', 'label' => 'Target Warning', 'desc' => 'Peringatan saat target sales di bawah 50%'],
                        ];
                    @endphp
                    @foreach($notifs as $n)
                        <div class="notif-row">
                            <div>
                                <div style="font-size:13px;color:#374151;font-weight:500">{{ $n['label'] }}</div>
                                <div style="font-size:11px;color:#9ca3af">{{ $n['desc'] }}</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="{{ $n['key'] }}" value="1" {{ ($settings[$n['key']] ?? '0') == '1' ? 'checked' : '' }}>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    @endforeach
                    <div class="s-divider"></div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>
                            Simpan</button>
                    </div>
                </form>
            </div>

            {{-- Pipeline Stages (info only) --}}
            <div class="settings-card d-none" id="sec-pipeline">
                <div class="s-title">Pipeline Stages</div>
                <div class="s-desc">Tahapan pipeline penjualan yang aktif di sistem.</div>
                @php
                    $stages = [
                        ['Identifying', '#3b82f6', '10%'],
                        ['Approaching', '#10b981', '30%'],
                        ['Follow Up', '#f59e0b', '50%'],
                        ['Closing', '#f97316', '80%'],
                        ['Won', '#16a34a', '100%'],
                    ];
                @endphp
                @foreach($stages as $s)
                    <div class="d-flex align-items-center gap-3 mb-3 p-3"
                        style="background:#f9fafb;border-radius:8px;border:1px solid #f0f0f0">
                        <div style="width:6px;height:36px;border-radius:3px;background:{{ $s[1] }};flex-shrink:0"></div>
                        <div style="flex:1">
                            <div style="font-size:13px;font-weight:600;color:#374151">{{ $s[0] }}</div>
                            <div style="font-size:11px;color:#9ca3af">Default probability: {{ $s[2] }}</div>
                        </div>
                        <span
                            style="font-size:11px;padding:2px 8px;border-radius:20px;background:#f3f4f6;color:#6b7280">Default</span>
                    </div>
                @endforeach
                <div class="alert alert-info" style="font-size:12px;border-radius:8px">
                    <i class="fas fa-info-circle me-1"></i>
                    Pipeline stages saat ini bersifat default. Kustomisasi stages akan tersedia di versi berikutnya.
                </div>
            </div>

            {{-- About --}}
            <div class="settings-card d-none" id="sec-about">
                <div class="s-title">About</div>
                <div class="s-desc">Informasi versi sistem CRM.</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div style="background:#f9fafb;border-radius:8px;padding:16px">
                            <div
                                style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.5px">
                                Versi Aplikasi</div>
                            <div style="font-size:22px;font-weight:700;color:#0f1d35;margin-top:4px">v1.0.0</div>
                            <div style="font-size:12px;color:#6b7280">Logistic CRM</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div style="background:#f9fafb;border-radius:8px;padding:16px">
                            <div
                                style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.5px">
                                Tech Stack</div>
                            <div style="font-size:22px;font-weight:700;color:#0f1d35;margin-top:4px">Laravel 12</div>
                            <div style="font-size:12px;color:#6b7280">PHP 8.2 · Bootstrap 5.3 · MySQL</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div style="background:#f9fafb;border-radius:8px;padding:16px">
                            <div
                                style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.5px">
                                Logged In As</div>
                            <div style="font-size:16px;font-weight:700;color:#0f1d35;margin-top:4px">
                                {{ auth()->user()->name }}</div>
                            <div style="font-size:12px;color:#6b7280">{{ auth()->user()->email }} ·
                                {{ auth()->user()->role }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div style="background:#f9fafb;border-radius:8px;padding:16px">
                            <div
                                style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.5px">
                                Company</div>
                            <div style="font-size:16px;font-weight:700;color:#0f1d35;margin-top:4px">
                                {{ $settings['company_name'] ?? '-' }}</div>
                            <div style="font-size:12px;color:#6b7280">{{ $settings['company_email'] ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function showSection(sec, el) {
            document.querySelectorAll('.sn-item').forEach(a => a.classList.remove('active'));
            el.classList.add('active');
            ['company', 'general', 'notif', 'pipeline', 'about'].forEach(s => {
                const d = document.getElementById('sec-' + s);
                if (d) d.classList.toggle('d-none', s !== sec);
            });
        }
    </script>
@endpush