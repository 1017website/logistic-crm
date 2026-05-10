@extends('layouts.app')
@section('title', 'Delivery Orders')
@section('page-title', 'Delivery Orders')
@section('page-subtitle', 'Kelola data DO, revenue, dan biaya operasional')

@section('content')
<div class="row g-3">
<div class="col-12">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDoModal">
                <i class="fas fa-plus me-1"></i> Tambah DO
            </button>
            <a href="{{ route('delivery-orders.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-download me-1"></i> Export CSV
            </a>
        </div>
        <div class="d-flex gap-3 flex-wrap">
            @foreach([[$totalDo,'Total DO','#111'],[$doneDo,'Done','#059669'],[$revenue,'Revenue','#2563eb'],[$grossProfit,'Gross Profit','#10b981'],[$nettProfit,'Nett Profit','#7c3aed']] as $s)
            <div class="text-center {{ !$loop->first ? 'ps-3' : '' }}" style="{{ !$loop->first ? 'border-left:1px solid var(--border-color)' : '' }}">
                <div style="font-size:{{ $loop->index >= 2 ? '1rem' : '1.2rem' }};font-weight:800;color:{{ $s[2] }}">
                    {{ $loop->index >= 2 ? idrm($s[0]) : $s[0] }}
                </div>
                <div style="font-size:.68rem;color:var(--text-muted)">{{ $s[1] }}</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('delivery-orders.index') }}">
        <div class="card mb-3"><div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="all">All Status</option>
                        @foreach(['Done','In Progress','Cancelled'] as $s)
                        <option value="{{ $s }}" @selected($status==$s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="service_type" class="form-select form-select-sm">
                        <option value="all">All Service</option>
                        @foreach($serviceTypes as $t)
                        <option value="{{ $t }}" @selected($serviceType==$t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari DO, customer, route..." value="{{ $search }}">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </div></div>
    </form>

    {{-- Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:13px">
                    <thead style="background:#f8f9fa">
                        <tr>
                            <th class="px-3 py-2">No. DO</th>
                            <th class="py-2">Customer</th>
                            <th class="py-2">Vendor</th>
                            <th class="py-2">Service / Route</th>
                            <th class="py-2">Revenue</th>
                            <th class="py-2">Cost Vendor</th>
                            <th class="py-2">Other Cost</th>
                            <th class="py-2">Gross Profit</th>
                            <th class="py-2">Nett Profit</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Tgl Order</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dos as $do)
                        @php
                            $statusColor = match($do->status) {
                                'Done'        => ['#d1fae5','#059669'],
                                'In Progress' => ['#dbeafe','#2563eb'],
                                'Cancelled'   => ['#fee2e2','#dc2626'],
                                default       => ['#f3f4f6','#6b7280'],
                            };
                        @endphp
                        <tr>
                            <td class="px-3 py-2" style="font-weight:700;color:var(--primary)">{{ $do->do_number }}</td>
                            <td class="py-2">{{ $do->customer?->company_name ?? '-' }}</td>
                            <td class="py-2" style="color:#6b7280">{{ $do->vendor?->vendor_name ?? '-' }}</td>
                            <td class="py-2">
                                <div style="font-size:12px">{{ $do->service_type }}</div>
                                <div style="font-size:11px;color:#6b7280">{{ $do->route }}</div>
                            </td>
                            <td class="py-2" style="font-weight:600;color:var(--primary)">{{ idrm($do->amount) }}</td>
                            <td class="py-2" style="color:#dc2626">{{ $do->cost > 0 ? idrm($do->cost) : '-' }}</td>
                            <td class="py-2" style="color:#f97316">{{ $do->other_cost > 0 ? idrm($do->other_cost) : '-' }}</td>
                            <td class="py-2" style="font-weight:600;color:#10b981">{{ idrm($do->gross_profit) }}</td>
                            <td class="py-2" style="font-weight:600;color:#7c3aed">{{ idrm($do->nett_profit) }}</td>
                            <td class="py-2">
                                <span style="font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600;background:{{ $statusColor[0] }};color:{{ $statusColor[1] }}">
                                    {{ $do->status }}
                                </span>
                            </td>
                            <td class="py-2" style="color:#6b7280;font-size:12px">{{ $do->order_date?->format('d M Y') }}</td>
                            <td class="py-2">
                                <button class="btn btn-sm btn-outline-secondary" style="padding:3px 7px"
                                    onclick="openEditDo(
                                        {{ $do->id }},
                                        '{{ $do->customer_id }}','{{ $do->vendor_id }}','{{ $do->lead_id }}',
                                        '{{ addslashes($do->service_type) }}','{{ addslashes($do->route) }}',
                                        '{{ $do->amount }}','{{ $do->cost }}','{{ $do->other_cost }}',
                                        '{{ $do->currency }}','{{ $do->status }}','{{ $do->order_date?->format('Y-m-d') }}'
                                    )">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <form method="POST" action="{{ route('delivery-orders.destroy', $do) }}" class="d-inline"
                                    onsubmit="return confirm('Hapus DO {{ $do->do_number }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:3px 7px">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="12" class="text-center py-4" style="color:#9ca3af">Belum ada data DO pada periode ini</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($dos->hasPages())
            <div class="px-3 py-2">{{ $dos->links() }}</div>
            @endif
        </div>
    </div>

</div>
</div>

{{-- Modal Tambah DO --}}
<div class="modal fade" id="addDoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Tambah Delivery Order</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('delivery-orders.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer</label>
                            <select name="customer_id" class="form-select">
                                <option value="">-- Pilih Customer --</option>
                                @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vendor</label>
                            <select name="vendor_id" class="form-select">
                                <option value="">-- Pilih Vendor --</option>
                                @foreach($vendors as $v)
                                <option value="{{ $v->id }}">{{ $v->vendor_name }} ({{ $v->vendor_type }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Linked Lead <span style="color:#9ca3af;font-size:11px">(opsional)</span></label>
                            <select name="lead_id" class="form-select">
                                <option value="">-- Pilih Lead --</option>
                                @foreach($leads as $l)
                                <option value="{{ $l->id }}">[{{ $l->lead_code }}] {{ $l->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tgl Order <span class="text-danger">*</span></label>
                            <input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="In Progress">In Progress</option>
                                <option value="Done">Done</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Service Type <span class="text-danger">*</span></label>
                            <input type="text" name="service_type" class="form-control" placeholder="Shipping Line, Trucking, Air, dll" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Route <span class="text-danger">*</span></label>
                            <input type="text" name="route" class="form-control" placeholder="Surabaya - Jakarta" required>
                        </div>

                        <div class="col-12"><hr class="my-1"><div style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:-4px">FINANSIAL</div></div>

                        <div class="col-md-4">
                            <label class="form-label">Revenue (Amount) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text" style="font-size:12px">Rp</span>
                                <input type="number" name="amount" class="form-control" placeholder="0" min="0" required oninput="calcProfit()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Biaya Vendor / HPP</label>
                            <div class="input-group">
                                <span class="input-group-text" style="font-size:12px">Rp</span>
                                <input type="number" name="cost" id="addCost" class="form-control" placeholder="0" min="0" oninput="calcProfit()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Biaya Operasional Lain</label>
                            <div class="input-group">
                                <span class="input-group-text" style="font-size:12px">Rp</span>
                                <input type="number" name="other_cost" id="addOtherCost" class="form-control" placeholder="0" min="0" oninput="calcProfit()">
                            </div>
                        </div>

                        {{-- Preview Profit --}}
                        <div class="col-12">
                            <div class="d-flex gap-3 p-2 rounded" style="background:#f8f9fa;font-size:12px">
                                <div>Gross Profit: <strong id="previewGross" style="color:#10b981">Rp 0</strong></div>
                                <div style="border-left:1px solid #e5e7eb;padding-left:12px">Nett Profit: <strong id="previewNett" style="color:#7c3aed">Rp 0</strong></div>
                                <div style="border-left:1px solid #e5e7eb;padding-left:12px">Margin: <strong id="previewMargin" style="color:#6b7280">0%</strong></div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Currency</label>
                            <select name="currency" class="form-select">
                                <option value="IDR">IDR</option>
                                <option value="USD">USD</option>
                                <option value="SGD">SGD</option>
                            </select>
                        </div>
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
<div class="modal fade" id="editDoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Edit Delivery Order</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editDoForm">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer</label>
                            <select name="customer_id" id="edCustomer" class="form-select">
                                <option value="">-- Pilih Customer --</option>
                                @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vendor</label>
                            <select name="vendor_id" id="edVendor" class="form-select">
                                <option value="">-- Pilih Vendor --</option>
                                @foreach($vendors as $v)
                                <option value="{{ $v->id }}">{{ $v->vendor_name }} ({{ $v->vendor_type }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Linked Lead</label>
                            <select name="lead_id" id="edLead" class="form-select">
                                <option value="">-- Pilih Lead --</option>
                                @foreach($leads as $l)
                                <option value="{{ $l->id }}">[{{ $l->lead_code }}] {{ $l->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tgl Order <span class="text-danger">*</span></label>
                            <input type="date" name="order_date" id="edDate" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="edStatus" class="form-select" required>
                                <option value="In Progress">In Progress</option>
                                <option value="Done">Done</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Service Type <span class="text-danger">*</span></label>
                            <input type="text" name="service_type" id="edService" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Route <span class="text-danger">*</span></label>
                            <input type="text" name="route" id="edRoute" class="form-control" required>
                        </div>

                        <div class="col-12"><hr class="my-1"><div style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:-4px">FINANSIAL</div></div>

                        <div class="col-md-4">
                            <label class="form-label">Revenue (Amount) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text" style="font-size:12px">Rp</span>
                                <input type="number" name="amount" id="edAmount" class="form-control" min="0" required oninput="calcProfitEdit()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Biaya Vendor / HPP</label>
                            <div class="input-group">
                                <span class="input-group-text" style="font-size:12px">Rp</span>
                                <input type="number" name="cost" id="edCost" class="form-control" min="0" oninput="calcProfitEdit()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Biaya Operasional Lain</label>
                            <div class="input-group">
                                <span class="input-group-text" style="font-size:12px">Rp</span>
                                <input type="number" name="other_cost" id="edOtherCost" class="form-control" min="0" oninput="calcProfitEdit()">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="d-flex gap-3 p-2 rounded" style="background:#f8f9fa;font-size:12px">
                                <div>Gross Profit: <strong id="edPreviewGross" style="color:#10b981">Rp 0</strong></div>
                                <div style="border-left:1px solid #e5e7eb;padding-left:12px">Nett Profit: <strong id="edPreviewNett" style="color:#7c3aed">Rp 0</strong></div>
                                <div style="border-left:1px solid #e5e7eb;padding-left:12px">Margin: <strong id="edPreviewMargin" style="color:#6b7280">0%</strong></div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Currency</label>
                            <select name="currency" id="edCurrency" class="form-select">
                                <option value="IDR">IDR</option>
                                <option value="USD">USD</option>
                                <option value="SGD">SGD</option>
                            </select>
                        </div>
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
function formatRp(n) {
    return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

function calcProfit() {
    const amt   = parseFloat(document.querySelector('[name=amount]')?.value) || 0;
    const cost  = parseFloat(document.getElementById('addCost')?.value) || 0;
    const other = parseFloat(document.getElementById('addOtherCost')?.value) || 0;
    const gross = amt - cost;
    const nett  = amt - cost - other;
    const margin = amt > 0 ? Math.round((nett / amt) * 100) : 0;
    document.getElementById('previewGross').textContent  = formatRp(gross);
    document.getElementById('previewNett').textContent   = formatRp(nett);
    document.getElementById('previewMargin').textContent = margin + '%';
    document.getElementById('previewGross').style.color  = gross >= 0 ? '#10b981' : '#dc2626';
    document.getElementById('previewNett').style.color   = nett  >= 0 ? '#7c3aed' : '#dc2626';
}

function calcProfitEdit() {
    const amt   = parseFloat(document.getElementById('edAmount')?.value) || 0;
    const cost  = parseFloat(document.getElementById('edCost')?.value) || 0;
    const other = parseFloat(document.getElementById('edOtherCost')?.value) || 0;
    const gross = amt - cost;
    const nett  = amt - cost - other;
    const margin = amt > 0 ? Math.round((nett / amt) * 100) : 0;
    document.getElementById('edPreviewGross').textContent  = formatRp(gross);
    document.getElementById('edPreviewNett').textContent   = formatRp(nett);
    document.getElementById('edPreviewMargin').textContent = margin + '%';
    document.getElementById('edPreviewGross').style.color  = gross >= 0 ? '#10b981' : '#dc2626';
    document.getElementById('edPreviewNett').style.color   = nett  >= 0 ? '#7c3aed' : '#dc2626';
}

function openEditDo(id, customerId, vendorId, leadId, service, route, amount, cost, otherCost, currency, status, date) {
    document.getElementById('editDoForm').action = `/delivery-orders/${id}`;
    document.getElementById('edCustomer').value  = customerId  || '';
    document.getElementById('edVendor').value    = vendorId    || '';
    document.getElementById('edLead').value      = leadId      || '';
    document.getElementById('edService').value   = service;
    document.getElementById('edRoute').value     = route;
    document.getElementById('edAmount').value    = amount;
    document.getElementById('edCost').value      = cost;
    document.getElementById('edOtherCost').value = otherCost;
    document.getElementById('edCurrency').value  = currency;
    document.getElementById('edStatus').value    = status;
    document.getElementById('edDate').value      = date;
    calcProfitEdit();
    new bootstrap.Modal(document.getElementById('editDoModal')).show();
}
</script>
@endpush
@endsection
