@extends('layouts.app')
@section('title', 'Delivery Orders')
@section('page-title', 'Delivery Orders')
@section('page-subtitle', 'Kelola data DO, revenue, dan profit per layanan')

@section('content')
    <div class="row g-3">
        <div class="col-12">

            {{-- Header + KPI --}}
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPoModal">
                        <i class="fas fa-plus me-1"></i> Tambah DO
                    </button>
                    <a href="{{ route('delivery-orders.export', request()->query()) }}"
                        class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-download me-1"></i> Export Excel
                    </a>
                </div>
                <div class="d-flex gap-3 flex-wrap">
                    @foreach([[$volumeDo, 'Volume DO', '#111'], [$revenue, 'Revenue', '#111111'], [$grossProfit, 'Gross Profit', '#10b981']] as $s)
                        <div class="text-center {{ !$loop->first ? 'ps-3' : '' }}"
                            style="{{ !$loop->first ? 'border-left:1px solid var(--border-color)' : '' }}">
                            <div style="font-size:{{ $loop->index >= 1 ? '1rem' : '1.2rem' }};font-weight:800;color:{{ $s[2] }}">
                                {{ $loop->index >= 1 ? idr($s[0]) : $s[0] }}
                            </div>
                            <div style="font-size:.68rem;color:var(--text-muted)">{{ $s[1] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Filter --}}
            <form method="GET" action="{{ route('delivery-orders.index') }}">
                <div class="card mb-3">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2"><input type="date" name="start_date" class="form-control form-control-sm"
                                    value="{{ $startDate }}"></div>
                            <div class="col-md-2"><input type="date" name="end_date" class="form-control form-control-sm"
                                    value="{{ $endDate }}"></div>
                            <div class="col-md-2">
                                <select name="status" class="form-select form-select-sm">
                                    <option value="all">All Status</option>
                                    @foreach(['Done', 'In Progress', 'Cancelled'] as $st)
                                        <option value="{{ $st }}" @selected($status == $st)>{{ $st }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control form-control-sm"
                                    placeholder="Cari DO, customer, layanan..." value="{{ $search }}">
                            </div>
                            <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100"><i
                                        class="fas fa-search"></i></button></div>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Table --}}
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="font-size:13px">
                            <thead style="background:#f8f9fa">
                                <tr>
                                    <th class="px-3 py-2" style="width:28px"></th>
                                    <th class="px-3 py-2">No. DO</th>
                                    <th class="py-2">Customer</th>
                                    <th class="py-2">Vendor</th>
                                    <th class="py-2">Sales PIC</th>
                                    <th class="py-2">Revenue</th>
                                    <th class="py-2">HPP</th>
                                    <th class="py-2">Gross Profit</th>
                                    <th class="py-2">Margin</th>
                                    <th class="py-2">Status</th>
                                    <th class="py-2">Tgl Order</th>
                                    <th class="py-2">Type / Tracking</th>
                                    <th class="py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dos as $po)
                                    @php
                                        $sc = ['Done' => ['#d1fae5', '#059669'], 'In Progress' => ['#e5e5e5', '#111111'], 'Cancelled' => ['#fee2e2', '#dc2626']];
                                        $c = $sc[$po->status] ?? ['#f3f4f6', '#6b7280'];
                                    @endphp
                                    <tr id="po-row-{{ $po->id }}">
                                        <td class="px-3 py-2" style="text-align:center">
                                            <button class="btn btn-sm" style="padding:2px 6px;border:none;background:none;color:#6b7280"
                                                onclick="toggleDetail({{ $po->id }}, this)" title="Lihat detail item">
                                                <i class="fas fa-chevron-right" style="font-size:10px;transition:.2s"></i>
                                            </button>
                                        </td>
                                        <td class="px-3 py-2" style="font-weight:700;color:var(--primary)">{{ $po->do_number }}</td>
                                        <td class="py-2" style="font-size:12px">{{ $po->customer?->company_name ?? '-' }}</td>
                                        <td class="py-2" style="color:#6b7280;font-size:12px">{{ $po->vendor?->vendor_name ?? '-' }}</td>
                                        <td class="py-2" style="font-size:12px;font-weight:600">{{ $po->salesUser?->name ?? '-' }}</td>
                                        <td class="py-2" style="font-weight:600;color:var(--primary);white-space:nowrap">{{ idr($po->total_revenue) }}</td>
                                        <td class="py-2" style="color:#dc2626;font-size:12px;white-space:nowrap">{{ idr($po->total_cost) }}</td>
                                        <td class="py-2" style="font-weight:600;color:#10b981;white-space:nowrap">{{ idr($po->gross_profit) }}</td>
                                        <td class="py-2" style="font-size:12px;color:#6b7280">{{ $po->gross_margin }}%</td>
                                        <td class="py-2">
                                            <span style="font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600;background:{{ $c[0] }};color:{{ $c[1] }}">{{ $po->status }}</span>
                                        </td>
                                        <td class="py-2" style="color:#6b7280;font-size:12px">{{ $po->order_date?->format('d M Y') }}</td>
                                        <td class="py-2" style="color:#6b7280;font-size:11px">
                                            {{ $po->delivery_type ?? '-' }}<br>
                                            @if($po->tracking_number)
                                            <span style="font-size:10px;color:#111111">{{ $po->tracking_number }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2">
                                            <button class="btn btn-sm btn-outline-secondary" style="padding:3px 7px" onclick="openEditPo({{ $po->id }})">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>
                                            @if(auth()->user()->isAdmin())
                                            <form method="POST" action="{{ route('delivery-orders.destroy', $po) }}" class="d-inline"
                                                onsubmit="return confirm('Hapus DO {{ $po->do_number }}?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:3px 7px">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            @endif
                                        </td>
                                    </tr>
                                    {{-- Detail row (collapsed) --}}
                                    <tr id="po-detail-{{ $po->id }}" style="display:none;background:#f8faff">
                                        <td></td>
                                        <td colspan="11" class="px-3 py-2">
                                            <div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">
                                                Detail Item — {{ $po->do_number }}
                                                @if($po->notes) <span style="font-weight:400;color:#9ca3af;margin-left:8px"><i class="fas fa-sticky-note me-1"></i>{{ $po->notes }}</span> @endif
                                            </div>
                                            <table style="width:100%;font-size:12px;border-collapse:collapse">
                                                <thead>
                                                    <tr style="background:#e8f0fe">
                                                        <th style="padding:5px 8px;text-align:left;font-size:11px;color:#3b4a6b">Layanan</th>
                                                        <th style="padding:5px 8px;text-align:center;font-size:11px;color:#3b4a6b">Satuan</th>
                                                        <th style="padding:5px 8px;text-align:right;font-size:11px;color:#3b4a6b">Tonase</th>
                                                        <th style="padding:5px 8px;text-align:right;font-size:11px;color:#3b4a6b">Qty</th>
                                                        <th style="padding:5px 8px;text-align:right;font-size:11px;color:#3b4a6b">Harga Beli</th>
                                                        <th style="padding:5px 8px;text-align:right;font-size:11px;color:#3b4a6b">Harga Jual</th>
                                                        <th style="padding:5px 8px;text-align:right;font-size:11px;color:#3b4a6b">Subtotal Revenue</th>
                                                        <th style="padding:5px 8px;text-align:right;font-size:11px;color:#3b4a6b">Gross Profit</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($po->items as $item)
                                                    <tr style="border-bottom:1px solid #e5e7eb">
                                                        <td style="padding:5px 8px;font-weight:600">{{ $item->service_name }}</td>
                                                        <td style="padding:5px 8px;text-align:center;color:#6b7280">{{ $item->unit }}</td>
                                                        <td style="padding:5px 8px;text-align:right;color:#6b7280">{{ $item->tonnage !== null ? number_format($item->tonnage, 2, ',', '.') : '-' }}</td>
                                                        <td style="padding:5px 8px;text-align:right">{{ number_format($item->qty, 2, ',', '.') }}</td>
                                                        <td style="padding:5px 8px;text-align:right;color:#dc2626">{{ idr($item->buy_price) }}</td>
                                                        <td style="padding:5px 8px;text-align:right;color:var(--primary)">{{ idr($item->sell_price) }}</td>
                                                        <td style="padding:5px 8px;text-align:right;font-weight:600;color:var(--primary)">{{ idr($item->qty * $item->sell_price) }}</td>
                                                        <td style="padding:5px 8px;text-align:right;font-weight:600;color:#10b981">{{ idr(($item->sell_price - $item->buy_price) * $item->qty) }}</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr style="background:#f0f4ff;font-weight:700">
                                                        <td colspan="6" style="padding:5px 8px;text-align:right;font-size:11px;color:#6b7280">TOTAL</td>
                                                        <td style="padding:5px 8px;text-align:right;color:var(--primary)">{{ idr($po->total_revenue) }}</td>
                                                        <td style="padding:5px 8px;text-align:right;color:#10b981">{{ idr($po->gross_profit) }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center py-4" style="color:#9ca3af">Belum ada data DO pada
                                            periode ini</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($dos->hasPages())
                    <div class="px-3 py-2">{{ $dos->links() }}</div>@endif
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Tambah DO --}}
    <div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="addPoModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Tambah Delivery Order</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('delivery-orders.store') }}" id="addDoForm">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Customer <span class="text-danger">*</span></label>
                                <select name="customer_id" id="addCustomerSelect" class="form-select" onchange="onCustomerChange(this,'addLeadDisplay'); setDefaultSalesPic(this,'addSalesPicSelect')" required>
                                    <option value="">-- Pilih Customer --</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}" data-name="{{ strtolower(trim($c->company_name)) }}" data-user-id="{{ $c->user_id }}">{{ $c->company_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Vendor</label>
                                <select name="vendor_id" class="form-select" id="addVendorSelect" onchange="onVendorChange(this,'addItemsBody')">
                                    <option value="">-- Pilih Vendor --</option>
                                    @foreach($vendors as $s)
                                        <option value="{{ $s->id }}">{{ $s->vendor_name }} ({{ $s->vendor_type }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Linked Lead</label>
                                <input type="text" id="addLeadDisplay" class="form-control"
                                    placeholder="Otomatis dari Customer" readonly
                                    style="background:#f9fafb;cursor:default;color:#374151">
                                <input type="hidden" name="lead_id" id="addLeadHidden" value="">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sales PIC <span class="text-danger">*</span></label>
                                <select name="user_id" id="addSalesPicSelect" class="form-select" required>
                                    <option value="">-- Pilih Sales PIC --</option>
                                    @foreach($salesUsers as $u)
                                        <option value="{{ $u->id }}" @selected(auth()->id() === $u->id)>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tgl Order <span class="text-danger">*</span></label>
                                <input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}"
                                    required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-select" required>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Done">Done</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Currency</label>
                                <select name="currency" class="form-select">
                                    <option value="IDR">IDR</option>
                                    <option value="USD">USD</option>
                                    <option value="SGD">SGD</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control" placeholder="Keterangan tambahan">
                            </div>
                        </div>

                        {{-- Logistic Fields --}}
                        <hr class="my-2">
                        <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:8px">DETAIL PENGIRIMAN</div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Delivery Type</label>
                                <select name="delivery_type" id="addDeliveryType" class="form-select form-select-sm">
                                    <option value="">- Pilih -</option>
                                    @foreach(\App\Models\Vendor::serviceTypeOptions() as $dt)
                                        <option value="{{ $dt }}">{{ $dt }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Origin</label>
                                <input type="text" name="origin" class="form-control form-control-sm" placeholder="Kota asal">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Destination</label>
                                <input type="text" name="destination" class="form-control form-control-sm" placeholder="Kota tujuan">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tracking Number</label>
                                <input type="text" name="tracking_number" class="form-control form-control-sm" placeholder="Nomor resi / tracking">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Estimasi Tiba (ETA)</label>
                                <input type="date" name="estimated_arrival" class="form-control form-control-sm">
                            </div>
                        </div>

                        {{-- Line Items --}}
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div style="font-size:12px;font-weight:700;color:#374151">ITEM LAYANAN</div>
                            <button type="button" class="btn btn-outline-primary btn-sm"
                                onclick="addItemRow('addItemsBody')">
                                <i class="fas fa-plus me-1"></i> Tambah Item
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered mb-2" style="font-size:12px">
                                <thead style="background:#f8f9fa">
                                    <tr>
                                        <th style="min-width:200px">Nama Layanan <span class="text-danger">*</span></th>
                                        <th style="width:80px">Satuan</th>
                                        <th style="width:90px">Tonase</th>
                                        <th style="width:100px">Qty <span class="text-danger">*</span></th>
                                        <th style="width:140px">Harga Beli (HPP) <span class="text-danger">*</span></th>
                                        <th style="width:140px">Harga Jual <span class="text-danger">*</span></th>
                                        <th style="width:110px">Gross Profit</th>
                                        <th style="width:40px"></th>
                                    </tr>
                                </thead>
                                <tbody id="addItemsBody">
                                    {{-- Diisi via JS addItemRow() saat modal dibuka --}}
                                </tbody>
                                <tfoot>
                                    <tr style="background:#f8f9fa;font-weight:700">
                                        <td colspan="4" class="text-end">Total:</td>
                                        <td id="addTotalRevenue" class="text-end" style="color:var(--primary)">Rp 0</td>
                                        <td id="addTotalProfit" class="text-end" style="color:#10b981">Rp 0</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan DO</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Edit DO --}}
    <div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editPoModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Edit Delivery Order — <span id="editPoNumber"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editDoForm">
                    @csrf @method('PUT')
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Customer <span class="text-danger">*</span></label>
                                <select name="customer_id" id="epCustomer" class="form-select" onchange="onCustomerChange(this,'epLeadDisplay'); setDefaultSalesPic(this,'epSalesPicSelect')" required>
                                    <option value="">-- Pilih Customer --</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}" data-name="{{ strtolower(trim($c->company_name)) }}" data-user-id="{{ $c->user_id }}">{{ $c->company_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Vendor</label>
                                <select name="vendor_id" id="epVendor" class="form-select">
                                    <option value="">-- Pilih Vendor --</option>
                                    @foreach($vendors as $s)
                                        <option value="{{ $s->id }}">{{ $s->vendor_name }} ({{ $s->vendor_type }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Linked Lead</label>
                                <input type="text" id="epLeadDisplay" class="form-control"
                                    placeholder="Otomatis dari Customer" readonly
                                    style="background:#f9fafb;cursor:default;color:#374151">
                                <input type="hidden" name="lead_id" id="epLeadHidden" value="">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sales PIC <span class="text-danger">*</span></label>
                                <select name="user_id" id="epSalesPicSelect" class="form-select" required>
                                    <option value="">-- Pilih Sales PIC --</option>
                                    @foreach($salesUsers as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tgl Order <span class="text-danger">*</span></label>
                                <input type="date" name="order_date" id="epDate" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" id="epStatus" class="form-select" required>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Done">Done</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Currency</label>
                                <select name="currency" id="epCurrency" class="form-select">
                                    <option value="IDR">IDR</option>
                                    <option value="USD">USD</option>
                                    <option value="SGD">SGD</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" id="epNotes" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Delivery Type</label>
                                <select name="delivery_type" id="epDeliveryType" class="form-select">
                                    <option value="">- Pilih -</option>
                                    @foreach(\App\Models\Vendor::serviceTypeOptions() as $dt)
                                        <option value="{{ $dt }}">{{ $dt }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Origin</label>
                                <input type="text" name="origin" id="epOrigin" class="form-control" placeholder="Kota asal">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Destination</label>
                                <input type="text" name="destination" id="epDestination" class="form-control" placeholder="Kota tujuan">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tracking Number</label>
                                <input type="text" name="tracking_number" id="epTracking" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Estimated Arrival</label>
                                <input type="date" name="estimated_arrival" id="epEta" class="form-control">
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div style="font-size:12px;font-weight:700;color:#374151">ITEM LAYANAN</div>
                            <button type="button" class="btn btn-outline-primary btn-sm"
                                onclick="addItemRow('editItemsBody')">
                                <i class="fas fa-plus me-1"></i> Tambah Item
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered mb-2" style="font-size:12px">
                                <thead style="background:#f8f9fa">
                                    <tr>
                                        <th style="min-width:200px">Nama Layanan</th>
                                        <th style="width:80px">Satuan</th>
                                        <th style="width:90px">Tonase</th>
                                        <th style="width:100px">Qty</th>
                                        <th style="width:140px">Harga Beli (HPP)</th>
                                        <th style="width:140px">Harga Jual</th>
                                        <th style="width:110px">Gross Profit</th>
                                        <th style="width:40px"></th>
                                    </tr>
                                </thead>
                                <tbody id="editItemsBody"></tbody>
                                <tfoot>
                                    <tr style="background:#f8f9fa;font-weight:700">
                                        <td colspan="4" class="text-end">Total:</td>
                                        <td id="editTotalRevenue" class="text-end" style="color:var(--primary)">Rp 0</td>
                                        <td id="editTotalProfit" class="text-end" style="color:#10b981">Rp 0</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            let itemIndex = 1;

            function formatNum(n) {
                if (!n && n !== 0) return '';
                return Math.round(n).toLocaleString('id-ID');
            }

            function parseNum(str) {
                if (!str) return 0;
                return parseFloat(String(str).replace(/\./g, '').replace(',', '.')) || 0;
            }

            function formatPriceInput(el) {
                const raw = parseNum(el.value);
                if (raw > 0) el.value = formatNum(raw);
                calcRow(el);
            }

            function formatRp(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }

            function syncHidden(el, hiddenClass) {
                const row = el.closest('tr');
                const hidden = row.querySelector('.' + hiddenClass);
                // Simpan posisi cursor
                const pos = el.selectionStart;
                const raw = el.value.replace(/\./g, '').replace(/[^0-9]/g, '');
                const formatted = raw ? parseInt(raw).toLocaleString('id-ID') : '';
                const diff = formatted.length - el.value.length;
                el.value = formatted;
                // Restore cursor
                try { el.setSelectionRange(pos + diff, pos + diff); } catch (e) { }
                if (hidden) hidden.value = raw || 0;
            }

            function calcRow(el) {
                const row = el.closest('tr');
                const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
                const buy = parseNum(row.querySelector('.item-buy')?.value);
                const sell = parseNum(row.querySelector('.item-sell')?.value);
                const profit = (sell - buy) * qty;
                row.querySelector('.item-profit').textContent = formatRp(profit);
                row.querySelector('.item-profit').style.color = profit >= 0 ? '#10b981' : '#dc2626';
                recalcTotal(row.closest('tbody').id);
            }

            function recalcTotal(bodyId) {
                const body = document.getElementById(bodyId);
                const prefix = bodyId === 'addItemsBody' ? 'add' : 'edit';
                let revenue = 0, profit = 0;
                body.querySelectorAll('tr').forEach(row => {
                    const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
                    const buy = parseNum(row.querySelector('.item-buy')?.value);
                    const sell = parseNum(row.querySelector('.item-sell')?.value);
                    revenue += qty * sell;
                    profit += (sell - buy) * qty;
                });
                document.getElementById(prefix + 'TotalRevenue').textContent = formatRp(revenue);
                document.getElementById(prefix + 'TotalProfit').textContent = formatRp(profit);
                document.getElementById(prefix + 'TotalProfit').style.color = profit >= 0 ? '#10b981' : '#dc2626';
            }

            // Map vendor_id → services dari controller
            const vendorServicesMap = @json($vendorServices->groupBy('vendor_id'));

            // Map customer_id & company_name → lead info untuk Linked Lead
            const leadsByCustomerId = {};
            const leadsByName       = {};
            @foreach($leads as $l)
            @if($l->customer_id)
            leadsByCustomerId['{{ $l->customer_id }}'] = { id: {{ $l->id }}, label: '[{{ $l->lead_code }}] {{ addslashes($l->company_name) }}' };
            @endif
            leadsByName['{{ strtolower(trim($l->company_name)) }}'] = { id: {{ $l->id }}, label: '[{{ $l->lead_code }}] {{ addslashes($l->company_name) }}' };
            @endforeach

            function onVendorChange(sel, bodyId) {
                // Reset dropdown produk di semua rows body tersebut
                const vendorId = sel.value;
                const body = document.getElementById(bodyId);
                if (!body) return;
                body.querySelectorAll('.po-product-select').forEach(function(s) {
                    const svcs = vendorId && vendorServicesMap[vendorId] ? vendorServicesMap[vendorId] : [];
                    const tr = s.closest('tr');
                    const hidden = tr.querySelector('.po-product-hidden');
                    const unitInput = tr.querySelector('.po-unit-input');
                    const prevName = hidden ? (hidden.value || '') : '';

                    // Rebuild options
                    s.innerHTML = '<option value="">-- Pilih atau ketik --</option>';
                    svcs.forEach(p => {
                        const o = document.createElement('option');
                        o.value = p.service_name;
                        o.dataset.unit = p.unit || '';
                        o.textContent = p.service_name;
                        s.appendChild(o);
                    });
                    // Jika sebelumnya sudah ada nama (mis. ketik manual / dari edit) dan
                    // tidak ada di daftar vendor baru, pertahankan sebagai opsi agar tidak hilang.
                    if (prevName && prevName !== '__manual__' && !Array.from(s.options).some(o => o.value === prevName)) {
                        const keep = new Option(prevName, prevName, true, true);
                        s.appendChild(keep);
                    }
                    // Tambah opsi manual
                    const manualOpt = document.createElement('option');
                    manualOpt.value = '__manual__';
                    manualOpt.textContent = '+ Ketik manual...';
                    s.appendChild(manualOpt);

                    // Restore selection + sinkron hidden (hindari desync hidden vs select)
                    if (prevName && prevName !== '__manual__') {
                        s.value = prevName;
                        if (hidden) hidden.value = prevName;
                    } else {
                        if (hidden) hidden.value = '';
                    }
                    if (window.jQuery && $(s).data('select2')) {
                        $(s).val(s.value).trigger('change.select2');
                    }
                });
            }

            function onProductSelect(sel) {
                const tr = sel.closest('tr');
                const hiddenInput = tr.querySelector('.po-product-hidden');
                const unitInput = tr.querySelector('.po-unit-input');

                if (!hiddenInput) return;

                if (sel.value === '__manual__') {
                    const manual = prompt('Nama service:', '');
                    const productName = manual ? manual.trim() : '';

                    if (productName !== '') {
                        hiddenInput.value = productName;

                        let existingOpt = Array.from(sel.options).find(o => o.value === productName);
                        if (!existingOpt) {
                            existingOpt = new Option(productName, productName, true, true);
                            const manualOpt = Array.from(sel.options).find(o => o.value === '__manual__');
                            if (manualOpt) {
                                sel.insertBefore(existingOpt, manualOpt);
                            } else {
                                sel.appendChild(existingOpt);
                            }
                        }

                        existingOpt.selected = true;
                        sel.value = productName;

                        if (unitInput && !unitInput.value) {
                            unitInput.value = 'unit';
                        }

                        // Penting: trigger "change" penuh agar Select2 refresh tampilan text-nya.
                        if (window.jQuery && $(sel).data('select2')) {
                            $(sel).val(productName).trigger('change');
                            $(sel).select2('close');
                        }
                    } else {
                        hiddenInput.value = '';
                        sel.value = '';

                        if (window.jQuery && $(sel).data('select2')) {
                            $(sel).val('').trigger('change');
                            $(sel).select2('close');
                        }
                    }

                    return;
                }

                hiddenInput.value = sel.value || '';

                const opt = sel.options[sel.selectedIndex];
                if (opt && opt.dataset.unit && unitInput) {
                    unitInput.value = opt.dataset.unit;
                }
            }

            function addItemRow(bodyId, data = {}) {
                const idx = itemIndex++;
                const body = document.getElementById(bodyId);
                const prefix = bodyId === 'addItemsBody' ? 'items' : 'items';
                // Cari vendor yang dipilih
                const vendorSel = bodyId === 'addItemsBody' ? document.getElementById('addVendorSelect') : document.getElementById('epVendor');
                const vendorId = vendorSel ? vendorSel.value : null;
                const svcs = vendorId && vendorServicesMap[vendorId] ? vendorServicesMap[vendorId] : [];

                // Build service options
                let productOptions = '<option value="">-- Pilih atau ketik --</option>';
                let productExists = false;
                svcs.forEach(p => {
                    const selected = data.service_name === p.service_name ? 'selected' : '';
                    if (data.service_name === p.service_name) productExists = true;
                    productOptions += `<option value="${p.service_name}" data-unit="${p.unit||''}" ${selected}>${p.service_name}</option>`;
                });

                // Jika service berasal dari input manual / data lama, tetap tampilkan di dropdown saat edit.
                if (data.service_name && !productExists) {
                    productOptions += `<option value="${data.service_name}" selected>${data.service_name}</option>`;
                }

                productOptions += '<option value="__manual__">+ Ketik manual...</option>';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>
                    <input type="hidden" name="${prefix}[${idx}][service_name]" class="po-product-hidden" value="${data.service_name || ''}" required>
                    <select class="form-select form-select-sm po-product-select" onchange="onProductSelect(this)" data-hidden-name="${prefix}[${idx}][service_name]">
                        ${productOptions}
                    </select>
                </td>
                <td><input type="text" name="${prefix}[${idx}][unit]" class="form-control form-control-sm po-unit-input" value="${data.unit || 'unit'}"></td>
                <td><input type="number" name="${prefix}[${idx}][tonnage]" class="form-control form-control-sm item-tonnage" step="0.001" min="0" value="${data.tonnage ?? ''}" placeholder="0"></td>
                <td><input type="number" name="${prefix}[${idx}][qty]" class="form-control form-control-sm item-qty" step="0.001" min="0" value="${data.qty || ''}" required oninput="calcRow(this)"></td>
                <td>
                    <input type="hidden" name="${prefix}[${idx}][buy_price]" class="item-buy-hidden" value="${data.buy_price || 0}">
                    <input type="text" class="form-control form-control-sm item-buy" value="${data.buy_price ? formatNum(data.buy_price) : ''}" placeholder="0"
                        oninput="syncHidden(this,'item-buy-hidden');calcRow(this)"
                        onblur="formatPriceInput(this)">
                </td>
                <td>
                    <input type="hidden" name="${prefix}[${idx}][sell_price]" class="item-sell-hidden" value="${data.sell_price || 0}">
                    <input type="text" class="form-control form-control-sm item-sell" value="${data.sell_price ? formatNum(data.sell_price) : ''}" placeholder="0"
                        oninput="syncHidden(this,'item-sell-hidden');calcRow(this)"
                        onblur="formatPriceInput(this)">
                </td>
                <td class="item-profit text-end" style="font-weight:600;color:#10b981;vertical-align:middle">Rp 0</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)" style="padding:2px 6px"><i class="fas fa-times"></i></button></td>
            `;
                body.appendChild(tr);

                // Row ditambahkan secara dinamis, jadi Select2 perlu di-init ulang khusus row baru.
                if (typeof initSelect2 === 'function') {
                    initSelect2(tr);
                }

                // Jika row berasal dari edit / manual product, paksa Select2 menampilkan value yang sudah ada.
                if (data.service_name) {
                    const productSelect = tr.querySelector('.po-product-select');
                    const hiddenInput = tr.querySelector('.po-product-hidden');

                    if (productSelect) {
                        let opt = Array.from(productSelect.options).find(o => o.value === data.service_name);
                        if (!opt) {
                            opt = new Option(data.service_name, data.service_name, true, true);
                            productSelect.appendChild(opt);
                        }

                        opt.selected = true;
                        productSelect.value = data.service_name;
                    }

                    if (hiddenInput) {
                        hiddenInput.value = data.service_name;
                    }

                    if (window.jQuery && productSelect && $(productSelect).data('select2')) {
                        $(productSelect).val(data.service_name).trigger('change');
                    }
                }

                if (data.qty && data.buy_price && data.sell_price) {
                    calcRow(tr.querySelector('.item-qty'));
                }
            }

            function removeRow(btn) {
                const row = btn.closest('tr');
                const body = row.closest('tbody');
                if (body.querySelectorAll('tr').length <= 1) { alert('Minimal 1 item layanan'); return; }
                row.remove();
                recalcTotal(body.id);
            }

            function normalizeDateForInput(value) {
                if (!value) return '';
                const str = String(value).trim();

                // Jika dari backend sudah Y-m-d, langsung pakai.
                const ymd = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
                if (ymd) return `${ymd[1]}-${ymd[2]}-${ymd[3]}`;

                // Fallback untuk format seperti "24 May 2026" / Date string browser.
                const parsed = new Date(str);
                if (!isNaN(parsed.getTime())) {
                    const yyyy = parsed.getFullYear();
                    const mm = String(parsed.getMonth() + 1).padStart(2, '0');
                    const dd = String(parsed.getDate()).padStart(2, '0');
                    return `${yyyy}-${mm}-${dd}`;
                }

                return '';
            }

            function setDateInputValue(id, value) {
                const el = document.getElementById(id);
                if (!el) return;

                const dateValue = normalizeDateForInput(value);

                // Set native input value
                el.value = dateValue;
                el.setAttribute('value', dateValue);
                el.dataset.pendingDate = dateValue;

                // Air Datepicker: set lewat instance agar UI ikut terisi.
                if (el._airDatepicker && dateValue) {
                    el._airDatepicker.selectDate(new Date(dateValue));
                }

                // Fallback untuk datepicker lain yang mendengar event input/change.
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }

            async function openEditPo(id) {
                const res = await fetch(`/delivery-orders/${id}/edit`);
                const po  = await res.json();

                document.getElementById('editDoForm').action = `/delivery-orders/${id}`;
                document.getElementById('editPoNumber').textContent = po.do_number;

                document.getElementById('epStatus').value   = po.status;
                document.getElementById('epCurrency').value = po.currency;
                document.getElementById('epNotes').value    = po.notes || '';
                if (document.getElementById('epDeliveryType')) document.getElementById('epDeliveryType').value = po.delivery_type || '';
                if (document.getElementById('epOrigin')) document.getElementById('epOrigin').value = po.origin || '';
                if (document.getElementById('epDestination')) document.getElementById('epDestination').value = po.destination || '';
                if (document.getElementById('epTracking')) document.getElementById('epTracking').value = po.tracking_number || '';
                if (document.getElementById('epEta')) document.getElementById('epEta').value = po.estimated_arrival || '';

                const setSelect2 = (elId, val) => {
                    const el = document.getElementById(elId);
                    if (!el) return;
                    const v = (val === null || val === undefined) ? '' : String(val);
                    // Pastikan option dengan value tsb ada (untuk native maupun select2)
                    if (v !== '' && !Array.from(el.options).some(o => String(o.value) === v)) {
                        // Tidak ada di daftar — biarkan kosong agar tidak memaksa value invalid
                    }
                    // Init Select2 bila belum (agar tampilan ter-update saat val di-set)
                    if (window.jQuery && typeof initSelect2 === 'function' && !$(el).data('select2')) {
                        initSelect2(el.closest('.modal') || document);
                    }
                    if (window.jQuery && $(el).data('select2')) {
                        $(el).val(v || null).trigger('change');
                    } else {
                        el.value = v;
                    }
                };

                setSelect2('epCustomer', po.customer_id);
                setSelect2('epVendor', po.vendor_id);
                setSelect2('epSalesPicSelect', po.user_id);

                // Auto-fill linked lead berdasarkan customer
                const epCustEl = document.getElementById('epCustomer');
                if (epCustEl) onCustomerChange(epCustEl, 'epLeadDisplay');
                // Override jika DO punya lead_id spesifik
                if (po.lead_id) {
                    const epLeadHid  = document.getElementById('epLeadHidden');
                    const epLeadDisp = document.getElementById('epLeadDisplay');
                    if (epLeadHid) epLeadHid.value = po.lead_id;
                    // Cari label lead
                    let label = '';
                    for (const key in leadsByCustomerId) {
                        if (String(leadsByCustomerId[key].id) === String(po.lead_id)) {
                            label = leadsByCustomerId[key].label; break;
                        }
                    }
                    if (!label) {
                        for (const key in leadsByName) {
                            if (String(leadsByName[key].id) === String(po.lead_id)) {
                                label = leadsByName[key].label; break;
                            }
                        }
                    }
                    if (epLeadDisp && label) epLeadDisp.value = label;
                }

                const body = document.getElementById('editItemsBody');
                body.innerHTML = '';
                itemIndex = 1000;
                po.items.forEach(item => addItemRow('editItemsBody', item));
                recalcTotal('editItemsBody');

                // Tgl Order harus di-set langsung sebelum modal tampil dan setelah modal tampil.
                // Ini menghindari case input date kosong karena re-render modal / plugin select2.
                setDateInputValue('epDate', po.order_date);

                const modalEl = document.getElementById('editPoModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

                const oldHandler = modalEl._shownHandler;
                if (oldHandler) modalEl.removeEventListener('shown.bs.modal', oldHandler);

                const shownHandler = function () {
                    // Re-apply Select2 values setelah modal benar-benar tampil
                    // (Select2 sering gagal render value saat di-set ketika modal masih hidden).
                    setSelect2('epCustomer', po.customer_id);
                    setSelect2('epVendor', po.vendor_id);
                    setSelect2('epSalesPicSelect', po.user_id);
                    setDateInputValue('epDate', po.order_date);
                    setTimeout(function () {
                        setSelect2('epCustomer', po.customer_id);
                        setSelect2('epVendor', po.vendor_id);
                        setSelect2('epSalesPicSelect', po.user_id);
                        setDateInputValue('epDate', po.order_date);
                    }, 60);
                };

                modalEl._shownHandler = shownHandler;
                modalEl.addEventListener('shown.bs.modal', shownHandler, { once: true });

                modal.show();
            }


            function setDefaultSalesPic(custSel, salesSelectId) {
                const salesSelect = document.getElementById(salesSelectId);
                if (!salesSelect || !custSel) return;
                const opt = custSel.options[custSel.selectedIndex];
                const userId = opt?.dataset?.userId || '';
                if (userId) {
                    if (window.jQuery && $(salesSelect).data('select2')) {
                        $(salesSelect).val(userId).trigger('change');
                    } else {
                        salesSelect.value = userId;
                    }
                }
            }

            // ── Filter Linked Lead berdasarkan Customer yang dipilih ──
            function onCustomerChange(custSel, displayId) {
                const customerId   = String(custSel.value || '').trim();
                const customerName = String(custSel.options[custSel.selectedIndex]?.dataset?.name || '').trim();
                const isAdd        = displayId === 'addLeadDisplay';
                const display      = document.getElementById(displayId);
                const hiddenId     = isAdd ? 'addLeadHidden' : 'epLeadHidden';
                const hidden       = document.getElementById(hiddenId);

                if (!customerId) {
                    if (display) { display.value = ''; display.placeholder = 'Otomatis dari Customer'; }
                    if (hidden)  hidden.value = '';
                    return;
                }

                // Cari lead: by customer_id dulu, fallback by nama
                let lead = leadsByCustomerId[customerId]
                        || leadsByName[customerName]
                        || null;

                if (lead) {
                    if (display) display.value = lead.label;
                    if (hidden)  hidden.value  = lead.id;
                } else {
                    if (display) { display.value = ''; display.placeholder = 'Tidak ada lead terkait'; }
                    if (hidden)  hidden.value = '';
                }
            }

            // ── Expand/collapse detail row DO ──
            function toggleDetail(poId, btn) {
                const detail = document.getElementById('po-detail-' + poId);
                const icon   = btn.querySelector('i');
                if (!detail) return;
                if (detail.style.display === 'none') {
                    detail.style.display = 'table-row';
                    icon.style.transform = 'rotate(90deg)';
                } else {
                    detail.style.display = 'none';
                    icon.style.transform = 'rotate(0deg)';
                }
            }

            // ── Init modal Add DO: tambah 1 row kosong saat modal dibuka, reset saat ditutup ──
            document.addEventListener('DOMContentLoaded', function () {
                const addModal = document.getElementById('addPoModal');
                if (!addModal) return;

                addModal.addEventListener('show.bs.modal', function () {
                    const body = document.getElementById('addItemsBody');
                    if (body && body.querySelectorAll('tr').length === 0) {
                        addItemRow('addItemsBody');
                    }
                });

                addModal.addEventListener('hidden.bs.modal', function () {
                    // Reset tbody dan vendor select saat modal ditutup
                    const body = document.getElementById('addItemsBody');
                    if (body) body.innerHTML = '';
                    const vendorSel = document.getElementById('addVendorSelect');
                    if (vendorSel) vendorSel.value = '';
                    itemIndex = 0;
                });
            });

            // ── FIX SAVE DO: sinkronkan hidden + bersihkan baris kosong sebelum submit ──
            // Penyebab "kadang tidak save": hidden service_name / harga tidak ter-sync
            // dari select/input (mis. quirk Select2, ganti vendor), sehingga validasi
            // server (items.*.service_name required) gagal tanpa pesan jelas.
            function prepareDoSubmit(form, bodyId) {
                const body = document.getElementById(bodyId);
                if (!body) return true;

                let validCount = 0;

                body.querySelectorAll('tr').forEach(function (row) {
                    const hidden = row.querySelector('.po-product-hidden');
                    const select = row.querySelector('.po-product-select');

                    // 1. Sinkron nama layanan: utamakan nilai select aktif
                    if (hidden) {
                        let name = '';
                        if (select && select.value && select.value !== '__manual__') {
                            name = select.value;
                        } else if (hidden.value && hidden.value !== '__manual__') {
                            name = hidden.value;
                        }
                        hidden.value = (name || '').trim();
                    }

                    // 2. Sinkron harga hidden dari input text (jaga-jaga belum ter-sync)
                    const buyTxt  = row.querySelector('.item-buy');
                    const buyHid  = row.querySelector('.item-buy-hidden');
                    const sellTxt = row.querySelector('.item-sell');
                    const sellHid = row.querySelector('.item-sell-hidden');
                    if (buyHid)  buyHid.value  = parseNum(buyTxt ? buyTxt.value : 0)  || 0;
                    if (sellHid) sellHid.value = parseNum(sellTxt ? sellTxt.value : 0) || 0;

                    // 3. Tentukan baris valid (punya nama + qty > 0)
                    const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
                    const hasName = hidden && hidden.value !== '';

                    if (!hasName || qty <= 0) {
                        // Baris kosong / tak lengkap → buang agar tidak menggagalkan validasi
                        if (!hasName && qty <= 0) {
                            row.remove();
                            return;
                        }
                        // Jika sebagian terisi tapi tak lengkap, tandai untuk pesan
                        if (!hasName) {
                            row.querySelector('.po-product-select')?.classList.add('is-invalid');
                        }
                    } else {
                        validCount++;
                    }
                });

                if (validCount === 0) {
                    alert('Minimal 1 item layanan dengan Nama Layanan dan Qty harus diisi.');
                    return false;
                }

                // Pastikan ada baris bernama tapi qty 0 / sebaliknya tidak lolos diam-diam
                let incomplete = false;
                body.querySelectorAll('tr').forEach(function (row) {
                    const hidden = row.querySelector('.po-product-hidden');
                    const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
                    if (hidden && hidden.value !== '' && qty <= 0) incomplete = true;
                    if (hidden && hidden.value === '' && qty > 0) incomplete = true;
                });
                if (incomplete) {
                    alert('Ada item yang belum lengkap (Nama Layanan & Qty wajib diisi bersamaan). Periksa kembali.');
                    return false;
                }

                return true;
            }

            (function attachDoSubmitGuards() {
                const addForm = document.getElementById('addDoForm');
                if (addForm) {
                    addForm.addEventListener('submit', function (e) {
                        if (!prepareDoSubmit(addForm, 'addItemsBody')) e.preventDefault();
                    });
                }
                const editForm = document.getElementById('editDoForm');
                if (editForm) {
                    editForm.addEventListener('submit', function (e) {
                        if (!prepareDoSubmit(editForm, 'editItemsBody')) e.preventDefault();
                    });
                }
            })();
        </script>
    @endpush
@endsection