<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Lead;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryOrderController extends Controller
{
    public function index(Request $request)
    {
        $search    = $request->get('search');
        $status    = $request->get('status');
        $serviceType = $request->get('service_type');
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->get('end_date',   now()->endOfMonth()->format('Y-m-d'));

        $query = DeliveryOrder::with(['customer', 'vendor', 'lead'])
            ->whereBetween('order_date', [$startDate, $endDate]);

        if ($status && $status !== 'all') $query->where('status', $status);
        if ($serviceType && $serviceType !== 'all') $query->where('service_type', $serviceType);
        if ($search) {
            $query->where(fn($q) => $q
                ->where('do_number', 'like', "%$search%")
                ->orWhereHas('customer', fn($q) => $q->where('company_name', 'like', "%$search%"))
                ->orWhere('route', 'like', "%$search%")
            );
        }

        $dos = $query->orderByDesc('order_date')->orderByDesc('id')->paginate(15)->withQueryString();

        // KPI
        $kpiQuery    = DeliveryOrder::whereBetween('order_date', [$startDate, $endDate]);
        $totalDo     = (clone $kpiQuery)->count();
        $doneDo      = (clone $kpiQuery)->where('status', 'Done')->count();
        $revenue     = (clone $kpiQuery)->where('status', 'Done')->where('currency', 'IDR')->sum('amount');
        $vendorCost  = (clone $kpiQuery)->where('status', 'Done')->where('currency', 'IDR')->sum('cost');
        $totalCost   = (clone $kpiQuery)->where('status', 'Done')->where('currency', 'IDR')->selectRaw('SUM(cost + other_cost) as total')->value('total') ?? 0;
        $grossProfit = $revenue - $vendorCost;
        $nettProfit  = $revenue - $totalCost;

        $customers    = Customer::orderBy('company_name')->get(['id', 'company_name']);
        $vendors      = Vendor::where('status', 'Active')->orderBy('vendor_name')->get(['id', 'vendor_name', 'vendor_type']);
        $leads        = Lead::whereIn('pipeline_stage', ['Closing', 'Won', 'Maintaining'])->orderBy('company_name')->get(['id', 'company_name', 'lead_code']);
        $serviceTypes = DeliveryOrder::distinct()->pluck('service_type')->filter()->sort()->values();

        return view('delivery_orders.index', compact(
            'dos', 'totalDo', 'doneDo', 'revenue', 'grossProfit', 'nettProfit',
            'customers', 'vendors', 'leads', 'serviceTypes',
            'search', 'status', 'serviceType', 'startDate', 'endDate'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'  => 'nullable|exists:customers,id',
            'vendor_id'    => 'nullable|exists:vendors,id',
            'lead_id'      => 'nullable|exists:leads,id',
            'service_type' => 'required|string|max:100',
            'route'        => 'required|string|max:255',
            'amount'       => 'required|numeric|min:0',
            'cost'         => 'nullable|numeric|min:0',
            'other_cost'   => 'nullable|numeric|min:0',
            'currency'     => 'required|in:IDR,USD,SGD',
            'status'       => 'required|in:Done,In Progress,Cancelled',
            'order_date'   => 'required|date',
        ]);

        $validated['do_number']  = $this->generateDoNumber();
        $validated['cost']       = $validated['cost']       ?? 0;
        $validated['other_cost'] = $validated['other_cost'] ?? 0;

        DeliveryOrder::create($validated);

        return redirect()->route('delivery-orders.index')
            ->with('success', 'DO ' . $validated['do_number'] . ' berhasil ditambahkan.');
    }

    public function update(Request $request, DeliveryOrder $deliveryOrder)
    {
        $validated = $request->validate([
            'customer_id'  => 'nullable|exists:customers,id',
            'vendor_id'    => 'nullable|exists:vendors,id',
            'lead_id'      => 'nullable|exists:leads,id',
            'service_type' => 'required|string|max:100',
            'route'        => 'required|string|max:255',
            'amount'       => 'required|numeric|min:0',
            'cost'         => 'nullable|numeric|min:0',
            'other_cost'   => 'nullable|numeric|min:0',
            'currency'     => 'required|in:IDR,USD,SGD',
            'status'       => 'required|in:Done,In Progress,Cancelled',
            'order_date'   => 'required|date',
        ]);

        $validated['cost']       = $validated['cost']       ?? 0;
        $validated['other_cost'] = $validated['other_cost'] ?? 0;

        $deliveryOrder->update($validated);

        return redirect()->route('delivery-orders.index')
            ->with('success', 'DO ' . $deliveryOrder->do_number . ' berhasil diperbarui.');
    }

    public function destroy(DeliveryOrder $deliveryOrder)
    {
        $doNumber = $deliveryOrder->do_number;
        $deliveryOrder->delete();

        return redirect()->route('delivery-orders.index')
            ->with('success', 'DO ' . $doNumber . ' berhasil dihapus.');
    }

    public function export(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->get('end_date',   now()->endOfMonth()->format('Y-m-d'));

        $dos = DeliveryOrder::with(['customer', 'vendor'])
            ->whereBetween('order_date', [$startDate, $endDate])
            ->orderByDesc('order_date')->get();

        $filename = 'delivery-orders-' . $startDate . '-sd-' . $endDate . '.csv';
        $headers  = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"$filename\""];

        $callback = function () use ($dos) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['No. DO', 'Customer', 'Vendor', 'Service Type', 'Route', 'Revenue', 'Cost Vendor', 'Other Cost', 'Gross Profit', 'Nett Profit', 'Currency', 'Status', 'Tgl Order']);
            foreach ($dos as $do) {
                fputcsv($f, [
                    $do->do_number,
                    $do->customer?->company_name ?? '-',
                    $do->vendor?->vendor_name ?? '-',
                    $do->service_type,
                    $do->route,
                    $do->amount,
                    $do->cost,
                    $do->other_cost,
                    $do->gross_profit,
                    $do->nett_profit,
                    $do->currency,
                    $do->status,
                    $do->order_date?->format('Y-m-d'),
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function generateDoNumber(): string
    {
        $prefix = 'DO-' . date('Ym') . '-';
        $last   = DeliveryOrder::where('do_number', 'like', $prefix . '%')
            ->orderByDesc('do_number')->value('do_number');
        $seq    = $last ? (intval(substr($last, -4)) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
