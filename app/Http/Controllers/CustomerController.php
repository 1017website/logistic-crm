<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Models\Activity;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $status   = $request->get('status');
        $industry = $request->get('industry');
        $search   = $request->get('search');
        $salesId  = $request->get('user_id');

        $query = Customer::with(['salesUser', 'deliveryOrders', 'activities']);
        if ($status && $status !== 'all')     $query->where('status', $status);
        if ($industry && $industry !== 'all') $query->where('industry', $industry);
        if ($salesId)  $query->where('user_id', $salesId);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%$search%")
                    ->orWhere('pic_name', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
            });
        }

        $customers         = $query->orderBy('company_name')->paginate(10)->withQueryString();
        $totalCustomer     = Customer::count();
        $potentialCustomer = Customer::where('status', 'Potential')->count();
        $existingCustomer  = Customer::where('status', 'Existing')->count();
        $industries        = Customer::whereNotNull('industry')->distinct()->pluck('industry')->filter()->sort()->values();
        $salesUsers        = User::orderBy('name')->get();

        $selectedCustomer = $request->get('selected_id')
            ? Customer::with(['salesUser', 'deliveryOrders', 'activities.salesUser', 'leads'])->find($request->get('selected_id'))
            : null;

        return view('customers.index', compact(
            'customers',
            'totalCustomer',
            'potentialCustomer',
            'existingCustomer',
            'industries',
            'salesUsers',
            'selectedCustomer',
            'status',
            'industry',
            'search',
            'salesId'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name'   => 'required|string|max:255',
            'pic_name'       => 'required|string|max:255',
            'pic_position'   => 'nullable|string|max:100',
            'phone'          => 'required|string|max:20',
            'email'          => 'nullable|email|max:255',
            'industry'       => 'nullable|string|max:100',
            'location'       => 'nullable|string|max:255',
            'address'        => 'nullable|string',
            'status'         => 'required|in:Existing,Potential',
            'user_id'  => 'required|exists:users,id',
            'customer_since' => 'nullable|date',
            'notes'          => 'nullable|string',
        ]);
        if (auth()->user()->isSalesExecutive()) {
            $validated['user_id'] = auth()->id();
        }
        Customer::create($validated);
        return redirect()->route('customers.index')->with('success', 'Customer berhasil ditambahkan.');
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'company_name'   => 'sometimes|string|max:255',
            'pic_name'       => 'sometimes|string|max:255',
            'pic_position'   => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:255',
            'industry'       => 'nullable|string|max:100',
            'location'       => 'nullable|string|max:255',
            'address'        => 'nullable|string',
            'status'         => 'sometimes|in:Existing,Potential',
            'user_id'  => 'sometimes|exists:users,id',
            'customer_since' => 'nullable|date',
            'notes'          => 'nullable|string',
        ]);
        $customer->update($validated);
        return redirect()->back()->with('success', 'Data customer diupdate.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return redirect()->route('customers.index')->with('success', 'Customer dihapus.');
    }

    public function export()
    {
        $customers = Customer::with(['salesUser'])->orderBy('company_name')->get();
        $headers   = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customers_' . date('Ymd_His') . '.csv"',
        ];
        $callback = function () use ($customers) {
            $f = fopen('php://output', 'w');
            fputs($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Company Name', 'PIC Name', 'Position', 'Phone', 'Email', 'Industry', 'Location', 'Status', 'Sales PIC', 'Customer Since']);
            foreach ($customers as $c) {
                fputcsv($f, [
                    $c->company_name,
                    $c->pic_name,
                    $c->pic_position,
                    $c->phone,
                    $c->email,
                    $c->industry,
                    $c->location,
                    $c->status,
                    $c->salesUser?->name,
                    $c->customer_since?->format('Y-m-d'),
                ]);
            }
            fclose($f);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);
        $handle   = fopen($request->file('file')->getRealPath(), 'r');
        $header   = fgetcsv($handle);
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3 || empty(trim($row[0]))) continue;
            $salesUser = User::where('name', trim($row[8] ?? ''))->first();
            Customer::create([
                'company_name'   => trim($row[0]),
                'pic_name'       => trim($row[1]),
                'pic_position'   => trim($row[2] ?? ''),
                'phone'          => trim($row[3] ?? ''),
                'email'          => trim($row[4] ?? ''),
                'industry'       => trim($row[5] ?? ''),
                'location'       => trim($row[6] ?? ''),
                'status'         => in_array(trim($row[7] ?? ''), ['Existing', 'Potential']) ? trim($row[7]) : 'Potential',
                'user_id'  => $salesUser?->id,
                'customer_since' => !empty($row[9]) ? $row[9] : null,
            ]);
            $imported++;
        }
        fclose($handle);
        return redirect()->route('customers.index')->with('success', "Berhasil import {$imported} customer.");
    }

    // AJAX: Add activity ke customer
    public function storeActivity(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'type'          => 'required|in:Call,Visit,Email,Note,Task',
            'subject'       => 'required|string|max:255',
            'description'   => 'nullable|string',
            'activity_at'   => 'required|date',
            'status'        => 'required|in:Planned,Pending,Done,Overdue',
            'user_id' => 'required|exists:users,id',
        ]);
        $validated['customer_id'] = $customer->id;
        if (auth()->user()->isSalesExecutive()) {
            $validated['user_id'] = auth()->id();
        }
        Activity::create($validated);
        return redirect()->back()->with('success', 'Activity ditambahkan.');
    }
}
