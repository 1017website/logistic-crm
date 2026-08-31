@extends('layouts.app')
@section('title', 'Laporan Logistik')
@section('page-title', 'Laporan Logistik')
@section('page-subtitle', 'Laporan Delivery Order & Invoice')

@section('content')
<div class="row g-3">
    <div class="col-md-3 col-6">
        <a href="{{ route('logistic-reports.do') }}" class="text-decoration-none">
            <div class="card h-100" style="transition:.15s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                <div class="card-body text-center p-4">
                    <div style="width:56px;height:56px;border-radius:14px;background:#e0e7ff;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                        <i class="fas fa-truck" style="font-size:24px;color:#4f46e5"></i>
                    </div>
                    <div style="font-weight:700">Laporan DO</div>
                    <div style="font-size:12px;color:#6b7280">Rekap Delivery Order per client & bulan</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="{{ route('logistic-reports.invoice') }}" class="text-decoration-none">
            <div class="card h-100" style="transition:.15s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                <div class="card-body text-center p-4">
                    <div style="width:56px;height:56px;border-radius:14px;background:#dcfce7;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                        <i class="fas fa-file-invoice-dollar" style="font-size:24px;color:#16a34a"></i>
                    </div>
                    <div style="font-weight:700">Laporan Invoice</div>
                    <div style="font-size:12px;color:#6b7280">Tagihan, jatuh tempo & umur piutang</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="{{ route('logistic-reports.outstanding') }}" class="text-decoration-none">
            <div class="card h-100" style="transition:.15s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                <div class="card-body text-center p-4">
                    <div style="width:56px;height:56px;border-radius:14px;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                        <i class="fas fa-hand-holding-usd" style="font-size:24px;color:#dc2626"></i>
                    </div>
                    <div style="font-weight:700">Laporan Outstanding</div>
                    <div style="font-size:12px;color:#6b7280">Piutang belum terbayar & aging per client</div>
                </div>
            </div>
        </a>
    </div>
</div>
@endsection
