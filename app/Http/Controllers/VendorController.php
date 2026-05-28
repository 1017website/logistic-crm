<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\VendorService;
use App\Models\VendorPic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $vendorType         = $request->get('vendor_type');
        $serviceType        = $request->get('service_type');
        $status             = $request->get('status');
        $relationshipStatus = $request->get('relationship_status');
        $search             = $request->get('search');

        $query = Vendor::with(['shipmentOrders', 'services', 'pics']);
        if ($vendorType         && $vendorType         !== 'all') $query->where('vendor_type', $vendorType);
        if ($serviceType        && $serviceType        !== 'all') $query->where('service_type', $serviceType);
        if ($status             && $status             !== 'all') $query->where('status', $status);
        if ($relationshipStatus && $relationshipStatus !== 'all') $query->where('relationship_status', $relationshipStatus);
        if ($search) {
            $query->where(fn($q) => $q
                ->where('vendor_name', 'like', "%$search%")
                ->orWhere('pic_name',    'like', "%$search%")
                ->orWhere('phone',       'like', "%$search%")
                ->orWhere('service_type', 'like', "%$search%")
                ->orWhereHas('services', fn($p) => $p->where('service_name', 'like', "%$search%"))
            );
        }

        $vendors           = $query->orderBy('is_preferred', 'desc')->orderBy('rating', 'desc')->paginate(10)->withQueryString();
        $totalVendor       = Vendor::count();
        $externalVendor    = Vendor::where('vendor_type', 'External')->count();
        $internalVendor    = Vendor::where('vendor_type', 'Internal')->count();
        $existingVendor    = Vendor::where('relationship_status', 'Existing')->count();
        $potentialVendor   = Vendor::where('relationship_status', 'Potential')->count();

        $selectedVendor = $request->get('selected_id')
            ? Vendor::with(['shipmentOrders', 'services', 'pics'])->find($request->get('selected_id'))
            : null;

        return view('vendors.index', compact(
            'vendors', 'totalVendor', 'externalVendor', 'internalVendor',
            'existingVendor', 'potentialVendor', 'selectedVendor',
            'vendorType', 'serviceType', 'status', 'relationshipStatus', 'search'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_name'         => 'required|string|max:255',
            'vendor_type'         => 'required|in:External,Internal',
            'service_type'        => 'nullable|string|max:100',
            'service_mode'        => 'nullable|array',
            'service_mode.*'      => 'string|in:Tracking,Kontainer,Wingbox',
            'pic_name'            => 'required|string|max:255',
            'pic_position'        => 'nullable|string|max:100',
            'phone'               => 'required|string|max:20',
            'email'               => 'nullable|email|max:255',
            'address'             => 'nullable|string',
            'payment_term'        => 'nullable|string|max:100',
            'status'              => 'required|in:Active,Non-Active',
            'relationship_status' => 'required|in:Potential,Existing',
            'is_preferred'        => 'boolean',
            'rating'              => 'nullable|numeric|min:0|max:5',
            'vendor_since'        => 'nullable|date',
            // inline pics & services
            'pics'                => 'nullable|array',
            'pics.*.pic_name'     => 'nullable|string|max:255',
            'pics.*.pic_position' => 'nullable|string|max:100',
            'pics.*.phone'        => 'nullable|string|max:20',
            'pics.*.email'        => 'nullable|email|max:255',
            'services'            => 'nullable|array',
            'services.*.service_name'   => 'nullable|string|max:255',
            'services.*.unit'           => 'nullable|string|max:50',
            'services.*.tariff'         => 'nullable|numeric|min:0',
            'services.*.tariff_unit'    => 'nullable|string|max:50',
            'services.*.route_origin'        => 'nullable|string|max:255',
            'services.*.route_destination'   => 'nullable|string|max:255',
            'services.*.description'    => 'nullable|string',
        ]);

        // service_mode array → comma-separated string untuk storage
        $validated['service_mode'] = !empty($validated['service_mode'])
            ? implode(',', $validated['service_mode'])
            : null;

        $validated['is_preferred'] = $request->boolean('is_preferred');
        $validated['rating']       = $validated['rating'] ?? 0;

        DB::transaction(function () use ($request, $validated) {
            $pics     = $validated['pics'] ?? [];
            $services = $validated['services'] ?? [];
            unset($validated['pics'], $validated['services']);

            $vendor = Vendor::create($validated);

            $picIndex = 0;
            foreach ($pics as $pic) {
                $picName = trim($pic['pic_name'] ?? '');
                if ($picName === '') continue;

                $vendor->pics()->create([
                    'pic_name'     => $picName,
                    'pic_position' => $pic['pic_position'] ?? null,
                    'phone'        => $pic['phone'] ?? null,
                    'email'        => $pic['email'] ?? null,
                    'is_primary'   => $picIndex === 0,
                ]);
                $picIndex++;
            }

            foreach ($services as $svc) {
                $serviceName = trim($svc['service_name'] ?? '');
                if ($serviceName === '') continue;

                $vendor->services()->create([
                    'service_name'      => $serviceName,
                    'unit'              => $svc['unit'] ?? 'kg',
                    'tariff'            => $svc['tariff'] ?? 0,
                    'tariff_unit'       => $svc['tariff_unit'] ?? 'per kg',
                    'route_origin'      => $svc['route_origin'] ?? null,
                    'route_destination' => $svc['route_destination'] ?? null,
                    'description'       => $svc['description'] ?? null,
                ]);
            }
        });

        return redirect()->route('vendors.index')->with('success', 'Vendor berhasil ditambahkan.');
    }

    public function update(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'vendor_name'         => 'sometimes|string|max:255',
            'vendor_type'         => 'sometimes|in:External,Internal',
            'service_type'        => 'nullable|string|max:100',
            'service_mode'        => 'nullable|array',
            'service_mode.*'      => 'string|in:Tracking,Kontainer,Wingbox',
            'pic_name'            => 'sometimes|string|max:255',
            'pic_position'        => 'nullable|string|max:100',
            'phone'               => 'nullable|string|max:20',
            'email'               => 'nullable|email|max:255',
            'address'             => 'nullable|string',
            'payment_term'        => 'nullable|string|max:100',
            'status'              => 'sometimes|in:Active,Non-Active',
            'relationship_status' => 'sometimes|in:Potential,Existing',
            'is_preferred'        => 'boolean',
            'rating'              => 'nullable|numeric|min:0|max:5',
            'pics'                => 'nullable|array',
            'pics.*.pic_name'     => 'nullable|string|max:255',
            'pics.*.pic_position' => 'nullable|string|max:100',
            'pics.*.phone'        => 'nullable|string|max:20',
            'pics.*.email'        => 'nullable|email|max:255',
            'services'            => 'nullable|array',
            'services.*.service_name'   => 'nullable|string|max:255',
            'services.*.unit'           => 'nullable|string|max:50',
            'services.*.tariff'         => 'nullable|numeric|min:0',
            'services.*.tariff_unit'    => 'nullable|string|max:50',
            'services.*.route_origin'        => 'nullable|string|max:255',
            'services.*.route_destination'   => 'nullable|string|max:255',
            'services.*.description'    => 'nullable|string',
        ]);

        if (array_key_exists('service_mode', $validated)) {
            $validated['service_mode'] = !empty($validated['service_mode'])
                ? implode(',', $validated['service_mode'])
                : null;
        }

        $validated['is_preferred'] = $request->boolean('is_preferred');
        if (array_key_exists('rating', $validated)) $validated['rating'] = $validated['rating'] ?? 0;

        DB::transaction(function () use ($request, $validated, $vendor) {
            $pics     = $validated['pics'] ?? [];
            $services = $validated['services'] ?? [];
            unset($validated['pics'], $validated['services']);

            $vendor->update($validated);

            if ($request->has('pics_submitted')) {
                $vendor->pics()->delete();
                $picIndex = 0;
                foreach ($pics as $pic) {
                    $picName = trim($pic['pic_name'] ?? '');
                    if ($picName === '') continue;

                    $vendor->pics()->create([
                        'pic_name'     => $picName,
                        'pic_position' => $pic['pic_position'] ?? null,
                        'phone'        => $pic['phone'] ?? null,
                        'email'        => $pic['email'] ?? null,
                        'is_primary'   => $picIndex === 0,
                    ]);
                    $picIndex++;
                }
            }

            if ($request->has('services_submitted')) {
                $vendor->services()->delete();
                foreach ($services as $svc) {
                    $serviceName = trim($svc['service_name'] ?? '');
                    if ($serviceName === '') continue;

                    $vendor->services()->create([
                        'service_name'      => $serviceName,
                        'unit'              => $svc['unit'] ?? 'kg',
                        'tariff'            => $svc['tariff'] ?? 0,
                        'tariff_unit'       => $svc['tariff_unit'] ?? 'per kg',
                        'route_origin'      => $svc['route_origin'] ?? null,
                        'route_destination' => $svc['route_destination'] ?? null,
                        'description'       => $svc['description'] ?? null,
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
            'pic_name'     => 'required|string|max:255',
            'pic_position' => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:255',
        ]);
        $vendor->pics()->create([
            'pic_name'     => $request->pic_name,
            'pic_position' => $request->pic_position,
            'phone'        => $request->phone,
            'email'        => $request->email,
            'is_primary'   => $vendor->pics()->count() === 0,
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
            'service_name'      => 'required|string|max:255',
            'unit'              => 'required|string|max:50',
            'tariff'            => 'nullable|numeric|min:0',
            'tariff_unit'       => 'nullable|string|max:50',
            'route_origin'      => 'nullable|string|max:255',
            'route_destination' => 'nullable|string|max:255',
            'description'       => 'nullable|string',
        ]);
        $vendor->services()->create([
            'service_name'      => $request->service_name,
            'unit'              => $request->unit,
            'tariff'            => $request->tariff ?? 0,
            'tariff_unit'       => $request->tariff_unit ?? 'per kg',
            'route_origin'      => $request->route_origin,
            'route_destination' => $request->route_destination,
            'description'       => $request->description,
        ]);
        return redirect()->back()->with('success', 'Layanan ditambahkan.');
    }

    public function destroyService(Vendor $vendor, VendorService $service)
    {
        abort_if((int) $service->vendor_id !== (int) $vendor->id, 404);
        $service->delete();
        return redirect()->back()->with('success', 'Layanan dihapus.');
    }

    public function export(Request $request)
    {
        $vendorType         = $request->get('vendor_type');
        $serviceType        = $request->get('service_type');
        $status             = $request->get('status');
        $relationshipStatus = $request->get('relationship_status');
        $search             = $request->get('search');

        $query = Vendor::with('services');

        if ($vendorType && $vendorType !== 'all') $query->where('vendor_type', $vendorType);
        if ($serviceType && $serviceType !== 'all') $query->where('service_type', $serviceType);
        if ($status && $status !== 'all') $query->where('status', $status);
        if ($relationshipStatus && $relationshipStatus !== 'all') $query->where('relationship_status', $relationshipStatus);

        if ($search) {
            $query->where(fn($q) => $q
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
}
