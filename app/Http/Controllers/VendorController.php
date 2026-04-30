<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $type   = $request->get('vendor_type');
        $status = $request->get('status');
        $search = $request->get('search');

        $query = Vendor::with(['deliveryOrders', 'rates']);
        if ($type && $type !== 'all')     $query->where('vendor_type', $type);
        if ($status && $status !== 'all') $query->where('status', $status);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('vendor_name', 'like', "%$search%")
                  ->orWhere('pic_name', 'like', "%$search%");
            });
        }

        $vendors         = $query->orderBy('is_preferred', 'desc')->orderBy('rating', 'desc')->paginate(10);
        $totalVendor     = Vendor::count();
        $activeVendor    = Vendor::where('status', 'Active')->count();
        $nonActiveVendor = Vendor::where('status', 'Non-Active')->count();
        $preferredVendor = Vendor::where('is_preferred', true)->count();

        $selectedVendor = $request->get('selected_id')
            ? Vendor::with(['deliveryOrders', 'rates'])->find($request->get('selected_id'))
            : Vendor::with(['deliveryOrders', 'rates'])->first();

        return view('vendors.index', compact(
            'vendors', 'totalVendor', 'activeVendor', 'nonActiveVendor',
            'preferredVendor', 'selectedVendor', 'type', 'status', 'search'
        ));
    }
}
