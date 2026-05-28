<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Customer;
use App\Models\User;
use App\Models\DeliveryOrder;
use App\Models\Activity;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $startDate  = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate    = $request->get('end_date', now()->endOfMonth()->toDateString());
        $reportType = $request->get('report_type', 'sales');
        $salesId    = $request->get('user_id');
        $status     = $request->get('status');
        $search     = $request->get('search');

        // ── Summary KPI dari PO items ──
        $doneDOs     = DeliveryOrder::with('items')->whereBetween('order_date', [$startDate, $endDate])->where('currency', 'IDR')->where('status', 'Done')->get();
        $revenue     = $doneDOs->sum(fn($po) => $po->total_revenue);
        $totalCost   = $doneDOs->sum(fn($po) => $po->total_cost);
        $grossProfit = $revenue - $totalCost;
        $nettProfit  = $grossProfit;

        $totalDeals     = Lead::where('pipeline_stage', 'Won')->whereBetween('updated_at', [$startDate, $endDate])->count();
        $avgDealValue   = $totalDeals > 0 ? $revenue / $totalDeals : 0;
        $totalLeadsP    = Lead::whereBetween('created_at', [$startDate, $endDate])->count();
        $conversionRate = $totalLeadsP > 0 ? round(($totalDeals / $totalLeadsP) * 100, 1) : 0;
        $winRate        = Lead::count() > 0 ? round((Lead::where('pipeline_stage', 'Won')->count() / Lead::count()) * 100, 1) : 0;

        $reportData = null;

        switch ($reportType) {
            case 'sales':
                $q = Lead::with(['salesUser'])->whereBetween('created_at', [$startDate, $endDate]);
                if ($salesId) $q->where('user_id', $salesId);
                if ($status)  $q->where('pipeline_stage', $status);
                if ($search)  $q->where('company_name', 'like', "%$search%");
                $reportData = $q->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
                break;

            case 'customer':
                $q = Customer::with(['salesUser'])->whereBetween('created_at', [$startDate, $endDate]);
                if ($salesId) $q->where('user_id', $salesId);
                if ($search)  $q->where('company_name', 'like', "%$search%");
                $reportData = $q->orderBy('company_name')->paginate(15)->withQueryString();
                break;

            case 'pipeline':
                $q = Lead::with(['salesUser'])->whereNotIn('pipeline_stage', ['Won', 'Lost']);
                if ($salesId) $q->where('user_id', $salesId);
                if ($status)  $q->where('pipeline_stage', $status);
                if ($search)  $q->where('company_name', 'like', "%$search%");
                $reportData = $q->orderBy('updated_at', 'desc')->paginate(15)->withQueryString();
                break;

            case 'performance':
                $reportData = User::orderBy('name')->get()->map(function ($u) use ($startDate, $endDate) {
                    $total   = Lead::where('user_id', $u->id)->count();
                    $won     = Lead::where('user_id', $u->id)->where('pipeline_stage', 'Won')->whereBetween('updated_at', [$startDate, $endDate])->count();
                    $revenue = Lead::where('user_id', $u->id)->where('pipeline_stage', 'Won')->sum('potensi_revenue');
                    return [
                        'sales'      => $u,
                        'total'      => $total,
                        'won'        => $won,
                        'revenue'    => $revenue,
                        'conversion' => $total > 0 ? round(($won / $total) * 100, 1) : 0,
                    ];
                })->sortByDesc('revenue');
                break;

            case 'po':
                $q = DeliveryOrder::with(['customer', 'vendor', 'items'])->whereBetween('order_date', [$startDate, $endDate]);
                if ($status) $q->where('status', $status);
                if ($search) $q->where('do_number', 'like', "%$search%")->orWhereHas('customer', fn($c) => $c->where('company_name', 'like', "%$search%"));
                $reportData = $q->orderBy('order_date', 'desc')->paginate(15)->withQueryString();
                break;
        }

        $salesUsers = User::orderBy('name')->get();

        return view('reports.index', compact(
            'reportData', 'revenue', 'grossProfit', 'nettProfit', 'totalDeals',
            'avgDealValue', 'conversionRate', 'winRate',
            'salesUsers', 'startDate', 'endDate', 'reportType', 'salesId', 'status', 'search'
        ));
    }

    public function export(Request $request)
    {
        $startDate  = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate    = $request->get('end_date', now()->endOfMonth()->toDateString());
        $reportType = $request->get('report_type', 'sales');
        $salesId    = $request->get('user_id');

        $filename = 'report_' . $reportType . '_' . date('Ymd_His');

        switch ($reportType) {
            case 'sales':
                $headers = ['Lead Code', 'Company', 'PIC', 'Product Interest', 'Stage', 'Temperature', 'Potensi Revenue', 'Probability', 'Expected Closing', 'Sales PIC', 'Created At'];
                $leads   = Lead::with(['salesUser'])->whereBetween('created_at', [$startDate, $endDate]);
                if ($salesId) $leads->where('user_id', $salesId);
                $rows = $leads->get()->map(fn($l) => [
                    $l->lead_code, $l->company_name, $l->pic_name, $l->product_interest,
                    $l->pipeline_stage, $l->temperature, (float)$l->potensi_revenue, $l->probability,
                    $l->expected_closing?->format('Y-m-d'), $l->salesUser?->name, $l->created_at->format('Y-m-d H:i'),
                ])->toArray();
                return \App\Helpers\ExcelExport::download($filename, $headers, $rows, 'Sales Report');

            case 'po':
                $headers = ['DO Number', 'Customer', 'Vendor', 'Product', 'Unit', 'Qty', 'Buy Price', 'Sell Price', 'Gross Profit', 'Currency', 'Status', 'Order Date'];
                $pos     = DeliveryOrder::with(['customer', 'vendor', 'items'])->whereBetween('order_date', [$startDate, $endDate])->get();
                $rows    = [];
                foreach ($pos as $po) {
                    foreach ($po->items as $item) {
                        $rows[] = [
                            $po->do_number, $po->customer?->company_name, $po->vendor?->vendor_name,
                            $item->product_name, $item->unit, (float)$item->qty,
                            (float)$item->buy_price, (float)$item->sell_price, (float)$item->gross_profit,
                            $po->currency, $po->status, $po->order_date?->format('Y-m-d'),
                        ];
                    }
                }
                return \App\Helpers\ExcelExport::download($filename, $headers, $rows, 'PO Report');

            case 'customer':
                $headers = ['Company Name', 'PIC', 'Phone', 'Email', 'Industry', 'Status', 'Sales PIC', 'Customer Since'];
                $rows    = Customer::with('salesUser')->get()->map(fn($c) => [
                    $c->company_name, $c->pic_name, $c->phone, $c->email,
                    $c->industry, $c->status, $c->salesUser?->name, $c->customer_since?->format('Y-m-d'),
                ])->toArray();
                return \App\Helpers\ExcelExport::download($filename, $headers, $rows, 'Customer Report');

            default:
                return back();
        }
    }
}
