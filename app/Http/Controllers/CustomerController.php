<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPic;
use App\Models\User;
use App\Models\Activity;
use App\Models\VendorService;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $status   = $request->get('status');
        $industry = $request->get('industry');
        $search   = $request->get('search');
        $salesId  = $request->get('user_id');

        $query = Customer::with(['salesUser', 'deliveryOrders', 'activities', 'pics', 'productItems']);
        if ($status && $status !== 'all')     $query->where('status', $status);
        if ($industry && $industry !== 'all') $query->where('industry', $industry);

        // Sales Executive hanya lihat customer miliknya
        if (auth()->user()->isSalesExecutive()) {
            $query->where('user_id', auth()->id());
        } elseif ($salesId) {
            $query->where('user_id', $salesId);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%$search%")
                  ->orWhere('pic_name', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
            });
        }

        $customers         = $query->orderBy('company_name')->paginate(10)->withQueryString();
        $totalCustomer     = Customer::count();
        $potentialCustomer = Customer::where('status', 'Potential')->count();
        $existingCustomer  = Customer::where('status', 'Existing')->count();
        $industries        = Customer::whereNotNull('industry')->distinct()->pluck('industry')->filter()->sort()->values();
        $salesUsers        = User::orderBy('name')->get();

        $selectedCustomer = $request->get('selected_id')
            ? Customer::with(['salesUser','deliveryOrders','activities.salesUser','leads','pics','productItems'])->find($request->get('selected_id'))
            : null;

        $vendorServices = VendorService::with('vendor')->orderBy('service_name')->get();

        return view('customers.index', compact(
            'customers','totalCustomer','potentialCustomer','existingCustomer',
            'industries','salesUsers','selectedCustomer','vendorServices','status','industry','search','salesId'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name'   => 'required|string|max:255',
            'pic_name'       => 'required|string|max:255',
            'pic_position'   => 'nullable|string|max:100',
            'phone'          => 'required|string|max:20',
            'email'          => 'nullable|email|max:255',
            'industry'       => 'nullable|string|max:100',
            'location'       => 'nullable|string|max:255',
            'address'        => 'nullable|string',
            'user_id'        => 'required|exists:users,id',
            'customer_since' => 'nullable|date',
            'notes'          => 'nullable|string',
            'pics'                => 'nullable|array',
            'pics.*.pic_name'     => 'required_with:pics|string|max:255',
            'pics.*.pic_position' => 'nullable|string|max:100',
            'pics.*.phone'        => 'nullable|string|max:20',
            'pics.*.email'        => 'nullable|email|max:255',
            // Kebutuhan layanan — pilihan mengikuti layanan vendor
            'products_list'                => 'nullable|array',
            'products_list.*.service_name' => 'required_with:products_list|string|max:255',
            'products_list.*.unit'         => 'nullable|string|max:100',
        ]);

        // Revisi #1: customer dari menu Customer SELALU Existing
        $validated['status'] = 'Existing';

        if (auth()->user()->isSalesExecutive()) {
            $validated['user_id'] = auth()->id();
        }

        $picsData     = $validated['pics'] ?? [];
        $productsList = $validated['products_list'] ?? [];
        unset($validated['pics'], $validated['products_list']);

        $customer = DB::transaction(function () use ($validated, $picsData, $productsList) {

            // Default customer_since jika kosong (karena langsung Existing)
            if (empty($validated['customer_since'])) {
                $validated['customer_since'] = now()->toDateString();
            }

            $customer = Customer::create($validated);

            // PIC utama + PIC tambahan
            foreach ($picsData as $i => $pic) {
                $customer->pics()->create([
                    'pic_name'     => $pic['pic_name'],
                    'pic_position' => $pic['pic_position'] ?? null,
                    'phone'        => $pic['phone'] ?? null,
                    'email'        => $pic['email'] ?? null,
                    'is_primary'   => $i === 0,
                ]);
            }

            // Kebutuhan layanan -> tabel relasi customer_products
            foreach ($productsList as $prod) {
                $name = trim($prod['service_name'] ?? $prod['product_name'] ?? '');
                if ($name === '') continue;
                $customer->productItems()->create([
                    'service_name' => $name,
                    'product_name' => $name,
                    'unit'         => trim($prod['unit'] ?? ''),
                ]);
            }

            // Revisi #1: create customer existing sekaligus create lead stage Maintaining
            $lead = Lead::create([
                'lead_code'      => Lead::generateLeadCode(),
                'customer_id'    => $customer->id,
                'company_name'   => $customer->company_name,
                'pic_name'       => $customer->pic_name,
                'pic_position'   => $customer->pic_position,
                'phone'          => $customer->phone,
                'email'          => $customer->email,
                'address'        => $customer->address,
                'industry'       => $customer->industry,
                'location'       => $customer->location,
                'pipeline_stage' => 'Maintaining',
                'temperature'    => 'Warm',
                'user_id'        => $customer->user_id,
            ]);

            // Salin layanan customer ke lead products agar konsisten
            foreach ($customer->productItems as $cp) {
                $lead->products()->create([
                    'service_name' => $cp->service_name ?? $cp->product_name,
                    'product_name' => $cp->service_name ?? $cp->product_name,
                    'unit'         => $cp->unit ?? '',
                ]);
            }

            return $customer;
        });

        return redirect()->route('customers.index')->with('success', 'Customer berhasil ditambahkan & lead (Maintaining) dibuat otomatis.');
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'company_name'   => 'sometimes|string|max:255',
            'pic_name'       => 'sometimes|string|max:255',
            'pic_position'   => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:255',
            'industry'       => 'nullable|string|max:100',
            'location'       => 'nullable|string|max:255',
            'address'        => 'nullable|string',
            'user_id'        => 'sometimes|exists:users,id',
            'customer_since' => 'nullable|date',
            'notes'          => 'nullable|string',

            'pics'                => 'nullable|array',
            'pics.*.pic_name'     => 'nullable|string|max:255',
            'pics.*.pic_position' => 'nullable|string|max:100',
            'pics.*.phone'        => 'nullable|string|max:20',
            'pics.*.email'        => 'nullable|email|max:255',

            // Kebutuhan layanan — pilihan mengikuti layanan vendor
            'products_list'                => 'nullable|array',
            'products_list.*.service_name' => 'nullable|string|max:255',
            'products_list.*.unit'         => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($validated, $customer, $request) {

            if (auth()->user()->isSalesExecutive()) {
                $validated['user_id'] = auth()->id();
            }

            // Revisi #2: status customer TIDAK bisa diubah manual dari sini.
            // Status hanya naik ke Existing via sales activity (stage Won), atau
            // sudah Existing sejak dibuat dari menu Customer. Maka unset status.
            unset($validated['status']);

            $picsData     = $validated['pics'] ?? [];
            $productsList = $validated['products_list'] ?? [];
            unset($validated['pics'], $validated['products_list']);

            $customer->update($validated);

            /**
             * products_submitted: daftar layanan dari modal edit dianggap final.
             * Replace seluruh customer_products dengan data dari form.
             */
            if ($request->has('products_submitted')) {
                $customer->productItems()->delete();
                foreach ($productsList as $product) {
                    $name = trim($product['service_name'] ?? $product['product_name'] ?? '');
                    if ($name === '') continue;
                    $customer->productItems()->create([
                        'product_name' => $name,
                            'unit'         => trim($product['unit'] ?? ''),
                    ]);
                }
            }

            /**
             * pics_submitted: PIC tambahan dari modal edit dianggap final.
             */
            if ($request->has('pics_submitted')) {
                $customer->pics()->delete();

                foreach ($picsData as $pic) {
                    $picName = trim($pic['pic_name'] ?? '');
                    if ($picName === '') continue;

                    $customer->pics()->create([
                        'pic_name'     => $picName,
                        'pic_position' => $pic['pic_position'] ?? null,
                        'phone'        => $pic['phone'] ?? null,
                        'email'        => $pic['email'] ?? null,
                        'is_primary'   => false,
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Data customer berhasil diupdate.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return redirect()->route('customers.index')->with('success', 'Customer dihapus.');
    }

    // ── Customer PICs ──
    public function storePic(Request $request, Customer $customer)
    {
        $request->validate([
            'pic_name'     => 'required|string|max:255',
            'pic_position' => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:255',
        ]);
        $customer->pics()->create([
            'pic_name'     => $request->pic_name,
            'pic_position' => $request->pic_position,
            'phone'        => $request->phone,
            'email'        => $request->email,
            'is_primary'   => $customer->pics()->count() === 0,
        ]);
        return redirect()->back()->with('success', 'PIC ditambahkan.');
    }

    public function destroyPic(Customer $customer, CustomerPic $pic)
    {
        abort_if((int) $pic->customer_id !== (int) $customer->id, 404);
        $pic->delete();
        return redirect()->back()->with('success', 'PIC dihapus.');
    }

    // ── Transfer Sales (Admin only) ──
    public function transferSales(Request $request, Customer $customer)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $request->validate(['user_id' => 'required|exists:users,id']);
        $customer->update(['user_id' => $request->user_id]);
        return redirect()->back()->with('success', 'Sales PIC berhasil dipindah.');
    }

    public function export(Request $request)
    {
        $status   = $request->get('status');
        $industry = $request->get('industry');
        $search   = $request->get('search');
        $salesId  = $request->get('user_id');

        $query = Customer::with(['salesUser']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($industry && $industry !== 'all') {
            $query->where('industry', $industry);
        }

        if (auth()->user()->isSalesExecutive()) {
            $query->where('user_id', auth()->id());
        } elseif ($salesId) {
            $query->where('user_id', $salesId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('pic_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('company_name')->get();
        $headers   = ['Company Name','PIC Name','Position','Phone','Email','Industry','Location','Status','Sales PIC','Customer Since'];
        $rows      = $customers->map(fn($c) => [
            $c->company_name, $c->pic_name, $c->pic_position,
            $c->phone, $c->email, $c->industry, $c->location,
            $c->status, $c->salesUser?->name,
            $c->customer_since?->format('Y-m-d'),
        ])->toArray();

        return \App\Helpers\ExcelExport::download('customers_' . date('Ymd_His'), $headers, $rows, 'Customers');
    }

    public function template()
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_import_customers.csv"',
        ];
        $callback = function () {
            $f = fopen('php://output', 'w');
            fputs($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Company Name', 'PIC Name', 'Position', 'Phone', 'Email', 'Industry', 'Location', 'Sales PIC Email']);
            fputcsv($f, ['PT. Contoh Kimia', 'Budi Santoso', 'Purchasing Manager', '0812-1234-5678', 'budi@contoh.co.id', 'Manufacturing', 'Surabaya', 'sales@crm.com']);
            fclose($f);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);
        $handle   = fopen($request->file('file')->getRealPath(), 'r');
        $header   = fgetcsv($handle);
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3 || empty(trim($row[0]))) continue;
            $salesUser = User::where('name', trim($row[7] ?? ''))->first();
            // Revisi #1: import dari menu Customer = Existing
            Customer::create([
                'company_name'   => trim($row[0]),
                'pic_name'       => trim($row[1]),
                'pic_position'   => trim($row[2] ?? ''),
                'phone'          => trim($row[3] ?? ''),
                'email'          => trim($row[4] ?? ''),
                'industry'       => trim($row[5] ?? ''),
                'location'       => trim($row[6] ?? ''),
                'status'         => 'Existing',
                'user_id'        => $salesUser?->id,
                'customer_since' => now()->toDateString(),
            ]);
            $imported++;
        }
        fclose($handle);
        return redirect()->route('customers.index')->with('success', "Berhasil import {$imported} customer.");
    }

    // Add activity ke customer — disamakan dengan Sales Activity
    public function storeActivity(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'type'           => 'required|in:Call,Visit,Email,Note,Others',
            'subject'        => 'required|string|max:255',
            'description'    => 'nullable|string',
            'activity_at'    => 'required|date',
            'status'         => 'required|in:Planned,Pending,Done,Overdue',
            'next_follow_up' => 'nullable|date',
            'pipeline_stage' => 'nullable|in:Identifying,Approaching,Follow Up,Won,Lost,Maintaining',
            'user_id'        => 'nullable|exists:users,id',
        ]);

        $validated['customer_id'] = $customer->id;
        $validated['user_id'] = auth()->user()->isSalesExecutive() ? auth()->id() : ($validated['user_id'] ?? $customer->user_id ?? auth()->id());
        $validated['sales_user_id'] = $validated['user_id'];

        $lead = Lead::where('customer_id', $customer->id)->orderByDesc('updated_at')->first();
        if (!$lead) {
            $lead = Lead::create([
                'lead_code'      => Lead::generateLeadCode(),
                'customer_id'    => $customer->id,
                'company_name'   => $customer->company_name,
                'pic_name'       => $customer->pic_name,
                'pic_position'   => $customer->pic_position,
                'phone'          => $customer->phone,
                'email'          => $customer->email,
                'address'        => $customer->address,
                'industry'       => $customer->industry,
                'location'       => $customer->location,
                'pipeline_stage' => $customer->status === 'Existing' ? 'Maintaining' : 'Identifying',
                'temperature'    => 'Warm',
                'user_id'        => $customer->user_id ?: auth()->id(),
            ]);
        }

        if (!empty($validated['pipeline_stage'])) {
            $allowed = $customer->status === 'Existing'
                ? ['Follow Up','Won','Lost','Maintaining']
                : ['Identifying','Approaching','Follow Up','Won','Lost','Maintaining'];
            if (in_array($validated['pipeline_stage'], $allowed, true)) {
                $lead->update(['pipeline_stage' => $validated['pipeline_stage']]);
                LeadsController::syncToCustomer($lead->fresh());
            }
        }

        if ($lead) {
            $validated['lead_id'] = $lead->id;
        }

        unset($validated['pipeline_stage']);
        Activity::create($validated);
        return redirect()->back()->with('success', 'Activity ditambahkan.');
    }

}

