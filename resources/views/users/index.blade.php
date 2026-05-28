@extends('layouts.app')
@section('title', 'Users Management')
@section('page-title', 'Users Management')
@section('page-subtitle', 'Kelola data sales, manager, dan admin dalam sistem CRM')

@section('content')

{{-- KPI --}}
<div class="row g-3 mb-4">
    @foreach([
        ['bg'=>'#eff6ff','icon'=>'fas fa-users','color'=>'#3b82f6','label'=>'Total Users','value'=>$totalUsers],
        ['bg'=>'#f0fdf4','icon'=>'fas fa-user-check','color'=>'#10b981','label'=>'Active','value'=>$activeUsers],
        ['bg'=>'#faf5ff','icon'=>'fas fa-user-tie','color'=>'#7c3aed','label'=>'Sales Executive','value'=>$totalSales],
        ['bg'=>'#fff7ed','icon'=>'fas fa-crown','color'=>'#f97316','label'=>'Sales Manager','value'=>$totalManager],
    ] as $k)
    <div class="col-md-3">
        <div style="background:#fff;border-radius:12px;border:1px solid #f0f0f0;padding:16px 20px;display:flex;align-items:center;gap:12px">
            <div style="width:44px;height:44px;border-radius:50%;background:{{ $k['bg'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="{{ $k['icon'] }}" style="color:{{ $k['color'] }}"></i>
            </div>
            <div>
                <div style="font-size:12px;color:#6b7280">{{ $k['label'] }}</div>
                <div style="font-size:22px;font-weight:700;color:#0f1d35">{{ $k['value'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Filter + Header --}}
<div class="card">
    <div class="card-body p-3 pb-0">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <form method="GET" action="{{ route('users.index') }}" class="d-flex gap-2 align-items-center">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama / email..." value="{{ $search }}" style="width:200px">
                <select name="role" class="form-select form-select-sm" style="width:160px">
                    <option value="">Semua Role</option>
                    @foreach($roles as $r)
                    <option value="{{ $r }}" @selected($role==$r)>{{ $r }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select form-select-sm" style="width:130px">
                    <option value="">Semua Status</option>
                    <option value="Active" @selected($status=='Active')>Active</option>
                    <option value="Non-Active" @selected($status=='Non-Active')>Non-Active</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </form>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus me-1"></i> Add User
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="table-responsive">
        <table class="table mb-0" style="font-size:13px">
            <thead>
                <tr style="background:#f9fafb">
                    <th style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;padding:10px 14px;border-bottom:2px solid #f0f0f0">User</th>
                    <th style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;padding:10px 14px;border-bottom:2px solid #f0f0f0">Role</th>
                    <th style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;padding:10px 14px;border-bottom:2px solid #f0f0f0">Contact</th>
                    <th style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;padding:10px 14px;border-bottom:2px solid #f0f0f0;text-align:center">Total Leads</th>
                    <th style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;padding:10px 14px;border-bottom:2px solid #f0f0f0;text-align:center">Deals Won</th>
                    <th style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;padding:10px 14px;border-bottom:2px solid #f0f0f0">Target & Progress</th>
                    <th style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;padding:10px 14px;border-bottom:2px solid #f0f0f0">Status</th>
                    <th style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;padding:10px 14px;border-bottom:2px solid #f0f0f0;width:80px">Action</th>
                </tr>
            </thead>
            <tbody>
                @php $avatarColors = ['#3b82f6','#10b981','#f59e0b','#f97316','#8b5cf6','#ec4899']; @endphp
                @forelse($users as $user)
                @php
                $color    = $avatarColors[$loop->index % count($avatarColors)];
                $initials = collect(explode(' ', $user->name))->take(2)->map(fn($w) => strtoupper($w[0]))->join('');
                $target   = $user->target ?? 500000000;
                $achieved = $revenues[$user->id] ?? 0;
                $pct      = $target > 0 ? min(100, round(($achieved / $target) * 100)) : 0;
                $barColor = $pct >= 80 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
                $roleClass = str_contains($user->role ?? '', 'Manager') ? 'background:#faf5ff;color:#7c3aed' : (str_contains($user->role ?? '', 'Admin') ? 'background:#fff7ed;color:#c2410c' : 'background:#eff6ff;color:#2563eb');
                @endphp
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:12px 14px">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width:36px;height:36px;border-radius:50%;background:{{ $color }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0">{{ $initials }}</div>
                            <div>
                                <div style="font-weight:600;color:#0f1d35">{{ $user->name }}</div>
                                <div style="font-size:11px;color:#9ca3af">{{ $user->email ?? 'No email' }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:12px 14px">
                        <span style="font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600;{{ $roleClass }}">{{ $user->role ?? '-' }}</span>
                    </td>
                    <td style="padding:12px 14px;font-size:12px;color:#6b7280">{{ $user->phone ?? '-' }}</td>
                    <td style="padding:12px 14px;font-weight:600;text-align:center">{{ $user->leads_count }}</td>
                    <td style="padding:12px 14px;font-weight:600;text-align:center;color:#10b981">{{ $user->deals_won }}</td>
                    <td style="padding:12px 14px;min-width:160px">
                        <div style="font-size:11px;color:#6b7280;margin-bottom:3px">
                            {{ $pct }}% dari {{ idrm($target) }}
                        </div>
                        <div style="background:#e5e7eb;border-radius:3px;height:5px">
                            <div style="width:{{ $pct }}%;height:5px;border-radius:3px;background:{{ $barColor }}"></div>
                        </div>
                        <div style="font-size:10px;color:#9ca3af;margin-top:2px">Achieved: {{ idrm($achieved) }}</div>
                    </td>
                    <td style="padding:12px 14px">
                        @php $isActive = ($user->status ?? 'Active') === 'Active'; @endphp
                        <span style="font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600;{{ $isActive ? 'background:#dcfce7;color:#16a34a' : 'background:#fee2e2;color:#dc2626' }}">
                            {{ $user->status ?? 'Active' }}
                        </span>
                    </td>
                    <td style="padding:12px 14px">
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm" style="padding:4px 8px;border:1px solid #e5e7eb;border-radius:6px;font-size:11px;color:#6b7280"
                                onclick="openEditUser({{ $user->id }},'{{ addslashes($user->name) }}','{{ $user->email }}','{{ $user->phone }}','{{ $user->position }}','{{ $user->role }}','{{ $user->status ?? 'Active' }}','{{ $user->target ?? 0 }}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            @if($user->status === 'Active')
                            <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline"
                                onsubmit="return confirm('Nonaktifkan user {{ addslashes($user->name) }}? User tidak akan bisa login.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="padding:4px 8px;border:1px solid #fde68a;border-radius:6px;font-size:11px;color:#d97706" title="Nonaktifkan">
                                    <i class="fas fa-user-slash"></i>
                                </button>
                            </form>
                            @else
                            <span style="font-size:10px;color:#9ca3af;padding:4px 6px;border:1px solid #e5e7eb;border-radius:6px">Non-Active</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4" style="color:#9ca3af">
                        <i class="fas fa-users" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.2"></i>
                        Tidak ada user ditemukan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
    <div class="p-3 d-flex justify-content-between align-items-center">
        <span style="font-size:13px;color:#6b7280">Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}</span>
        {{ $users->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

{{-- Add User Modal --}}
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="addUserModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Tambah User Baru</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form action="{{ route('users.store') }}" method="POST">@csrf
            <div class="modal-body"><div class="row g-3">
                <div class="col-12"><label class="form-label">Nama Lengkap <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required autocomplete="off"></div>
                <div class="col-md-6"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" required autocomplete="off"></div>
                <div class="col-md-6"><label class="form-label">No. HP</label><input type="text" name="phone" class="form-control" autocomplete="off"></div>
                <div class="col-md-6"><label class="form-label">Password <span class="text-danger">*</span></label><input type="password" name="password" class="form-control" required placeholder="Min. 6 karakter" autocomplete="new-password"></div>
                <div class="col-md-6"><label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label><input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password"></div>
                <div class="col-12"><label class="form-label">Jabatan / Position</label><input type="text" name="position" class="form-control" placeholder="Contoh: Senior Sales Executive"></div>
                <div class="col-md-6"><label class="form-label">Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        @foreach(['Sales Executive','Sales Manager','Admin','Marketing'] as $r)
                        <option>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Active">Active</option><option value="Non-Active">Non-Active</option>
                    </select>
                </div>
                <div class="col-12"><label class="form-label">Target Bulanan (IDR)</label>
                    <input type="text" name="target" class="form-control idr-input" placeholder="Contoh: 500.000.000">
                </div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Tambah User</button></div>
        </form>
    </div></div>
</div>

{{-- Edit User Modal (shared - 1 modal saja) --}}
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editUserModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Edit User</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form id="editUserForm" method="POST">@csrf @method('PATCH')
            <div class="modal-body"><div class="row g-3">
                <div class="col-12"><label class="form-label">Nama Lengkap</label><input type="text" name="name" id="euName" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="euEmail" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">No. HP</label><input type="text" name="phone" id="euPhone" class="form-control"></div>
                <div class="col-12"><label class="form-label">Jabatan / Position</label><input type="text" name="position" id="euPosition" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Role</label>
                    <select name="role" id="euRole" class="form-select">
                        @foreach(['Sales Executive','Sales Manager','Admin','Marketing'] as $r)
                        <option>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Status</label>
                    <select name="status" id="euStatus" class="form-select">
                        <option value="Active">Active</option><option value="Non-Active">Non-Active</option>
                    </select>
                </div>
                <div class="col-12"><label class="form-label">Target Bulanan (IDR)</label>
                    <input type="text" name="target" id="euTarget" class="form-control idr-input">
                </div>
                <div class="col-12">
                    <div style="background:#f9fafb;border-radius:8px;padding:12px;border:1px solid #e5e7eb">
                        <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:8px"><i class="fas fa-key me-1 text-warning"></i> Reset Password (opsional)</div>
                        <div class="row g-2">
                            <div class="col-6"><input type="password" name="new_password" class="form-control form-control-sm" placeholder="Password baru (min. 6)"></div>
                            <div class="col-6"><input type="password" name="new_password_confirmation" class="form-control form-control-sm" placeholder="Konfirmasi password"></div>
                        </div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:4px">Kosongkan jika tidak ingin mengubah password</div>
                    </div>
                </div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div></div>
</div>

@endsection

@push('scripts')
<script>
function openEditUser(id, name, email, phone, position, role, status, target) {
    document.getElementById('editUserForm').action = `/users/${id}`;
    document.getElementById('euName').value     = name;
    document.getElementById('euEmail').value    = email;
    document.getElementById('euPhone').value    = phone;
    document.getElementById('euPosition').value = position;
    document.getElementById('euRole').value     = role;
    document.getElementById('euStatus').value   = status;
    // Format target dengan separator
    const raw = parseInt(String(target).replace(/\D/g,'')) || 0;
    document.getElementById('euTarget').value   = raw > 0 ? raw.toLocaleString('id-ID') : '';
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>
@endpush