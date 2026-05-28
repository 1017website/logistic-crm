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
                <a href="{{ route('customers.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-download me-1"></i> Export Excel
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
                            <select name="user_id" class="form-select form-select-sm">
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
                            <th>Status</th><th>Sales PIC</th><th>Layanan</th><th>Last Activity</th><th>Action</th>
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
                            <td style="font-size:.72rem;max-width:160px">
                                @if($cust->productItems && $cust->productItems->count())
                                    <div style="display:flex;flex-wrap:wrap;gap:3px">
                                        @foreach($cust->productItems as $p)
                                            <span style="background:#eff6ff;color:#2563eb;padding:1px 6px;border-radius:10px;font-size:.65rem;white-space:nowrap">
                                                {{ $p->display_name }}{{ $p->unit ? ' — '.$p->unit : '' }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span style="color:#d1d5db">-</span>
                                @endif
                            </td>
                            <td style="font-size:.72rem">
                                @if($cust->activities->count())
                                    <div>{{ $cust->activities->sortByDesc('activity_at')->first()->activity_at->format('d M Y') }}</div>
                                    <div style="color:var(--text-muted)">{{ $cust->activities->sortByDesc('activity_at')->first()->type }}</div>
                                @else<span style="color:#d1d5db">-</span>@endif
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('customers.index', array_merge(request()->query(), ['selected_id'=>$cust->id])) }}"
                                        class="btn btn-sm btn-outline-primary" style="padding:3px 7px">
                                        <i class="fas fa-eye" style="font-size:.7rem"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-secondary" style="padding:3px 7px"
                                        onclick="openEditModal({{ $cust->id }})">
                                        <i class="fas fa-edit" style="font-size:.7rem"></i>
                                    </button>
                                    <form method="POST" action="{{ route('customers.destroy', $cust) }}" class="d-inline"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus customer {{ addslashes($cust->company_name) }}? Tindakan ini tidak dapat dibatalkan.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:3px 7px">
                                            <i class="fas fa-trash" style="font-size:.7rem"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center py-4" style="color:var(--text-muted)">
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
                    <li class="nav-item"><a class="nav-link" href="#" onclick="showTab('pics',this);return false" style="padding:6px 10px">PICs</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" onclick="showTab('activity',this);return false" style="padding:6px 10px">Activity</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" onclick="showTab('transaction',this);return false" style="padding:6px 10px">PO</a></li>
                </ul>

                {{-- Tab Overview --}}
                <div id="tab-overview">
                    @foreach([
                        ['PIC Utama',$selectedCustomer->pic_name],
                        ['Jabatan',$selectedCustomer->pic_position??'-'],
                        ['Phone',$selectedCustomer->phone??'-'],
                        ['Email',$selectedCustomer->email??'-'],
                        ['Industry',$selectedCustomer->industry??'-'],
                        ['Location',$selectedCustomer->location??'-'],
                        ['Sales PIC',$selectedCustomer->salesUser?->name??'-'],
                        ['Customer Since',$selectedCustomer->customer_since?->format('d M Y')??'-'],
                    ] as $f)
                    <div class="d-flex justify-content-between py-1" style="border-bottom:1px solid #f9fafb;font-size:.77rem">
                        <span style="color:var(--text-muted);min-width:90px">{{ $f[0] }}</span>
                        <span style="font-weight:500;text-align:right;max-width:55%">{{ $f[1] }}</span>
                    </div>
                    @endforeach

                    @if($selectedCustomer->productItems && $selectedCustomer->productItems->count())
                    <div class="mt-2 pt-2" style="border-top:1px solid #f3f4f6">
                        <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px">Kebutuhan Layanan</div>
                        @foreach($selectedCustomer->productItems as $cp)
                        <div style="font-size:.78rem">
                            • {{ $cp->display_name }}
                            @if($cp->unit)<span style="color:var(--text-muted);font-size:.7rem">{{ $cp->unit }}</span>@endif
                        </div>
                        @endforeach
                    </div>
                    @endif

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
                    </div>
                    <div class="row g-1 mb-3">
                        @foreach([['phone','Log Call','#d1fae5','#059669',"quickActCust('Call')"],['building','Visit','#dbeafe','#2563eb',"quickActCust('Visit')"],['envelope','Email','#fef3c7','#d97706',"quickActCust('Email')"],['sticky-note','Note','#f3e8ff','#7c3aed',"quickActCust('Note')"]] as $qa)
                        <div class="col-3">
                            <div class="quick-action-btn" onclick="{{ $qa[4] }}" style="padding:8px 4px;cursor:pointer">
                                <div class="qa-icon" style="width:28px;height:28px;background:{{ $qa[2] }}"><i class="fas fa-{{ $qa[0] }}" style="color:{{ $qa[3] }};font-size:.7rem"></i></div>
                                <span class="qa-label" style="font-size:.62rem">{{ $qa[1] }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary flex-fill" style="font-size:.75rem"
                            onclick="openEditModal({{ $selectedCustomer->id }})">
                            <i class="fas fa-edit me-1"></i> Edit
                        </button>
                        <form method="POST" action="{{ route('customers.destroy', $selectedCustomer) }}" class="flex-fill"
                            onsubmit="return confirm('Apakah Anda yakin ingin menghapus {{ addslashes($selectedCustomer->company_name) }}? Tindakan ini tidak dapat dibatalkan.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100" style="font-size:.75rem"><i class="fas fa-trash me-1"></i> Hapus</button>
                        </form>
                    </div>

                    {{-- Transfer Sales (Admin only) --}}
                    @if(auth()->user()->isAdmin())
                    <div class="mt-3 pt-3" style="border-top:1px solid #f3f4f6">
                        <div style="font-size:.75rem;font-weight:600;color:var(--text-muted);margin-bottom:8px">Pindah Sales PIC</div>
                        <form method="POST" action="{{ route('customers.transfer-sales', $selectedCustomer) }}">
                            @csrf @method('PATCH')
                            <div class="d-flex gap-2">
                                <select name="user_id" class="form-select form-select-sm flex-fill">
                                    @foreach($salesUsers as $su)
                                    <option value="{{ $su->id }}" {{ $selectedCustomer->user_id==$su->id ? 'selected' : '' }}>{{ $su->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-warning" style="font-size:.75rem;white-space:nowrap">Pindah</button>
                            </div>
                        </form>
                    </div>
                    @endif
                </div>

                {{-- Tab PICs --}}
                <div id="tab-pics" style="display:none">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong style="font-size:.8rem">Daftar PIC</strong>
                        <button class="btn btn-sm btn-primary" style="font-size:.72rem;padding:3px 8px" data-bs-toggle="modal" data-bs-target="#addCustPicModal">
                            <i class="fas fa-plus me-1"></i> Add
                        </button>
                    </div>
                    {{-- PIC Utama --}}
                    <div class="d-flex align-items-start gap-2 mb-3 pb-2" style="border-bottom:1px solid #f3f4f6">
                        <div style="width:32px;height:32px;background:#dbeafe;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fas fa-user" style="color:#2563eb;font-size:.65rem"></i>
                        </div>
                        <div style="flex:1">
                            <div style="font-size:.8rem;font-weight:600">{{ $selectedCustomer->pic_name }}
                                <span style="font-size:.65rem;background:#dbeafe;color:#1d4ed8;padding:1px 6px;border-radius:10px;margin-left:4px">Utama</span>
                            </div>
                            @if($selectedCustomer->pic_position)<div style="font-size:.72rem;color:var(--text-muted)">{{ $selectedCustomer->pic_position }}</div>@endif
                            @if($selectedCustomer->phone)<div style="font-size:.72rem">{{ $selectedCustomer->phone }}</div>@endif
                            @if($selectedCustomer->email)<div style="font-size:.72rem;color:var(--primary)">{{ $selectedCustomer->email }}</div>@endif
                        </div>
                    </div>
                    {{-- PIC tambahan --}}
                    @forelse($selectedCustomer->pics as $pic)
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <div style="width:32px;height:32px;background:#f3f4f6;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fas fa-user" style="color:#6b7280;font-size:.65rem"></i>
                        </div>
                        <div style="flex:1">
                            <div style="font-size:.8rem;font-weight:600">{{ $pic->pic_name }}</div>
                            @if($pic->pic_position)<div style="font-size:.72rem;color:var(--text-muted)">{{ $pic->pic_position }}</div>@endif
                            @if($pic->phone)<div style="font-size:.72rem">{{ $pic->phone }}</div>@endif
                            @if($pic->email)<div style="font-size:.72rem;color:var(--primary)">{{ $pic->email }}</div>@endif
                        </div>
                        <form method="POST" action="{{ route('customers.pics.destroy', [$selectedCustomer, $pic]) }}"
                            onsubmit="return confirm('Hapus PIC {{ addslashes($pic->pic_name) }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm p-0" style="color:#ef4444;background:none;border:none"><i class="fas fa-times"></i></button>
                        </form>
                    </div>
                    @empty
                    <div class="text-center py-3" style="color:var(--text-muted);font-size:.8rem">Belum ada PIC tambahan.</div>
                    @endforelse
                </div>

                {{-- Tab Activity --}}
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

                {{-- Tab PO --}}
                <div id="tab-transaction" style="display:none">
                    <strong style="font-size:.8rem;display:block;margin-bottom:10px">Purchase Orders</strong>
                    @forelse($selectedCustomer->deliveryOrders->sortByDesc('order_date') as $do)
                    <div class="d-flex align-items-start gap-2 mb-3 pb-2" style="border-bottom:1px solid #f9fafb">
                        <div style="width:32px;height:32px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fas fa-ship" style="font-size:.7rem;color:#2563eb"></i>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div style="font-size:.78rem;font-weight:600">{{ $do->do_number }}</div>
                            <div style="font-size:.7rem;color:var(--text-muted)">{{ $do->vendor?->vendor_name ?? "-" }}</div>
                            <div style="font-size:.7rem;color:var(--text-muted)">{{ \Carbon\Carbon::parse($do->order_date)->format('d M Y') }}</div>
                        </div>
                        <div class="text-end" style="flex-shrink:0">
                            <div style="font-size:.75rem;font-weight:600">{{ idrm($do->total_revenue) }}</div>
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
{{-- Add Customer --}}
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Add Customer Baru</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" action="{{ route('customers.store') }}" id="addCustomerForm">@csrf
            <div class="modal-body"><div class="row g-3">
                <div class="col-12">
                    <div class="alert alert-info py-2 mb-0" style="font-size:.74rem">
                        <i class="fas fa-info-circle me-1"></i> Customer dari menu ini otomatis berstatus <strong>Existing</strong> dan akan langsung membuat <strong>Lead</strong> dengan stage <strong>Maintaining</strong>.
                    </div>
                </div>
                <div class="col-12"><div style="font-size:.78rem;font-weight:600;color:var(--primary)"><i class="fas fa-building me-1"></i> Info Perusahaan</div></div>
                <div class="col-md-6"><label class="form-label">Company Name <span class="text-danger">*</span></label><input type="text" name="company_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Industry</label><input type="text" name="industry" class="form-control" placeholder="Manufaktur, Retail, dll"></div>
                <div class="col-md-6"><label class="form-label">Lokasi</label><input type="text" name="location" class="form-control" placeholder="Kota/Wilayah"></div>
                <div class="col-md-6"><label class="form-label">Customer Since</label><input type="date" name="customer_since" class="form-control"></div>
                <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                <div class="col-12 mt-2"><div style="font-size:.78rem;font-weight:600;color:var(--primary)"><i class="fas fa-user me-1"></i> PIC Utama</div></div>
                <div class="col-md-6"><label class="form-label">Nama PIC <span class="text-danger">*</span></label><input type="text" name="pic_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Jabatan PIC</label><input type="text" name="pic_position" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Phone <span class="text-danger">*</span></label><input type="text" name="phone" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Status</label>
                    <input type="text" class="form-control" value="Existing" disabled>
                    <input type="hidden" name="status" value="Existing"></div>
                <div class="col-md-6">@include('components.sales-pic-field')</div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>

                {{-- Tambahan PICs --}}
                <div class="col-12 mt-1">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div style="font-size:.78rem;font-weight:600;color:var(--primary)"><i class="fas fa-users me-1"></i> PIC Tambahan</div>
                        <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px" onclick="addCustPicRow('addCustPicsContainer')"><i class="fas fa-plus me-1"></i> Add PIC</button>
                    </div>
                    <div id="addCustPicsContainer"></div>
                </div>

                {{-- Kebutuhan Layanan --}}
                <div class="col-12 mt-1">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div style="font-size:.78rem;font-weight:600;color:var(--primary)"><i class="fas fa-box me-1"></i> Kebutuhan Layanan</div>
                        <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px" onclick="addCustProductRow('addCustProductsContainer')"><i class="fas fa-plus me-1"></i> Add Layanan</button>
                    </div>
                    <div id="addCustProductsContainer"></div>
                </div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div></div>
</div>

{{-- Edit Customer --}}
<div class="modal fade" id="editCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Edit Customer</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" id="editCustomerForm">@csrf @method('PUT')
            <input type="hidden" name="pics_submitted" value="1">
            <input type="hidden" name="products_submitted" value="1">
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label">Company Name</label><input type="text" name="company_name" id="editCompanyName" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Industry</label><input type="text" name="industry" id="editIndustry" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">PIC Name</label><input type="text" name="pic_name" id="editPicName" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Jabatan PIC</label><input type="text" name="pic_position" id="editPicPosition" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="editPhone" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="editEmail" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Location</label><input type="text" name="location" id="editLocation" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Status</label>
                    <input type="text" id="editStatusDisplay" class="form-control" value="" disabled>
                    <div class="form-text" style="font-size:.68rem">Status hanya berubah ke Existing via Sales Activity (stage Won/Closing).</div></div>
                <div class="col-md-6">@include('components.sales-pic-field', ['fieldId' => 'editSalesPIC'])</div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea></div>

                {{-- Tambahan PICs (edit) --}}
                <div class="col-12 mt-1">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div style="font-size:.78rem;font-weight:600;color:var(--primary)"><i class="fas fa-users me-1"></i> PIC Tambahan</div>
                        <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px" onclick="addCustPicRow('editCustPicsContainer')"><i class="fas fa-plus me-1"></i> Add PIC</button>
                    </div>
                    <div id="editCustPicsContainer"></div>
                </div>

                {{-- Kebutuhan Layanan (edit) --}}
                <div class="col-12 mt-1">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div style="font-size:.78rem;font-weight:600;color:var(--primary)"><i class="fas fa-box me-1"></i> Kebutuhan Layanan</div>
                        <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px" onclick="addCustProductRow('editCustProductsContainer')"><i class="fas fa-plus me-1"></i> Add Layanan</button>
                    </div>
                    <div id="editCustProductsContainer"></div>
                </div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div></div>
</div>

{{-- Add Activity (Customer) --}}
@if($selectedCustomer)
<div class="modal fade" id="addCustActivityModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Add Activity — {{ $selectedCustomer->company_name }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" action="{{ route('customers.activity.store', $selectedCustomer) }}">@csrf
            <div class="modal-body"><div class="row g-3">
                <div class="col-6"><label class="form-label">Jenis <span class="text-danger">*</span></label>
                    <select name="type" id="custActType" class="form-select" required><option value="Call">Call</option><option value="Visit">Visit</option><option value="Email">Email</option><option value="Note">Note</option><option value="Others">Task</option></select></div>
                <div class="col-6"><label class="form-label">Status</label>
                    <select name="status" class="form-select"><option value="Done">Done</option><option value="Planned">Planned</option><option value="Pending">Pending</option></select></div>
                <div class="col-12"><label class="form-label">Subject <span class="text-danger">*</span></label><input type="text" name="subject" class="form-control" required></div>
                <div class="col-6"><label class="form-label">Tanggal & Waktu</label><input type="datetime-local" name="activity_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}"></div>
                <div class="col-6"><label class="form-label">Sales PIC</label>
                    <select name="user_id" class="form-select" required>
                        @foreach($salesUsers as $su)<option value="{{ $su->id }}" {{ $selectedCustomer->user_id==$su->id?'selected':'' }}>{{ $su->name }}</option>@endforeach
                    </select></div>
                <div class="col-12"><label class="form-label">Keterangan</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div></div>
</div>

{{-- Add PIC (Customer) --}}
<div class="modal fade" id="addCustPicModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title fw-bold">Tambah PIC — {{ $selectedCustomer->company_name }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" action="{{ route('customers.pics.store', $selectedCustomer) }}">@csrf
            <div class="modal-body"><div class="row g-3">
                <div class="col-6"><label class="form-label">Nama PIC <span class="text-danger">*</span></label><input type="text" name="pic_name" class="form-control" required></div>
                <div class="col-6"><label class="form-label">Jabatan</label><input type="text" name="pic_position" class="form-control"></div>
                <div class="col-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
                <div class="col-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary btn-sm">Simpan PIC</button></div>
        </form>
    </div></div>
</div>
@endif


<datalist id="vendorServiceOptions">
    @foreach(($vendorServices ?? collect()) as $svc)
        <option value="{{ $svc->service_name }}">{{ $svc->vendor?->company_name ?? $svc->vendor?->vendor_name ?? '' }}</option>
    @endforeach
</datalist>

@endsection

@push('scripts')
<script>
@php
    $customerEditPayload = $customers->getCollection()
        ->merge($selectedCustomer ? collect([$selectedCustomer]) : collect())
        ->unique('id')
        ->values()
        ->mapWithKeys(function ($c) {
            return [$c->id => [
                'id' => $c->id,
                'company_name' => $c->company_name,
                'pic_name' => $c->pic_name,
                'pic_position' => $c->pic_position,
                'phone' => $c->phone,
                'email' => $c->email,
                'industry' => $c->industry,
                'location' => $c->location,
                'address' => $c->address,
                'status' => $c->status,
                'user_id' => (string) $c->user_id,
                'notes' => $c->notes,
                'products' => $c->products,
                'product_items' => $c->relationLoaded('productItems')
                    ? $c->productItems->map(function ($p) {
                        return [
                            'service_name' => $p->display_name,
                            'qty' => $p->qty,
                            'unit' => $p->unit,
                        ];
                    })->values()
                    : [],
                'pics' => $c->relationLoaded('pics')
                    ? $c->pics->map(function ($p) {
                        return [
                            'pic_name' => $p->pic_name,
                            'pic_position' => $p->pic_position,
                            'phone' => $p->phone,
                            'email' => $p->email,
                        ];
                    })->values()
                    : [],
            ]];
        });
@endphp
function showTab(tab, el) {
    document.querySelectorAll('#custTabs .nav-link').forEach(a => a.classList.remove('active'));
    el.classList.add('active');
    ['overview','pics','activity','transaction'].forEach(t => {
        const d = document.getElementById('tab-'+t);
        if(d) d.style.display = t===tab?'block':'none';
    });
}
// ── Data untuk modal edit customer ──
const customerEditData = @json($customerEditPayload);

function safeValue(value) {
    return value === null || value === undefined ? '' : value;
}

function splitProducts(products) {
    products = safeValue(products).trim();
    if (!products) return [];

    return products.split(',').map(function(item) {
        item = item.trim();
        if (!item) return null;

        const match = item.match(/^(.*?)\s*\((.*?)\)$/);
        if (match) {
            return {
                service_name: match[1].trim(),
                unit: match[2].trim()
            };
        }

        return {
            service_name: item,
            unit: ''
        };
    }).filter(Boolean);
}

// ── Inline PIC rows (Customer) ──
let custPicIdx = 0;
function addCustPicRow(containerId, data = {}) {
    const i = custPicIdx++;
    const html = `<div class="row g-2 mb-2 align-items-center" id="custPic_${i}">
        <div class="col-4"><input type="text" name="pics[${i}][pic_name]" class="form-control form-control-sm" placeholder="Nama PIC *" value="${escapeHtml(safeValue(data.pic_name))}" required></div>
        <div class="col-3"><input type="text" name="pics[${i}][pic_position]" class="form-control form-control-sm" placeholder="Jabatan" value="${escapeHtml(safeValue(data.pic_position))}"></div>
        <div class="col-2"><input type="text" name="pics[${i}][phone]" class="form-control form-control-sm" placeholder="Phone" value="${escapeHtml(safeValue(data.phone))}"></div>
        <div class="col-2"><input type="email" name="pics[${i}][email]" class="form-control form-control-sm" placeholder="Email" value="${escapeHtml(safeValue(data.email))}"></div>
        <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger p-1" onclick="document.getElementById('custPic_${i}').remove()"><i class="fas fa-times"></i></button></div>
    </div>`;
    document.getElementById(containerId).insertAdjacentHTML('beforeend', html);
}

// ── Inline Service rows (Customer) — field: service_name + catatan/rute ──
let custProdIdx = 0;
function addCustProductRow(containerId, data = {}) {
    const i = custProdIdx++;
    const html = `<div class="row g-2 mb-2 align-items-center" id="custProd_${i}">
        <div class="col-5"><input type="text" name="products_list[${i}][service_name]" list="vendorServiceOptions" class="form-control form-control-sm" placeholder="Nama Layanan *" value="${escapeHtml(safeValue(data.service_name))}" required></div>
        <div class="col-3"><input type="number" name="products_list[${i}][qty]" class="form-control form-control-sm" placeholder="Qty" min="0" step="0.01" value="${escapeHtml(safeValue(data.qty))}"></div>
        <div class="col-3"><input type="text" name="products_list[${i}][unit]" class="form-control form-control-sm" placeholder="Satuan (ton, kg...)" value="${escapeHtml(safeValue(data.unit))}"></div>
        <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger p-1" onclick="document.getElementById('custProd_${i}').remove()"><i class="fas fa-times"></i></button></div>
    </div>`;
    document.getElementById(containerId).insertAdjacentHTML('beforeend', html);
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function setSelectValue(selector, value) {
    const $el = $(selector);
    if (!$el.length) return;

    value = safeValue(value).toString();

    // Pastikan Select2 sudah aktif dulu, lalu trigger change agar tampilan ikut update.
    if (!$el.data('select2') && typeof initSelect2 === 'function') {
        initSelect2($el.closest('.modal'));
    }

    $el.val(value).trigger('change');
}

function openEditModal(id) {
    const data = customerEditData[id];
    if (!data) return;

    const modalEl = document.getElementById('editCustomerModal');
    const form = document.getElementById('editCustomerForm');

    form.action = `/customers/${id}`;
    document.getElementById('editCompanyName').value = safeValue(data.company_name);
    document.getElementById('editPicName').value = safeValue(data.pic_name);
    document.getElementById('editPicPosition').value = safeValue(data.pic_position);
    document.getElementById('editPhone').value = safeValue(data.phone);
    document.getElementById('editEmail').value = safeValue(data.email);
    document.getElementById('editIndustry').value = safeValue(data.industry);
    document.getElementById('editLocation').value = safeValue(data.location);
    document.getElementById('editNotes').value = safeValue(data.notes);
    document.getElementById('editStatusDisplay').value = safeValue(data.status);

    setSelectValue('#editSalesPIC', data.user_id);

    const picContainer = document.getElementById('editCustPicsContainer');
    const productContainer = document.getElementById('editCustProductsContainer');
    picContainer.innerHTML = '';
    productContainer.innerHTML = '';

    custPicIdx = 0;
    custProdIdx = 0;

    (data.pics || []).forEach(function(pic) {
        addCustPicRow('editCustPicsContainer', pic);
    });

    (data.product_items || []).forEach(function(product) {
        addCustProductRow('editCustProductsContainer', product);
    });

    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    setTimeout(function() {
        setSelectValue('#editSalesPIC', data.user_id);
    }, 150);
}
function quickActCust(type) {
    const el = document.getElementById('custActType');
    if(el) el.value = type;
    const m = document.getElementById('addCustActivityModal');
    if(m) new bootstrap.Modal(m).show();
}
// Prevent data loss add customer modal
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addCustomerForm');
    const modalEl = document.getElementById('addCustomerModal');
    if (!form || !modalEl) return;
    modalEl.addEventListener('hide.bs.modal', function(e) {
        const inputs = form.querySelectorAll('input[type=text],input[type=email],textarea');
        let hasData = false;
        inputs.forEach(i => { if (i.value.trim()) hasData = true; });
        if (hasData && !confirm('Data yang sudah diisi akan hilang. Tutup form?')) {
            e.preventDefault();
        }
    });
});
</script>
@endpush