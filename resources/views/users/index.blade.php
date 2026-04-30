@extends('layouts.app')

@section('title', 'Users')

@push('styles')
<style>
    .kpi-card { background:#fff;border-radius:12px;border:1px solid #f0f0f0;padding:18px 20px; }
    .kpi-icon { width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0; }
    .kpi-value { font-size:22px;font-weight:700;color:#0f1d35; }
    .kpi-label { font-size:12px;color:#6b7280; }

    .user-table { font-size:13px; }
    .user-table th { font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;padding:10px 14px;border-bottom:2px solid #f0f0f0;background:#f9fafb; }
    .user-table td { padding:12px 14px;border-bottom:1px solid #f9fafb;vertical-align:middle; }
    .user-table tbody tr:hover td { background:#fafbfc; }

    .user-avatar-lg { width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0; }
    .badge-role { font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600; }
    .role-manager { background:#faf5ff;color:#7c3aed; }
    .role-sales { background:#eff6ff;color:#2563eb; }
    .role-admin { background:#fff7ed;color:#c2410c; }

    .badge-status { font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600; }
    .status-active { background:#dcfce7;color:#16a34a; }
    .status-nonactive { background:#fee2e2;color:#dc2626; }

    .progress-target { height:6px;border-radius:3px;background:#e5e7eb;overflow:hidden; }
    .progress-fill { height:100%;border-radius:3px; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#0f1d35">Users Management</h4>
        <p class="text-muted mb-0" style="font-size:13px">Kelola data sales dan admin dalam sistem CRM</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal" style="border-radius:8px;font-size:13px">
        <i class="fas fa-plus me-1"></i> Add User
    </button>
</div>

<!-- KPI -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card d-flex align-items-center gap-3">
            <div class="kpi-icon" style="background:#eff6ff"><i class="fas fa-users" style="color:#3b82f6"></i></div>
            <div>
                <div class="kpi-label">Total Users</div>
                <div class="kpi-value">{{ $totalUsers }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card d-flex align-items-center gap-3">
            <div class="kpi-icon" style="background:#f0fdf4"><i class="fas fa-user-check" style="color:#10b981"></i></div>
            <div>
                <div class="kpi-label">Active</div>
                <div class="kpi-value">{{ $users->where('status','Active')->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card d-flex align-items-center gap-3">
            <div class="kpi-icon" style="background:#faf5ff"><i class="fas fa-user-tie" style="color:#7c3aed"></i></div>
            <div>
                <div class="kpi-label">Sales</div>
                <div class="kpi-value">{{ $users->where('role','Sales Executive')->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card d-flex align-items-center gap-3">
            <div class="kpi-icon" style="background:#fff7ed"><i class="fas fa-crown" style="color:#f97316"></i></div>
            <div>
                <div class="kpi-label">Manager</div>
                <div class="kpi-value">{{ $users->where('role','Sales Manager')->count() }}</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter + Table -->
<div style="background:#fff;border-radius:12px;border:1px solid #f0f0f0;padding:20px 24px">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" action="{{ route('users.index') }}" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama / email..." value="{{ $search }}" style="width:220px;border-radius:8px;font-size:13px">
                <select name="role" class="form-select form-select-sm" style="width:160px;font-size:13px;border-radius:8px">
                    <option value="">Semua Role</option>
                    @foreach($roles as $r)
                    <option value="{{ $r }}" {{ $role === $r ? 'selected' : '' }}>{{ $r }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary btn-sm" style="border-radius:8px;font-size:13px">Filter</button>
                <a href="{{ route('users.index') }}" class="btn btn-light btn-sm" style="border-radius:8px;font-size:13px;border:1px solid #e5e7eb">Reset</a>
            </form>
        </div>
        <div style="font-size:13px;color:#6b7280">{{ $users->total() }} users ditemukan</div>
    </div>

    <div class="table-responsive">
        <table class="table user-table mb-0">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Contact</th>
                    <th>Total Leads</th>
                    <th>Deals Won</th>
                    <th>Target Bulan Ini</th>
                    <th>Status</th>
                    <th style="width:80px">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            @php
                            $colors = ['#3b82f6','#10b981','#f59e0b','#f97316','#8b5cf6','#ec4899'];
                            $color = $colors[$loop->index % count($colors)];
                            $initials = collect(explode(' ', $user->name))->take(2)->map(fn($w) => strtoupper($w[0]))->join('');
                            @endphp
                            <div class="user-avatar-lg" style="background:{{ $color }}">{{ $initials }}</div>
                            <div>
                                <div style="font-weight:600;color:#0f1d35">{{ $user->name }}</div>
                                <div style="font-size:11px;color:#9ca3af">{{ $user->email ?? 'No email' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @php
                        $roleClass = match(true) {
                            str_contains($user->role, 'Manager') => 'role-manager',
                            str_contains($user->role, 'Admin')   => 'role-admin',
                            default => 'role-sales',
                        };
                        @endphp
                        <span class="badge-role {{ $roleClass }}">{{ $user->role }}</span>
                    </td>
                    <td style="font-size:12px;color:#6b7280">{{ $user->phone ?? '-' }}</td>
                    <td style="font-weight:600;text-align:center">{{ $user->leads_count ?? $user->leads()->count() }}</td>
                    <td style="font-weight:600;text-align:center;color:#10b981">{{ $user->leads()->where('pipeline_stage','Won')->count() }}</td>
                    <td style="min-width:130px">
                        @php
                        $target = $user->target ?? 500000000;
                        $achieved = $user->leads()->where('pipeline_stage','Won')->sum('potensi_revenue');
                        $pct = $target > 0 ? min(100, round(($achieved / $target) * 100)) : 0;
                        $barColor = $pct >= 80 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
                        @endphp
                        <div style="font-size:11px;color:#6b7280;margin-bottom:3px">{{ number_format($pct) }}% dari Rp {{ number_format($target/1000000) }}M</div>
                        <div class="progress-target">
                            <div class="progress-fill" style="width:{{ $pct }}%;background:{{ $barColor }}"></div>
                        </div>
                    </td>
                    <td>
                        <span class="badge-status {{ $user->status === 'Active' ? 'status-active' : 'status-nonactive' }}">
                            {{ $user->status ?? 'Active' }}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm" style="padding:4px 8px;border:1px solid #e5e7eb;border-radius:6px;font-size:11px;color:#6b7280"
                                data-bs-toggle="modal" data-bs-target="#editUserModal{{ $user->id }}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" action="{{ route('users.destroy', $user->id) }}" onsubmit="return confirm('Hapus user ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="padding:4px 8px;border:1px solid #fecaca;border-radius:6px;font-size:11px;color:#dc2626">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>

                <!-- Edit Modal per user -->
                <div class="modal fade" id="editUserModal{{ $user->id }}" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h6 class="modal-title fw-bold">Edit User: {{ $user->name }}</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form action="{{ route('users.update', $user->id) }}" method="POST">
                                @csrf @method('PATCH')
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Nama</label>
                                            <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Role</label>
                                            <select name="role" class="form-select">
                                                @foreach(['Sales Executive','Sales Manager','Admin','Marketing'] as $r)
                                                <option value="{{ $r }}" {{ $user->role === $r ? 'selected' : '' }}>{{ $r }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select">
                                                <option value="Active" {{ ($user->status ?? 'Active') === 'Active' ? 'selected' : '' }}>Active</option>
                                                <option value="Non-Active" {{ ($user->status ?? '') === 'Non-Active' ? 'selected' : '' }}>Non-Active</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Target Bulanan (IDR)</label>
                                            <input type="number" name="target" class="form-control" value="{{ $user->target ?? '' }}" placeholder="Contoh: 500000000">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4" style="color:#9ca3af;font-size:13px">
                        <i class="fas fa-users" style="font-size:24px;display:block;margin-bottom:8px"></i>
                        Tidak ada user ditemukan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $users->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Tambah User Baru</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="name" class="form-control" required placeholder="Contoh: Budi Santoso">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required placeholder="email@perusahaan.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No. HP</label>
                            <input type="text" name="phone" class="form-control" placeholder="08xx-xxxx-xxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option>Sales Executive</option>
                                <option>Sales Manager</option>
                                <option>Admin</option>
                                <option>Marketing</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Non-Active">Non-Active</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Target Bulanan (IDR)</label>
                            <input type="number" name="target" class="form-control" placeholder="Contoh: 500000000">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Tambah User</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
