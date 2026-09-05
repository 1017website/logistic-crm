<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

class DocumentVerificationController extends Controller
{
    public function show(Request $request, string $kind, int $id)
    {
        $companyName = Setting::get('company_name', 'PT Firman Tangguh Logistik');
        $defaultSigner = Setting::get('company_signatory_name') ?: $companyName;
        $defaultTitle = Setting::get('company_signatory_title') ?: 'Direktur';

        $verification = match ($kind) {
            'quotation' => $this->quotation($id),
            'invoice' => $this->invoice($id, $defaultSigner, $defaultTitle),
            'delivery_order' => $this->deliveryOrder($id, $defaultSigner, $defaultTitle),
            'report' => $this->report($request, $id, $defaultSigner, $defaultTitle),
            default => abort(404),
        };

        return view('documents.verify', compact('verification', 'companyName'));
    }

    private function quotation(int $id): array
    {
        $document = Quotation::with('user')->findOrFail($id);

        return [
            'label' => 'Surat Penawaran Harga',
            'number' => $document->quotation_number,
            'number_label' => 'Nomor surat',
            'attachment' => $document->attachment ?: '-',
            'subject' => $document->subject ?: '-',
            'date' => $document->quotation_date?->translatedFormat('d F Y'),
            'counterparty' => $document->company_name,
            'signer' => $document->signatory_name,
            'title' => $document->resolvedSignatoryTitle(),
            'status' => $document->status_label,
        ];
    }

    private function invoice(int $id, string $signer, string $title): array
    {
        $document = Invoice::with('customer')->findOrFail($id);
        $salesManager = User::where('role', 'Sales Manager')->where('status', 'Active')->orderBy('id')->first();

        return [
            'label' => 'Invoice',
            'number' => $document->invoice_number,
            'date' => $document->tgl_buat?->translatedFormat('d F Y'),
            'counterparty' => $document->customer?->company_name ?? '-',
            'signer' => $salesManager?->name ?: $signer,
            'title' => $salesManager ? ($salesManager->position ?: 'Sales Manager') : $title,
            'status' => $document->status_label,
        ];
    }

    private function deliveryOrder(int $id, string $signer, string $title): array
    {
        $document = DeliveryOrder::with('customer')->findOrFail($id);

        return [
            'label' => 'Surat Jalan / Delivery Order',
            'number' => $document->do_number,
            'date' => $document->do_date?->translatedFormat('d F Y'),
            'counterparty' => $document->customer?->company_name ?? '-',
            'signer' => $signer,
            'title' => $title,
            'status' => $document->flow_label,
        ];
    }

    private function report(Request $request, int $id, string $signer, string $title): array
    {
        $data = $request->validate([
            'report_type' => ['required', 'in:sales,customer,pipeline,performance,po'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
        $labels = [
            'sales' => 'Sales Report',
            'customer' => 'Customer Report',
            'pipeline' => 'Pipeline Report',
            'performance' => 'Performance Report',
            'po' => 'PO Report',
        ];

        return [
            'label' => 'Laporan CRM',
            'number' => 'RPT-' . $id,
            'date' => now()->translatedFormat('d F Y'),
            'counterparty' => $labels[$data['report_type']],
            'signer' => $signer,
            'title' => $title,
            'status' => 'Diterbitkan',
            'period' => $data['start_date'] . ' s/d ' . $data['end_date'],
        ];
    }
}
