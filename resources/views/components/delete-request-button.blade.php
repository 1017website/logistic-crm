@php
    /**
     * Komponen tombol hapus universal.
     * - Admin  : tombol hapus langsung (eksekusi via deletion-requests.store, auto-approve di controller).
     * - Non-admin: tombol "Request Hapus" → buat permintaan, butuh persetujuan admin.
     *
     * Props:
     *  - module     : slug modul (leads|customers|vendors|delivery-orders)
     *  - id         : id record
     *  - label      : label data (untuk konfirmasi)
     *  - pending    : bool, apakah sedang menunggu persetujuan (untuk badge)
     *  - size       : 'sm' (default) ukuran tombol
     */
    $module  = $module  ?? null;
    $id      = $id      ?? null;
    $label   = $label   ?? '';
    $pending = $pending ?? false;
    $isAdmin = auth()->user()->isAdmin();
@endphp

@if($pending && !$isAdmin)
    {{-- Sudah ada permintaan: tampilkan badge menunggu, tombol non-aktif --}}
    <span class="badge bg-warning text-dark" style="font-size:.65rem" title="Menunggu persetujuan admin">
        <i class="fas fa-clock me-1"></i> Menunggu Hapus
    </span>
@elseif($pending && $isAdmin)
    {{-- Admin melihat data yang ada request pending → tetap bisa hapus langsung --}}
    <form method="POST" action="{{ route('deletion-requests.store') }}"
          onsubmit="return confirm('Hapus {{ addslashes($label) }}?')" style="display:inline">
        @csrf
        <input type="hidden" name="module" value="{{ $module }}">
        <input type="hidden" name="model_id" value="{{ $id }}">
        <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:3px 7px" title="Hapus (ada permintaan menunggu)">
            <i class="fas fa-trash" style="font-size:.7rem"></i>
        </button>
    </form>
@else
    <form method="POST" action="{{ route('deletion-requests.store') }}"
          onsubmit="return {{ $isAdmin ? "confirm('Hapus " . addslashes($label) . "?')" : "confirm('Ajukan permintaan hapus untuk " . addslashes($label) . "? Perlu persetujuan administrator.')" }}"
          style="display:inline">
        @csrf
        <input type="hidden" name="module" value="{{ $module }}">
        <input type="hidden" name="model_id" value="{{ $id }}">
        <button type="submit"
                class="btn btn-sm {{ $isAdmin ? 'btn-outline-danger' : 'btn-outline-warning' }}"
                style="padding:3px 7px"
                title="{{ $isAdmin ? 'Hapus' : 'Request Hapus (perlu persetujuan admin)' }}">
            <i class="fas {{ $isAdmin ? 'fa-trash' : 'fa-trash-restore-alt' }}" style="font-size:.7rem"></i>
        </button>
    </form>
@endif
