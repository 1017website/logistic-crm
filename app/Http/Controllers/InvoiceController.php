<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
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
 * DO mulai dapat ditagih setelah ditutup. Satu komponen DO hanya dapat
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
        $periode = $request->get('periode');

        $statusMap = ['draft' => 'draft', 'invoice' => 'invoice', 'paid' => 'settled'];
        $status = $statusMap[$tab] ?? 'draft';

        $query = Invoice::with(['customer', 'operator', 'items', 'payments']);
        $status === 'settled'
            ? $query->whereIn('status', ['termin', 'paid'])
            : $query->where('status', $status);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        if (array_key_exists((string) $jenis, Invoice::TYPES)) {
            $query->where('jenis', $jenis);
        }
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $periode)) {
            $query->whereDate('periode_invoice', $periode . '-01');
        }
        if ($search) {
            $query->where(fn($q) => $q
                ->where('invoice_id', 'like', "%{$search}%")
                ->orWhere('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn($q) => $q->where('company_name', 'like', "%{$search}%")));
        }

        // Pagination dilakukan per customer agar satu bundle tidak terpotong ke halaman lain.
        $bundlePage = (clone $query)
            ->setEagerLoads([])
            ->select('customer_id')
            ->selectRaw('MAX(id) as latest_invoice_id')
            ->groupBy('customer_id')
            ->orderByDesc('latest_invoice_id')
            ->paginate(20)
            ->withQueryString();

        $pageCustomerIds = $bundlePage->getCollection()->pluck('customer_id');
        $listedInvoices = collect();
        if ($pageCustomerIds->isNotEmpty()) {
            $listedInvoices = (clone $query)
                ->where(function ($customerQuery) use ($pageCustomerIds) {
                    $ids = $pageCustomerIds->filter(fn($id) => $id !== null)->values();
                    if ($ids->isNotEmpty()) {
                        $customerQuery->whereIn('customer_id', $ids);
                    }
                    if ($pageCustomerIds->contains(null)) {
                        $ids->isNotEmpty()
                            ? $customerQuery->orWhereNull('customer_id')
                            : $customerQuery->whereNull('customer_id');
                    }
                })
                ->orderByDesc('id')
                ->get();
        }

        $invoicesByCustomer = $listedInvoices->groupBy(
            fn(Invoice $invoice) => $invoice->customer_id === null ? 'legacy-null' : 'customer-' . $invoice->customer_id
        );
        $bundlePage->setCollection($bundlePage->getCollection()->map(function ($row) use ($invoicesByCustomer) {
            $key = $row->customer_id === null ? 'legacy-null' : 'customer-' . $row->customer_id;
            /** @var Collection<int, Invoice> $bundleInvoices */
            $bundleInvoices = $invoicesByCustomer->get($key, collect());

            return [
                'key' => $key,
                'customer' => $bundleInvoices->first()?->customer,
                'invoices' => $bundleInvoices,
                'invoice_count' => $bundleInvoices->count(),
                'total_hpp' => $bundleInvoices->sum(fn(Invoice $invoice) => (float) $invoice->total_hpp),
                'total_invoice' => $bundleInvoices->sum(fn(Invoice $invoice) => (float) ($invoice->grand_total ?: $invoice->total_jual)),
                'total_paid' => $bundleInvoices->sum(fn(Invoice $invoice) => $invoice->total_paid),
                'outstanding' => $bundleInvoices->sum(fn(Invoice $invoice) => $invoice->outstanding),
            ];
        }));
        $invoiceBundles = $bundlePage;
        $customers = Customer::orderBy('company_name')
            ->get(['id', 'company_name', 'customer_code', 'invoice_code']);
        $eligibleCustomerIds = $this->availableInvoiceDos()->pluck('customer_id')->unique();
        $invoiceCustomers = Customer::whereIn('id', $eligibleCustomerIds)
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'customer_code', 'invoice_code']);
        $pendingDeletionIds = \App\Models\DeletionRequest::pendingIdsFor(Invoice::class);

        return view('invoices.index', compact(
            'invoiceBundles',
            'listedInvoices',
            'tab',
            'status',
            'search',
            'customers',
            'invoiceCustomers',
            'customerId',
            'jenis',
            'periode',
            'pendingDeletionIds'
        ));
    }

    /**
     * DO final yang sudah ditutup dan masih memiliki komponen yang
     * belum masuk invoice.
     */
    public function availableDos(Request $request)
    {
        $customerId = $request->integer('customer_id');
        if (!$customerId) {
            return response()->json([]);
        }

        return response()->json($this->availableInvoiceDos($customerId));
    }

    /**
     * DO Closed yang masih memiliki minimal satu komponen belum ditagih.
     * Dipakai bersama oleh pilihan customer dan endpoint daftar DO.
     */
    private function availableInvoiceDos(?int $customerId = null): Collection
    {
        $query = DeliveryOrder::with([
            'requestOrder.jobDetails',
            'requestOrder.items',
            'invoiceItems.invoice',
        ])
            ->where('status', 'closed')
            ->whereHas('requestOrder', fn($q) => $q->where('do_approved', true))
            ->orderByDesc('do_date')
            ->orderByDesc('id');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        return $query->get()
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
                    'customer_id' => $do->customer_id,
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
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'tgl_buat' => 'required|date',
            'periode_invoice' => 'nullable|date_format:Y-m',
            'tgl_tempo' => 'nullable|date|after_or_equal:tgl_buat',
            'selections' => 'required|array|min:1',
            'selections.*' => ['required', 'string', 'regex:/^\d+:(TR|NTR)$/'],
            // Nilai combined lama tetap diterima agar form/cache lama tidak error,
            // tetapi penyimpanan di bawah selalu memisahkan TR dan NTR.
            'billing_mode' => 'nullable|in:combined,separate',
            'ppn_mode' => 'nullable|in:ppn,non_ppn',
            'ppn_types' => 'nullable|array',
            'ppn_types.*' => 'in:TR,NTR',
            'ppn_persen' => 'required_with:ppn_types|nullable|in:1.1,11',
            'notes' => 'nullable|string|max:2000',
        ]);

        $ppnTypes = collect($data['ppn_types'] ?? [])->unique()->values();
        // Kompatibilitas payload/form lama: mode PPN berarti kedua jenis terkena pajak.
        if ($ppnTypes->isEmpty() && ($data['ppn_mode'] ?? null) === 'ppn') {
            $ppnTypes = collect(['TR', 'NTR']);
        }
        if ($ppnTypes->isNotEmpty() && empty($data['ppn_persen'])) {
            throw ValidationException::withMessages([
                'ppn_persen' => 'Pilih tarif PPN 11% atau 1,1%.',
            ]);
        }

        $selectionMap = collect($data['selections'])
            ->unique()
            ->mapWithKeys(function (string $selection) {
                [$doId, $type] = explode(':', $selection, 2);
                return [$doId . ':' . $type => ['do_id' => (int) $doId, 'type' => $type]];
            });

        $created = DB::transaction(function () use ($data, $selectionMap, $ppnTypes) {
            // Lock customer menyerialkan nomor urut per customer.
            $customer = Customer::query()->lockForUpdate()->findOrFail($data['customer_id']);
            $doIds = $selectionMap->pluck('do_id')->unique()->sort()->values();

            // Lock DO menyerialkan pemakaian komponen TR/NTR di invoice.
            $dos = DeliveryOrder::with(['requestOrder.jobDetails', 'requestOrder.items'])
                ->whereKey($doIds)
                ->where('customer_id', $customer->id)
                ->where('status', 'closed')
                ->whereHas('requestOrder', fn($q) => $q->where('do_approved', true))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($dos->count() !== $doIds->count()) {
                throw ValidationException::withMessages([
                    'selections' => 'Ada DO yang belum ditutup, belum disetujui, atau bukan milik customer terpilih.',
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
            $defaultDueDate = Carbon::parse($customer->dueDateFrom($data['tgl_buat']))->toDateString();
            foreach ($groups as $groupRows) {
                /** @var Collection<int, array> $groupRows */
                $types = $groupRows->pluck('type')->unique()->values();
                $jenis = $types->count() > 1 ? 'MIX' : $types->first();
                $ppnPersen = $ppnTypes->contains($jenis) ? (float) $data['ppn_persen'] : 0.0;
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
                    'periode_invoice' => Carbon::parse(
                        ($data['periode_invoice'] ?? Carbon::parse($data['tgl_buat'])->format('Y-m')) . '-01'
                    )->startOfMonth()->toDateString(),
                    // Due date wajib ada; kalau kosong pakai TOP customer (atau
                    // default global). Invoice tanpa due date tidak akan pernah
                    // terhitung menua di laporan piutang.
                    'tgl_tempo' => ($data['tgl_tempo'] ?? null)
                        ?: $customer->dueDateFrom($data['tgl_buat']),
                    'tgl_tempo_manual' => !empty($data['tgl_tempo'])
                        && $data['tgl_tempo'] !== $defaultDueDate,
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
        $data = $request->validate([
            'periode_invoice' => 'nullable|date_format:Y-m',
        ]);

        DB::transaction(function () use ($invoice, $data) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['general' => 'Hanya draft yang bisa diterbitkan.']);
            }

            $period = !empty($data['periode_invoice'])
                ? Carbon::parse($data['periode_invoice'] . '-01')->startOfMonth()
                : ($locked->periode_invoice ?: $locked->tgl_buat ?: $locked->created_at)->copy()->startOfMonth();
            $submittedAt = now();
            $locked->update([
                'status' => 'invoice',
                'periode_invoice' => $period->toDateString(),
                'submitted_at' => $submittedAt,
                'tgl_tempo' => $locked->tgl_tempo_manual
                    ? $locked->tgl_tempo
                    : $locked->customer->dueDateFrom($submittedAt),
            ]);
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
            $locked->update(['status' => 'draft', 'submitted_at' => null]);

            // Tanpa sinkronisasi ini DO tetap tercatat "Invoice Terbit" padahal
            // tagihannya sudah kembali menjadi draft.
            $doIds = $locked->items()->pluck('delivery_order_id')->filter()->unique();
            app(InvoiceBillingService::class)->sync(
                $doIds,
                "Invoice {$locked->invoice_number} dikembalikan ke draft."
            );
        });

        return back()->with('success', 'Invoice dikembalikan ke draft.');
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'payment_type' => 'required|in:termin,pelunasan',
            'tgl_pencairan' => 'required|date',
            'amount' => 'required_if:payment_type,termin|nullable|numeric|gt:0',
            'note' => 'nullable|string|max:1000',
        ]);

        $result = DB::transaction(function () use ($invoice, $data) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if (!in_array($locked->status, ['invoice', 'termin'], true)) {
                throw ValidationException::withMessages([
                    'general' => 'Pembayaran hanya dapat dicatat pada invoice terbit atau invoice termin.',
                ]);
            }

            $locked->payments()->lockForUpdate()->get();
            $grandTotal = (float) ($locked->grand_total ?: $locked->total_jual);
            $alreadyPaid = (float) $locked->payments()->sum('amount');
            $outstanding = max(0, $grandTotal - $alreadyPaid);
            if ($outstanding <= 0) {
                throw ValidationException::withMessages(['general' => 'Invoice ini sudah tidak memiliki sisa tagihan.']);
            }

            $amount = $data['payment_type'] === 'pelunasan'
                ? $outstanding
                : round((float) $data['amount']);

            if ($data['payment_type'] === 'termin' && $amount >= $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal termin harus lebih kecil dari sisa tagihan. Pilih Pelunasan untuk membayar seluruh sisa.',
                ]);
            }

            InvoicePayment::create([
                'invoice_id' => $locked->id,
                'payment_date' => $data['tgl_pencairan'],
                'amount' => $amount,
                'payment_type' => $data['payment_type'],
                'note' => $data['note'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            $remaining = max(0, $outstanding - $amount);
            $isPaid = $remaining <= 0;
            $locked->update([
                'status' => $isPaid ? 'paid' : 'termin',
                'tgl_pencairan' => $isPaid ? $data['tgl_pencairan'] : null,
            ]);
            $doIds = $locked->items()->pluck('delivery_order_id')->filter()->unique();
            $legacyRequestOrderIds = $locked->items()
                ->whereNull('delivery_order_id')
                ->pluck('request_order_id')
                ->filter()
                ->unique();
            if ($isPaid) {
                RequestOrder::whereIn('id', $legacyRequestOrderIds)->update(['invoice_status' => 'paid']);
            }

            // Kenaikan DO ke tahap "Lunas" ditangani terpusat di billing service
            // agar konsisten dengan jalur unsubmit dan hapus invoice.
            app(InvoiceBillingService::class)->sync(
                $doIds,
                $isPaid ? "Lunas mengikuti invoice {$locked->invoice_number}." : null
            );

            return ['is_paid' => $isPaid, 'remaining' => $remaining];
        });

        return back()->with('success', $result['is_paid']
            ? 'Pelunasan dicatat. Invoice dan DO terkait otomatis menjadi lunas setelah seluruh komponennya lunas.'
            : 'Pembayaran titip / termin dicatat. Sisa tagihan ' . idr($result['remaining']) . '.');
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
        $user = auth()->user();
        $canSetDraftTax = $invoice->status === 'draft' && ($user->isFinance() || $user->isAdmin());
        if (!$canSetDraftTax) {
            $this->authorizeInvoiceEdit($invoice);
        }

        $data = $request->validate([
            'ppn_types' => 'nullable|array',
            'ppn_types.*' => 'in:TR,NTR',
            'ppn_persen' => 'required_with:ppn_types|nullable|in:1.1,11',
        ], [
            'ppn_persen.required_with' => 'Pilih tarif PPN 11% atau 1,1%.',
            'ppn_persen.in' => 'Tarif PPN hanya dapat dipilih 11% atau 1,1%.',
        ]);
        $selectedTypes = collect($data['ppn_types'] ?? [])->unique();
        $ppnPersen = $selectedTypes->isNotEmpty() ? (float) $data['ppn_persen'] : 0.0;
        $targets = $canSetDraftTax ? $this->relatedDraftTaxInvoices($invoice) : collect([$invoice]);

        DB::transaction(function () use ($targets, $selectedTypes, $ppnPersen) {
            foreach ($targets as $target) {
                $targetPpn = $selectedTypes->contains($target->jenis) ? $ppnPersen : 0.0;
                $this->recalcTotals(
                    $target,
                    (float) $target->total_hpp,
                    (float) $target->total_jual,
                    $targetPpn
                );
            }
        });

        $taxedLabels = $selectedTypes
            ->map(fn(string $type) => $type === 'TR' ? 'Trucking (TR)' : 'Non-Trucking (Non-TR)')
            ->implode(' dan ');
        return back()->with('success', $selectedTypes->isNotEmpty()
            ? 'PPN ' . rtrim(rtrim(number_format($ppnPersen, 2, '.', ''), '0'), '.') . '% diterapkan untuk ' . $taxedLabels . '.'
            : 'Draft Trucking dan Non-Trucking diubah menjadi Non-PPN.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load([
            'customer',
            'operator',
            'editRequester',
            'editReviewer',
            'payments.recorder',
            'items.requestOrder.jobDetails',
            'items.deliveryOrder.requestOrder',
        ]);

        $taxInvoices = $invoice->status === 'draft'
            ? $this->relatedDraftTaxInvoices($invoice)->keyBy('jenis')
            : collect([$invoice->jenis => $invoice]);

        return view('invoices.show', compact('invoice', 'taxInvoices'));
    }

    public function requestEdit(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->isFinance()) abort(403);
        if (in_array($invoice->status, ['termin', 'paid'], true)) {
            return back()->withErrors(['general' => 'Invoice yang sudah memiliki pembayaran tidak dapat diminta untuk diedit.']);
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
            if (in_array($locked->status, ['termin', 'paid'], true)) {
                throw ValidationException::withMessages([
                    'general' => 'Invoice yang sudah memiliki pembayaran tidak dapat dihapus.',
                ]);
            }

            $doIds = $locked->items()->pluck('delivery_order_id')->filter()->unique();
            $legacyRequestOrderIds = $locked->items()
                ->whereNull('delivery_order_id')
                ->pluck('request_order_id')
                ->filter()
                ->unique();
            $invoiceNumber = $locked->invoice_number;
            $locked->items()->delete();
            $locked->delete();
            RequestOrder::whereIn('id', $legacyRequestOrderIds)->update(['invoice_status' => 'uninvoiced']);
            app(InvoiceBillingService::class)->sync(
                $doIds,
                "Invoice {$invoiceNumber} dihapus. DO dibuka kembali untuk ditagih."
            );
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
        $periode = $request->get('periode');

        $query = Invoice::with(['customer', 'items.deliveryOrder', 'items.requestOrder', 'payments']);
        if ($status === 'settled') {
            $query->whereIn('status', ['termin', 'paid']);
        } elseif ($status && $status !== 'all') {
            $query->where('status', $status);
        }
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        if (array_key_exists((string) $jenis, Invoice::TYPES)) {
            $query->where('jenis', $jenis);
        }
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $periode)) {
            $query->whereDate('periode_invoice', $periode . '-01');
        }

        $headers = [
            'Invoice ID', 'No Invoice', 'Customer', 'Status', 'Periode Invoice', 'Tgl Buat', 'Tgl Submit', 'No DO',
            'Nama', 'Uraian', 'Jenis Truck', 'Qty', 'Harga', 'Jumlah', 'PPN Invoice',
            'Grand Total Invoice', 'Total Terbayar', 'Sisa Tagihan',
        ];
        $rows = $query->orderByDesc('tgl_buat')->get()->flatMap(function (Invoice $inv) {
            if ($inv->items->isEmpty()) {
                return [[
                    $inv->invoice_id,
                    $inv->invoice_number,
                    $inv->customer?->company_name ?? '-',
                    $inv->status_label,
                    $inv->periode_invoice?->format('Y-m'),
                    $inv->tgl_buat?->format('Y-m-d'),
                    $inv->submitted_at?->format('Y-m-d H:i:s'),
                    '-',
                    $inv->jenis_label ?: 'Invoice',
                    $inv->notes ?: 'Ringkasan invoice (rincian DO tidak tersedia)',
                    '-', 1, (float) $inv->total_jual, (float) $inv->total_jual,
                    (float) $inv->ppn_nominal,
                    (float) ($inv->grand_total ?: $inv->total_jual),
                    (float) $inv->total_paid,
                    (float) $inv->outstanding,
                ]];
            }

            return $inv->items->map(fn(InvoiceItem $item) => [
                $inv->invoice_id,
                $inv->invoice_number,
                $inv->customer?->company_name ?? '-',
                $inv->status_label,
                $inv->periode_invoice?->format('Y-m'),
                $inv->tgl_buat?->format('Y-m-d'),
                $inv->submitted_at?->format('Y-m-d H:i:s'),
                $item->deliveryOrder?->do_number ?? $item->requestOrder?->do_number ?? '-',
                $item->item_name,
                $item->description,
                $item->truck_type,
                (float) $item->quantity,
                (float) $item->unit_price,
                (float) $item->jual,
                (float) $inv->ppn_nominal,
                (float) $inv->grand_total,
                (float) $inv->total_paid,
                (float) $inv->outstanding,
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
        $query = Invoice::with(['customer', 'items.deliveryOrder', 'items.requestOrder', 'payments']);
        if ($request->status === 'settled') $query->whereIn('status', ['termin', 'paid']);
        elseif ($request->filled('status') && $request->status !== 'all') $query->where('status', $request->status);
        if ($request->filled('customer_id')) $query->where('customer_id', $request->integer('customer_id'));
        if (array_key_exists((string) $request->jenis, Invoice::TYPES)) $query->where('jenis', $request->jenis);
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->periode)) {
            $query->whereDate('periode_invoice', $request->periode . '-01');
        }
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
        if (in_array($invoice->status, ['termin', 'paid'], true)) {
            abort(403, 'Invoice yang sudah memiliki pembayaran tidak dapat diedit.');
        }
        $user = auth()->user();
        abort_unless($user->isSuperAdmin() || ($user->isFinance() && $invoice->edit_request_status === 'approved'), 403,
            'Finance harus mengajukan permintaan edit dan menunggu persetujuan Super Admin.');
    }

    /** Draft TR/NTR pasangan dengan kumpulan DO/Request yang sama. */
    private function relatedDraftTaxInvoices(Invoice $invoice): Collection
    {
        $invoice->loadMissing('items');
        $billingKeys = $this->invoiceBillingKeys($invoice);
        if ($billingKeys->isEmpty()) {
            return collect([$invoice]);
        }

        return Invoice::with('items')
            ->where('customer_id', $invoice->customer_id)
            ->where('status', 'draft')
            ->whereIn('jenis', ['TR', 'NTR'])
            ->get()
            ->filter(fn(Invoice $candidate) => $this->invoiceBillingKeys($candidate)->all() === $billingKeys->all())
            ->whenEmpty(fn(Collection $items) => $items->push($invoice))
            ->values();
    }

    private function invoiceBillingKeys(Invoice $invoice): Collection
    {
        return $invoice->items
            ->map(fn(InvoiceItem $item) => $item->delivery_order_id
                ? 'do:' . $item->delivery_order_id
                : ($item->request_order_id ? 'request:' . $item->request_order_id : null))
            ->filter()
            ->unique()
            ->sort()
            ->values();
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
