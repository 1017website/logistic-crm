<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $status   = $request->get('status');
        $industry = $request->get('industry');
        $search   = $request->get('search');

        $query = Customer::with(['salesUser', 'deliveryOrders', 'activities']);
        if ($status && $status !== 'all')   $query->where('status', $status);
        if ($industry && $industry !== 'all') $query->where('industry', $industry);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%$search%")
                  ->orWhere('pic_name', 'like', "%$search%");
            });
        }

        $customers         = $query->orderBy('company_name')->paginate(10);
        $totalCustomer     = Customer::count();
        $potentialCustomer = Customer::where('status', 'Potential')->count();
        $existingCustomer  = Customer::where('status', 'Existing')->count();
        $industries        = Customer::distinct()->pluck('industry');

        $selectedCustomer = $request->get('selected_id')
            ? Customer::with(['salesUser', 'deliveryOrders', 'activities', 'leads'])->find($request->get('selected_id'))
            : Customer::with(['salesUser', 'deliveryOrders', 'activities', 'leads'])->first();

        return view('customers.index', compact(
            'customers', 'totalCustomer', 'potentialCustomer', 'existingCustomer',
            'industries', 'selectedCustomer', 'status', 'industry', 'search'
        ));
    }

    public function show(Customer $customer)
    {
        $customer->load(['salesUser', 'deliveryOrders', 'activities.salesUser', 'leads', 'quotations']);
        return view('customers.show', compact('customer'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name'  => 'required|string|max:255',
            'pic_name'      => 'required|string|max:255',
            'phone'         => 'required|string',
            'email'         => 'nullable|email',
            'industry'      => 'nullable|string',
            'location'      => 'nullable|string',
            'status'        => 'required|in:Existing,Potential',
            'sales_user_id' => 'required|exists:sales_users,id',
        ]);
        Customer::create($validated);
        return redirect()->route('customers.index')->with('success', 'Customer berhasil ditambahkan.');
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($request->validate([
            'company_name'  => 'sometimes|string|max:255',
            'pic_name'      => 'sometimes|string|max:255',
            'phone'         => 'sometimes|string',
            'status'        => 'sometimes|in:Existing,Potential',
        ]));
        return redirect()->back()->with('success', 'Data customer diupdate.');
    }
}
