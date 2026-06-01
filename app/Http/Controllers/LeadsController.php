<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Models\Activity;
use App\Models\VendorService;
use App\Models\LeadProduct;
use App\Models\LeadPic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Customer;

use App\Models\Notification;

class LeadsController extends Controller
{
    public function index(Request $request)
    {
        $stage  = $request->get('stage');
        $search = $request->get('search');

        $query = Lead::with(['salesUser', 'activities']);

        if (auth()->user()->isSalesExecutive()) {
            $query->where('user_id', auth()->id());
        }

        if ($stage)  $query->where('pipeline_stage', $stage);
        if ($search) $query->where('company_name', 'like', "%$search%");

        $leads      = $query->orderBy('updated_at', 'desc')->paginate(15);
        $salesUsers = User::orderBy('name')->get();
        $vendorServices = VendorService::with('vendor')->orderBy('service_name')->get();

        return view('leads.index', compact('leads', 'salesUsers', 'vendorServices', 'stage', 'search'));
    }

    public function show(Lead $lead)
    {
        $lead->load(['salesUser', 'activities.salesUser', 'products', 'pics']);
        $salesUsers = User::orderBy('name')->get();
        $vendorServices = VendorService::with('vendor')->orderBy('service_name')->get();
        return view('leads.show', compact('lead', 'salesUsers', 'vendorServices'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name'    => 'required|string|max:255',
            'pic_name'        => 'required|string|max:255',
            'pic_position'    => 'nullable|string|max:100',
            'phone'           => 'nullable|string|max:20',
            'email'           => 'nullable|email|max:255',
            'address'         => 'nullable|string',
            'industry'        => 'nullable|string|max:100',
            'location'        => 'nullable|string|max:255',
            'pipeline_stage'  => 'nullable|in:Identifying,Approaching,Follow Up,Won,Lost,Maintaining',
            'temperature'     => 'nullable|in:Hot,Warm,Cold',
            'volume_estimate' => 'nullable|string|max:100',
            'probability'     => 'nullable|integer|min:0|max:100',
            'lead_source'     => 'nullable|string|max:100',
            'competitor'      => 'nullable|string|max:255',
            'expected_closing' => 'nullable|date',
            'user_id'         => 'required|exists:users,id',
            'notes_kebutuhan' => 'nullable|string',
            // inline pics
            'pics'                => 'nullable|array',
            'pics.*.pic_name'     => 'required_with:pics|string|max:255',
            'pics.*.pic_position' => 'nullable|string|max:100',
            'pics.*.phone'        => 'nullable|string|max:20',
            'pics.*.email'        => 'nullable|email|max:255',
            // inline products
            'products'                => 'nullable|array',
            'products.*.service_name' => 'required_with:products|string|max:255',
            'products.*.qty'          => 'nullable|numeric|min:0',
            'products.*.unit'         => 'nullable|string|max:50',
            'products.*.tonnage'      => 'nullable|numeric|min:0',
            'products.*.shipping_zone' => 'nullable|string|max:255',
        ]);

        $validated['lead_code']      = Lead::generateLeadCode();
        $validated['pipeline_stage'] = $validated['pipeline_stage'] ?? 'Identifying';

        if (auth()->user()->isSalesExecutive()) {
            $validated['user_id'] = auth()->id();
        }

        $picsData     = $validated['pics'] ?? [];
        $productsData = $validated['products'] ?? [];
        unset($validated['pics'], $validated['products']);

        $lead = Lead::create($validated);

        // Simpan inline PICs
        foreach ($picsData as $i => $pic) {
            $lead->pics()->create([
                'pic_name'     => $pic['pic_name'],
                'pic_position' => $pic['pic_position'] ?? null,
                'phone'        => $pic['phone'] ?? null,
                'email'        => $pic['email'] ?? null,
                'is_primary'   => $i === 0,
            ]);
        }

        // Simpan inline Products
        foreach ($productsData as $prod) {
            $lead->products()->create([
                'service_name' => $prod['service_name'],
                'product_name' => $prod['service_name'],
                'qty'          => $prod['qty'] ?? 0,
                'unit'         => trim($prod['unit'] ?? ''),
                'tonnage'      => $prod['tonnage'] ?? null,
                'shipping_zone' => $prod['shipping_zone'] ?? null,
            ]);
        }

        // Auto-sync ke database customer
        self::syncToCustomer($lead);

        // Notifikasi: Lead Baru
        Notification::broadcast(
            'new_lead',
            'Lead Baru: ' . $lead->company_name,
            $lead->company_name . ' ditambahkan oleh ' . auth()->user()->name,
            route('leads.show', $lead)
        );

        return redirect()->route('leads.show', $lead)->with('success', 'Lead berhasil ditambahkan.');
    }

    public function update(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'company_name'    => 'sometimes|string|max:255',
            'pic_name'        => 'sometimes|string|max:255',
            'pic_position'    => 'nullable|string|max:100',
            'phone'           => 'nullable|string|max:20',
            'email'           => 'nullable|email|max:255',
            'address'         => 'nullable|string',
            'industry'        => 'nullable|string|max:100',
            'location'        => 'nullable|string|max:255',
            'pipeline_stage'  => 'sometimes|in:Identifying,Approaching,Follow Up,Won,Lost,Maintaining',
            'temperature'     => 'nullable|in:Hot,Warm,Cold',
            'volume_estimate' => 'nullable|string|max:100',
            'probability'     => 'nullable|integer|min:0|max:100',
            'lead_source'     => 'nullable|string|max:100',
            'competitor'      => 'nullable|string|max:255',
            'expected_closing' => 'nullable|date',
            'user_id'         => 'sometimes|exists:users,id',
            'notes_kebutuhan' => 'nullable|string',
            'catatan_internal' => 'nullable|string',
            'next_follow_up'  => 'nullable|date',
            'next_follow_up_notes' => 'nullable|string',
        ]);

        $lead->update($validated);

        // Auto-sync ke database customer setiap kali stage berubah
        if (isset($validated['pipeline_stage'])) {
            self::syncToCustomer($lead);
        }

        // Notifikasi: Deal Won
        if (isset($validated['pipeline_stage']) && $validated['pipeline_stage'] === 'Won') {
            Notification::sendAll(
                'deal_won',
                'Deal Won: ' . $lead->company_name,
                $lead->company_name . ' berhasil di-close oleh ' . auth()->user()->name,
                route('leads.show', $lead)
            );
        }
        // Notifikasi: Stage Change (bukan Won/Lost)
        elseif (isset($validated['pipeline_stage']) && !in_array($validated['pipeline_stage'], ['Won', 'Lost'])) {
            Notification::broadcast(
                'stage_change',
                'Stage Berubah: ' . $lead->company_name,
                $lead->company_name . ' pindah ke stage ' . $validated['pipeline_stage'],
                route('leads.show', $lead)
            );
        }

        if ($request->expectsJson() || $request->isJson()) {
            return response()->json(['success' => true, 'stage' => $lead->pipeline_stage]);
        }

        return redirect()->back()->with('success', 'Lead berhasil diupdate.');
    }

    /**
     * Sync lead ke database customer otomatis berdasarkan pipeline stage.
     *
     * Revisi #2 — aturan status customer:
     * - Customer naik ke "Existing" HANYA jika lead pernah/menjadi stage "Won" (= Closing).
     * - Stage lain (Identifying/Approaching/Follow Up/Lost/Maintaining) TIDAK menaikkan
     *   status. Saat membuat customer baru dari lead, defaultnya "Potential".
     * - Status "Existing" yang sudah ada TIDAK PERNAH diturunkan kembali ke "Potential"
     *   (mis. customer yang dibuat dari menu Customer sudah Existing sejak awal, atau
     *   lead yang sudah pernah Won lalu pindah ke Maintaining).
     */
    public static function syncToCustomer(Lead $lead): void
    {
        $lead->loadMissing(['products', 'pics']);

        $stage = $lead->pipeline_stage;

        // Hanya stage Won yang memenuhi syarat menaikkan customer ke Existing.
        $wonNow = ($stage === 'Won');

        $customerData = [
            'company_name' => $lead->company_name,
            'pic_name'     => $lead->pic_name,
            'pic_position' => $lead->pic_position,
            'phone'        => $lead->phone ?? '',
            'email'        => $lead->email,
            'address'      => $lead->address,
            'industry'     => $lead->industry,
            'location'     => $lead->location ?? null,
            'user_id'      => $lead->user_id,
        ];

        $customer = null;

        if ($lead->customer_id) {
            $customer = Customer::find($lead->customer_id);
        }

        if (!$customer) {
            $customer = Customer::where('company_name', $lead->company_name)->first();
        }

        if (!$customer) {
            // Customer baru hasil sync lead: Existing hanya jika Won, selain itu Potential.
            $customerData['status'] = $wonNow ? 'Existing' : 'Potential';
            if ($wonNow) {
                $customerData['customer_since'] = now()->toDateString();
            }
            $customer = Customer::create($customerData);
        } else {
            // Customer sudah ada: JANGAN turunkan status Existing yang sudah ada.
            // Naikkan ke Existing hanya bila stage Won dan saat ini belum Existing.
            if ($wonNow && $customer->status !== 'Existing') {
                $customerData['status'] = 'Existing';
                $customerData['customer_since'] = $customer->customer_since
                    ? $customer->customer_since->toDateString()
                    : now()->toDateString();
            }
            $customer->update($customerData);
        }

        if ((int) $lead->customer_id !== (int) $customer->id) {
            $lead->updateQuietly(['customer_id' => $customer->id]);
        }

        // Sync kebutuhan layanan lead ke customer_products.
        // Tidak menghapus layanan manual customer; hanya menambah yang belum ada.
        foreach ($lead->products as $leadProduct) {
            $name = trim($leadProduct->service_name ?? $leadProduct->product_name ?? '');
            if ($name === '') {
                continue;
            }
            $unit = trim($leadProduct->unit ?? '');

            $exists = $customer->productItems()
                ->where(function ($q) use ($name) {
                    $q->whereRaw('LOWER(product_name) = ?', [mb_strtolower($name)]);
                })
                ->exists();

            if (!$exists) {
                $customer->productItems()->create([
                    'service_name' => $name,
                    'product_name' => $name,
                    'unit'         => $unit,
                ]);
            }
        }

        // Fallback: product_interest lama jika lead tidak punya layanan terstruktur.
        if ($lead->products->isEmpty() && trim((string) $lead->product_interest) !== '') {
            $piName = trim((string) $lead->product_interest);
            $exists = $customer->productItems()
                ->where(function ($q) use ($piName) {
                    $q->whereRaw('LOWER(product_name) = ?', [mb_strtolower($piName)]);
                })
                ->exists();
            if (!$exists) {
                $customer->productItems()->create([
                    'service_name' => $piName,
                    'product_name' => $piName,
                    'unit'         => '',
                ]);
            }
        }

        // Sync PIC tambahan dari lead. Tidak menghapus PIC customer yang sudah ada.
        foreach ($lead->pics as $leadPic) {
            $picName = trim($leadPic->pic_name ?? '');
            if ($picName === '') {
                continue;
            }

            $exists = $customer->pics()
                ->where('pic_name', $picName)
                ->when($leadPic->phone, fn ($q) => $q->where('phone', $leadPic->phone))
                ->exists();

            if (!$exists) {
                $customer->pics()->create([
                    'pic_name'     => $picName,
                    'pic_position' => $leadPic->pic_position,
                    'phone'        => $leadPic->phone,
                    'email'        => $leadPic->email,
                    'is_primary'   => false,
                ]);
            }
        }
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();
        return redirect()->route('leads.index')->with('success', 'Lead dihapus.');
    }

    // ── Lead Products ──
    public function storeProduct(Request $request, Lead $lead)
    {
        $request->validate([
            'service_name' => 'required|string|max:255',
            'qty'          => 'nullable|numeric|min:0',
            'unit'         => 'nullable|string|max:100',
            'tonnage'      => 'nullable|numeric|min:0',
            'shipping_zone' => 'nullable|string|max:255',
        ]);
        $lead->products()->create([
            'service_name' => $request->service_name,
            'product_name' => $request->service_name,
            'qty'          => $request->qty ?? 0,
            'unit'         => trim($request->unit ?? ''),
            'tonnage'      => $request->tonnage,
            'shipping_zone' => $request->shipping_zone,
        ]);
        return redirect()->back()->with('success', 'Layanan ditambahkan.');
    }

    public function destroyProduct(Lead $lead, LeadProduct $product)
    {
        abort_if((int) $product->lead_id !== (int) $lead->id, 404);
        $product->delete();
        return redirect()->back()->with('success', 'Layanan dihapus.');
    }

    // ── Lead PICs ──
    public function storePic(Request $request, Lead $lead)
    {
        $request->validate([
            'pic_name'     => 'required|string|max:255',
            'pic_position' => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:255',
        ]);
        $lead->pics()->create([
            'pic_name'     => $request->pic_name,
            'pic_position' => $request->pic_position,
            'phone'        => $request->phone,
            'email'        => $request->email,
            'is_primary'   => $lead->pics()->count() === 0,
        ]);
        return redirect()->back()->with('success', 'PIC ditambahkan.');
    }

    public function destroyPic(Lead $lead, LeadPic $pic)
    {
        abort_if((int) $pic->lead_id !== (int) $lead->id, 404);
        $pic->delete();
        return redirect()->back()->with('success', 'PIC dihapus.');
    }

    // ── Add Activity ke Lead ──
    public function storeActivity(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'type'           => 'required|in:Call,Visit,Email,Note,Others',
            'subject'        => 'required|string|max:255',
            'description'    => 'nullable|string',
            'activity_at'    => 'required|date',
            'status'         => 'required|in:Planned,Pending,Done,Overdue',
            'user_id'        => 'nullable|exists:users,id',
            'next_follow_up' => 'nullable|date',
            'pipeline_stage' => 'nullable|in:Identifying,Approaching,Follow Up,Won,Maintaining',
        ]);

        $validated['lead_id'] = $lead->id;
        if ($lead->customer_id) {
            $validated['customer_id'] = $lead->customer_id;
        }
        $validated['user_id'] = auth()->user()->isSalesExecutive() ? auth()->id() : ($validated['user_id'] ?? $lead->user_id ?? auth()->id());
        $validated['sales_user_id'] = $validated['user_id'];

        if (!empty($validated['pipeline_stage'])) {
            $lead->update(['pipeline_stage' => $validated['pipeline_stage']]);
            self::syncToCustomer($lead->fresh());
        }

        Activity::create($validated);

        return redirect()->route('sales.activity')->with('success', 'Activity berhasil ditambahkan.');
    }

    // ── Export CSV ──
    public function export(Request $request)
    {
        $leads = Lead::with(['salesUser'])->orderBy('created_at', 'desc')->get();

        $headers = [
            'Lead Code', 'Company Name', 'PIC Name', 'Phone', 'Email',
            'Pipeline Stage', 'Temperature', 'Product Interest', 'Volume Estimate',
            'Potensi Revenue', 'Probability %', 'Expected Closing',
            'Sales PIC', 'Lead Source', 'Created At',
        ];

        $rows = $leads->map(fn($lead) => [
            $lead->lead_code,
            $lead->company_name,
            $lead->pic_name,
            $lead->phone,
            $lead->email,
            $lead->pipeline_stage,
            $lead->temperature,
            $lead->product_interest,
            $lead->volume_estimate,
            $lead->potensi_revenue,
            $lead->probability,
            $lead->expected_closing?->format('Y-m-d'),
            $lead->salesUser?->name,
            $lead->lead_source,
            $lead->created_at->format('Y-m-d H:i'),
        ])->toArray();

        return \App\Helpers\ExcelExport::download('leads_' . date('Ymd_His'), $headers, $rows, 'Leads');
    }

    public function template()
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_import_leads.csv"',
        ];
        $callback = function () {
            $f = fopen('php://output', 'w');
            fputs($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Lead Code', 'Company Name', 'PIC Name', 'Phone', 'Email', 'Pipeline Stage', 'Temperature', 'Product Interest', 'Volume Estimate', 'Potensi Revenue', 'Probability', 'Expected Closing', 'Sales PIC', 'Lead Source']);
            fputcsv($f, ['LEAD-2026-0001', 'PT. Contoh Kimia', 'Budi Santoso', '0812-1234-5678', 'budi@contoh.co.id', 'Identifying', 'Warm', 'Solvent IPA', '5 Ton/Bulan', '50000000', '30', '2026-12-31', 'sales@crm.com', 'Referral']);
            fclose($f);
        };
        return response()->stream($callback, 200, $headers);
    }

    // ── Import CSV ──
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $file    = $request->file('file');
        $handle  = fopen($file->getRealPath(), 'r');
        $header  = fgetcsv($handle); // skip header
        $imported = 0;
        $errors  = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 5) continue;

            try {
                // Cari sales user by name
                $salesUser = User::where('name', trim($row[12] ?? ''))->first();

                Lead::create([
                    'lead_code'      => Lead::generateLeadCode(),
                    'company_name'   => trim($row[1] ?? ''),
                    'pic_name'       => trim($row[2] ?? ''),
                    'phone'          => trim($row[3] ?? ''),
                    'email'          => trim($row[4] ?? ''),
                    'pipeline_stage' => (function ($stage) {
                        $stage = trim((string) $stage);
                        if ($stage === 'Closing') return 'Won';
                        return in_array($stage, ['Identifying', 'Approaching', 'Follow Up', 'Won', 'Lost', 'Maintaining']) ? $stage : 'Identifying';
                    })($row[5] ?? ''),
                    'temperature'    => in_array(trim($row[6] ?? ''), ['Hot', 'Warm', 'Cold']) ? trim($row[6]) : 'Cold',
                    'product_interest' => trim($row[7] ?? ''),
                    'route'          => trim($row[8] ?? ''),
                    'potensi_revenue' => is_numeric($row[9] ?? '') ? $row[9] : 0,
                    'probability'    => is_numeric($row[10] ?? '') ? $row[10] : 0,
                    'expected_closing' => !empty($row[11]) ? $row[11] : null,
                    'user_id'  => $salesUser?->id,
                    'lead_source'    => trim($row[13] ?? ''),
                ]);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = 'Baris ' . ($imported + count($errors) + 2) . ': ' . $e->getMessage();
            }
        }

        fclose($handle);

        $msg = "Berhasil import {$imported} leads.";
        if ($errors) $msg .= ' ' . count($errors) . ' baris gagal.';

        return redirect()->route('leads.index')->with('success', $msg);
    }
}
