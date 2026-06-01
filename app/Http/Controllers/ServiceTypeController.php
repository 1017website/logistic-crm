<?php

namespace App\Http\Controllers;

use App\Models\ServiceType;
use App\Models\Vendor;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $query = ServiceType::query();
        if ($search) {
            $query->where('name', 'like', "%$search%");
        }

        $serviceTypes = $query->orderBy('sort_order')->orderBy('name')->paginate(20)->withQueryString();

        // Hitung pemakaian di vendor (read-only, untuk info)
        $usage = Vendor::selectRaw('service_type, COUNT(*) as total')
            ->whereNotNull('service_type')
            ->groupBy('service_type')->pluck('total', 'service_type');

        $totalActive   = ServiceType::where('is_active', true)->count();
        $totalInactive = ServiceType::where('is_active', false)->count();

        return view('service_types.index', compact(
            'serviceTypes', 'usage', 'totalActive', 'totalInactive', 'search'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:150|unique:service_types,name',
            'is_active'  => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_active']  = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? (ServiceType::max('sort_order') + 1);

        ServiceType::create($validated);

        return redirect()->route('service-types.index')->with('success', 'Service Type berhasil ditambahkan.');
    }

    public function update(Request $request, ServiceType $serviceType)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:150|unique:service_types,name,' . $serviceType->id,
            'is_active'  => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $oldName = $serviceType->name;
        $validated['is_active'] = $request->boolean('is_active', $serviceType->is_active);

        $serviceType->update($validated);

        // Sinkronkan nama lama di vendor agar tidak yatim
        if ($oldName !== $serviceType->name) {
            Vendor::where('service_type', $oldName)->update(['service_type' => $serviceType->name]);
        }

        return redirect()->route('service-types.index')->with('success', 'Service Type berhasil diperbarui.');
    }

    public function destroy(ServiceType $serviceType)
    {
        $inUse = Vendor::where('service_type', $serviceType->name)->count();
        if ($inUse > 0) {
            return redirect()->route('service-types.index')
                ->withErrors(['delete' => "Tidak bisa dihapus: masih dipakai $inUse vendor. Nonaktifkan saja jika tidak ingin tampil di pilihan."]);
        }

        $serviceType->delete();

        return redirect()->route('service-types.index')->with('success', 'Service Type berhasil dihapus.');
    }
}
