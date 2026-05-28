<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Vendor;
use App\Models\VendorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryOrderController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $query = DeliveryOrder::with(['customer', 'vendor', 'lead', 'items', 'salesUser'])
            ->whereBetween('order_date', [$startDate, $endDate]);
        if ($status && $status !== 'all')
            $query->where('status', $status);
        if ($search) {
            $query->where(
                fn($q) => $q
                    ->where('do_number', 'like', "%$search%")
                    ->orWhere('tracking_number', 'like', "%$search%")
                    ->orWhereHas('customer', fn($q) => $q->where('company_name', 'like', "%$search%"))
                    ->orWhereHas('items', fn($q) => $q->where('service_name.*like', "%$search%"))
            );
        }

        $dos = $query->orderByDesc('order_date')->orderByDesc('id')->paginate(15)->withQueryString();

        // KPI — hitung dari items
        $allDone = DeliveryOrder::with('items')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'Done')->where('currency', 'IDR')->get();

        $revenue = $allDone->sum(fn($so) => $so->total_revenue);
        $totalCost = $allDone->sum(fn($so) => $so->total_cost);
        $grossProfit = $revenue - $totalCost;
        $volumeDo = $allDone->count();

        $customers = Customer::orderBy('company_name')->get(['id', 'company_name']);
        $vendors = Vendor::where('status', 'Active')->orderBy('vendor_name')->get(['id', 'vendor_name', 'vendor_type', 'service_type']);
        $leads = Lead::where(function ($q) {
            $q->whereIn('pipeline_stage', ['Closing', 'Won', 'Maintaining'])
                ->orWhereNotNull('customer_id');
        })->orderBy('company_name')->get(['id', 'company_name', 'lead_code', 'customer_id']);

        // Vendor services untuk dropdown SO items
        $vendorServices = VendorService::with('vendor')
            ->orderBy('service_name')->get(['id', 'vendor_id', 'service_name', 'unit', 'tariff', 'tariff_unit']);

        return view('delivery_orders.index', compact(
            'dos',
            'revenue',
            'grossProfit',
            'volumeDo',
            'totalCost',
            'customers',
            'vendors',
            'leads',
            'vendorServices',
            'search',
            'status',
            'startDate',
            'endDate'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'lead_id' => 'nullable|exists:leads,id',
            'currency' => 'required|in:IDR,USD,SGD',
            'status' => 'required|in:Done,In Progress,Cancelled',
            'order_date' => 'required|date',
            'delivery_type' => 'nullable|string|max:100',
            'origin' => 'nullable|string|max:255',
            'destination' => 'nullable|string|max:255',
            'tracking_number' => 'nullable|string|max:100',
            'estimated_arrival' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.service_name' => 'required|string|max:255',
            'items.*.unit' => 'required|string|max:50',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.buy_price' => 'required|numeric|min:0',
            'items.*.sell_price' => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $userId = null;
            if ($request->lead_id) {
                $userId = Lead::find($request->lead_id)?->user_id;
            }
            $userId = $userId ?? auth()->id();

            $so = DeliveryOrder::create([
                'do_number' => DeliveryOrder::generateSoNumber(),
                'customer_id' => $request->customer_id,
                'vendor_id' => $request->vendor_id,
                'lead_id' => $request->lead_id,
                'user_id' => $userId,
                'currency' => $request->currency,
                'status' => $request->status,
                'order_date' => $request->order_date,
                'delivery_type' => $request->delivery_type,
                'origin' => $request->origin,
                'destination' => $request->destination,
                'tracking_number' => $request->tracking_number,
                'estimated_arrival' => $request->estimated_arrival,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $so->items()->create([
                    'service_name' => $item['service_name'],
                    'unit' => $item['unit'],
                    'qty' => $item['qty'],
                    'buy_price' => $item['buy_price'],
                    'sell_price' => $item['sell_price'],
                    'description' => $item['description'] ?? null,
                ]);
            }
        });

        return redirect()->route('delivery-orders.index')->with('success', 'Delivery Order berhasil ditambahkan.');
    }

    public function edit(DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->load(['items', 'customer', 'vendor', 'lead']);
        $data = $deliveryOrder->toArray();
        $data['order_date'] = $deliveryOrder->order_date?->format('Y-m-d');
        $data['estimated_arrival'] = $deliveryOrder->estimated_arrival?->format('Y-m-d');
        return response()->json($data);
    }

    public function update(Request $request, DeliveryOrder $deliveryOrder)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'lead_id' => 'nullable|exists:leads,id',
            'currency' => 'required|in:IDR,USD,SGD',
            'status' => 'required|in:Done,In Progress,Cancelled',
            'order_date' => 'required|date',
            'delivery_type' => 'nullable|string|max:100',
            'origin' => 'nullable|string|max:255',
            'destination' => 'nullable|string|max:255',
            'tracking_number' => 'nullable|string|max:100',
            'estimated_arrival' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.service_name' => 'required|string|max:255',
            'items.*.unit' => 'required|string|max:50',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.buy_price' => 'required|numeric|min:0',
            'items.*.sell_price' => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $deliveryOrder) {
            $deliveryOrder->update([
                'customer_id' => $request->customer_id,
                'vendor_id' => $request->vendor_id,
                'lead_id' => $request->lead_id,
                'currency' => $request->currency,
                'status' => $request->status,
                'order_date' => $request->order_date,
                'delivery_type' => $request->delivery_type,
                'origin' => $request->origin,
                'destination' => $request->destination,
                'tracking_number' => $request->tracking_number,
                'estimated_arrival' => $request->estimated_arrival,
                'notes' => $request->notes,
            ]);

            $deliveryOrder->items()->delete();
            foreach ($request->items as $item) {
                $deliveryOrder->items()->create([
                    'service_name' => $item['service_name'],
                    'unit' => $item['unit'],
                    'qty' => $item['qty'],
                    'buy_price' => $item['buy_price'],
                    'sell_price' => $item['sell_price'],
                    'description' => $item['description'] ?? null,
                ]);
            }
        });

        return redirect()->route('delivery-orders.index')->with('success', 'Delivery Order berhasil diperbarui.');
    }

    public function destroy(DeliveryOrder $deliveryOrder)
    {
        $soNumber = $deliveryOrder->do_number;
        $deliveryOrder->delete();
        return redirect()->route('delivery-orders.index')->with('success', 'SO ' . $soNumber . ' berhasil dihapus.');
    }

    public function export(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $sos = DeliveryOrder::with(['customer', 'vendor', 'items'])
            ->whereBetween('order_date', [$startDate, $endDate])
            ->orderByDesc('order_date')->get();

        $headers = ['DO Number', 'Customer', 'Vendor', 'Delivery Type', 'Origin', 'Destination', 'Tracking', 'Service', 'Unit', 'Qty', 'Buy Price', 'Sell Price', 'Subtotal Revenue', 'Subtotal HPP', 'Gross Profit', 'Currency', 'Status', 'Tgl Order', 'ETA'];

        $rows = [];
        foreach ($sos as $so) {
            foreach ($so->items as $item) {
                $rows[] = [
                    $so->do_number,
                    $so->customer?->company_name ?? '-',
                    $so->vendor?->vendor_name ?? '-',
                    $so->delivery_type,
                    $so->origin,
                    $so->destination,
                    $so->tracking_number,
                    $item->service_name,
                    $item->unit,
                    (float) $item->qty,
                    (float) $item->buy_price,
                    (float) $item->sell_price,
                    (float) $item->subtotal_revenue,
                    (float) $item->subtotal_cost,
                    (float) $item->gross_profit,
                    $so->currency,
                    $so->status,
                    $so->order_date?->format('Y-m-d'),
                    $so->estimated_arrival?->format('Y-m-d'),
                ];
            }
        }

        return \App\Helpers\ExcelExport::download(
            'delivery-orders-' . $startDate . '-sd-' . $endDate,
            $headers,
            $rows,
            'Delivery Orders'
        );
    }
}