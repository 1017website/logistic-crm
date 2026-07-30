<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RequestOrder;
use App\Services\InvoiceBillingService;
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
            'billing_mode' => 'required|in:combined,separate',
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

            $groups = $data['billing_mode'] === 'separate'
                ? $rows->groupBy('type')
                : collect(['combined' => $rows]);

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
                    'billing_mode' => $data['billing_mode'],
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
                        'description' => $row['description'],
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
        $data = $request->validate(['invoice_number' => 'required|string|max:100']);
        $invoice->update(['invoice_number' => $data['invoice_number']]);

        return back()->with('success', 'Nomor invoice diperbarui.');
    }

    public function updatePpn(Request $request, Invoice $invoice)
    {
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
            'items.requestOrder.jobDetails',
            'items.deliveryOrder.requestOrder',
        ]);

        return view('invoices.show', compact('invoice'));
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

    public function print(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'type' => 'nullable|in:all,TR,NTR',
        ]);
        $printType = $data['type'] ?? 'all';

        $invoice->load([
            'customer',
            'items.requestOrder',
            'items.deliveryOrder.requestOrder',
        ]);

        $printItems = $invoice->items
            ->when($printType !== 'all', fn(Collection $items) => $items->where('item_type', $printType))
            ->values();

        if ($printItems->isEmpty()) {
            throw ValidationException::withMessages([
                'type' => 'Tipe layanan yang dipilih tidak ada pada invoice ini.',
            ]);
        }

        $printSubtotal = (float) $printItems->sum('jual');
        $printPpn = round($printSubtotal * (float) $invoice->ppn_persen / 100);
        $printGrand = $printSubtotal + $printPpn;

        $company = [
            'name' => \App\Models\Setting::get('company_name', 'Perusahaan'),
            'address' => \App\Models\Setting::get('company_address', ''),
            'phone' => \App\Models\Setting::get('company_phone', ''),
            'email' => \App\Models\Setting::get('company_email', ''),
            'website' => \App\Models\Setting::get('company_website', ''),
            'logo' => \App\Models\Setting::get('company_doc_logo')
                ?: \App\Models\Setting::get('company_logo', ''),
        ];

        return view('invoices.print', compact(
            'invoice',
            'printType',
            'printItems',
            'printSubtotal',
            'printPpn',
            'printGrand',
            'company'
        ));
    }

    public function export(Request $request)
    {
        $status = $request->get('status');
        $customerId = $request->get('customer_id');
        $jenis = $request->get('jenis');

        $query = Invoice::with('customer');
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
            'Invoice ID', 'No Invoice', 'Customer', 'Tipe', 'Status', 'Tgl Buat',
            'Tgl Tempo', 'HPP', 'Jual', 'Laba', 'PPN', 'Grand Total', 'Tgl Cair', 'Umur (hari)',
        ];
        $rows = $query->orderByDesc('tgl_buat')->get()->map(fn(Invoice $inv) => [
            $inv->invoice_id,
            $inv->invoice_number,
            $inv->customer?->company_name ?? '-',
            $inv->jenis_label,
            $inv->status_label,
            $inv->tgl_buat?->format('Y-m-d'),
            $inv->tgl_tempo?->format('Y-m-d'),
            (float) $inv->total_hpp,
            (float) $inv->total_jual,
            (float) $inv->laba,
            (float) $inv->ppn_nominal,
            (float) $inv->grand_total,
            $inv->tgl_pencairan?->format('Y-m-d'),
            $inv->umur_hari,
        ])->all();

        return \App\Helpers\ExcelExport::download(
            'invoices-' . date('Ymd'),
            $headers,
            $rows,
            'Invoices'
        );
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

}
