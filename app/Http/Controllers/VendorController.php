<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\VendorRate;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $type   = $request->get('vendor_type');
        $status = $request->get('status');
        $search = $request->get('search');

        $query = Vendor::with(['deliveryOrders', 'rates']);
        if ($type   && $type   !== 'all') $query->where('vendor_type', $type);
        if ($status && $status !== 'all') $query->where('status', $status);
        if ($search) {
            $query->where(fn($q) => $q->where('vendor_name', 'like', "%$search%")
                ->orWhere('pic_name', 'like', "%$search%")
                ->orWhere('phone', 'like', "%$search%"));
        }

        $vendors         = $query->orderBy('is_preferred', 'desc')->orderBy('rating', 'desc')->paginate(10)->withQueryString();
        $totalVendor     = Vendor::count();
        $activeVendor    = Vendor::where('status', 'Active')->count();
        $nonActiveVendor = Vendor::where('status', 'Non-Active')->count();
        $preferredVendor = Vendor::where('is_preferred', true)->count();

        $selectedVendor = $request->get('selected_id')
            ? Vendor::with(['deliveryOrders', 'rates'])->find($request->get('selected_id'))
            : null;

        return view('vendors.index', compact(
            'vendors', 'totalVendor', 'activeVendor', 'nonActiveVendor',
            'preferredVendor', 'selectedVendor', 'type', 'status', 'search'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_name'   => 'required|string|max:255',
            'vendor_type'   => 'required|string|max:100',
            'pic_name'      => 'required|string|max:255',
            'pic_position'  => 'nullable|string|max:100',
            'phone'         => 'required|string|max:20',
            'email'         => 'nullable|email|max:255',
            'address'       => 'nullable|string',
            'coverage_area' => 'nullable|string|max:255',
            'payment_term'  => 'nullable|string|max:100',
            'status'        => 'required|in:Active,Non-Active',
            'is_preferred'  => 'boolean',
            'rating'        => 'nullable|numeric|min:0|max:5',
            'vendor_since'  => 'nullable|date',
            'notes'         => 'nullable|string',
        ]);
        $validated['is_preferred'] = $request->boolean('is_preferred');
        Vendor::create($validated);
        return redirect()->route('vendors.index')->with('success', 'Vendor berhasil ditambahkan.');
    }

    public function update(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'vendor_name'   => 'sometimes|string|max:255',
            'vendor_type'   => 'sometimes|string|max:100',
            'pic_name'      => 'sometimes|string|max:255',
            'pic_position'  => 'nullable|string|max:100',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:255',
            'address'       => 'nullable|string',
            'coverage_area' => 'nullable|string|max:255',
            'payment_term'  => 'nullable|string|max:100',
            'status'        => 'sometimes|in:Active,Non-Active',
            'is_preferred'  => 'boolean',
            'rating'        => 'nullable|numeric|min:0|max:5',
            'notes'         => 'nullable|string',
        ]);
        $validated['is_preferred'] = $request->boolean('is_preferred');
        $vendor->update($validated);
        return redirect()->back()->with('success', 'Data vendor diupdate.');
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();
        return redirect()->route('vendors.index')->with('success', 'Vendor dihapus.');
    }

    public function storeRate(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'route'          => 'required|string|max:255',
            'container_type' => 'nullable|string|max:100',
            'price'          => 'required|numeric|min:0',
            'currency'       => 'required|in:IDR,USD,SGD,EUR',
            'last_updated'   => 'nullable|date',
        ]);
        $validated['vendor_id']     = $vendor->id;
        $validated['last_updated']  = $validated['last_updated'] ?? now()->toDateString();
        VendorRate::create($validated);
        return redirect()->back()->with('success', 'Rate berhasil ditambahkan.');
    }

    public function export()
    {
        $vendors = Vendor::orderBy('vendor_name')->get();
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="vendors_' . date('Ymd_His') . '.csv"',
        ];
        $callback = function () use ($vendors) {
            $f = fopen('php://output', 'w');
            fputs($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Vendor Name', 'Type', 'PIC Name', 'Position', 'Phone', 'Email', 'Coverage Area', 'Status', 'Preferred', 'Rating', 'Payment Term']);
            foreach ($vendors as $v) {
                fputcsv($f, [
                    $v->vendor_name, $v->vendor_type, $v->pic_name, $v->pic_position,
                    $v->phone, $v->email, $v->coverage_area, $v->status,
                    $v->is_preferred ? 'Yes' : 'No', $v->rating, $v->payment_term,
                ]);
            }
            fclose($f);
        };
        return response()->stream($callback, 200, $headers);
    }
}

