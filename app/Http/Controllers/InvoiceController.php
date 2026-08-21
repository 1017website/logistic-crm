<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RequestOrder;
use App\Models\User;
use App\Services\InvoiceBillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invoice multi-DO dengan komponen Trucking dan Non-Trucking.
 *
 * DO mulai dapat ditagih setelah POD diterima. Satu komponen DO hanya dapat
 * berada dalam satu invoice aktif, termasuk saat dua request tiba bersamaan.
 */
class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'draft');
        $search = $request->get('search');
        $customerId = $request->get('customer_id');
        $jenis = $request->get('jenis', 'all');

        $statusMap = ['draft' => 'draft', 'invoice' => 'invoice', 'paid' => 'paid'];
        $status = $statusMap[$tab] ?? 'draft';

        $query = Invoice::with(['customer', 'operator', 'items'])
            ->where('status', $status);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        if (array_key_exists((string) $jenis, Invoice::TYPES)) {
            $query->where('jenis', $jenis);
        }
        if ($search) {
            $query->where(fn($q) => $q
                ->where('invoice_id', 'like', "%{$search}%")
                ->orWhere('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn($q) => $q->where('company_name', 'like', "%{$search}%")));
        }

        $invoices = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $customers = Customer::orderBy('company_name')
            ->get(['id', 'company_name', 'customer_code', 'invoice_code']);
        $pendingDeletionIds = \App\Models\DeletionRequest::pendingIdsFor(Invoice::class);

        return view('invoices.index', compact(
            'invoices',
            'tab',
            'status',
            'search',
            'customers',
            'customerId',
            'jenis',
            'pendingDeletionIds'
        ));
    }

    /**
     * DO final yang POD-nya sudah diterima dan masih memiliki komponen yang
     * belum masuk invoice.
     */
    public function availableDos(Request $request)
    {
        $customerId = $request->integer('customer_id');
        if (!$customerId) {
            return response()->json([]);
        }

        $dos = DeliveryOrder::with([
            'requestOrder.jobDetails',
            'requestOrder.items',
            'invoiceItems.invoice',
        ])
            ->where('customer_id', $customerId)
            ->whereNotNull('pod_at')
            ->where('status', '!=', 'cancelled')
            ->whereHas('requestOrder', fn($q) => $q->where('do_approved', true))
            ->orderByDesc('do_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (DeliveryOrder $do) {
                $usedTypes = $do->invoiceItems
                    ->filter(fn(InvoiceItem $item) => $item->invoice !== null)
                    ->pluck('item_type')
                    ->filter()
                    ->unique();

                $types = collect($do->invoiceBreakdown())
                    ->map(fn(array $row, string $type) => [
                        ...$row,
                        'available' => !$usedTypes->contains($type),
                    ])
                    ->values();

                return [
                    'id' => $do->id,
                    'do_number' => $do->do_number,
                    'request_number' => $do->requestOrder?->do_number,
                    'do_date' => $do->do_date?->format('d M Y'),
                    'pod_at' => $do->pod_at?->format('d M Y H:i'),
                    'origin' => $do->origin,
                    'destination' => $do->destination,
                    'types' => $types,
                ];
            })
            ->filter(fn(array $do) => collect($do['types'])->contains('available', true))
            ->values();

        return response()->json($dos);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'tgl_buat' => 'required|date',
            'tgl_tempo' => 'nullable|date|after_or_equal:tgl_buat',
            'selections' => 'required|array|min:1',
            'selections.*' => ['required', 'string', 'regex:/^\d+:(TR|NTR)$/'],
            // Nilai combined lama tetap diterima agar form/cache lama tidak error,
            // tetapi penyimpanan di bawah selalu memisahkan TR dan NTR.
            'billing_mode' => 'nullable|in:combined,separate',
            'ppn_mode' => 'required|in:ppn,non_ppn',
            'ppn_persen' => 'required_if:ppn_mode,ppn|nullable|numeric|min:0.01|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        $selectionMap = collect($data['selections'])
            ->unique()
            ->mapWithKeys(function (string $selection) {
                [$doId, $type] = explode(':', $selection, 2);
                return [$doId . ':' . $type => ['do_id' => (int) $doId, 'type' => $type]];
            });

        $created = DB::transaction(function () use ($data, $selectionMap) {
            // Lock customer menyerialkan nomor urut per customer.
            $customer = Customer::query()->lockForUpdate()->findOrFail($data['customer_id']);
            $doIds = $selectionMap->pluck('do_id')->unique()->sort()->values();

            // Lock DO menyerialkan pemakaian komponen TR/NTR di invoice.
            $dos = DeliveryOrder::with(['requestOrder.jobDetails', 'requestOrder.items'])
                ->whereKey($doIds)
                ->where('customer_id', $customer->id)
                ->whereNotNull('pod_at')
                ->where('status', '!=', 'cancelled')
                ->whereHas('requestOrder', fn($q) => $q->where('do_approved', true))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($dos->count() !== $doIds->count()) {
                throw ValidationException::withMessages([
                    'selections' => 'Ada DO yang belum menerima POD, belum disetujui, atau bukan milik customer terpilih.',
                ]);
            }

            $existing = InvoiceItem::query()
                ->whereIn('delivery_order_id', $doIds)
                ->lockForUpdate()
                ->get()
                ->mapWithKeys(fn(InvoiceItem $item) => [
                    $item->delivery_order_id . ':' . $item->item_type => true,
                ]);

            $rows = $selectionMap->map(function (array $selection, string $key) use ($dos, $existing) {
                if ($existing->has($key)) {
                    throw ValidationException::withMessages([
                        'selections' => "Komponen {$selection['type']} pada DO terpilih sudah masuk invoice lain.",
                    ]);
                }

                $do = $dos->get($selection['do_id']);
                $breakdown = $do->invoiceBreakdown();
                if (!isset($breakdown[$selection['type']])) {
                    throw ValidationException::withMessages([
                        'selections' => "Komponen {$selection['type']} tidak tersedia pada {$do->do_number}.",
                    ]);
                }

                return [
                    'do' => $do,
                    ...$breakdown[$selection['type']],
                ];
            })->values();

            // Kebijakan baru: komponen Trucking dan Non-Trucking selalu terpisah,
            // tetapi setiap invoice tetap dapat memuat banyak DO customer yang sama.
            $groups = $rows->groupBy('type');

            $createdInvoices = collect();
            $ppnPersen = $data['ppn_mode'] === 'ppn' ? (float) $data['ppn_persen'] : 0.0;

            foreach ($groups as $groupRows) {
                /** @var Collection<int, array> $groupRows */
                $types = $groupRows->pluck('type')->unique()->values();
                $jenis = $types->count() > 1 ? 'MIX' : $types->first();
                $seq = Invoice::nextCustomerSeq($customer->id);

                // ID sementara unik menghindari race nomor internal global.
                $invoice = Invoice::create([
                    'invoice_id' => 'TMP-' . Str::uuid(),
                    'invoice_number' => Invoice::buildInvoiceNumber(
                        $seq,
                        $customer->invoice_number_code,
                        Carbon::parse($data['tgl_buat'])
                    ),
                    'customer_seq' => $seq,
                    'customer_id' => $customer->id,
                    'status' => 'draft',
                    'tgl_buat' => $data['tgl_buat'],
                    'tgl_tempo' => $data['tgl_tempo'] ?? null,
                    'jenis' => $jenis,
                    'billing_mode' => 'separate',
                    'operator_id' => auth()->id(),
                    'notes' => $data['notes'] ?? null,
                ]);
                $invoice->update([
                    'invoice_id' => 'IV' . Carbon::parse($data['tgl_buat'])->format('ym')
                        . str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT),
                ]);

                foreach ($groupRows as $row) {
                    /** @var DeliveryOrder $do */
                    $do = $row['do'];
                    $invoice->items()->create([
                        'request_order_id' => $do->request_order_id,
                        'delivery_order_id' => $do->id,
                        'item_type' => $row['type'],
                        'item_name' => $row['type'] === 'TR' ? 'Trucking' : 'Non-Trucking',
                        'description' => $row['description'],
                        'truck_type' => $do->requestOrder?->jenis_truck,
                        'quantity' => 1,
                        'unit_price' => $row['jual'],
                        'hpp' => $row['hpp'],
                        'jual' => $row['jual'],
                    ]);
                }

                $this->recalcTotals(
                    $invoice,
                    (float) $groupRows->sum('hpp'),
                    (float) $groupRows->sum('jual'),
                    $ppnPersen
                );
                $createdInvoices->push($invoice);
            }

            app(InvoiceBillingService::class)->sync($doIds);

            return $createdInvoices;
        }, 3);

        $message = $created->count() > 1
            ? "{$created->count()} draft invoice berhasil dibuat terpisah."
            : 'Draft invoice berhasil dibuat.';

        return redirect()->route('invoices.index', ['tab' => 'draft'])->with('success', $message);
    }

    public function submit(Request $request, Invoice $invoice)
    {
        DB::transaction(function () use ($invoice) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['general' => 'Hanya draft yang bisa diterbitkan.']);
            }

            $locked->update(['status' => 'invoice']);
            $doIds = $locked->items()->pluck('delivery_order_id')->filter()->unique();

            DeliveryOrder::whereIn('id', $doIds)->where('status', 'closed')->get()
                ->each(fn(DeliveryOrder $do) => $do->transition(
                    'invoiced',
                    "Invoice {$locked->invoice_number} diterbitkan.",
                    auth()->id()
                ));
            app(InvoiceBillingService::class)->sync($doIds);
        });

        return back()->with('success', 'Invoice resmi diterbitkan.');
    }

    public function unsubmit(Invoice $invoice)
    {
        DB::transaction(function () use ($invoice) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status !== 'invoice') {
                throw ValidationException::withMessages(['general' => 'Hanya invoice terbit yang bisa dikembalikan ke draft.']);
            }
            $locked->update(['status' => 'draft']);
        });

        return back()->with('success', 'Invoice dikembalikan ke draft.');
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $data = $request->validate(['tgl_pencairan' => 'required|date']);

        DB::transaction(function () use ($invoice, $data) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status !== 'invoice') {
                throw ValidationException::withMessages(['general' => 'Hanya invoice terbit yang bisa ditandai lunas.']);
            }

            $locked->update(['status' => 'paid', 'tgl_pencairan' => $data['tgl_pencairan']]);
            $doIds = $locked->items()->pluck('delivery_order_id')->filter()->unique();
            $legacyRequestOrderIds = $locked->items()
                ->whereNull('delivery_order_id')
                ->pluck('request_order_id')
                ->filter()
                ->unique();
            RequestOrder::whereIn('id', $legacyRequestOrderIds)->update(['invoice_status' => 'paid']);
            app(InvoiceBillingService::class)->sync($doIds);

            DeliveryOrder::whereIn('id', $doIds)
                ->where('invoice_status', 'paid')
                ->whereIn('status', ['closed', 'invoiced'])
                ->get()
                ->each(fn(DeliveryOrder $do) => $do->transition(
                    'paid',
                    "Lunas mengikuti invoice {$locked->invoice_number}.",
                    auth()->id()
                ));
        });

        return back()->with(
            'success',
            'Invoice ditandai lunas. DO otomatis menjadi lunas setelah seluruh komponen tagihannya lunas.'
        );
    }

    public function updateNumber(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoiceEdit($invoice);
        $data = $request->validate(['invoice_number' => 'required|string|max:100']);
        $invoice->update(['invoice_number' => $data['invoice_number']]);

        return back()->with('success', 'Nomor invoice diperbarui.');
    }

    public function updatePpn(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoiceEdit($invoice);
        $data = $request->validate(['ppn_persen' => 'required|numeric|min:0|max:100']);
        $this->recalcTotals(
            $invoice,
            (float) $invoice->total_hpp,
            (float) $invoice->total_jual,
            (float) $data['ppn_persen']
        );

        return back()->with('success', 'PPN diperbarui.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load([
            'customer',
            'operator',
            'editRequester',
            'editReviewer',
            'items.requestOrder.jobDetails',
            'items.deliveryOrder.requestOrder',
        ]);

        return view('invoices.show', compact('invoice'));
    }

    public function requestEdit(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->isFinance()) abort(403);
        if ($invoice->status === 'paid') {
            return back()->withErrors(['general' => 'Invoice lunas tidak dapat diminta untuk diedit.']);
        }

        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $invoice->update([
            'edit_request_status' => 'pending',
            'edit_request_reason' => $data['reason'],
            'edit_requested_by' => auth()->id(),
            'edit_requested_at' => now(),
            'edit_reviewed_by' => null,
            'edit_reviewed_at' => null,
            'edit_review_note' => null,
        ]);

        User::where('role', 'Super Admin')->where('status', 'Active')->each(fn(User $admin) =>
            \App\Models\Notification::send(
                $admin->id,
                'invoice_edit_request',
                'Permintaan edit invoice',
                $invoice->invoice_number . ' menunggu persetujuan Super Admin.',
                route('invoices.show', $invoice)
            )
        );

        return back()->with('success', 'Permintaan edit dikirim ke Super Admin.');
    }

    public function reviewEdit(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->isSuperAdmin()) abort(403);
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'note' => 'nullable|string|max:1000',
        ]);
        if ($invoice->edit_request_status !== 'pending') {
            return back()->withErrors(['general' => 'Tidak ada permintaan edit yang sedang menunggu.']);
        }

        $invoice->update([
            'edit_request_status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'edit_reviewed_by' => auth()->id(),
            'edit_reviewed_at' => now(),
            'edit_review_note' => $data['note'] ?? null,
        ]);

        return back()->with('success', $data['action'] === 'approve'
            ? 'Permintaan disetujui. Finance sekarang dapat mengedit invoice.'
            : 'Permintaan edit ditolak.');
    }

    public function finishEdit(Invoice $invoice)
    {
        if (!auth()->user()->isFinance() || $invoice->edit_request_status !== 'approved') abort(403);
        $invoice->update(['edit_request_status' => 'none']);
        return back()->with('success', 'Edit selesai dan invoice dikunci kembali.');
    }

    public function updateItem(Request $request, Invoice $invoice, InvoiceItem $invoiceItem)
    {
        $this->authorizeInvoiceEdit($invoice);
        abort_unless($invoiceItem->invoice_id === $invoice->id, 404);
        $data = $request->validate([
            'item_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'truck_type' => 'nullable|string|max:255',
            'quantity' => 'required|numeric|min:0.001',
            'unit_price' => 'required|numeric|min:0',
        ]);
        $data['jual'] = round((float) $data['quantity'] * (float) $data['unit_price']);
        $invoiceItem->update($data);
        $invoice->refresh();
        $this->recalcTotals(
            $invoice,
            (float) $invoice->items()->sum('hpp'),
            (float) $invoice->items()->sum('jual'),
            (float) $invoice->ppn_persen
        );

        return back()->with('success', 'Rincian invoice diperbarui.');
    }

    public function destroy(Invoice $invoice)
    {
        DB::transaction(function () use ($invoice) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status === 'paid') {
                throw ValidationException::withMessages([
                    'general' => 'Invoice yang sudah lunas tidak dapat dihapus.',
                ]);
            }

            $doIds = $locked->items()->pluck('delivery_order_id')->filter()->unique();
            $legacyRequestOrderIds = $locked->items()
                ->whereNull('delivery_order_id')
                ->pluck('request_order_id')
                ->filter()
                ->unique();
            $locked->items()->delete();
            $locked->delete();
            RequestOrder::whereIn('id', $legacyRequestOrderIds)->update(['invoice_status' => 'uninvoiced']);
            app(InvoiceBillingService::class)->sync($doIds);
        });

        return back()->with('success', 'Invoice dihapus dan komponen DO dilepas untuk ditagih ulang.');
    }

    public function print(Request $request, Invoice $invoice, \App\Services\DocumentSignatureService $documentSignature)
    {
        return view('invoices.print', [
            ...$this->printPayload($request, $invoice, $documentSignature),
            'isPdf' => false,
        ]);
    }

    public function pdf(Request $request, Invoice $invoice, \App\Services\DocumentSignatureService $documentSignature)
    {
        $payload = [...$this->printPayload($request, $invoice, $documentSignature), 'isPdf' => true];
        $logo = $payload['company']['logo'] ?? null;
        if ($logo && !str_starts_with($logo, 'data:') && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)) {
            $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($logo) ?: 'image/png';
            $payload['company']['logo'] = 'data:' . $mime . ';base64,'
                . base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($logo));
        } elseif ($logo && !str_starts_with($logo, 'data:')) {
            $payload['company']['logo'] = null;
        }
        return Pdf::loadView('invoices.print', $payload)->setPaper('a4', 'landscape')
            ->download('invoice-' . Str::slug($invoice->invoice_number ?: $invoice->invoice_id) . '.pdf');
    }

    public function exportInvoice(Invoice $invoice)
    {
        $invoice->load(['customer', 'items.deliveryOrder', 'items.requestOrder']);
        $headers = ['No Invoice', 'Customer', 'No DO', 'Nama', 'Uraian', 'Jenis Truck', 'Qty', 'Harga', 'Jumlah'];
        $rows = $invoice->items->isNotEmpty()
            ? $invoice->items->map(fn(InvoiceItem $item) => [
                $invoice->invoice_number,
                $invoice->customer?->company_name ?? '-',
                $item->deliveryOrder?->do_number ?? $item->requestOrder?->do_number ?? '-',
                $item->item_name,
                $item->description,
                $item->truck_type,
                (float) $item->quantity,
                (float) $item->unit_price,
                (float) $item->jual,
            ])->all()
            : [[
                $invoice->invoice_number,
                $invoice->customer?->company_name ?? '-',
                '-', $invoice->jenis_label ?: 'Invoice',
                $invoice->notes ?: 'Ringkasan invoice (rincian DO tidak tersedia)',
                '-', 1, (float) $invoice->total_jual, (float) $invoice->total_jual,
            ]];

        return \App\Helpers\ExcelExport::download(
            'invoice-' . ($invoice->invoice_id ?: $invoice->id), $headers, $rows, 'Invoice'
        );
    }

    public function export(Request $request)
    {
        $status = $request->get('status');
        $customerId = $request->get('customer_id');
        $jenis = $request->get('jenis');

        $query = Invoice::with(['customer', 'items.deliveryOrder', 'items.requestOrder']);
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        if (array_key_exists((string) $jenis, Invoice::TYPES)) {
            $query->where('jenis', $jenis);
        }

        $headers = [
            'Invoice ID', 'No Invoice', 'Customer', 'Status', 'Tgl Buat', 'No DO',
            'Nama', 'Uraian', 'Jenis Truck', 'Qty', 'Harga', 'Jumlah', 'PPN Invoice', 'Grand Total Invoice',
        ];
        $rows = $query->orderByDesc('tgl_buat')->get()->flatMap(function (Invoice $inv) {
            if ($inv->items->isEmpty()) {
                return [[
                    $inv->invoice_id,
                    $inv->invoice_number,
                    $inv->customer?->company_name ?? '-',
                    $inv->status_label,
                    $inv->tgl_buat?->format('Y-m-d'),
                    '-',
                    $inv->jenis_label ?: 'Invoice',
                    $inv->notes ?: 'Ringkasan invoice (rincian DO tidak tersedia)',
                    '-', 1, (float) $inv->total_jual, (float) $inv->total_jual,
                    (float) $inv->ppn_nominal,
                    (float) ($inv->grand_total ?: $inv->total_jual),
                ]];
            }

            return $inv->items->map(fn(InvoiceItem $item) => [
                $inv->invoice_id,
                $inv->invoice_number,
                $inv->customer?->company_name ?? '-',
                $inv->status_label,
                $inv->tgl_buat?->format('Y-m-d'),
                $item->deliveryOrder?->do_number ?? $item->requestOrder?->do_number ?? '-',
                $item->item_name,
                $item->description,
                $item->truck_type,
                (float) $item->quantity,
                (float) $item->unit_price,
                (float) $item->jual,
                (float) $inv->ppn_nominal,
                (float) $inv->grand_total,
            ]);
        })->all();

        return \App\Helpers\ExcelExport::download(
            'invoices-' . date('Ymd'),
            $headers,
            $rows,
            'Invoices'
        );
    }

    public function exportPdf(Request $request)
    {
        $query = Invoice::with(['customer', 'items.deliveryOrder', 'items.requestOrder']);
        if ($request->filled('status') && $request->status !== 'all') $query->where('status', $request->status);
        if ($request->filled('customer_id')) $query->where('customer_id', $request->integer('customer_id'));
        if (array_key_exists((string) $request->jenis, Invoice::TYPES)) $query->where('jenis', $request->jenis);
        $invoices = $query->orderByDesc('tgl_buat')->get();
        $customer = $request->filled('customer_id') ? Customer::find($request->integer('customer_id')) : null;

        return Pdf::loadView('invoices.export_pdf', compact('invoices', 'customer'))
            ->setPaper('a4', 'landscape')
            ->download('rekap-invoice-' . ($customer ? Str::slug($customer->company_name) : 'semua-customer') . '.pdf');
    }

    private function recalcTotals(Invoice $invoice, float $hpp, float $jual, float $ppnPersen): void
    {
        $ppnNominal = round($jual * $ppnPersen / 100);
        $invoice->update([
            'total_hpp' => $hpp,
            'total_jual' => $jual,
            'ppn_persen' => $ppnPersen,
            'ppn_nominal' => $ppnNominal,
            'grand_total' => $jual + $ppnNominal,
        ]);
    }

    private function authorizeInvoiceEdit(Invoice $invoice): void
    {
        if ($invoice->status === 'paid') abort(403, 'Invoice lunas tidak dapat diedit.');
        $user = auth()->user();
        abort_unless($user->isSuperAdmin() || ($user->isFinance() && $invoice->edit_request_status === 'approved'), 403,
            'Finance harus mengajukan permintaan edit dan menunggu persetujuan Super Admin.');
    }

    private function printPayload(Request $request, Invoice $invoice, \App\Services\DocumentSignatureService $documentSignature): array
    {
        $data = $request->validate(['type' => 'nullable|in:all,TR,NTR']);
        $printType = $data['type'] ?? 'all';
        $invoice->load(['customer', 'items.requestOrder', 'items.deliveryOrder.requestOrder']);
        $printItems = $invoice->items
            ->when($printType !== 'all', fn(Collection $items) => $items->where('item_type', $printType))->values();
        if ($printItems->isEmpty()) {
            throw ValidationException::withMessages(['type' => 'Tipe layanan yang dipilih tidak ada pada invoice ini.']);
        }
        $printSubtotal = (float) $printItems->sum('jual');
        $printPpn = round($printSubtotal * (float) $invoice->ppn_persen / 100);
        $printGrand = $printSubtotal + $printPpn;
        $companyName = \App\Models\Setting::get('company_name', 'Perusahaan');
        $salesManager = User::where('role', 'Sales Manager')->where('status', 'Active')->orderBy('id')->first();
        $logo = \App\Models\Setting::get('company_doc_logo') ?: \App\Models\Setting::get('company_logo', '');
        $company = [
            'name' => $companyName,
            'address' => \App\Models\Setting::get('company_address', ''),
            'phone' => \App\Models\Setting::get('company_phone', ''),
            'email' => \App\Models\Setting::get('company_email', ''),
            'website' => \App\Models\Setting::get('company_website', ''),
            'logo' => $logo,
            'signatory_name' => $salesManager?->name ?: (\App\Models\Setting::get('company_signatory_name') ?: $companyName),
            'signatory_title' => $salesManager
                ? ($salesManager->position ?: 'Sales Manager')
                : (\App\Models\Setting::get('company_signatory_title') ?: 'Direktur'),
        ];
        $signature = $documentSignature->make('invoice', $invoice->getKey());
        return compact('invoice', 'printType', 'printItems', 'printSubtotal', 'printPpn', 'printGrand', 'company', 'signature');
    }

}
