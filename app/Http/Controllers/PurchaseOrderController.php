<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $search    = $request->get('search');
        $status    = $request->get('status');
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->get('end_date',   now()->endOfMonth()->format('Y-m-d'));

        $query = PurchaseOrder::with(['customer', 'supplier', 'lead', 'items', 'salesUser'])
            ->whereBetween('order_date', [$startDate, $endDate]);
        if ($status && $status !== 'all') $query->where('status', $status);
        if ($search) {
            $query->where(fn($q) => $q
                ->where('po_number', 'like', "%$search%")
                ->orWhereHas('customer', fn($q) => $q->where('company_name', 'like', "%$search%"))
                ->orWhereHas('items',    fn($q) => $q->where('product_name',  'like', "%$search%"))
            );
        }

        $pos = $query->orderByDesc('order_date')->orderByDesc('id')->paginate(15)->withQueryString();

        // KPI — hitung dari items
        $allDone = PurchaseOrder::with('items')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'Done')->where('currency', 'IDR')->get();

        $revenue     = $allDone->sum(fn($po) => $po->total_revenue);
        $totalCost   = $allDone->sum(fn($po) => $po->total_cost);
        $grossProfit = $revenue - $totalCost;
        $volumePo    = $allDone->count();

        $customers = Customer::orderBy('company_name')->get(['id', 'company_name']);
        $suppliers = Supplier::where('status', 'Active')->orderBy('supplier_name')->get(['id', 'supplier_name', 'source_type']);
        $leads = Lead::where(function($q) {
                // Semua stage closing/won/maintaining ATAU lead yang sudah linked ke customer
                $q->whereIn('pipeline_stage', ['Closing', 'Won', 'Maintaining'])
                  ->orWhereNotNull('customer_id');
            })->orderBy('company_name')->get(['id', 'company_name', 'lead_code', 'customer_id']);

        // Ambil semua products per supplier untuk dropdown PO items
        $supplierProducts = \App\Models\SupplierProduct::with('supplier')
            ->orderBy('product_name')->get(['id', 'supplier_id', 'product_name', 'unit']);

        return view('purchase_orders.index', compact(
            'pos', 'revenue', 'grossProfit', 'volumePo', 'totalCost',
            'customers', 'suppliers', 'leads', 'supplierProducts',
            'search', 'status', 'startDate', 'endDate'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'            => 'nullable|exists:customers,id',
            'supplier_id'            => 'nullable|exists:suppliers,id',
            'lead_id'                => 'nullable|exists:leads,id',
            'currency'               => 'required|in:IDR,USD,SGD',
            'status'                 => 'required|in:Done,In Progress,Cancelled',
            'order_date'             => 'required|date',
            'notes'                  => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.product_name'   => 'required|string|max:255',
            'items.*.unit'           => 'required|string|max:50',
            'items.*.qty'            => 'required|numeric|min:0.001',
            'items.*.buy_price'      => 'required|numeric|min:0',
            'items.*.sell_price'     => 'required|numeric|min:0',
            'items.*.description'    => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            // Ambil user_id dari lead jika ada, fallback ke user login
            $userId = null;
            if ($request->lead_id) {
                $userId = \App\Models\Lead::find($request->lead_id)?->user_id;
            }
            $userId = $userId ?? auth()->id();

            $po = PurchaseOrder::create([
                'po_number'   => PurchaseOrder::generatePoNumber(),
                'customer_id' => $request->customer_id,
                'supplier_id' => $request->supplier_id,
                'lead_id'     => $request->lead_id,
                'user_id'     => $userId,
                'currency'    => $request->currency,
                'status'      => $request->status,
                'order_date'  => $request->order_date,
                'notes'       => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $po->items()->create([
                    'product_name' => $item['product_name'],
                    'unit'         => $item['unit'],
                    'qty'          => $item['qty'],
                    'buy_price'    => $item['buy_price'],
                    'sell_price'   => $item['sell_price'],
                    'description'  => $item['description'] ?? null,
                ]);
            }
        });

        return redirect()->route('purchase-orders.index')->with('success', 'Purchase Order berhasil ditambahkan.');
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['items', 'customer', 'supplier', 'lead']);
        $data = $purchaseOrder->toArray();
        // Format order_date agar bisa langsung dipakai di input[type=date]
        $data['order_date'] = $purchaseOrder->order_date?->format('Y-m-d');
        return response()->json($data);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->validate([
            'customer_id'            => 'nullable|exists:customers,id',
            'supplier_id'            => 'nullable|exists:suppliers,id',
            'lead_id'                => 'nullable|exists:leads,id',
            'currency'               => 'required|in:IDR,USD,SGD',
            'status'                 => 'required|in:Done,In Progress,Cancelled',
            'order_date'             => 'required|date',
            'notes'                  => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.product_name'   => 'required|string|max:255',
            'items.*.unit'           => 'required|string|max:50',
            'items.*.qty'            => 'required|numeric|min:0.001',
            'items.*.buy_price'      => 'required|numeric|min:0',
            'items.*.sell_price'     => 'required|numeric|min:0',
            'items.*.description'    => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $purchaseOrder) {
            $purchaseOrder->update([
                'customer_id' => $request->customer_id,
                'supplier_id' => $request->supplier_id,
                'lead_id'     => $request->lead_id,
                'currency'    => $request->currency,
                'status'      => $request->status,
                'order_date'  => $request->order_date,
                'notes'       => $request->notes,
            ]);

            // Hapus items lama, insert baru
            $purchaseOrder->items()->delete();
            foreach ($request->items as $item) {
                $purchaseOrder->items()->create([
                    'product_name' => $item['product_name'],
                    'unit'         => $item['unit'],
                    'qty'          => $item['qty'],
                    'buy_price'    => $item['buy_price'],
                    'sell_price'   => $item['sell_price'],
                    'description'  => $item['description'] ?? null,
                ]);
            }
        });

        return redirect()->route('purchase-orders.index')->with('success', 'Purchase Order berhasil diperbarui.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $poNumber = $purchaseOrder->po_number;
        $purchaseOrder->delete(); // cascades ke items
        return redirect()->route('purchase-orders.index')->with('success', 'PO ' . $poNumber . ' berhasil dihapus.');
    }

    public function export(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->get('end_date',   now()->endOfMonth()->format('Y-m-d'));

        $pos = PurchaseOrder::with(['customer', 'supplier', 'items'])
            ->whereBetween('order_date', [$startDate, $endDate])
            ->orderByDesc('order_date')->get();

        $headers = ['PO Number', 'Customer', 'Supplier', 'Product', 'Unit', 'Qty', 'Buy Price', 'Sell Price', 'Subtotal Revenue', 'Subtotal HPP', 'Gross Profit', 'Currency', 'Status', 'Tgl Order'];

        $rows = [];
        foreach ($pos as $po) {
            foreach ($po->items as $item) {
                $rows[] = [
                    $po->po_number,
                    $po->customer?->company_name ?? '-',
                    $po->supplier?->supplier_name ?? '-',
                    $item->product_name,
                    $item->unit,
                    (float) $item->qty,
                    (float) $item->buy_price,
                    (float) $item->sell_price,
                    (float) $item->subtotal_revenue,
                    (float) $item->subtotal_cost,
                    (float) $item->gross_profit,
                    $po->currency,
                    $po->status,
                    $po->order_date?->format('Y-m-d'),
                ];
            }
        }

        return \App\Helpers\ExcelExport::download(
            'purchase-orders-' . $startDate . '-sd-' . $endDate,
            $headers, $rows, 'Purchase Orders'
        );
    }
}
