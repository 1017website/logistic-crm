<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\VendorService;
use App\Models\VendorPic;
use App\Services\SpreadsheetRowReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $vendorType = $request->get('vendor_type');
        $serviceType = $request->get('service_type');
        $status = $request->get('status');
        $relationshipStatus = $request->get('relationship_status');
        $search = $request->get('search');

        $query = Vendor::with(['deliveryOrders', 'services', 'pics']);
        if ($vendorType && $vendorType !== 'all')
            $query->where('vendor_type', $vendorType);
        if ($serviceType && $serviceType !== 'all')
            $query->where('service_type', $serviceType);
        if ($status && $status !== 'all')
            $query->where('status', $status);
        if ($relationshipStatus && $relationshipStatus !== 'all')
            $query->where('relationship_status', $relationshipStatus);
        if ($search) {
            $query->where(
                fn($q) => $q
                    ->where('vendor_name', 'like', "%$search%")
                    ->orWhere('pic_name', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhere('service_type', 'like', "%$search%")
                    ->orWhereHas('services', fn($p) => $p->where('service_name', 'like', "%$search%"))
            );
        }

        $vendors = $query->orderBy('is_preferred', 'desc')->orderBy('rating', 'desc')->paginate(10)->withQueryString();
        $totalVendor = Vendor::count();
        $externalVendor = Vendor::where('vendor_type', 'External')->count();
        $internalVendor = Vendor::where('vendor_type', 'Internal')->count();
        $existingVendor = Vendor::where('relationship_status', 'Existing')->count();
        $potentialVendor = Vendor::where('relationship_status', 'Potential')->count();

        $selectedVendor = $request->get('selected_id')
            ? Vendor::with(['deliveryOrders', 'services', 'pics'])->find($request->get('selected_id'))
            : null;

        $pendingDeletionVendorIds = \App\Models\DeletionRequest::pendingIdsFor(Vendor::class);

        return view('vendors.index', compact(
            'vendors',
            'totalVendor',
            'externalVendor',
            'internalVendor',
            'existingVendor',
            'potentialVendor',
            'selectedVendor',
            'vendorType',
            'serviceType',
            'status',
            'relationshipStatus',
            'search',
            'pendingDeletionVendorIds'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_name' => 'required|string|max:255',
            'vendor_type' => 'required|in:External,Internal',
            'service_type' => 'nullable|string|max:100',
            'custom_service_type' => 'nullable|string|max:100',
            'service_mode' => 'nullable|string|max:255',
            'pic_name' => 'required|string|max:255',
            'pic_position' => 'nullable|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'payment_term' => 'nullable|string|max:100',
            'status' => 'required|in:Active,Non-Active',
            'relationship_status' => 'required|in:Potential,Existing',
            'is_preferred' => 'boolean',
            'rating' => 'nullable|numeric|min:0|max:5',
            'vendor_since' => 'nullable|date',
            // inline pics & services
            'pics' => 'nullable|array',
            'pics.*.pic_name' => 'nullable|string|max:255',
            'pics.*.pic_position' => 'nullable|string|max:100',
            'pics.*.phone' => 'nullable|string|max:20',
            'pics.*.email' => 'nullable|email|max:255',
            'services' => 'nullable|array',
            'services.*.service_name' => 'nullable|string|max:255',
            'services.*.unit' => 'nullable|string|max:50',
            'services.*.tonnage' => 'nullable|numeric|min:0',
            'services.*.tariff' => 'nullable|numeric|min:0',
            'services.*.tariff_unit' => 'nullable|string|max:50',
            'services.*.route_origin' => 'nullable|string|max:255',
            'services.*.route_destination' => 'nullable|string|max:255',
            'services.*.description' => 'nullable|string',
        ]);

        $validated['service_type'] = $this->resolveServiceType($validated['service_type'] ?? null, $validated['custom_service_type'] ?? null);
        unset($validated['custom_service_type']);

        // service_mode free input, contoh: Tracking, Kontainer, Wingbox
        $validated['service_mode'] = trim($validated['service_mode'] ?? '') ?: null;

        $validated['is_preferred'] = $request->boolean('is_preferred');
        $validated['rating'] = $validated['rating'] ?? 0;

        DB::transaction(function () use ($request, $validated) {
            $pics = $validated['pics'] ?? [];
            $services = $validated['services'] ?? [];
            unset($validated['pics'], $validated['services']);

            $validated['vendor_code'] = Vendor::generateVendorCode();
            $vendor = Vendor::create($validated);

            $picIndex = 0;
            foreach ($pics as $pic) {
                $picName = trim($pic['pic_name'] ?? '');
                if ($picName === '')
                    continue;

                $vendor->pics()->create([
                    'pic_name' => $picName,
                    'pic_position' => $pic['pic_position'] ?? null,
                    'phone' => $pic['phone'] ?? null,
                    'email' => $pic['email'] ?? null,
                    'is_primary' => $picIndex === 0,
                ]);
                $picIndex++;
            }

            foreach ($services as $svc) {
                $serviceName = trim($svc['service_name'] ?? '');
                if ($serviceName === '')
                    continue;

                $vendor->services()->create([
                    'service_name' => $serviceName,
                    'unit' => trim($svc['unit'] ?? ''),
                    'tonnage' => $svc['tonnage'] ?? null,
                    'tariff' => $svc['tariff'] ?? 0,
                    'tariff_unit' => trim($svc['tariff_unit'] ?? '') ?: 'per shipment',
                    'route_origin' => $svc['route_origin'] ?? null,
                    'route_destination' => $svc['route_destination'] ?? null,
                    'description' => $svc['description'] ?? null,
                ]);
            }
        });

        return redirect()->route('vendors.index')->with('success', 'Vendor berhasil ditambahkan.');
    }

    public function update(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'vendor_name' => 'sometimes|string|max:255',
            'vendor_type' => 'sometimes|in:External,Internal',
            'service_type' => 'nullable|string|max:100',
            'custom_service_type' => 'nullable|string|max:100',
            'service_mode' => 'nullable|string|max:255',
            'pic_name' => 'sometimes|string|max:255',
            'pic_position' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'payment_term' => 'nullable|string|max:100',
            'status' => 'sometimes|in:Active,Non-Active',
            'relationship_status' => 'sometimes|in:Potential,Existing',
            'is_preferred' => 'boolean',
            'rating' => 'nullable|numeric|min:0|max:5',
            'pics' => 'nullable|array',
            'pics.*.pic_name' => 'nullable|string|max:255',
            'pics.*.pic_position' => 'nullable|string|max:100',
            'pics.*.phone' => 'nullable|string|max:20',
            'pics.*.email' => 'nullable|email|max:255',
            'services' => 'nullable|array',
            'services.*.service_name' => 'nullable|string|max:255',
            'services.*.unit' => 'nullable|string|max:50',
            'services.*.tonnage' => 'nullable|numeric|min:0',
            'services.*.tariff' => 'nullable|numeric|min:0',
            'services.*.tariff_unit' => 'nullable|string|max:50',
            'services.*.route_origin' => 'nullable|string|max:255',
            'services.*.route_destination' => 'nullable|string|max:255',
            'services.*.description' => 'nullable|string',
        ]);

        $validated['service_type'] = $this->resolveServiceType($validated['service_type'] ?? null, $validated['custom_service_type'] ?? null);
        unset($validated['custom_service_type']);

        if (array_key_exists('service_mode', $validated)) {
            $validated['service_mode'] = trim($validated['service_mode'] ?? '') ?: null;
        }

        $validated['is_preferred'] = $request->boolean('is_preferred');
        if (array_key_exists('rating', $validated))
            $validated['rating'] = $validated['rating'] ?? 0;

        DB::transaction(function () use ($request, $validated, $vendor) {
            $pics = $validated['pics'] ?? [];
            $services = $validated['services'] ?? [];
            unset($validated['pics'], $validated['services']);

            $vendor->update($validated);

            if ($request->has('pics_submitted')) {
                $vendor->pics()->delete();
                $picIndex = 0;
                foreach ($pics as $pic) {
                    $picName = trim($pic['pic_name'] ?? '');
                    if ($picName === '')
                        continue;

                    $vendor->pics()->create([
                        'pic_name' => $picName,
                        'pic_position' => $pic['pic_position'] ?? null,
                        'phone' => $pic['phone'] ?? null,
                        'email' => $pic['email'] ?? null,
                        'is_primary' => $picIndex === 0,
                    ]);
                    $picIndex++;
                }
            }

            if ($request->has('services_submitted')) {
                $vendor->services()->delete();
                foreach ($services as $svc) {
                    $serviceName = trim($svc['service_name'] ?? '');
                    if ($serviceName === '')
                        continue;

                    $vendor->services()->create([
                        'service_name' => $serviceName,
                        'unit' => trim($svc['unit'] ?? ''),
                    'tonnage' => $svc['tonnage'] ?? null,
                        'tariff' => $svc['tariff'] ?? 0,
                        'tariff_unit' => trim($svc['tariff_unit'] ?? '') ?: 'per shipment',
                        'route_origin' => $svc['route_origin'] ?? null,
                        'route_destination' => $svc['route_destination'] ?? null,
                        'description' => $svc['description'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('vendors.index')->with('success', 'Vendor berhasil diperbarui.');
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();
        return redirect()->route('vendors.index')->with('success', 'Vendor berhasil dihapus.');
    }

    // ── Vendor PICs ──
    public function storePic(Request $request, Vendor $vendor)
    {
        $request->validate([
            'pic_name' => 'required|string|max:255',
            'pic_position' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);
        $vendor->pics()->create([
            'pic_name' => $request->pic_name,
            'pic_position' => $request->pic_position,
            'phone' => $request->phone,
            'email' => $request->email,
            'is_primary' => $vendor->pics()->count() === 0,
        ]);
        return redirect()->back()->with('success', 'PIC ditambahkan.');
    }

    public function destroyPic(Vendor $vendor, VendorPic $pic)
    {
        abort_if((int) $pic->vendor_id !== (int) $vendor->id, 404);
        $pic->delete();
        return redirect()->back()->with('success', 'PIC dihapus.');
    }

    // ── Vendor Services ──
    public function storeService(Request $request, Vendor $vendor)
    {
        $request->validate([
            'service_name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'tonnage' => 'nullable|numeric|min:0',
            'tariff' => 'nullable|numeric|min:0',
            'tariff_unit' => 'nullable|string|max:50',
            'route_origin' => 'nullable|string|max:255',
            'route_destination' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);
        $vendor->services()->create([
            'service_name' => $request->service_name,
            'unit' => $request->unit,
            'tonnage' => $request->tonnage,
            'tariff' => $request->tariff ?? 0,
            'tariff_unit' => trim($request->tariff_unit ?? '') ?: 'per shipment',
            'route_origin' => $request->route_origin,
            'route_destination' => $request->route_destination,
            'description' => $request->description,
        ]);
        return redirect()->back()->with('success', 'Layanan ditambahkan.');
    }

    public function destroyService(Vendor $vendor, VendorService $service)
    {
        abort_if((int) $service->vendor_id !== (int) $vendor->id, 404);
        $service->delete();
        return redirect()->back()->with('success', 'Layanan dihapus.');
    }


    private function resolveServiceType(?string $serviceType, ?string $customServiceType): ?string
    {
        $serviceType = trim((string) $serviceType);
        $customServiceType = trim((string) $customServiceType);

        if ($serviceType === 'Lainnya') {
            return $customServiceType !== '' ? $customServiceType : null;
        }

        return $serviceType !== '' ? $serviceType : null;
    }

    public function export(Request $request)
    {
        $vendorType = $request->get('vendor_type');
        $serviceType = $request->get('service_type');
        $status = $request->get('status');
        $relationshipStatus = $request->get('relationship_status');
        $search = $request->get('search');

        $query = Vendor::with('services');

        if ($vendorType && $vendorType !== 'all')
            $query->where('vendor_type', $vendorType);
        if ($serviceType && $serviceType !== 'all')
            $query->where('service_type', $serviceType);
        if ($status && $status !== 'all')
            $query->where('status', $status);
        if ($relationshipStatus && $relationshipStatus !== 'all')
            $query->where('relationship_status', $relationshipStatus);

        if ($search) {
            $query->where(
                fn($q) => $q
                    ->where('vendor_name', 'like', "%$search%")
                    ->orWhere('pic_name', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhere('service_type', 'like', "%$search%")
                    ->orWhereHas('services', fn($p) => $p->where('service_name', 'like', "%$search%"))
            );
        }

        $vendors = $query->orderBy('is_preferred', 'desc')->orderBy('rating', 'desc')->get();

        $headers = ['Vendor Name', 'Vendor Type', 'Service Type', 'Service Mode', 'PIC', 'Phone', 'Email', 'Layanan', 'Relationship', 'Status', 'Preferred', 'Rating'];
        $rows = $vendors->map(fn($v) => [
            $v->vendor_name,
            $v->vendor_type,
            $v->service_type,
            $v->service_mode,
            $v->pic_name,
            $v->phone,
            $v->email,
            $v->services->map(fn($s) => trim($s->service_name . ($s->unit ? ' (' . $s->unit . ')' : '')))->implode(', '),
            $v->relationship_status,
            $v->status,
            $v->is_preferred ? 'Yes' : 'No',
            $v->rating,
        ])->toArray();

        return \App\Helpers\ExcelExport::download('vendors-' . date('Ymd'), $headers, $rows, 'Vendors');
    }

    public function template()
    {
        $headers = [
            'Vendor Name', 'Vendor Type', 'Service Type', 'Service Mode', 'PIC Name',
            'Position', 'Phone', 'Email', 'Address', 'Payment Term', 'Status',
            'Relationship', 'Preferred', 'Rating', 'Vendor Since', 'Service Name',
            'Unit', 'Tonnage', 'Tariff', 'Tariff Unit', 'Route Origin',
            'Route Destination', 'Service Description',
        ];
        $rows = [[
            'PT Contoh Transport', 'External', 'Trucking trailer', 'Kontainer',
            'Siti Aminah', 'Marketing', '0812-9876-5432', 'siti@vendor.co.id',
            'Jl. Vendor No. 5', '30 hari', 'Active', 'Existing', 'Yes', 4.5,
            now()->format('Y-m-d'), 'Trucking FCL', 'trip', 20, 3500000,
            'per shipment', 'Surabaya', 'Jakarta', 'Tarif contoh per pengiriman',
        ]];

        return \App\Helpers\ExcelExport::download('template_import_vendors', $headers, $rows, 'Vendors');
    }

    public function import(Request $request, SpreadsheetRowReader $reader)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $rows = $reader->read($request->file('file'), [
            'vendor_name' => ['Vendor Name', 'Nama Vendor'],
            'vendor_type' => ['Vendor Type', 'Tipe Vendor'],
            'service_type' => ['Service Type', 'Tipe Layanan'],
            'service_mode' => ['Service Mode', 'Mode Layanan'],
            'pic_name' => ['PIC Name', 'PIC', 'Nama PIC'],
            'pic_position' => ['Position', 'PIC Position', 'Jabatan'],
            'phone' => ['Phone', 'Telephone', 'No Telepon', 'Nomor Telepon'],
            'email' => ['Email', 'PIC Email'],
            'address' => ['Address', 'Alamat'],
            'payment_term' => ['Payment Term', 'Termin Pembayaran'],
            'status' => ['Status'],
            'relationship_status' => ['Relationship', 'Relationship Status', 'Relasi'],
            'is_preferred' => ['Preferred', 'Is Preferred'],
            'rating' => ['Rating'],
            'vendor_since' => ['Vendor Since', 'Tanggal Vendor'],
            'service_name' => ['Service Name', 'Layanan', 'Nama Layanan'],
            'unit' => ['Unit', 'Satuan'],
            'tonnage' => ['Tonnage', 'Tonase'],
            'tariff' => ['Tariff', 'Tarif'],
            'tariff_unit' => ['Tariff Unit', 'Satuan Tarif'],
            'route_origin' => ['Route Origin', 'Origin', 'Asal'],
            'route_destination' => ['Route Destination', 'Destination', 'Tujuan'],
            'description' => ['Service Description', 'Description', 'Keterangan Layanan'],
        ], ['vendor_name', 'pic_name', 'phone']);

        $imported = 0;
        $errors = [];

        foreach ($rows as $spreadsheetRow) {
            $rowNumber = $spreadsheetRow['row'];
            $data = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $spreadsheetRow['data']);
            $data['vendor_name'] = trim((string) $data['vendor_name']);
            $data['pic_name'] = trim((string) $data['pic_name']);
            $data['phone'] = trim((string) $data['phone']);
            $data['email'] = trim((string) $data['email']) ?: null;
            $data['vendor_type'] = $this->normalizeImportChoice($data['vendor_type'], Vendor::VENDOR_TYPES, 'External');
            $data['status'] = $this->normalizeImportChoice($data['status'], ['Active', 'Non-Active'], 'Active');
            $data['relationship_status'] = $this->normalizeImportChoice($data['relationship_status'], ['Potential', 'Existing'], 'Potential');
            $data['is_preferred'] = $this->normalizeImportBoolean($data['is_preferred']);
            $data['vendor_since'] = $this->normalizeImportDate($data['vendor_since']);

            $validator = Validator::make($data, [
                'vendor_name' => 'required|string|max:255',
                'vendor_type' => 'required|in:External,Internal',
                'service_type' => 'nullable|string|max:100',
                'service_mode' => 'nullable|string|max:255',
                'pic_name' => 'required|string|max:255',
                'pic_position' => 'nullable|string|max:100',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'payment_term' => 'nullable|string|max:100',
                'status' => 'required|in:Active,Non-Active',
                'relationship_status' => 'required|in:Potential,Existing',
                'is_preferred' => 'boolean',
                'rating' => 'nullable|numeric|min:0|max:5',
                'vendor_since' => 'nullable|date_format:Y-m-d',
                'service_name' => 'nullable|string|max:255',
                'unit' => 'nullable|string|max:50',
                'tonnage' => 'nullable|numeric|min:0',
                'tariff' => 'nullable|numeric|min:0',
                'tariff_unit' => 'nullable|string|max:50',
                'route_origin' => 'nullable|string|max:255',
                'route_destination' => 'nullable|string|max:255',
                'description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                $errors[] = "Baris {$rowNumber}: ".implode(' ', $validator->errors()->all());
                continue;
            }

            try {
                DB::transaction(function () use ($data) {
                    $vendor = Vendor::create([
                        'vendor_code' => Vendor::generateVendorCode(),
                        'vendor_name' => $data['vendor_name'],
                        'vendor_type' => $data['vendor_type'],
                        'service_type' => trim((string) $data['service_type']) ?: null,
                        'service_mode' => trim((string) $data['service_mode']) ?: null,
                        'pic_name' => $data['pic_name'],
                        'pic_position' => trim((string) $data['pic_position']) ?: null,
                        'phone' => $data['phone'],
                        'email' => $data['email'],
                        'address' => trim((string) $data['address']) ?: null,
                        'payment_term' => trim((string) $data['payment_term']) ?: null,
                        'status' => $data['status'],
                        'relationship_status' => $data['relationship_status'],
                        'is_preferred' => $data['is_preferred'],
                        'rating' => $data['rating'] !== null && $data['rating'] !== '' ? $data['rating'] : 0,
                        'vendor_since' => $data['vendor_since'],
                    ]);

                    if (trim((string) $data['service_name']) !== '') {
                        $vendor->services()->create([
                            'service_name' => trim((string) $data['service_name']),
                            'unit' => trim((string) $data['unit']),
                            'tonnage' => $data['tonnage'] !== null && $data['tonnage'] !== '' ? $data['tonnage'] : null,
                            'tariff' => $data['tariff'] !== null && $data['tariff'] !== '' ? $data['tariff'] : 0,
                            'tariff_unit' => trim((string) $data['tariff_unit']) ?: 'per shipment',
                            'route_origin' => trim((string) $data['route_origin']) ?: null,
                            'route_destination' => trim((string) $data['route_destination']) ?: null,
                            'description' => trim((string) $data['description']) ?: null,
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
            ? "Berhasil import {$imported} vendor."
            : 'Tidak ada vendor yang berhasil diimport.';
        $response = redirect()->route('vendors.index')
            ->with($imported > 0 ? 'success' : 'error', $message);

        return $errors !== []
            ? $response->with('warning', $this->summarizeImportErrors($errors))
            : $response;
    }

    private function normalizeImportChoice(mixed $value, array $choices, string $default): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $default;
        }

        foreach ($choices as $choice) {
            if (strcasecmp($value, $choice) === 0) {
                return $choice;
            }
        }

        return $value;
    }

    private function normalizeImportBoolean(mixed $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), ['1', 'yes', 'y', 'true', 'ya'], true);
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
        $summary = implode(' ', array_slice($errors, 0, 5));

        return count($errors) > 5
            ? $summary.' Dan '.(count($errors) - 5).' error lainnya.'
            : $summary;
    }
}
