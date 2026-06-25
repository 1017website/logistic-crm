<?php

namespace App\Http\Controllers;

use App\Models\Pekerjaan;
use Illuminate\Http\Request;

class PekerjaanController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $query = Pekerjaan::query();
        if ($search) {
            $query->where('name', 'like', "%$search%")->orWhere('code', 'like', "%$search%");
        }
        $pekerjaan = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('pekerjaan.index', compact('pekerjaan', 'search'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'code'      => 'nullable|string|max:20',
            'type'      => 'nullable|in:TR,NTR',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        Pekerjaan::create($data);

        return back()->with('success', 'Pekerjaan ditambahkan.');
    }

    public function update(Request $request, Pekerjaan $pekerjaan)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'code'      => 'nullable|string|max:20',
            'type'      => 'nullable|in:TR,NTR',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $pekerjaan->update($data);

        return back()->with('success', 'Pekerjaan diperbarui.');
    }

    public function destroy(Pekerjaan $pekerjaan)
    {
        $pekerjaan->delete();
        return back()->with('success', 'Pekerjaan dihapus.');
    }
}
