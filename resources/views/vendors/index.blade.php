@extends('layouts.app')

@section('title', 'Database Vendor')
@section('page-title', 'Database Vendor')
@section('page-subtitle', 'Kelola data vendor & supplier perusahaan')

@section('content')
<div class="row g-3">
    {{-- LEFT: Vendor Table --}}
    <div class="col-lg-8">
        {{-- Header stats --}}
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Add Vendor</button>
                <button class="btn btn-outline-secondary btn-sm"><i class="fas fa-upload me-1"></i> Import Vendor</button>
                <button class="btn btn-outline-secondary btn-sm"><i class="fas fa-download me-1"></i> Export Data</button>
            </div>
            <div class="d-flex gap-3">
                @foreach([
                    ['value'=>$totalVendor,'label'=>'Total Vendor','color'=>'#111'],
                    ['value'=>$activeVendor,'label'=>'Active Vendor','color'=>'#059669'],
                    ['value'=>$nonActiveVendor,'label'=>'Non-Active','color'=>'#dc2626'],
                    ['value'=>$preferredVendor,'label'=>'Preferred Vendor','color'=>'#d97706'],
                ] as $stat)
                <div class="text-center {{ !$loop->first ? 'ps-3' : '' }}" style="{{ !$loop->first ? 'border-left:1px solid var(--border-color)' : '' }}">
                    <div style="font-size:1.2rem;font-weight:800;color:{{ $stat['color'] }}">{{ $stat['value'] }}</div>
                    <div style="font-size:.68rem;color:var(--text-muted)">{{ $stat['label'] }}</div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET">
            <div class="card mb-3">
                <div class="card-body p-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <select name="vendor_type" class="form-select form-select-sm">
                                <option value="all">All Type</option>
                                @foreach(['Shipping Line','Trucking','Air Freight','Others'] as $t)
                                <option value="{{ $t }}" @selected($type == $t)>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <select name="service" class="form-select form-select-sm"><option>All Service</option></select>
                        </div>
                        <div class="col-auto">
                            <select name="location_filter" class="form-select form-select-sm"><option>All Location</option></select>
                        </div>
                        <div class="col-auto">
                            <select name="status" class="form-select form-select-sm">
                                <option value="all">All Status</option>
                                <option value="Active" @selected($status == 'Active')>Active</option>
                                <option value="Non-Active" @selected($status == 'Non-Active')>Non-Active</option>
                            </select>
                        </div>
                        <div class="col-3">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search vendor / PIC / phone..." value="{{ $search }}">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                            <a href="{{ route('vendors.index') }}" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
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
                            <th>Vendor Name</th>
                            <th>PIC Vendor</th>
                            <th>Contact</th>
                            <th>Service Type</th>
                            <th>Coverage Area</th>
                            <th>Rating</th>
                            <th>Last Order</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vendors as $i => $vendor)
                        <tr>
                            <td>{{ $vendors->firstItem() + $i }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar" style="width:30px;height:30px;font-size:.65rem;border-radius:6px;background:#374151">{{ $vendor->logo_initials }}</div>
                                    <div>
                                        <a href="{{ route('vendors.index', ['selected_id'=>$vendor->id]) }}" style="font-weight:600;color:#111;text-decoration:none;font-size:.82rem">{{ $vendor->vendor_name }}</a>
                                        @if($vendor->is_preferred)
                                        <div><span style="background:#fef3c7;color:#b45309;font-size:.62rem;padding:1px 5px;border-radius:10px">⭐ Preferred</span></div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:.78rem">
                                {{ $vendor->pic_name }}<br>
                                <span style="color:var(--text-muted)">{{ $vendor->pic_position }}</span>
                            </td>
                            <td style="font-size:.75rem">
                                {{ $vendor->phone }}<br>
                                <span style="color:var(--primary)">{{ $vendor->email }}</span>
                            </td>
                            <td>
                                <span style="font-size:.72rem;padding:2px 7px;border-radius:20px;font-weight:600;background:{{ $vendor->vendor_type === 'Shipping Line' ? '#dbeafe' : ($vendor->vendor_type === 'Trucking' ? '#d1fae5' : '#fef3c7') }};color:{{ $vendor->vendor_type === 'Shipping Line' ? '#1d4ed8' : ($vendor->vendor_type === 'Trucking' ? '#059669' : '#b45309') }}">
                                    {{ $vendor->vendor_type }}
                                </span>
                            </td>
                            <td style="font-size:.75rem">{{ $vendor->coverage_area }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <span style="color:#f59e0b">★</span>
                                    <span style="font-size:.78rem;font-weight:600">{{ $vendor->rating }}</span>
                                </div>
                            </td>
                            <td style="font-size:.75rem">
                                @if($vendor->deliveryOrders->count())
                                    {{ $vendor->deliveryOrders->max('order_date') ? \Carbon\Carbon::parse($vendor->deliveryOrders->max('order_date'))->format('d M Y') : '-' }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="{{ $vendor->status === 'Active' ? 'badge-existing' : 'badge-overdue' }}">{{ $vendor->status }}</span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('vendors.index', ['selected_id'=>$vendor->id]) }}" class="btn btn-sm btn-outline-primary" style="padding:3px 7px"><i class="fas fa-eye" style="font-size:.7rem"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-secondary" style="padding:3px 7px"><i class="fas fa-edit" style="font-size:.7rem"></i></a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="10" class="text-center py-4 text-muted">Tidak ada data vendor.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($vendors->hasPages())
            <div class="card-footer p-3 d-flex justify-content-between align-items-center">
                <span style="font-size:.78rem;color:var(--text-muted)">Showing {{ $vendors->firstItem() }} to {{ $vendors->lastItem() }} of {{ $vendors->total() }} entries</span>
                {{ $vendors->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- RIGHT: Vendor Detail --}}
    @if($selectedVendor)
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body p-3">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="user-avatar" style="width:44px;height:44px;border-radius:8px;background:#374151">{{ $selectedVendor->logo_initials }}</div>
                        <div>
                            <div style="font-weight:700;font-size:.9rem">{{ $selectedVendor->vendor_name }}</div>
                            <div class="d-flex gap-1 mt-1">
                                @if($selectedVendor->is_preferred)
                                <span style="background:#fef3c7;color:#b45309;font-size:.68rem;padding:2px 7px;border-radius:20px;font-weight:600">⭐ Preferred Vendor</span>
                                @endif
                                <span class="{{ $selectedVendor->status === 'Active' ? 'badge-existing' : 'badge-overdue' }}">{{ $selectedVendor->status }}</span>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-sm p-0" style="color:var(--text-muted)"><i class="fas fa-times"></i></button>
                </div>

                <ul class="nav nav-tabs nav-sm mb-3" style="font-size:.75rem">
                    @foreach(['Overview','Rates','Transactions','Performance','Notes'] as $tab)
                    <li class="nav-item"><a class="nav-link {{ $loop->first ? 'active' : '' }}" href="#" style="padding:5px 8px">{{ $tab }}</a></li>
                    @endforeach
                </ul>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong style="font-size:.8rem">Informasi Vendor</strong>
                    <a href="#" style="font-size:.72rem;color:var(--primary)"><i class="fas fa-edit me-1"></i>Edit</a>
                </div>
                @foreach([
                    ['icon'=>'user','label'=>'PIC Vendor','value'=>$selectedVendor->pic_name],
                    ['icon'=>'briefcase','label'=>'Position','value'=>$selectedVendor->pic_position ?? '-'],
                    ['icon'=>'phone','label'=>'Phone','value'=>$selectedVendor->phone],
                    ['icon'=>'envelope','label'=>'Email','value'=>$selectedVendor->email ?? '-'],
                    ['icon'=>'map-marker-alt','label'=>'Address','value'=>$selectedVendor->address ?? '-'],
                    ['icon'=>'truck','label'=>'Service Type','value'=>$selectedVendor->vendor_type],
                    ['icon'=>'globe','label'=>'Coverage Area','value'=>$selectedVendor->coverage_area ?? '-'],
                    ['icon'=>'calendar','label'=>'Vendor Since','value'=>$selectedVendor->vendor_since?->format('d M Y') ?? '-'],
                    ['icon'=>'credit-card','label'=>'Payment Term','value'=>$selectedVendor->payment_term ?? '-'],
                ] as $f)
                <div class="d-flex gap-2 py-1" style="border-bottom:1px solid #f9fafb;font-size:.77rem">
                    <i class="fas fa-{{ $f['icon'] }}" style="width:14px;color:var(--text-muted);font-size:.72rem;margin-top:2px"></i>
                    <span style="color:var(--text-muted);min-width:90px">{{ $f['label'] }}</span>
                    <span style="font-weight:500">{{ $f['value'] }}</span>
                </div>
                @endforeach

                {{-- Performance --}}
                <div class="mt-3 mb-2"><strong style="font-size:.8rem">Performance Overview</strong></div>
                <div class="row g-2 mb-3">
                    <div class="col-3 text-center">
                        <div style="font-size:1rem;font-weight:800">{{ $selectedVendor->deliveryOrders->count() }}</div>
                        <div style="font-size:.65rem;color:var(--text-muted)">Total DO</div>
                    </div>
                    <div class="col-3 text-center">
                        <div style="font-size:1rem;font-weight:800;color:#059669">92%</div>
                        <div style="font-size:.65rem;color:var(--text-muted)">On-Time</div>
                    </div>
                    <div class="col-3 text-center">
                        <div style="font-size:1rem;font-weight:800;color:#dc2626">8%</div>
                        <div style="font-size:.65rem;color:var(--text-muted)">Delay</div>
                    </div>
                    <div class="col-3 text-center">
                        <div style="font-size:1rem;font-weight:800;color:#f59e0b">{{ $selectedVendor->rating }}</div>
                        <div style="font-size:.65rem;color:var(--text-muted)">Rating</div>
                    </div>
                </div>

                {{-- Rates --}}
                @if($selectedVendor->rates->count())
                <div class="d-flex justify-content-between mb-2">
                    <strong style="font-size:.8rem">Rate History ({{ $selectedVendor->vendor_type }})</strong>
                    <a href="#" style="font-size:.72rem;color:var(--primary)">View All Rates</a>
                </div>
                <table class="table crm-table mb-3" style="font-size:.72rem">
                    <thead><tr><th>Route</th><th>Container</th><th>Price</th><th>Updated</th></tr></thead>
                    <tbody>
                        @foreach($selectedVendor->rates as $rate)
                        <tr>
                            <td>{{ $rate->route }}</td>
                            <td>{{ $rate->container_type }}</td>
                            <td>{{ $rate->currency }} {{ number_format($rate->price, 0) }}</td>
                            <td>{{ $rate->last_updated?->format('d M') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif

                {{-- Recent DO --}}
                @if($selectedVendor->deliveryOrders->count())
                <div class="d-flex justify-content-between mb-2">
                    <strong style="font-size:.8rem">Recent Transaction (DO)</strong>
                    <a href="#" style="font-size:.72rem;color:var(--primary)">View All</a>
                </div>
                <table class="table crm-table mb-3" style="font-size:.72rem">
                    <thead><tr><th>No. DO</th><th>Route</th><th>Service</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($selectedVendor->deliveryOrders->take(3) as $do)
                        <tr>
                            <td>{{ $do->do_number }}</td>
                            <td>{{ $do->route }}</td>
                            <td>{{ $do->service_type }}</td>
                            <td>{{ $do->currency }} {{ number_format($do->amount, 0) }}</td>
                            <td><span class="badge-{{ strtolower($do->status) }}">{{ $do->status }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif

                {{-- Quick Actions --}}
                <div class="row g-1">
                    @foreach([
                        ['phone','Call Vendor','#d1fae5','#059669'],
                        ['envelope','Send Email','#fef3c7','#d97706'],
                        ['plus','Add Order (DO)','#dbeafe','#2563eb'],
                        ['chart-line','Update Rate','#ede9fe','#7c3aed'],
                        ['star','Update Rating','#fef3c7','#f59e0b'],
                    ] as $qa)
                    <div class="col">
                        <div class="quick-action-btn" style="padding:10px 4px">
                            <div class="qa-icon" style="width:28px;height:28px;background:{{ $qa[2] }}">
                                <i class="fas fa-{{ $qa[0] }}" style="color:{{ $qa[3] }};font-size:.7rem"></i>
                            </div>
                            <span class="qa-label" style="font-size:.6rem">{{ $qa[1] }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
