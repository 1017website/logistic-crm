@php
    $isSales     = auth()->user()->isSalesExecutive();
    $currentUser = auth()->user();
    $fieldId     = $fieldId ?? 'salesPicField_' . uniqid();
    $selectedId  = $selectedId ?? ($isSales ? $currentUser->id : null);
    $fieldName   = $fieldName ?? 'user_id';
    $required    = $required ?? true;

    // Load all active sales users untuk dropdown (Admin/Manager)
    if (!$isSales) {
        $salesPicOptions = \App\Models\User::whereIn('role', ['Sales Executive', 'Sales Manager', 'Admin'])
            ->where('status', 'Active')
            ->orderBy('name')->get();
    }
@endphp

@if($isSales)
    {{-- Sales Executive: auto-filled, tidak bisa ganti --}}
    <input type="hidden" name="{{ $fieldName }}" value="{{ $currentUser->id }}">
    <label class="form-label">Sales PIC</label>
    <div class="form-control" style="background:#f9fafb;color:#374151;cursor:not-allowed;display:flex;align-items:center;gap:8px">
        <div style="width:22px;height:22px;border-radius:50%;background:#3b82f6;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">
            {{ $currentUser->avatar_initials }}
        </div>
        <span style="font-size:13px;font-weight:500">{{ $currentUser->name }}</span>
        <span style="font-size:11px;color:#9ca3af;margin-left:auto">Saya</span>
    </div>
@else
    {{-- Admin/Manager: dropdown pilih sales --}}
    <label class="form-label">Sales PIC {{ $required ? '*' : '' }}</label>
    <select name="{{ $fieldName }}" id="{{ $fieldId }}" class="form-select" {{ $required ? 'required' : '' }}>
        <option value="">- Pilih Sales PIC -</option>
        @foreach($salesPicOptions as $su)
        <option value="{{ $su->id }}" {{ $selectedId == $su->id ? 'selected' : '' }}>
            {{ $su->name }} ({{ $su->role }})
        </option>
        @endforeach
    </select>
@endif
