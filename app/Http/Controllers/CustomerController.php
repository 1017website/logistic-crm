<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPic;
use App\Models\User;
use App\Models\Activity;
use App\Models\VendorService;
use App\Models\Lead;
use App\Services\SpreadsheetRowReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

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
                  ->orWhere('phone', 'like', "%$search%")
                  ->orWhere('customer_code', 'like', "%$search%")
                  ->orWhere('invoice_code', 'like', "%$search%");
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

        $pendingDeletionCustomerIds = \App\Models\DeletionRequest::pendingIdsFor(Customer::class);

        return view('customers.index', compact(
            'customers','totalCustomer','potentialCustomer','existingCustomer',
            'industries','salesUsers','selectedCustomer','vendorServices','status','industry','search','salesId',
            'pendingDeletionCustomerIds'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name'   => 'required|string|max:255',
            'invoice_code'   => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('customers', 'invoice_code')],
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
            'products_list.*.tonnage'      => 'nullable|numeric|min:0',
            'products_list.*.shipping_zone' => 'nullable|string|max:255',
        ]);

        $validated['invoice_code'] = Customer::normalizeInvoiceCode($validated['invoice_code'] ?? null);

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

            $validated['customer_code'] = \App\Models\Customer::generateCustomerCode();
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
                    'tonnage'      => $prod['tonnage'] ?? null,
                    'shipping_zone' => $prod['shipping_zone'] ?? null,
                ]);
            }

            // Revisi #1: create customer existing sekaligus create lead stage Maintaining
            $leadPayload = [
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
            ];

            // Retry bila lead_code bentrok (race / sisa soft-deleted).
            $attempt = 0;
            do {
                try {
                    $lead = Lead::create($leadPayload); // lead_code otomatis via creating event
                    break;
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    if (++$attempt >= 5) throw $e;
                }
            } while (true);

            // Salin layanan customer ke lead products agar konsisten
            foreach ($customer->productItems as $cp) {
                $lead->products()->create([
                    'service_name' => $cp->service_name ?? $cp->product_name,
                    'product_name' => $cp->service_name ?? $cp->product_name,
                    'unit'         => $cp->unit ?? '',
                    'tonnage'      => $cp->tonnage ?? null,
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
            'invoice_code'   => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('customers', 'invoice_code')->ignore($customer->id)],
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
            'products_list.*.tonnage'      => 'nullable|numeric|min:0',
            'products_list.*.shipping_zone' => 'nullable|string|max:255',
        ]);

        $validated['invoice_code'] = Customer::normalizeInvoiceCode($validated['invoice_code'] ?? null);

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
                $customer->productItems()->get()->each(fn($row) => $row->delete());
                foreach ($productsList as $product) {
                    $name = trim($product['service_name'] ?? $product['product_name'] ?? '');
                    if ($name === '') continue;
                    $customer->productItems()->create([
                        'service_name' => $name,
                        'product_name' => $name,
                        'unit'         => trim($product['unit'] ?? ''),
                        'tonnage'      => $product['tonnage'] ?? null,
                        'shipping_zone' => $product['shipping_zone'] ?? null,
                    ]);
                }
            }

            /**
             * pics_submitted: PIC tambahan dari modal edit dianggap final.
             */
            if ($request->has('pics_submitted')) {
                $customer->pics()->get()->each(fn($row) => $row->delete());

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

            // Mirror field utama customer -> lead terkait (nama, PIC utama, kontak, dll).
            \App\Services\LeadCustomerSync::syncCustomerFieldsToLead($customer->fresh());
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
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('customer_code', 'like', "%{$search}%")
                  ->orWhere('invoice_code', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('company_name')->get();
        $headers   = ['Customer Code','Invoice Code','Company Name','PIC Name','Position','Phone','Email','Industry','Location','Status','Sales PIC','Customer Since'];
        $rows      = $customers->map(fn($c) => [
            $c->customer_code, $c->invoice_code,
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
            'Company Name', 'PIC Name', 'Position', 'Phone', 'Email', 'Industry',
            'Location', 'Address', 'Kode Invoice', 'Sales PIC Email/Name',
            'Customer Since', 'Notes', 'Service Name', 'Unit', 'Tonnage', 'Shipping Zone',
        ];
        $rows = [[
            'PT Contoh Logistik', 'Budi Santoso', 'Purchasing Manager', '0812-1234-5678',
            'budi@contoh.co.id', 'Manufacturing', 'Surabaya', 'Jl. Contoh No. 10',
            'PCL', 'sales@crm.com', now()->format('Y-m-d'), 'Customer existing',
            'Trucking', 'trip', 10, 'Surabaya - Jakarta',
        ]];

        return \App\Helpers\ExcelExport::download('template_import_customers', $headers, $rows, 'Customers');
    }

    public function import(Request $request, SpreadsheetRowReader $reader)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $rows = $reader->read($request->file('file'), [
            'company_name' => ['Company Name', 'Company', 'Nama Perusahaan', 'Customer Name'],
            'pic_name' => ['PIC Name', 'PIC', 'Nama PIC'],
            'pic_position' => ['Position', 'PIC Position', 'Jabatan'],
            'phone' => ['Phone', 'Telephone', 'No Telepon', 'Nomor Telepon'],
            'email' => ['Email', 'PIC Email'],
            'industry' => ['Industry', 'Industri'],
            'location' => ['Location', 'Lokasi'],
            'address' => ['Address', 'Alamat'],
            'invoice_code' => ['Kode Invoice', 'Invoice Code'],
            'sales_key' => ['Sales PIC Email/Name', 'Sales PIC', 'Sales Email'],
            'customer_since' => ['Customer Since', 'Tanggal Customer'],
            'notes' => ['Notes', 'Catatan'],
            'service_name' => ['Service Name', 'Layanan', 'Nama Layanan'],
            'unit' => ['Unit', 'Satuan'],
            'tonnage' => ['Tonnage', 'Tonase'],
            'shipping_zone' => ['Shipping Zone', 'Zona Pengiriman', 'Route'],
        ], ['company_name', 'pic_name', 'phone']);

        $imported = 0;
        $errors = [];

        foreach ($rows as $spreadsheetRow) {
            $rowNumber = $spreadsheetRow['row'];
            $data = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $spreadsheetRow['data']);
            $data['company_name'] = trim((string) $data['company_name']);
            $data['pic_name'] = trim((string) $data['pic_name']);
            $data['phone'] = trim((string) $data['phone']);
            $data['email'] = trim((string) $data['email']) ?: null;
            $data['invoice_code'] = Customer::normalizeInvoiceCode((string) $data['invoice_code']);
            $data['customer_since'] = $this->normalizeImportDate($data['customer_since']);

            $validator = Validator::make($data, [
                'company_name' => 'required|string|max:255',
                'pic_name' => 'required|string|max:255',
                'pic_position' => 'nullable|string|max:100',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'industry' => 'nullable|string|max:100',
                'location' => 'nullable|string|max:255',
                'address' => 'nullable|string',
                'invoice_code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/'],
                'customer_since' => 'nullable|date_format:Y-m-d',
                'notes' => 'nullable|string',
                'service_name' => 'nullable|string|max:255',
                'unit' => 'nullable|string|max:100',
                'tonnage' => 'nullable|numeric|min:0',
                'shipping_zone' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                $errors[] = "Baris {$rowNumber}: ".implode(' ', $validator->errors()->all());
                continue;
            }

            if ($data['invoice_code'] && Customer::withTrashed()->where('invoice_code', $data['invoice_code'])->exists()) {
                $errors[] = "Baris {$rowNumber}: kode invoice {$data['invoice_code']} sudah digunakan.";
                continue;
            }

            $salesUser = null;
            if (auth()->user()->isSalesExecutive()) {
                $salesUser = auth()->user();
            } elseif (trim((string) $data['sales_key']) !== '') {
                $salesKey = trim((string) $data['sales_key']);
                $salesUser = User::where('email', $salesKey)->orWhere('name', $salesKey)->first();
                if (!$salesUser) {
                    $errors[] = "Baris {$rowNumber}: Sales PIC '{$salesKey}' tidak ditemukan.";
                    continue;
                }
            }

            try {
                DB::transaction(function () use ($data, $salesUser) {
                    $customer = Customer::create([
                        'customer_code' => Customer::generateCustomerCode(),
                        'invoice_code' => $data['invoice_code'],
                        'company_name' => $data['company_name'],
                        'pic_name' => $data['pic_name'],
                        'pic_position' => $data['pic_position'] ?: null,
                        'phone' => $data['phone'],
                        'email' => $data['email'],
                        'industry' => $data['industry'] ?: null,
                        'location' => $data['location'] ?: null,
                        'address' => $data['address'] ?: null,
                        'notes' => $data['notes'] ?: null,
                        'status' => 'Existing',
                        'user_id' => $salesUser?->id,
                        'customer_since' => $data['customer_since'] ?: now()->toDateString(),
                    ]);

                    if (trim((string) $data['service_name']) !== '') {
                        $customer->productItems()->create([
                            'service_name' => trim((string) $data['service_name']),
                            'product_name' => trim((string) $data['service_name']),
                            'unit' => trim((string) $data['unit']),
                            'tonnage' => $data['tonnage'] !== null && $data['tonnage'] !== '' ? $data['tonnage'] : null,
                            'shipping_zone' => $data['shipping_zone'] ?: null,
                        ]);
                    }

                    $lead = Lead::create([
                        'customer_id' => $customer->id,
                        'company_name' => $customer->company_name,
                        'pic_name' => $customer->pic_name,
                        'pic_position' => $customer->pic_position,
                        'phone' => $customer->phone,
                        'email' => $customer->email,
                        'address' => $customer->address,
                        'industry' => $customer->industry,
                        'location' => $customer->location,
                        'pipeline_stage' => 'Maintaining',
                        'temperature' => 'Warm',
                        'user_id' => $customer->user_id,
                    ]);

                    foreach ($customer->productItems as $product) {
                        $lead->products()->create([
                            'service_name' => $product->service_name ?? $product->product_name,
                            'product_name' => $product->service_name ?? $product->product_name,
                            'unit' => $product->unit ?? '',
                            'tonnage' => $product->tonnage ?? null,
                        ]);
                    }
                });
                $imported++;
            } catch (\Throwable $exception) {
                report($exception);
                $errors[] = "Baris {$rowNumber}: data gagal disimpan.";
            }
        }

        $message = $imported > 0
            ? "Berhasil import {$imported} customer."
            : 'Tidak ada customer yang berhasil diimport.';
        $response = redirect()->route('customers.index')
            ->with($imported > 0 ? 'success' : 'error', $message);

        return $errors !== []
            ? $response->with('warning', $this->summarizeImportErrors($errors))
            : $response;
    }

    private function normalizeImportDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable $exception) {
                return (string) $value;
            }
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? (string) $value : date('Y-m-d', $timestamp);
    }

    /** @param array<int, string> $errors */
    private function summarizeImportErrors(array $errors): string
    {
        $visible = array_slice($errors, 0, 5);
        $summary = implode(' ', $visible);

        return count($errors) > 5
            ? $summary.' Dan '.(count($errors) - 5).' error lainnya.'
            : $summary;
    }

    // Add activity ke customer — disamakan dengan Sales Activity
    public function storeActivity(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'type'           => 'required|in:Call,Visit,Email,Note,Others',
            'subject'        => 'required|string|max:255',
            'description'    => 'nullable|string',
            'activity_at'    => 'nullable|date',
            'status'         => 'required|in:Planned,Pending,Done,Overdue',
            'next_follow_up' => 'nullable|date',
            'pipeline_stage' => 'nullable|in:Identifying,Approaching,Follow Up,Won,Maintaining',
            'user_id'        => 'nullable|exists:users,id',
        ]);

        $validated['activity_at'] = now();
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
            // Customer existing dibatasi: Follow Up, Won, Maintaining.
            if ($customer->status === 'Existing') {
                $allowed  = ['Follow Up', 'Won', 'Maintaining'];
                $newStage = in_array($validated['pipeline_stage'], $allowed, true) ? $validated['pipeline_stage'] : 'Maintaining';
            } else {
                $newStage = $validated['pipeline_stage'];
            }
            $lead->update(['pipeline_stage' => $newStage]);
            LeadsController::syncToCustomer($lead->fresh());
        }

        if ($lead) {
            $validated['lead_id'] = $lead->id;
        }

        Activity::create($validated);
        return redirect()->back()->with('success', 'Activity ditambahkan.');
    }

}
