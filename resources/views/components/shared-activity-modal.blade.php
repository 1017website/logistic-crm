@php
    $activityContextType = $activityContextType ?? null; // lead|customer|null
    $activityContextId = $activityContextId ?? null;
    $activityContextLabel = $activityContextLabel ?? null;
    $activityContextStage = $activityContextStage ?? null;
    $activityModalTitle = $activityModalTitle ?? 'Add Activity';
    $activityDefaultDate = $activityDefaultDate ?? now()->format('Y-m-d\\TH:i');
    $activityModalId = $activityModalId ?? 'addActivityModal';
@endphp

<div class="modal fade" id="{{ $activityModalId }}" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">{{ $activityModalTitle }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('sales.activity.store') }}" enctype="multipart/form-data" class="shared-activity-form">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Client / Company <span class="text-danger">*</span></label>
                        @if($activityContextType && $activityContextId)
                            <input type="text" class="form-control" value="{{ $activityContextLabel }}" readonly>
                            <input type="hidden" name="client_ref" value="{{ $activityContextType }}:{{ $activityContextId }}">
                            @if($activityContextType === 'lead')
                                <input type="hidden" name="lead_id" value="{{ $activityContextId }}">
                            @elseif($activityContextType === 'customer')
                                <input type="hidden" name="customer_id" value="{{ $activityContextId }}">
                            @endif
                        @else
                            <select name="client_ref" class="form-select shared-client-select" required>
                                <option value="">Pilih atau cari client</option>
                                @php
                                    $existingCustomerCompanyNames = \App\Models\Customer::where('status','Existing')->pluck('company_name')->map(fn($n) => strtolower(trim($n)))->toArray();
                                    $leadsNotExisting = \App\Models\Lead::orderBy('company_name')->get()->filter(fn($l) => !in_array(strtolower(trim($l->company_name)), $existingCustomerCompanyNames));
                                    $existingCustomers = \App\Models\Customer::where('status','Existing')->orderBy('company_name')->get();
                                @endphp
                                <optgroup label="— Leads —">
                                    @foreach($leadsNotExisting as $lead)
                                        <option value="lead:{{ $lead->id }}" data-type="lead" data-stage="{{ $lead->pipeline_stage }}">{{ $lead->company_name }} (Lead)</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="— Customer Existing —">
                                    @foreach($existingCustomers as $cust)
                                        @php
                                            $custLead = \App\Models\Lead::where('customer_id', $cust->id)
                                                ->orderByDesc('updated_at')->first()
                                                ?? \App\Models\Lead::whereRaw('LOWER(TRIM(company_name)) = ?', [strtolower(trim($cust->company_name))])
                                                ->orderByDesc('updated_at')->first();
                                            $custStage = $custLead?->pipeline_stage ?: 'Maintaining';
                                        @endphp
                                        <option value="customer:{{ $cust->id }}" data-type="customer" data-stage="{{ $custStage }}">{{ $cust->company_name }} (Existing)</option>
                                    @endforeach
                                </optgroup>
                            </select>
                        @endif
                    </div>

                    <div class="mb-3 shared-stage-wrap">
                        <label class="form-label">Pipeline Stage <span class="text-danger">*</span></label>
                        <select name="pipeline_stage" class="form-select shared-stage-select" required>
                            @foreach(['Identifying'=>'Identifying','Approaching'=>'Approaching','Follow Up'=>'Follow Up','Won'=>'Won/Closing','Maintaining'=>'Maintaining'] as $val => $label)
                                <option value="{{ $val }}" {{ $activityContextStage === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Stage ini akan tersimpan di activity dan ikut mengupdate pipeline Lead/Customer terkait.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Activity Type <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2 flex-wrap">
                            @foreach(['Call','Visit','Email','Note','Others'] as $t)
                                @php $radioId = $activityModalId.'_type_'.strtolower($t); @endphp
                                <div>
                                    <input type="radio" class="btn-check shared-activity-type" name="type" id="{{ $radioId }}" value="{{ $t }}" {{ $t === 'Call' ? 'checked' : '' }}>
                                    <label class="btn btn-sm btn-outline-secondary" for="{{ $radioId }}">{{ $t === 'Others' ? 'Others' : $t }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3 shared-photo-wrap" style="display:none">
                        <label class="form-label">Foto Kunjungan</label>
                        <input type="file" name="photo" class="form-control shared-photo-input" accept="image/jpg,image/jpeg,image/png,image/webp">
                        <div class="form-text">JPG/PNG/WebP, maks 3MB.</div>
                        <div class="shared-photo-preview mt-2" style="display:none">
                            <img src="" alt="Preview" style="max-width:100%;max-height:180px;border-radius:8px;border:1px solid #e5e7eb">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="Pending">Pending</option>
                                <option value="Planned">Planned</option>
                                <option value="Done">Done</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" placeholder="Judul aktivitas..." required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Tulis catatan aktivitas..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Next Follow Up</label>
                        <input type="date" name="next_follow_up" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Activity</button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    function applyStage(form) {
        if (!form) return;
        var $ = window.jQuery;
        var select = form.querySelector('.shared-client-select');
        var wrap = form.querySelector('.shared-stage-wrap');
        var stageSelect = form.querySelector('.shared-stage-select');
        if (!select || !wrap || !stageSelect) return;

        var opt = select.options[select.selectedIndex];
        var hint = wrap.querySelector('.form-text');

        if (select.value) {
            wrap.style.display = '';
            var stage = (opt && opt.dataset && opt.dataset.stage) ? opt.dataset.stage : '';
            if (stage === 'Closing') stage = 'Won';
            if (stage) {
                stageSelect.value = stage;
                // refresh tampilan Select2 jika aktif
                if ($ && $(stageSelect).data('select2')) {
                    $(stageSelect).val(stage).trigger('change.select2');
                }
            }
            // Kunci stage agar mengikuti pipeline terakhir client.
            stageSelect.setAttribute('readonly', 'readonly');
            stageSelect.style.pointerEvents = 'none';
            stageSelect.style.background = '#f3f4f6';
            if ($ && $(stageSelect).data('select2')) {
                // matikan interaksi Select2 stage
                $(stageSelect).next('.select2').css({'pointer-events':'none','opacity':'0.85'});
            }
            if (hint) hint.textContent = 'Stage mengikuti pipeline terakhir client yang dipilih.';
        } else {
            stageSelect.value = 'Identifying';
            if ($ && $(stageSelect).data('select2')) {
                $(stageSelect).val('Identifying').trigger('change.select2');
            }
        }
    }

    function bind() {
        var $ = window.jQuery;
        if ($) {
            // Delegated change — kompatibel dengan Select2 yang memicu event via jQuery.
            $(document).off('change.sharedStage').on('change.sharedStage', '.shared-client-select', function () {
                applyStage(this.closest('form'));
            });
        } else {
            document.querySelectorAll('.shared-client-select').forEach(function (select) {
                select.addEventListener('change', function () { applyStage(select.closest('form')); });
            });
        }

        // Saat modal dibuka, terapkan stage jika sudah ada client terpilih (mis. context lead/customer).
        if ($) {
            $(document).on('shown.bs.modal', '.modal', function () {
                var el = this.querySelector('.shared-client-select');
                if (el && el.value) applyStage(el.closest('form'));
            });
        }

        // Foto kunjungan (Visit)
        document.querySelectorAll('.shared-activity-form').forEach(function (form) {
            var photoWrap = form.querySelector('.shared-photo-wrap');
            var togglePhoto = function () {
                var checked = form.querySelector('.shared-activity-type:checked');
                if (photoWrap) photoWrap.style.display = checked && checked.value === 'Visit' ? '' : 'none';
            };
            form.querySelectorAll('.shared-activity-type').forEach(function (radio) {
                radio.addEventListener('change', togglePhoto);
            });
            togglePhoto();
        });

        document.querySelectorAll('.shared-photo-input').forEach(function (input) {
            input.addEventListener('change', function () {
                var wrap = input.closest('.shared-photo-wrap');
                var preview = wrap && wrap.querySelector('.shared-photo-preview');
                var img = preview && preview.querySelector('img');
                var file = input.files && input.files[0];
                if (!preview || !img || !file) return;
                img.src = URL.createObjectURL(file);
                preview.style.display = '';
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
</script>
@endpush
@endonce
