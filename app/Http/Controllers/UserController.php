<?php

namespace App\Http\Controllers;

use App\Models\SalesUser;
use App\Models\Lead;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $role   = $request->get('role');
        $status = $request->get('status');

        $query = SalesUser::withCount([
            'leads',
            'leads as deals_won' => fn($q) => $q->where('pipeline_stage', 'Won'),
        ]);

        if ($search) $query->where(fn($q) => $q->where('name', 'like', "%$search%")->orWhere('email', 'like', "%$search%"));
        if ($role)   $query->where('role', $role);
        if ($status) $query->where('status', $status);

        $users = $query->orderBy('name')->paginate(15)->withQueryString();

        // Hitung achieved revenue per user (eager — satu query)
        $userIds   = $users->pluck('id');
        $revenues  = Lead::whereIn('sales_user_id', $userIds)->where('pipeline_stage', 'Won')
            ->selectRaw('sales_user_id, SUM(potensi_revenue) as total')
            ->groupBy('sales_user_id')->pluck('total', 'sales_user_id');

        $totalUsers   = SalesUser::count();
        $activeUsers  = SalesUser::where('status', 'Active')->count();
        $totalSales   = SalesUser::where('role', 'Sales Executive')->count();
        $totalManager = SalesUser::where('role', 'Sales Manager')->count();
        $roles        = SalesUser::distinct()->whereNotNull('role')->pluck('role')->sort()->values();

        return view('users.index', compact(
            'users', 'revenues', 'totalUsers', 'activeUsers', 'totalSales', 'totalManager',
            'roles', 'search', 'role', 'status'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'nullable|email|unique:sales_users,email',
            'phone'    => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'role'     => 'required|string|max:100',
            'status'   => 'required|in:Active,Non-Active',
            'target'   => 'nullable|numeric|min:0',
        ]);
        SalesUser::create($validated);
        return redirect()->route('users.index')->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, SalesUser $user)
    {
        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => 'nullable|email|unique:sales_users,email,' . $user->id,
            'phone'    => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'role'     => 'sometimes|string|max:100',
            'status'   => 'sometimes|in:Active,Non-Active',
            'target'   => 'nullable|numeric|min:0',
        ]);
        $user->update($validated);
        return redirect()->back()->with('success', 'User diupdate.');
    }

    public function destroy(SalesUser $user)
    {
        $user->delete();
        return redirect()->route('users.index')->with('success', 'User dihapus.');
    }
}

