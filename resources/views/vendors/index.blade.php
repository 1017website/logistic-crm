@extends('layouts.app')
@section('title', 'Database Vendor')
@section('page-title', 'Database Vendor')
@section('page-subtitle', 'Kelola data vendor & supplier perusahaan')

@section('content')
<div class="row g-3">

    <div class="col-lg-{{ $selectedVendor ? '8' : '12' }}">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addVendorModal">
                    <i class="fas fa-plus me-1"></i> Add Vendor
                </button>
                <a href="{{ route('vendors.export') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-download me-1"></i> Export CSV
                </a>
            </div>
            <div class="d-flex gap-3">
                @foreach([[$totalVendor,'Total','#111'],[$existingVendor,'Existing','#059669'],[$potentialVendor,'Potential','#2563eb'],[$preferredVendor,'Preferred','#d97706'],[$nonActiveVendor,'Non-Active','#dc2626']] as $s)
                <div class="text-center {{ !$loop->first ? 'ps-3' : '' }}" style="{{ !$loop->first ? 'border-left:1px solid var(--border-color)' : '' }}">
                    <div style="font-size:1.2rem;font-weight:800;color:{{ $s[2] }}">{{ $s[0] }}</div>
                    <div style="font-size:.68rem;color:var(--text-muted)">{{ $s[1] }}</div>
                </div>
                @endforeach
            </div>
        </div>

        <form method="GET" action="{{ route('vendors.index') }}">
            <div class="card mb-3"><div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-auto">
                        <select name="vendor_type" class="form-select form-select-sm">
                            <option value="all">All Type</option>
                            @foreach(['Shipping Line','Trucking','Air Freight','EMKL','Others'] as $t)
                            <option value="{{ $t }}" @selected($type==$t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <select name="relationship_status" class="form-select form-select-sm">
                            <option value="all">All Relationship</option>
                            <option value="Existing" @selected($relationshipStatus=='Existing')>Existing</option>
                            <option value="Potential" @selected($relationshipStatus=='Potential')>Potential</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <select name="status" class="form-select form-select-sm">
                            <option value="all">All Status</option>
                            <option value="Active" @selected($status=='Active')>Active</option>
                            <option value="Non-Active" @selected($status=='Non-Active')>Non-Active</option>
                        </select>
                    </div>
                    <div class="col">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari vendor, PIC, phone..." value="{{ $search }}">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search me-1"></i> Filter</button>
                        <a href="{{ route('vendors.index') }}" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
                    </div>
                </div>
            </div></div>
        </form>

        <div class="card">
            <div class="card-body p-0">
                <table class="table crm-table mb-0">
                    <thead><tr>
                        <th>No.</th><th>Vendor</th><th>PIC</th><th>Contact</th>
                        <th>Type</th><th>Coverage</th><th>Rating</th><th>On-Time</th><th>Status</th><th>Action</th>
                    </tr></thead>
                    <tbody>
                        @forelse($vendors as $i => $v)
                        <tr>
                            <td style="color:#9ca3af;font-size:.75rem">{{ $vendors->firstItem() + $i }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar" style="width:30px;height:30px;font-size:.65rem;border-radius:6px;background:#374151;flex-shrink:0">{{ $v->logo_initials }}</div>
                                    <div>
                                        <a href="{{ route('vendors.index', array_merge(request()->query(), ['selected_id'=>$v->id])) }}"
                                            style="font-weight:600;color:#111;text-decoration:none;font-size:.82rem">{{ $v->vendor_name }}</a>
                                        @if($v->is_preferred)<div><span style="background:#fef3c7;color:#b45309;font-size:.6rem;padding:1px 6px;border-radius:10px;font-weight:600">⭐ Preferred</span></div>@endif
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:.75rem">{{ $v->pic_name }}@if($v->pic_position)<div style="color:var(--text-muted)">{{ $v->pic_position }}</div>@endif</td>
                            <td style="font-size:.75rem">{{ $v->phone }}@if($v->email)<div style="color:var(--primary);font-size:.7rem">{{ $v->email }}</div>@endif</td>
                            <td>
                                @php $tc=['Shipping Line'=>['#dbeafe','#1d4ed8'],'Trucking'=>['#d1fae5','#059669'],'Air Freight'=>['#fef3c7','#b45309'],'EMKL'=>['#ede9fe','#7c3aed']][$v->vendor_type]??['#f3f4f6','#374151']; @endphp
                                <span style="font-size:.7rem;padding:2px 7px;border-radius:20px;font-weight:600;background:{{ $tc[0] }};color:{{ $tc[1] }}">{{ $v->vendor_type }}</span>
                            </td>
                            <td style="font-size:.75rem">{{ $v->coverage_area ?? '-' }}</td>
                            <td><div class="d-flex align-items-center gap-1"><span style="color:#f59e0b">★</span><span style="font-size:.78rem;font-weight:600">{{ $v->rating ?? '-' }}</span></div></td>
                            <td>
                                @php $ot=$v->on_time_delivery; @endphp
                                <div style="font-size:.75rem;font-weight:600;color:{{ $ot>=90?'#059669':($ot>=70?'#d97706':'#dc2626') }}">{{ $ot }}%</div>
                                <div style="background:#e5e7eb;border-radius:3px;height:4px;width:50px;margin-top:2px">
                                    <div style="width:{{ $ot }}%;height:4px;border-radius:3px;background:{{ $ot>=90?'#10b981':($ot>=70?'#f59e0b':'#ef4444') }}"></div>
                                </div>
                            </td>
                            <td><span class="{{ $v->status==='Active'?'badge-existing':'badge-overdue' }}">{{ $v->status }}</span>
                                <div class="mt-1"><span style="font-size:.65rem;padding:2px 7px;border-radius:20px;font-weight:600;background:{{ $v->relationship_status==='Existing'?'#d1fae5':'#dbeafe' }};color:{{ $v->relationship_status==='Existing'?'#059669':'#1d4ed8' }}">{{ $v->relationship_status }}</span></div>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('vendors.index', array_merge(request()->query(), ['selected_id'=>$v->id])) }}" class="btn btn-sm btn-outline-primary" style="padding:3px 7px">
                                        <i class="fas fa-eye" style="font-size:.7rem"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-secondary" style="padding:3px 7px"
                                        onclick="openEditVendor({{ $v->id }},'{{ addslashes($v->vendor_name) }}','{{ $v->vendor_type }}','{{ addslashes($v->pic_name) }}','{{ $v->phone }}','{{ $v->email }}','{{ $v->coverage_area }}','{{ $v->status }}','{{ $v->relationship_status }}','{{ $v->is_preferred?1:0 }}','{{ $v->rating }}')">
                                        <i class="fas fa-edit" style="font-size:.7rem"></i>
                                    </button>
                                    <form method="POST" action="{{ route('vendors.destroy',$v) }}" class="d-inline" onsubmit="return confirm('Hapus {{ addslashes($v->vendor_name) }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:3px 7px"><i class="fas fa-trash" style="font-size:.7rem"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="10" class="text-center py-4" style="color:var(--text-muted)">
                            <i class="fas fa-handshake" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.2"></i>Tidak ada data vendor.
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($vendors->hasPages())
            <div class="card-footer p-3 d-flex justify-content-between align-items-center">
                <span style="font-size:.78rem;color:var(--text-muted)">Showing {{ $vendors->firstItem() }}–{{ $vendors->lastItem() }} of {{ $vendors->total() }}</span>
                {{ $vendors->links('pagination::bootstrap-5') }}
            </div>
            @endif
        </div>
    </div>

    @if($selectedVendor)
    <div class="col-lg-4">
        <div class="card" style="position:sticky;top:70px"><div class="card-body p-3">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="user-avatar" style="width:44px;height:44px;border-radius:8px;background:#374151;font-size:.85rem">{{ $selectedVendor->logo_initials }}</div>
                    <div>
                        <div style="font-weight:700;font-size:.9rem">{{ $selectedVendor->vendor_name }}</div>
                        <div class="d-flex gap-1 mt-1 flex-wrap">
                            @if($selectedVendor->is_preferred)<span style="background:#fef3c7;color:#b45309;font-size:.65rem;padding:2px 7px;border-radius:20px;font-weight:600">⭐ Preferred</span>@endif
                            <span class="{{ $selectedVendor->status==='Active'?'badge-existing':'badge-overdue' }}">{{ $selectedVendor->status }}</span>
                            <span style="font-size:.65rem;padding:2px 7px;border-radius:20px;font-weight:600;background:{{ $selectedVendor->relationship_status==='Existing'?'#d1fae5':'#dbeafe' }};color:{{ $selectedVendor->relationship_status==='Existing'?'#059669':'#1d4ed8' }}">{{ $selectedVendor->relationship_status }}</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('vendors.index', request()->except('selected_id')) }}" style="color:var(--text-muted)"><i class="fas fa-times"></i></a>
            </div>

            <ul class="nav nav-tabs mb-3" style="font-size:.75rem" id="vendorTabs">
                <li class="nav-item"><a class="nav-link active" href="#" onclick="showVTab('info',this);return false" style="padding:5px 8px">Info</a></li>
                <li class="nav-item"><a class="nav-link" href="#" onclick="showVTab('rates',this);return false" style="padding:5px 8px">Rates</a></li>
                <li class="nav-item"><a class="nav-link" href="#" onclick="showVTab('do',this);return false" style="padding:5px 8px">DO</a></li>
                <li class="nav-item"><a class="nav-link" href="#" onclick="showVTab('perf',this);return false" style="padding:5px 8px">Performa</a></li>
            </ul>

            <div id="vtab-info">
                @foreach([['PIC',$selectedVendor->pic_name],['Jabatan',$selectedVendor->pic_position??'\-'],['Phone',$selectedVendor->phone??'\-'],['Email',$selectedVendor->email??'\-'],['Coverage',$selectedVendor->coverage_area??'\-'],['Payment Term',$selectedVendor->payment_term??'\-'],['Vendor Since',$selectedVendor->vendor_since?->format('d M Y')??'\-']] as $f)
                <div class="d-flex justify-content-between py-1" style="border-bottom:1px solid #f9fafb;font-size:.77rem">
                    <span style="color:var(--text-muted);min-width:90px">{{ $f[0] }}</span>
                    <span style="font-weight:500;text-align:right;max-width:55%">{{ $f[1] }}</span>
                </div>
                @endforeach
                <div class="row g-2 mt-3 mb-3 text-center">
                    <div class="col-6"><div style="background:#eff6ff;border-radius:8px;padding:10px">
                        <div style="font-size:1.1rem;font-weight:800;color:var(--primary)">{{ $selectedVendor->deliveryOrders->count() }}</div>
                        <div style="font-size:.65rem;color:var(--text-muted)">Total DO</div>
                    </div></div>
                    <div class="col-6"><div style="background:#f0fdf4;border-radius:8px;padding:10px">
                        <div style="font-size:1.1rem;font-weight:800;color:#16a34a">{{ $selectedVendor->on_time_delivery }}%</div>
                        <div style="font-size:.65rem;color:var(--text-muted)">On-Time</div>
                    </div></div>
                    <div class="col-6"><div style="background:#fefce8;border-radius:8px;padding:10px">
                        <div style="font-size:1.1rem;font-weight:800;color:#d97706">{{ $selectedVendor->rating??'\-' }} <span style="color:#f59e0b;font-size:.8rem">★</span></div>
                        <div style="font-size:.65rem;color:var(--text-muted)">Rating</div>
                    </div></div>
                    <div class="col-6"><div style="background:#faf5ff;border-radius:8px;padding:10px">
                        <div style="font-size:1.1rem;font-weight:800;color:#7c3aed">{{ $selectedVendor->rates->count() }}</div>
                        <div style="font-size:.65rem;color:var(--text-muted)">Rate Entries</div>
                    </div></div>
                </div>
                <div class="row g-1">
                    @foreach([['phone','Log Call','#d1fae5','#059669',''],['envelope','Email','#fef3c7','#d97706',''],['table','Add Rate','#ede9fe','#7c3aed',"new bootstrap.Modal(document.getElementById('addRateModal')).show()"],['star','Update Rating','#fef3c7','#f59e0b',"openUpdateRating({{ $selectedVendor->id }},{{ $selectedVendor->rating??0 }})"]] as $qa)
                    <div class="col-3">
                        <div class="quick-action-btn" onclick="{{ $qa[4] }}" style="padding:8px 4px;cursor:pointer">
                            <div class="qa-icon" style="width:28px;height:28px;background:{{ $qa[2] }}"><i class="fas fa-{{ $qa[0] }}" style="color:{{ $qa[3] }};font-size:.7rem"></i></div>
                            <span class="qa-label" style="font-size:.62rem">{{ $qa[1] }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-secondary flex-fill" style="font-size:.75rem"
                        onclick="openEditVendor({{ $selectedVendor->id }},'{{ addslashes($selectedVendor->vendor_name) }}','{{ $selectedVendor->vendor_type }}','{{ addslashes($selectedVendor->pic_name) }}','{{ $selectedVendor->phone }}','{{ $selectedVendor->email }}','{{ $selectedVendor->coverage_area }}','{{ $selectedVendor->status }}','{{ $selectedVendor->relationship_status }}','{{ $selectedVendor->is_preferred?1:0 }}','{{ $selectedVendor->rating }}')">
                        <i class="fas fa-edit me-1"></i> Edit
                    </button>
                    <form method="POST" action="{{ route('vendors.destroy',$selectedVendor) }}" class="flex-fill" onsubmit="return confirm('Hapus vendor ini?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100" style="font-size:.75rem"><i class="fas fa-trash me-1"></i> Hapus</button>
                    </form>
                </div>
            </div>

            <div id="vtab-rates" style="display:none">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong style="font-size:.8rem">Rate List</strong>
                    <button class="btn btn-sm btn-primary" style="font-size:.72rem;padding:3px 8px" data-bs-toggle="modal" data-bs-target="#addRateModal">
                        <i class="fas fa-plus me-1"></i> Add Rate
                    </button>
                </div>
                @forelse($selectedVendor->rates->sortByDesc('last_updated') as $rate)
                <div class="d-flex align-items-center gap-2 mb-2 p-2" style="background:#f9fafb;border-radius:6px;font-size:.75rem">
                    <div style="flex:1">
                        <div style="font-weight:600">{{ $rate->route }}</div>
                        <div style="color:var(--text-muted)">{{ $rate->container_type }}</div>
                    </div>
                    <div class="text-end">
                        <div style="font-weight:700;color:var(--primary)">{{ $rate->currency }} {{ number_format($rate->price,0) }}</div>
                        <div style="font-size:.68rem;color:var(--text-muted)">{{ $rate->last_updated?->format('d M Y') }}</div>
                    </div>
                </div>
                @empty
                <div class="text-center py-3" style="color:var(--text-muted);font-size:.8rem">Belum ada rate.</div>
                @endforelse
            </div>

            <div id="vtab-do" style="display:none">
                <strong style="font-size:.8rem;display:block;margin-bottom:10px">Delivery Orders</strong>
                @forelse($selectedVendor->deliveryOrders->sortByDesc('order_date')->take(10) as $do)
                <div class="d-flex align-items-start gap-2 mb-2 pb-2" style="border-bottom:1px solid #f9fafb">
                    <div style="width:30px;height:30px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="fas fa-ship" style="font-size:.65rem;color:#2563eb"></i>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:.75rem;font-weight:600">{{ $do->do_number }}</div>
                        <div style="font-size:.7rem;color:var(--text-muted)">{{ $do->service_type }} · {{ $do->route }}</div>
                    </div>
                    <div class="text-end" style="flex-shrink:0">
                        <div style="font-size:.72rem;font-weight:600">{{ idrm($do->amount) }}</div>
                        <span class="badge-{{ strtolower($do->status) }}" style="font-size:.62rem">{{ $do->status }}</span>
                    </div>
                </div>
                @empty
                <div class="text-center py-3" style="color:var(--text-muted);font-size:.8rem">Belum ada DO.</div>
                @endforelse
            </div>

            <div id="vtab-perf" style="display:none">
                @php $ot=$selectedVendor->on_time_delivery; $delay=100-$ot; @endphp
                <strong style="font-size:.8rem;display:block;margin-bottom:12px">Performance Overview</strong>
                <div class="row g-2 text-center mb-3">
                    <div class="col-6"><div style="background:#f9fafb;border-radius:8px;padding:12px">
                        <div style="font-size:1.5rem;font-weight:800;color:#2563eb">{{ $selectedVendor->deliveryOrders->count() }}</div>
                        <div style="font-size:.7rem;color:var(--text-muted)">Total Shipment</div>
                    </div></div>
                    <div class="col-6"><div style="background:#f9fafb;border-radius:8px;padding:12px">
                        <div style="font-size:1.5rem;font-weight:800;color:#16a34a">{{ $selectedVendor->deliveryOrders->where('status','Done')->count() }}</div>
                        <div style="font-size:.7rem;color:var(--text-muted)">Completed</div>
                    </div></div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.75rem"><span>On-Time Delivery</span><span style="font-weight:600;color:#16a34a">{{ $ot }}%</span></div>
                    <div style="background:#e5e7eb;border-radius:20px;height:8px"><div style="width:{{ $ot }}%;height:8px;border-radius:20px;background:#10b981"></div></div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.75rem"><span>Delay Rate</span><span style="font-weight:600;color:#ef4444">{{ $delay }}%</span></div>
                    <div style="background:#e5e7eb;border-radius:20px;height:8px"><div style="width:{{ $delay }}%;height:8px;border-radius:20px;background:#ef4444"></div></div>
                </div>
                <div class="d-flex align-items-center justify-content-between p-3" style="background:#fefce8;border-radius:8px">
                    <span style="font-size:.8rem;font-weight:600">Overall Rating</span>
                    <div class="d-flex align-items-center gap-1">
                        @for($i=1;$i<=5;$i++)<i class="fas fa-star" style="color:{{ $i<=($selectedVendor->rating??0)?'#f59e0b':'#e5e7eb' }};font-size:.9rem"></i>@endfor
                        <span style="font-size:.85rem;font-weight:700;margin-left:4px">{{ $selectedVendor->rating??'\-' }}</span>
                    </div>
                </div>
            </div>

        </div></div>
    </div>
    @endif
</div>

{{-- MODALS --}}
<div class="modal fade" id="addVendorModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Add Vendor Baru</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" action="{{ route('vendors.store') }}">@csrf
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label">Vendor Name <span class="text-danger">*</span></label><input type="text" name="vendor_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Vendor Type <span class="text-danger">*</span></label>
                    <select name="vendor_type" class="form-select" required>@foreach(['Shipping Line','Trucking','Air Freight','EMKL','Others'] as $t)<option>{{ $t }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">PIC Name <span class="text-danger">*</span></label><input type="text" name="pic_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Jabatan PIC</label><input type="text" name="pic_position" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Phone <span class="text-danger">*</span></label><input type="text" name="phone" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Coverage Area</label><input type="text" name="coverage_area" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Payment Term</label><input type="text" name="payment_term" class="form-control" placeholder="NET 30"></div>
                <div class="col-md-4"><label class="form-label">Status</label>
                    <select name="status" class="form-select"><option value="Active">Active</option><option value="Non-Active">Non-Active</option></select></div>
                <div class="col-md-4"><label class="form-label">Relationship</label>
                    <select name="relationship_status" class="form-select"><option value="Potential">Potential</option><option value="Existing">Existing</option></select></div>
                <div class="col-md-4"><label class="form-label">Rating (0-5)</label><input type="number" name="rating" class="form-control" min="0" max="5" step="0.1"></div>
                <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                <div class="col-12"><div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_preferred" value="1">
                    <label class="form-check-label" style="font-size:13px">Tandai sebagai Preferred Vendor</label>
                </div></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Simpan Vendor</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="editVendorModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Edit Vendor</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" id="editVendorForm">@csrf @method('PUT')
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label">Vendor Name</label><input type="text" name="vendor_name" id="evName" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Vendor Type</label>
                    <select name="vendor_type" id="evType" class="form-select">@foreach(['Shipping Line','Trucking','Air Freight','EMKL','Others'] as $t)<option>{{ $t }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">PIC Name</label><input type="text" name="pic_name" id="evPic" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="evPhone" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="evEmail" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Coverage Area</label><input type="text" name="coverage_area" id="evCoverage" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Status</label>
                    <select name="status" id="evStatus" class="form-select"><option value="Active">Active</option><option value="Non-Active">Non-Active</option></select></div>
                <div class="col-md-4"><label class="form-label">Relationship</label>
                    <select name="relationship_status" id="evRelationship" class="form-select"><option value="Potential">Potential</option><option value="Existing">Existing</option></select></div>
                <div class="col-md-4"><label class="form-label">Rating</label><input type="number" name="rating" id="evRating" class="form-control" min="0" max="5" step="0.1"></div>
                <div class="col-md-4 d-flex align-items-end pb-1"><div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_preferred" id="evPreferred" value="1">
                    <label class="form-check-label" for="evPreferred" style="font-size:13px">Preferred</label>
                </div></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div></div>
</div>

@if($selectedVendor)
<div class="modal fade" id="addRateModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Add Rate — {{ $selectedVendor->vendor_name }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" action="{{ route('vendors.rates.store',$selectedVendor) }}">@csrf
            <div class="modal-body"><div class="row g-3">
                <div class="col-12"><label class="form-label">Route <span class="text-danger">*</span></label><input type="text" name="route" class="form-control" required placeholder="Jakarta – Shanghai"></div>
                <div class="col-md-6"><label class="form-label">Container/Type</label><input type="text" name="container_type" class="form-control" placeholder="20GP, 40HC"></div>
                <div class="col-md-3"><label class="form-label">Currency</label>
                    <select name="currency" class="form-select"><option>IDR</option><option>USD</option><option>SGD</option><option>EUR</option></select></div>
                <div class="col-md-3"><label class="form-label">Price <span class="text-danger">*</span></label><input type="text" name="price" class="form-control idr-input" required></div>
                <div class="col-12"><label class="form-label">Tanggal Update</label><input type="date" name="last_updated" class="form-control" value="{{ now()->format('Y-m-d') }}"></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Simpan Rate</button></div>
        </form>
    </div></div>
</div>
@endif

<div class="modal fade" id="updateRatingModal" tabindex="-1">
    <div class="modal-dialog modal-sm"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Update Rating</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" id="updateRatingForm">@csrf @method('PUT')
            <div class="modal-body">
                <label class="form-label">Rating (0 - 5)</label>
                <input type="number" name="rating" id="newRating" class="form-control" min="0" max="5" step="0.1" required>
                <div style="font-size:12px;color:var(--text-muted);margin-top:6px">5 = Sangat Baik · 1 = Buruk</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Update</button></div>
        </form>
    </div></div>
</div>

@endsection

@push('scripts')
<script>
function showVTab(tab, el) {
    document.querySelectorAll('#vendorTabs .nav-link').forEach(a => a.classList.remove('active'));
    el.classList.add('active');
    ['info','rates','do','perf'].forEach(t => {
        const d = document.getElementById('vtab-'+t);
        if(d) d.style.display = t===tab?'block':'none';
    });
}
function openEditVendor(id,name,type,pic,phone,email,coverage,status,relationship,preferred,rating) {
    document.getElementById('editVendorForm').action = `/vendors/${id}`;
    document.getElementById('evName').value = name;
    document.getElementById('evType').value = type;
    document.getElementById('evPic').value = pic;
    document.getElementById('evPhone').value = phone;
    document.getElementById('evEmail').value = email;
    document.getElementById('evCoverage').value = coverage;
    document.getElementById('evStatus').value = status;
    document.getElementById('evRelationship').value = relationship;
    document.getElementById('evPreferred').checked = preferred=='1';
    document.getElementById('evRating').value = rating;
    new bootstrap.Modal(document.getElementById('editVendorModal')).show();
}
function openUpdateRating(id, current) {
    document.getElementById('updateRatingForm').action = `/vendors/${id}`;
    document.getElementById('newRating').value = current;
    new bootstrap.Modal(document.getElementById('updateRatingModal')).show();
}
</script>
@endpush