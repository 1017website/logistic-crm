<?php

namespace App\Http\Controllers;

use App\Models\SalesUser;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $role   = $request->get('role');

        $query = SalesUser::query();
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }
        if ($role) $query->where('role', $role);

        $users      = $query->orderBy('name')->paginate(10);
        $totalUsers = SalesUser::count();
        $roles      = SalesUser::distinct()->pluck('role');

        return view('users.index', compact('users', 'totalUsers', 'roles', 'search', 'role'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:sales_users,email',
            'phone'    => 'nullable|string|max:20',
            'role'     => 'required|string|max:100',
            'status'   => 'required|in:Active,Non-Active',
            'target'   => 'nullable|numeric',
        ]);

        SalesUser::create($validated);
        return redirect()->route('users.index')->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, SalesUser $user)
    {
        $user->update($request->validate([
            'name'   => 'sometimes|string|max:255',
            'role'   => 'sometimes|string|max:100',
            'status' => 'sometimes|in:Active,Non-Active',
            'target' => 'nullable|numeric',
        ]));
        return redirect()->back()->with('success', 'User diupdate.');
    }

    public function destroy(SalesUser $user)
    {
        $user->delete();
        return redirect()->route('users.index')->with('success', 'User dihapus.');
    }
}
