@php
    $isEdit = $quotation->exists;
    $seedItems = old('items', $isEdit
        ? $quotation->items->map(fn($item) => [
            'origin' => $item->origin,
            'destination' => $item->destination,
            'commodity' => $item->commodity,
            'tonnage' => $item->tonnage,
            'unit' => $item->unit,
            'rate' => (float) $item->rate,
        ])->values()->all()
        : [['origin' => '', 'destination' => '', 'commodity' => '', 'tonnage' => '', 'unit' => '', 'rate' => '']]
    );
    $seedTerms = old('terms', $quotation->terms ?: $defaultTerms);
    $customerAutofillData = $customers->mapWithKeys(function ($customer) {
        $primaryPic = $customer->pics->sortByDesc('is_primary')->first();
        return [(string) $customer->id => [
            'company_name' => $customer->company_name,
            'pic_name' => $customer->pic_name ?: $primaryPic?->pic_name,
            'pic_position' => $customer->pic_position ?: $primaryPic?->pic_position,
            'phone' => $customer->phone ?: $primaryPic?->phone,
            'email' => $customer->email ?: $primaryPic?->email,
            'address' => $customer->address,
            'location' => $customer->location,
            'industry' => $customer->industry,
            'services' => $customer->productItems->map(fn ($product) => [
                'name' => $product->display_name,
                'unit' => $product->unit,
                'tonnage' => $product->tonnage,
                'shipping_zone' => $product->shipping_zone,
            ])->values()->all(),
        ]];
    });
@endphp

@push('styles')
<style>
    .customer-autofill-preview { border:1px solid #bfdbfe; background:#eff6ff; border-radius:10px; padding:.7rem .85rem; }
    .customer-autofill-preview-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:.65rem; }
    .customer-autofill-preview-label { color:#64748b; font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; }
    .customer-autofill-preview-value { color:#1e3a8a; font-size:.76rem; font-weight:600; overflow-wrap:anywhere; }
    @media (max-width:767.98px) { .customer-autofill-preview-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
</style>
@endpush

@if($errors->any())
    <div class="alert alert-danger py-2" style="font-size:13px">
        <div class="fw-semibold mb-1">Data belum dapat disimpan:</div>
        <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ $formAction }}" id="quotationForm">
    @csrf
    @if($formMethod !== 'POST') @method($formMethod) @endif

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <a href="{{ route('quotations.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
        <div class="d-flex gap-2">
            @if($isEdit)
                <a href="{{ route('quotations.pdf', $quotation) }}" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-file-pdf me-1"></i> Export PDF
                </a>
            @endif
            <button class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> {{ $submitLabel }}</button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white py-3">
            <div style="font-size:14px;font-weight:700">Data Surat</div>
            <div style="font-size:11px;color:#6b7280">Penerima, tanggal, dan identitas penawaran.</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Ambil dari Database Customer</label>
                    <select name="customer_id" id="customerSelect" class="form-select">
                        <option value="">Input manual / belum terdaftar</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}"
                                @selected((string) old('customer_id', $quotation->customer_id) === (string) $customer->id)>
                                {{ $customer->company_name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Nama perusahaan, PIC/jabatan, dan alamat akan terisi otomatis serta tetap dapat diedit.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nomor Surat</label>
                    <input type="text" class="form-control" value="{{ $isEdit ? $quotation->quotation_number : 'Otomatis setelah disimpan' }}" readonly>
                    <div class="form-text">Dibuat sistem dan tampil pada PDF serta verifikasi QR.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Surat <span class="text-danger">*</span></label>
                    <input type="date" name="quotation_date" class="form-control"
                        value="{{ old('quotation_date', $quotation->quotation_date?->format('Y-m-d')) }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        @foreach(\App\Models\Quotation::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', $quotation->status) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-none" id="customerAutofillPreview">
                    <div class="customer-autofill-preview">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <div style="font-size:.75rem;font-weight:700;color:#1d4ed8"><i class="fas fa-circle-check me-1"></i>Data customer berhasil dimuat</div>
                            <span class="badge bg-primary">Terisi otomatis</span>
                        </div>
                        <div class="customer-autofill-preview-grid">
                            <div><div class="customer-autofill-preview-label">PIC / Jabatan</div><div class="customer-autofill-preview-value" id="customerPreviewPic">-</div></div>
                            <div><div class="customer-autofill-preview-label">Kontak</div><div class="customer-autofill-preview-value" id="customerPreviewContact">-</div></div>
                            <div><div class="customer-autofill-preview-label">Lokasi / Industri</div><div class="customer-autofill-preview-value" id="customerPreviewLocation">-</div></div>
                            <div><div class="customer-autofill-preview-label">Layanan Tersimpan</div><div class="customer-autofill-preview-value" id="customerPreviewServices">-</div></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Baris Sapaan <span class="text-danger">*</span></label>
                    <input type="text" name="recipient_name" class="form-control"
                        value="{{ old('recipient_name', $quotation->recipient_name) }}" placeholder="Yth." required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Jabatan / Nama Penerima <span class="text-danger">*</span></label>
                    <input type="text" name="recipient_title" id="recipientTitle" class="form-control"
                        value="{{ old('recipient_title', $quotation->recipient_title) }}" placeholder="Bpk/Ibu Pimpinan" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nama Perusahaan <span class="text-danger">*</span></label>
                    <input type="text" name="company_name" id="companyName" class="form-control"
                        value="{{ old('company_name', $quotation->company_name) }}" placeholder="PT. Nama Customer" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Alamat / Lokasi Penerima</label>
                    <textarea name="recipient_address" id="recipientAddress" class="form-control" rows="2"
                        placeholder="Di tempat atau alamat lengkap">{{ old('recipient_address', $quotation->recipient_address) }}</textarea>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Lampiran <span class="text-danger">*</span></label>
                    <input type="text" name="attachment" class="form-control"
                        value="{{ old('attachment', $quotation->attachment) }}" placeholder="-" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Perihal <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control"
                        value="{{ old('subject', $quotation->subject) }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Kalimat Pembuka</label>
                    <textarea name="opening" class="form-control" rows="2"
                        placeholder="Kosongkan untuk memakai kalimat pembuka otomatis.">{{ old('opening', $quotation->opening) }}</textarea>
                    <div class="form-text">Jika kosong, PDF otomatis membuat pembuka berdasarkan nama perusahaan.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <div style="font-size:14px;font-weight:700">Detail Tarif</div>
                <div style="font-size:11px;color:#6b7280">Setiap baris akan menjadi satu baris pada tabel PDF.</div>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addItemBtn">
                <i class="fas fa-plus me-1"></i> Tambah Baris
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" style="min-width:1050px;font-size:12px">
                    <thead style="background:#f8f9fa">
                        <tr>
                            <th class="ps-3" style="width:40px">No</th>
                            <th>Origin</th>
                            <th>Destination</th>
                            <th>Komoditas</th>
                            <th style="width:125px">Tonase</th>
                            <th style="width:170px">Unit</th>
                            <th style="width:175px">Rate</th>
                            <th style="width:45px"></th>
                        </tr>
                    </thead>
                    <tbody id="itemRows"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <div style="font-size:14px;font-weight:700">Syarat dan Kondisi</div>
                <div style="font-size:11px;color:#6b7280">Urutan di bawah sama dengan urutan daftar bernomor pada PDF.</div>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addTermBtn">
                <i class="fas fa-plus me-1"></i> Tambah Syarat
            </button>
        </div>
        <div class="card-body" id="termRows"></div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white py-3">
            <div style="font-size:14px;font-weight:700">Penutup dan Tanda Tangan</div>
            <div style="font-size:11px;color:#6b7280">Gambar tanda tangan/cap mengikuti yang diunggah pada Settings.</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Kalimat Penutup</label>
                    <textarea name="closing" class="form-control" rows="2"
                        placeholder="Kosongkan untuk memakai kalimat penutup otomatis.">{{ old('closing', $quotation->closing) }}</textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nama Kontak</label>
                    <input type="text" name="contact_name" class="form-control"
                        value="{{ old('contact_name', $quotation->contact_name) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Telepon Kontak</label>
                    <input type="text" name="contact_phone" class="form-control"
                        value="{{ old('contact_phone', $quotation->contact_phone) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Kota <span class="text-danger">*</span></label>
                    <input type="text" name="city" class="form-control"
                        value="{{ old('city', $quotation->city) }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Nama Penanda Tangan <span class="text-danger">*</span></label>
                    <input type="text" name="signatory_name" class="form-control"
                        value="{{ old('signatory_name', $quotation->signatory_name) }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Jabatan <span class="text-danger">*</span></label>
                    <input type="text" name="signatory_title" class="form-control"
                        value="{{ old('signatory_title', $quotation->resolvedSignatoryTitle()) }}" required>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 pb-3">
        <a href="{{ route('quotations.index') }}" class="btn btn-outline-secondary">Batal</a>
        <button class="btn btn-primary"><i class="fas fa-save me-1"></i> {{ $submitLabel }}</button>
    </div>
</form>

@push('scripts')
<script>
(() => {
    const initialItems = @json($seedItems);
    const initialTerms = @json($seedTerms);
    const customerAutofillData = @json($customerAutofillData);
    const isEdit = @json($isEdit);
    const itemRows = document.getElementById('itemRows');
    const termRows = document.getElementById('termRows');

    function makeInput(name, value, placeholder, className = 'form-control form-control-sm') {
        const input = document.createElement('input');
        input.type = 'text';
        input.name = name;
        input.value = value ?? '';
        input.placeholder = placeholder;
        input.className = className;
        input.required = true;
        return input;
    }

    function addItem(data = {}) {
        const index = itemRows.children.length;
        const row = document.createElement('tr');
        const number = document.createElement('td');
        number.className = 'ps-3 text-muted item-number';
        row.appendChild(number);

        const fields = [
            ['origin', 'Gresik / Segoromadu'],
            ['destination', 'Sidoarjo'],
            ['commodity', 'Garam halus'],
            ['tonnage', '10 Ton'],
            ['unit', 'Fuso Three Way'],
        ];
        fields.forEach(([field, placeholder]) => {
            const cell = document.createElement('td');
            cell.appendChild(makeInput(`items[${index}][${field}]`, data[field], placeholder));
            row.appendChild(cell);
        });

        const rateCell = document.createElement('td');
        const rateGroup = document.createElement('div');
        rateGroup.className = 'input-group input-group-sm';
        const prefix = document.createElement('span');
        prefix.className = 'input-group-text';
        prefix.textContent = 'Rp';
        const rate = makeInput(`items[${index}][rate]`, data.rate, '2.500.000', 'form-control idr-input');
        rate.inputMode = 'numeric';
        rateGroup.append(prefix, rate);
        rateCell.appendChild(rateGroup);
        row.appendChild(rateCell);

        const action = document.createElement('td');
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-outline-danger';
        remove.innerHTML = '<i class="fas fa-times"></i>';
        remove.addEventListener('click', () => {
            row.remove();
            reindexItems();
        });
        action.appendChild(remove);
        row.appendChild(action);
        itemRows.appendChild(row);
        reindexItems();
    }

    function reindexItems() {
        [...itemRows.children].forEach((row, index) => {
            row.querySelector('.item-number').textContent = index + 1;
            row.querySelectorAll('input').forEach(input => {
                input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
            });
            row.querySelector('button').disabled = itemRows.children.length === 1;
        });
    }

    function addTerm(value = '') {
        const row = document.createElement('div');
        row.className = 'd-flex align-items-start gap-2 mb-2 term-row';
        const number = document.createElement('span');
        number.className = 'badge bg-dark mt-2 term-number';
        const input = makeInput('terms[]', value, 'Masukkan syarat atau kondisi');
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-outline-danger mt-1';
        remove.innerHTML = '<i class="fas fa-times"></i>';
        remove.addEventListener('click', () => {
            row.remove();
            reindexTerms();
        });
        row.append(number, input, remove);
        termRows.appendChild(row);
        reindexTerms();
    }

    function reindexTerms() {
        [...termRows.children].forEach((row, index) => {
            row.querySelector('.term-number').textContent = index + 1;
            row.querySelector('button').disabled = termRows.children.length === 1;
        });
    }

    initialItems.forEach(addItem);
    initialTerms.forEach(addTerm);
    document.getElementById('addItemBtn').addEventListener('click', () => addItem());
    document.getElementById('addTermBtn').addEventListener('click', () => addTerm());

    const customerSelect = document.getElementById('customerSelect');

    function customerRecipient(customer) {
        const parts = [];
        if (customer.pic_name) parts.push(`Bpk/Ibu ${customer.pic_name}`);
        if (customer.pic_position) parts.push(customer.pic_position);
        return parts.join(' — ') || 'Bpk/Ibu Pimpinan';
    }

    function renderCustomerPreview(customer) {
        const preview = document.getElementById('customerAutofillPreview');
        if (!customer) {
            preview.classList.add('d-none');
            return;
        }

        const pic = [customer.pic_name, customer.pic_position].filter(Boolean).join(' — ') || '-';
        const contact = [customer.phone, customer.email].filter(Boolean).join(' · ') || '-';
        const location = [customer.location || customer.address, customer.industry].filter(Boolean).join(' · ') || '-';
        const services = (customer.services || []).map(service => {
            const detail = [service.unit, service.tonnage ? `${service.tonnage} ton` : null, service.shipping_zone].filter(Boolean).join(' / ');
            return detail ? `${service.name} (${detail})` : service.name;
        }).filter(Boolean).join(', ') || '-';

        document.getElementById('customerPreviewPic').textContent = pic;
        document.getElementById('customerPreviewContact').textContent = contact;
        document.getElementById('customerPreviewLocation').textContent = location;
        document.getElementById('customerPreviewServices').textContent = services;
        preview.classList.remove('d-none');
    }

    function autofillCustomer(force = true) {
        const customer = customerAutofillData[String(customerSelect.value)] || null;
        renderCustomerPreview(customer);
        if (!customer || !force) return;

        document.getElementById('companyName').value = customer.company_name || '';
        document.getElementById('recipientAddress').value = customer.address || customer.location || 'Di tempat.';
        document.getElementById('recipientTitle').value = customerRecipient(customer);
    }

    customerSelect.addEventListener('change', () => autofillCustomer(true));
    if (window.jQuery) {
        window.jQuery(customerSelect).on('select2:select select2:clear', () => autofillCustomer(true));
    }

    if (customerSelect.value) {
        const companyIsEmpty = !document.getElementById('companyName').value.trim();
        autofillCustomer(!isEdit && companyIsEmpty);
    }
})();
</script>
@endpush
