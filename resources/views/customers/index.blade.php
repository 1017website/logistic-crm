@extends('layouts.app')
@section('title', 'Database Customer')
@section('page-title', 'Database Customer')
@section('page-subtitle', 'Kelola data customer perusahaan')

@section('content')
<div class="row g-3">

    {{-- LEFT: Table --}}
    <div class="col-lg-{{ $selectedCustomer ? '8' : '12' }}">

        {{-- Header --}}
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                    <i class="fas fa-plus me-1"></i> Add Customer
                </button>
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#importCustomerModal">
                    <i class="fas fa-upload me-1"></i> Import
                </button>
                <a href="{{ route('customers.export') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-download me-1"></i> Export CSV
                </a>
            </div>
            <div class="d-flex gap-3">
                <div class="text-center">
                    <div style="font-size:1.3rem;font-weight:800;color:#111">{{ $totalCustomer }}</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">Total</div>
                </div>
                <div class="text-center px-3" style="border-left:1px solid var(--border-color)">
                    <div style="font-size:1.3rem;font-weight:800;color:#059669">{{ $existingCustomer }}</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">Existing</div>
                </div>
                <div class="text-center px-3" style="border-left:1px solid var(--border-color)">
                    <div style="font-size:1.3rem;font-weight:800;color:#d97706">{{ $potentialCustomer }}</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">Potential</div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('customers.index') }}">
            <div class="card mb-3">
                <div class="card-body p-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <select name="status" class="form-select form-select-sm">
                                <option value="all">All Status</option>
                                <option value="Existing" @selected($status=='Existing')>Existing</option>
                                <option value="Potential" @selected($status=='Potential')>Potential</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <select name="industry" class="form-select form-select-sm">
                                <option value="all">All Industry</option>
                                @foreach($industries as $ind)
                                <option value="{{ $ind }}" @selected($industry==$ind)>{{ $ind }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <select name="sales_user_id" class="form-select form-select-sm">
                                <option value="">All Sales</option>
                                @foreach($salesUsers as $su)
                                <option value="{{ $su->id }}" @selected($salesId==$su->id)>{{ $su->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari company, PIC, phone..." value="{{ $search }}">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search me-1"></i> Filter</button>
                            <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div class="card">
            <div class="card-body p-0">
                <table class="table crm-table mb-0">
                    <thead>
                        <tr>
                            <th>No.</th><th>Company</th><th>Contact</th><th>Industry</th>
                            <th>Status</th><th>Sales PIC</th><th>Last Activity</th><th>Total Revenue</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $i => $cust)
                        <tr>
                            <td style="color:#9ca3af;font-size:.75rem">{{ $customers->firstItem() + $i }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar" style="width:30px;height:30px;font-size:.65rem;border-radius:6px;flex-shrink:0">{{ $cust->logo_initials }}</div>
                                    <div>
                                        <a href="{{ route('customers.index', array_merge(request()->query(), ['selected_id'=>$cust->id])) }}"
                                            style="font-weight:600;color:#111;text-decoration:none;font-size:.82rem">{{ $cust->company_name }}</a>
                                        <div style="font-size:.7rem;color:var(--text-muted)">{{ $cust->pic_name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:.75rem">
                                {{ $cust->phone }}
                                @if($cust->email)<div style="color:var(--primary);font-size:.7rem">{{ $cust->email }}</div>@endif
                            </td>
                            <td style="font-size:.75rem">{{ $cust->industry ?? '-' }}</td>
                            <td><span class="badge-{{ strtolower($cust->status) }}">{{ $cust->status }}</span></td>
                            <td style="font-size:.75rem">{{ $cust->salesUser?->name ?? '-' }}</td>
                            <td style="font-size:.72rem">
                                @if($cust->activities->count())
                                    <div>{{ $cust->activities->sortByDesc('activity_at')->first()->activity_at->format('d M Y') }}</div>
                                    <div style="color:var(--text-muted)">{{ $cust->activities->sortByDesc('activity_at')->first()->type }}</div>
                                @else<span style="color:#d1d5db">-</span>@endif
                            </td>
                            <td style="font-size:.78rem;font-weight:600;color:var(--primary)">
                                {{ $cust->total_revenue > 0 ? idrm($cust->total_revenue) : '-' }}
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('customers.index', array_merge(request()->query(), ['selected_id'=>$cust->id])) }}"
                                        class="btn btn-sm btn-outline-primary" style="padding:3px 7px">
                                        <i class="fas fa-eye" style="font-size:.7rem"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-secondary" style="padding:3px 7px"
                                        onclick="openEditModal({{ $cust->id }},'{{ addslashes($cust->company_name) }}','{{ addslashes($cust->pic_name) }}','{{ $cust->phone }}','{{ $cust->email }}','{{ $cust->industry }}','{{ $cust->location }}','{{ $cust->status }}','{{ $cust->sales_user_id }}')">
                                        <i class="fas fa-edit" style="font-size:.7rem"></i>
                                    </button>
                                    <form method="POST" action="{{ route('customers.destroy', $cust) }}" class="d-inline"
                                        onsubmit="return confirm('Hapus {{ addslashes($cust->company_name) }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:3px 7px">
                                            <i class="fas fa-trash" style="font-size:.7rem"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center py-4" style="color:var(--text-muted)">
                            <i class="fas fa-building" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.2"></i>
                            Tidak ada data customer.
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($customers->hasPages())
            <div class="card-footer p-3 d-flex justify-content-between align-items-center">
                <span style="font-size:.78rem;color:var(--text-muted)">Showing {{ $customers->firstItem() }}–{{ $customers->lastItem() }} of {{ $customers->total() }}</span>
                {{ $customers->links('pagination::bootstrap-5') }}
            </div>
            @endif
        </div>
    </div>

    {{-- RIGHT: Detail Panel --}}
    @if($selectedCustomer)
    <div class="col-lg-4">
        <div class="card" style="position:sticky;top:70px">
            <div class="card-body p-3">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="user-avatar" style="width:44px;height:44px;border-radius:8px;font-size:.85rem">{{ $selectedCustomer->logo_initials }}</div>
                        <div>
                            <div style="font-weight:700;font-size:.9rem">{{ $selectedCustomer->company_name }}</div>
                            <div class="d-flex gap-1 mt-1 flex-wrap">
                                <span class="badge-{{ strtolower($selectedCustomer->status) }}">{{ $selectedCustomer->status }}</span>
                                @if($selectedCustomer->value_tag)
                                <span style="background:#fef3c7;color:#b45309;font-size:.65rem;padding:2px 7px;border-radius:20px;font-weight:600">{{ $selectedCustomer->value_tag }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('customers.index', request()->except('selected_id')) }}" style="color:var(--text-muted)"><i class="fas fa-times"></i></a>
                </div>

                <ul class="nav nav-tabs mb-3" style="font-size:.75rem" id="custTabs">
                    <li class="nav-item"><a class="nav-link active" href="#" onclick="showTab('overview',this);return false" style="padding:6px 10px">Overview</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" onclick="showTab('activity',this);return false" style="padding:6px 10px">Activity</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" onclick="showTab('transaction',this);return false" style="padding:6px 10px">DO</a></li>
                </ul>

                <div id="tab-overview">
                    @foreach([
                        ['PIC',$selectedCustomer->pic_name],['Jabatan',$selectedCustomer->pic_position??'\-'],['Phone',$selectedCustomer->phone??'\-'],['Email',$selectedCustomer->email??'\-'],['Industry',$selectedCustomer->industry??'\-'],['Location',$selectedCustomer->location??'\-'],['Sales PIC',$selectedCustomer->salesUser?->name??'\-'],['Customer Since',$selectedCustomer->customer_since?->format('d M Y')??'\-'],
                    ] as $f)
                    <div class="d-flex justify-content-between py-1" style="border-bottom:1px solid #f9fafb;font-size:.77rem">
                        <span style="color:var(--text-muted);min-width:90px">{{ $f[0] }}</span>
                        <span style="font-weight:500;text-align:right;max-width:55%">{{ $f[1] }}</span>
                    </div>
                    @endforeach
                    <div class="row g-2 mt-3 mb-3 text-center">
                        <div class="col-6">
                            <div style="background:#eff6ff;border-radius:8px;padding:10px">
                                <div style="font-size:1rem;font-weight:800;color:var(--primary)">{{ $selectedCustomer->total_revenue > 0 ? idrm($selectedCustomer->total_revenue) : 'Rp 0' }}</div>
                                <div style="font-size:.65rem;color:var(--text-muted)">Total Revenue</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="background:#f0fdf4;border-radius:8px;padding:10px">
                                <div style="font-size:1rem;font-weight:800;color:#16a34a">{{ $selectedCustomer->deliveryOrders->count() }}</div>
                                <div style="font-size:.65rem;color:var(--text-muted)">Total DO</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="background:#fefce8;border-radius:8px;padding:10px">
                                <div style="font-size:.85rem;font-weight:700">{{ $selectedCustomer->deliveryOrders->count() > 0 ? \Carbon\Carbon::parse($selectedCustomer->deliveryOrders->sortByDesc('order_date')->first()->order_date)->diffForHumans() : '-' }}</div>
                                <div style="font-size:.65rem;color:var(--text-muted)">Last Order</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="background:#faf5ff;border-radius:8px;padding:10px">
                                <div style="font-size:.85rem;font-weight:700">{{ $selectedCustomer->activities->count() }}</div>
                                <div style="font-size:.65rem;color:var(--text-muted)">Activities</div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-1">
                        @foreach([['phone','Log Call','#d1fae5','#059669',"quickActCust('Call')"],['building','Visit','#dbeafe','#2563eb',"quickActCust('Visit')"],['envelope','Email','#fef3c7','#d97706',"quickActCust('Email')"],['sticky-note','Note','#f3e8ff','#7c3aed',"quickActCust('Note')"]] as $qa)
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
                            onclick="openEditModal({{ $selectedCustomer->id }},'{{ addslashes($selectedCustomer->company_name) }}','{{ addslashes($selectedCustomer->pic_name) }}','{{ $selectedCustomer->phone }}','{{ $selectedCustomer->email }}','{{ $selectedCustomer->industry }}','{{ $selectedCustomer->location }}','{{ $selectedCustomer->status }}','{{ $selectedCustomer->sales_user_id }}')">
                            <i class="fas fa-edit me-1"></i> Edit
                        </button>
                        <form method="POST" action="{{ route('customers.destroy', $selectedCustomer) }}" class="flex-fill" onsubmit="return confirm('Hapus customer ini?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100" style="font-size:.75rem"><i class="fas fa-trash me-1"></i> Hapus</button>
                        </form>
                    </div>
                </div>

                <div id="tab-activity" style="display:none">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong style="font-size:.8rem">Activity History</strong>
                        <button class="btn btn-sm btn-primary" style="font-size:.72rem;padding:3px 8px" data-bs-toggle="modal" data-bs-target="#addCustActivityModal">
                            <i class="fas fa-plus me-1"></i> Add
                        </button>
                    </div>
                    @forelse($selectedCustomer->activities->sortByDesc('activity_at') as $act)
                    <div class="d-flex gap-2 mb-3">
                        <div class="activity-icon" style="width:28px;height:28px;flex-shrink:0;background:{{ $act->type==='Call'?'#d1fae5':($act->type==='Visit'?'#dbeafe':'#fef3c7') }}">
                            <i class="fas fa-{{ $act->type_icon }}" style="font-size:.65rem;color:{{ $act->type==='Call'?'#059669':($act->type==='Visit'?'#2563eb':'#d97706') }}"></i>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div style="font-size:.75rem;font-weight:600">{{ $act->subject ?: $act->type }}</div>
                            @if($act->description)<div style="font-size:.7rem;color:#374151">{{ Str::limit($act->description,60) }}</div>@endif
                            <div style="font-size:.67rem;color:var(--text-muted)">{{ $act->salesUser?->name }} · {{ $act->activity_at->format('d M Y H:i') }}</div>
                        </div>
                        <span class="badge-{{ strtolower($act->status) }}" style="font-size:.62rem;flex-shrink:0">{{ $act->status }}</span>
                    </div>
                    @empty
                    <div class="text-center py-3" style="color:var(--text-muted);font-size:.8rem">Belum ada activity.</div>
                    @endforelse
                </div>

                <div id="tab-transaction" style="display:none">
                    <strong style="font-size:.8rem;display:block;margin-bottom:10px">Delivery Orders</strong>
                    @forelse($selectedCustomer->deliveryOrders->sortByDesc('order_date') as $do)
                    <div class="d-flex align-items-start gap-2 mb-3 pb-2" style="border-bottom:1px solid #f9fafb">
                        <div style="width:32px;height:32px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fas fa-ship" style="font-size:.7rem;color:#2563eb"></i>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div style="font-size:.78rem;font-weight:600">{{ $do->do_number }}</div>
                            <div style="font-size:.7rem;color:var(--text-muted)">{{ $do->service_type }} · {{ $do->route }}</div>
                            <div style="font-size:.7rem;color:var(--text-muted)">{{ \Carbon\Carbon::parse($do->order_date)->format('d M Y') }}</div>
                        </div>
                        <div class="text-end" style="flex-shrink:0">
                            <div style="font-size:.75rem;font-weight:600">{{ idrm($do->amount) }}</div>
                            <span class="badge-{{ strtolower($do->status) }}" style="font-size:.62rem">{{ $do->status }}</span>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-3" style="color:var(--text-muted);font-size:.8rem">Belum ada transaksi.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- MODALS --}}
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Add Customer Baru</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" action="{{ route('customers.store') }}">@csrf
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label">Company Name <span class="text-danger">*</span></label><input type="text" name="company_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">PIC Name <span class="text-danger">*</span></label><input type="text" name="pic_name" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Jabatan PIC</label><input type="text" name="pic_position" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Phone <span class="text-danger">*</span></label><input type="text" name="phone" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Industry</label><input type="text" name="industry" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Location</label><input type="text" name="location" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Customer Since</label><input type="date" name="customer_since" class="form-control"></div>
                <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                <div class="col-md-6"><label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required><option value="Potential">Potential</option><option value="Existing">Existing</option></select></div>
                <div class="col-md-6"><label class="form-label">Sales PIC <span class="text-danger">*</span></label>
                    <select name="sales_user_id" class="form-select" required>
                        @foreach($salesUsers as $su)<option value="{{ $su->id }}">{{ $su->name }}</option>@endforeach
                    </select></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="editCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Edit Customer</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" id="editCustomerForm">@csrf @method('PUT')
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label">Company Name</label><input type="text" name="company_name" id="editCompanyName" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">PIC Name</label><input type="text" name="pic_name" id="editPicName" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" id="editPhone" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" id="editEmail" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Industry</label><input type="text" name="industry" id="editIndustry" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Location</label><input type="text" name="location" id="editLocation" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Status</label>
                    <select name="status" id="editStatus" class="form-select"><option value="Potential">Potential</option><option value="Existing">Existing</option></select></div>
                <div class="col-md-4"><label class="form-label">Sales PIC</label>
                    <select name="sales_user_id" id="editSalesPIC" class="form-select">
                        @foreach($salesUsers as $su)<option value="{{ $su->id }}">{{ $su->name }}</option>@endforeach
                    </select></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="importCustomerModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Import Customer dari CSV</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" action="{{ route('customers.import') }}" enctype="multipart/form-data">@csrf
            <div class="modal-body">
                <div class="mb-3 p-3" style="background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb">
                    <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:6px"><i class="fas fa-info-circle text-primary me-1"></i> Format CSV</div>
                    <div style="font-size:11px;color:#6b7280">Kolom: <strong>Company Name, PIC Name, Position, Phone, Email, Industry, Location, Status, Sales PIC, Customer Since</strong></div>
                    <a href="{{ route('customers.export') }}" class="btn btn-sm btn-outline-primary mt-2" style="font-size:11px"><i class="fas fa-download me-1"></i> Download Template</a>
                </div>
                <div><label class="form-label">File CSV <span class="text-danger">*</span></label><input type="file" name="file" class="form-control" accept=".csv,.txt" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload me-1"></i> Import</button></div>
        </form>
    </div></div>
</div>

@if($selectedCustomer)
<div class="modal fade" id="addCustActivityModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Add Activity — {{ $selectedCustomer->company_name }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" action="{{ route('customers.activity.store', $selectedCustomer) }}">@csrf
            <div class="modal-body"><div class="row g-3">
                <div class="col-6"><label class="form-label">Jenis <span class="text-danger">*</span></label>
                    <select name="type" id="custActType" class="form-select" required><option>Call</option><option>Visit</option><option>Email</option><option>Note</option><option>Task</option></select></div>
                <div class="col-6"><label class="form-label">Status</label>
                    <select name="status" class="form-select"><option value="Done">Done</option><option value="Planned">Planned</option><option value="Pending">Pending</option></select></div>
                <div class="col-12"><label class="form-label">Subject <span class="text-danger">*</span></label><input type="text" name="subject" class="form-control" required></div>
                <div class="col-6"><label class="form-label">Tanggal & Waktu</label><input type="datetime-local" name="activity_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}"></div>
                <div class="col-6"><label class="form-label">Sales PIC</label>
                    <select name="sales_user_id" class="form-select" required>
                        @foreach($salesUsers as $su)<option value="{{ $su->id }}" {{ $selectedCustomer->sales_user_id==$su->id?'selected':'' }}>{{ $su->name }}</option>@endforeach
                    </select></div>
                <div class="col-12"><label class="form-label">Keterangan</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div></div>
</div>
@endif

@endsection

@push('scripts')
<script>
function showTab(tab, el) {
    document.querySelectorAll('#custTabs .nav-link').forEach(a => a.classList.remove('active'));
    el.classList.add('active');
    ['overview','activity','transaction'].forEach(t => {
        const d = document.getElementById('tab-'+t);
        if(d) d.style.display = t===tab?'block':'none';
    });
}
function openEditModal(id,company,pic,phone,email,industry,location,status,salesId) {
    document.getElementById('editCustomerForm').action = `/customers/${id}`;
    document.getElementById('editCompanyName').value = company;
    document.getElementById('editPicName').value = pic;
    document.getElementById('editPhone').value = phone;
    document.getElementById('editEmail').value = email;
    document.getElementById('editIndustry').value = industry;
    document.getElementById('editLocation').value = location;
    document.getElementById('editStatus').value = status;
    document.getElementById('editSalesPIC').value = salesId;
    new bootstrap.Modal(document.getElementById('editCustomerModal')).show();
}
function quickActCust(type) {
    const el = document.getElementById('custActType');
    if(el) el.value = type;
    const m = document.getElementById('addCustActivityModal');
    if(m) new bootstrap.Modal(m).show();
}
</script>
@endpush