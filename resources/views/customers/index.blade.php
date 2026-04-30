@extends('layouts.app')

@section('title', 'Database Customer')
@section('page-title', 'Database Customer')
@section('page-subtitle', 'Kelola data customer perusahaan')

@section('content')
<div class="row g-3">
    {{-- LEFT: Table --}}
    <div class="col-lg-8">
        {{-- Header --}}
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                    <i class="fas fa-plus me-1"></i> Add Customer
                </button>
                <button class="btn btn-outline-secondary btn-sm"><i class="fas fa-upload me-1"></i> Import Data</button>
                <button class="btn btn-outline-secondary btn-sm"><i class="fas fa-download me-1"></i> Export Data</button>
            </div>
            <div class="d-flex gap-3">
                <div class="text-center">
                    <div style="font-size:1.3rem;font-weight:800;color:#111">{{ $totalCustomer }}</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">Total Customer</div>
                </div>
                <div class="text-center px-3" style="border-left:1px solid var(--border-color)">
                    <div style="font-size:1.3rem;font-weight:800;color:#d97706">{{ $potentialCustomer }}</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">Potential Customer</div>
                </div>
                <div class="text-center px-3" style="border-left:1px solid var(--border-color)">
                    <div style="font-size:1.3rem;font-weight:800;color:#059669">{{ $existingCustomer }}</div>
                    <div style="font-size:.7rem;color:var(--text-muted)">Existing Customer</div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET">
            <div class="card mb-3">
                <div class="card-body p-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <select name="status" class="form-select form-select-sm">
                                <option value="all">All Status</option>
                                <option value="Existing" @selected($status == 'Existing')>Existing</option>
                                <option value="Potential" @selected($status == 'Potential')>Potential</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <select name="customer_type" class="form-select form-select-sm">
                                <option>All Type</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <select name="industry" class="form-select form-select-sm">
                                <option value="all">All Industry</option>
                                @foreach($industries as $ind)
                                <option value="{{ $ind }}" @selected($industry == $ind)>{{ $ind }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-3">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search company or PIC..." value="{{ $search }}">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
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
                            <th>No.</th>
                            <th>Company Name</th>
                            <th>PIC</th>
                            <th>Industry</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Last Activity</th>
                            <th>Total Revenue</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $i => $cust)
                        <tr>
                            <td>{{ $customers->firstItem() + $i }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar" style="width:30px;height:30px;font-size:.65rem;border-radius:6px">{{ $cust->logo_initials }}</div>
                                    <div>
                                        <a href="{{ route('customers.index', ['selected_id'=>$cust->id]) }}" style="font-weight:600;color:#111;text-decoration:none;font-size:.82rem">{{ $cust->company_name }}</a>
                                        <div style="font-size:.7rem;color:var(--text-muted)">{{ $cust->pic_name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:.78rem">{{ $cust->phone }}<br><span style="color:var(--primary);font-size:.7rem">{{ $cust->email }}</span></td>
                            <td style="font-size:.78rem">{{ $cust->industry }}</td>
                            <td style="font-size:.78rem">{{ $cust->location }}</td>
                            <td>
                                <span class="badge-{{ strtolower($cust->status) }}">{{ $cust->status }}</span>
                            </td>
                            <td style="font-size:.75rem">
                                @if($cust->activities->count())
                                    {{ $cust->activities->last()->activity_at->format('d M Y') }}<br>
                                    <span style="color:var(--text-muted)">{{ $cust->activities->last()->type }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td style="font-size:.78rem;font-weight:600">Rp {{ number_format($cust->total_revenue/1000000000,2) }}M</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('customers.index', ['selected_id'=>$cust->id]) }}" class="btn btn-sm btn-outline-primary" style="padding:3px 7px"><i class="fas fa-eye" style="font-size:.7rem"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-secondary" style="padding:3px 7px"><i class="fas fa-edit" style="font-size:.7rem"></i></a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center py-4 text-muted">Tidak ada data customer.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($customers->hasPages())
            <div class="card-footer p-3 d-flex justify-content-between align-items-center">
                <span style="font-size:.78rem;color:var(--text-muted)">Showing {{ $customers->firstItem() }} to {{ $customers->lastItem() }} of {{ $customers->total() }} entries</span>
                {{ $customers->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- RIGHT: Customer Detail --}}
    @if($selectedCustomer)
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body p-3">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="user-avatar" style="width:44px;height:44px;border-radius:8px">{{ $selectedCustomer->logo_initials }}</div>
                        <div>
                            <div style="font-weight:700;font-size:.9rem">{{ $selectedCustomer->company_name }}</div>
                            <div class="d-flex gap-1 mt-1">
                                <span class="badge-{{ strtolower($selectedCustomer->status) }}">{{ $selectedCustomer->status }}</span>
                                @if($selectedCustomer->value_tag === 'High Value')
                                <span style="background:#fef3c7;color:#b45309;font-size:.68rem;padding:2px 7px;border-radius:20px;font-weight:600">High Value</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-sm p-0" style="color:var(--text-muted)"><i class="fas fa-times"></i></button>
                </div>

                {{-- Tabs --}}
                <ul class="nav nav-tabs nav-sm mb-3" style="font-size:.75rem">
                    <li class="nav-item"><a class="nav-link active" href="#" style="padding:6px 10px">Overview</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" style="padding:6px 10px">Activity</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" style="padding:6px 10px">Transaction</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" style="padding:6px 10px">Notes</a></li>
                </ul>

                {{-- Info --}}
                @foreach([
                    ['label'=>'PIC','value'=>$selectedCustomer->pic_name],
                    ['label'=>'Position','value'=>$selectedCustomer->pic_position ?? '-'],
                    ['label'=>'Phone','value'=>$selectedCustomer->phone],
                    ['label'=>'Email','value'=>$selectedCustomer->email ?? '-'],
                    ['label'=>'Address','value'=>$selectedCustomer->address ?? '-'],
                    ['label'=>'Industry','value'=>$selectedCustomer->industry ?? '-'],
                    ['label'=>'Customer Since','value'=>$selectedCustomer->customer_since?->format('d F Y') ?? '-'],
                    ['label'=>'Sales PIC','value'=>$selectedCustomer->salesUser?->name ?? '-'],
                ] as $f)
                <div class="d-flex justify-content-between py-1" style="border-bottom:1px solid #f9fafb;font-size:.77rem">
                    <span style="color:var(--text-muted);min-width:100px">{{ $f['label'] }}</span>
                    <span style="font-weight:500;text-align:right">{{ $f['value'] }}</span>
                </div>
                @endforeach

                {{-- Stats --}}
                <div class="row g-2 mt-3 mb-3">
                    <div class="col-3 text-center">
                        <div style="font-size:1rem;font-weight:800;color:var(--primary)">Rp {{ number_format($selectedCustomer->total_revenue/1000000000,2) }}M</div>
                        <div style="font-size:.65rem;color:var(--text-muted)">Total Revenue</div>
                        <div style="font-size:.65rem;color:#059669">+15.6%</div>
                    </div>
                    <div class="col-3 text-center">
                        <div style="font-size:1rem;font-weight:800;color:#374151">{{ $selectedCustomer->deliveryOrders->count() }}</div>
                        <div style="font-size:.65rem;color:var(--text-muted)">Total DO</div>
                        <div style="font-size:.65rem;color:#059669">+12 Shipment</div>
                    </div>
                    <div class="col-3 text-center">
                        <div style="font-size:.78rem;font-weight:700">{{ $selectedCustomer->deliveryOrders->max('order_date') ? \Carbon\Carbon::parse($selectedCustomer->deliveryOrders->max('order_date'))->diffForHumans() : '-' }}</div>
                        <div style="font-size:.65rem;color:var(--text-muted)">Last Order</div>
                    </div>
                    <div class="col-3 text-center">
                        <div style="font-size:.78rem;font-weight:700">2.1 / mo</div>
                        <div style="font-size:.65rem;color:var(--text-muted)">Frequency</div>
                        <div style="font-size:.65rem;color:var(--text-muted)">≈ Rata-rata</div>
                    </div>
                </div>

                {{-- Recent Activity --}}
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong style="font-size:.8rem">Recent Activity</strong>
                    <a href="#" style="font-size:.72rem;color:var(--primary)">View All</a>
                </div>
                @foreach($selectedCustomer->activities->take(3) as $act)
                <div class="d-flex gap-2 mb-2">
                    <div class="activity-icon" style="width:26px;height:26px;background:{{ $act->type === 'Call' ? '#d1fae5' : '#fef3c7' }}">
                        <i class="fas fa-{{ $act->type_icon }}" style="font-size:.65rem;color:{{ $act->type === 'Call' ? '#059669' : '#d97706' }}"></i>
                    </div>
                    <div class="flex-1">
                        <span style="font-size:.72rem;font-weight:600;color:{{ $act->type === 'Call' ? '#059669' : '#d97706' }}">{{ $act->type }}</span>
                        <div style="font-size:.72rem">{{ Str::limit($act->description, 50) }}</div>
                        <div style="font-size:.67rem;color:var(--text-muted)">{{ $act->salesUser?->name }} · {{ $act->activity_at->format('d M Y H:i') }}</div>
                    </div>
                </div>
                @endforeach

                {{-- Latest DO --}}
                @if($selectedCustomer->deliveryOrders->count())
                <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                    <strong style="font-size:.8rem">Latest Transaction (DO)</strong>
                    <a href="#" style="font-size:.72rem;color:var(--primary)">View All</a>
                </div>
                <table class="table crm-table mb-2" style="font-size:.72rem">
                    <thead><tr><th>No. DO</th><th>Service</th><th>Route</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($selectedCustomer->deliveryOrders->take(3) as $do)
                        <tr>
                            <td>{{ $do->do_number }}</td>
                            <td>{{ $do->service_type }}</td>
                            <td>{{ $do->route }}</td>
                            <td>{{ $do->currency }} {{ number_format($do->amount, 0) }}</td>
                            <td><span class="badge-{{ strtolower($do->status) }}">{{ $do->status }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif

                {{-- Quick Action --}}
                <div class="row g-1 mt-2">
                    @foreach([['phone','Call','#d1fae5','#059669'],['building','Schedule Visit','#dbeafe','#2563eb'],['envelope','Send Email','#fef3c7','#d97706'],['plus','Add Activity','#f3f4f6','#374151']] as $qa)
                    <div class="col-3">
                        <div class="quick-action-btn" style="padding:10px 4px">
                            <div class="qa-icon" style="width:28px;height:28px;background:{{ $qa[2] }}">
                                <i class="fas fa-{{ $qa[0] }}" style="color:{{ $qa[3] }};font-size:.7rem"></i>
                            </div>
                            <span class="qa-label" style="font-size:.62rem">{{ $qa[1] }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- Add Customer Modal --}}
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Add Customer Baru</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('customers.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">Company Name *</label><input type="text" name="company_name" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">PIC Name *</label><input type="text" name="pic_name" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Phone *</label><input type="text" name="phone" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                        <div class="col-6"><label class="form-label">Industry</label><input type="text" name="industry" class="form-control"></div>
                        <div class="col-6"><label class="form-label">Location</label><input type="text" name="location" class="form-control"></div>
                        <div class="col-6">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="Potential">Potential</option>
                                <option value="Existing">Existing</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Sales PIC *</label>
                            <select name="sales_user_id" class="form-select" required>
                                @foreach(\App\Models\SalesUser::all() as $su)
                                <option value="{{ $su->id }}">{{ $su->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Address</label><input type="text" name="address" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
