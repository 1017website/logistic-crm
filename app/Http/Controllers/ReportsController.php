<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Customer;
use App\Models\SalesUser;
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
        $salesId    = $request->get('sales_user_id');
        $status     = $request->get('status');
        $search     = $request->get('search');

        // ── Summary KPI ──
        $revenue        = DeliveryOrder::whereBetween('order_date', [$startDate, $endDate])->where('currency', 'IDR')->sum('amount');
        $totalDeals     = Lead::where('pipeline_stage', 'Won')->whereBetween('updated_at', [$startDate, $endDate])->count();
        $avgDealValue   = $totalDeals > 0 ? $revenue / $totalDeals : 0;
        $totalLeadsP    = Lead::whereBetween('created_at', [$startDate, $endDate])->count();
        $conversionRate = $totalLeadsP > 0 ? round(($totalDeals / $totalLeadsP) * 100, 1) : 0;
        $winRate        = Lead::count() > 0 ? round((Lead::where('pipeline_stage', 'Won')->count() / Lead::count()) * 100, 1) : 0;

        // ── Report data berdasarkan type ──
        $reportData = null;
        $columns    = [];

        switch ($reportType) {
            case 'sales':
                $q = Lead::with(['salesUser'])->whereBetween('created_at', [$startDate, $endDate]);
                if ($salesId) $q->where('sales_user_id', $salesId);
                if ($status)  $q->where('pipeline_stage', $status);
                if ($search)  $q->where('company_name', 'like', "%$search%");
                $reportData = $q->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
                break;

            case 'customer':
                $q = Customer::with(['salesUser'])->whereBetween('created_at', [$startDate, $endDate]);
                if ($salesId) $q->where('sales_user_id', $salesId);
                if ($search)  $q->where('company_name', 'like', "%$search%");
                $reportData = $q->orderBy('company_name')->paginate(15)->withQueryString();
                break;

            case 'pipeline':
                $q = Lead::with(['salesUser'])->whereNotIn('pipeline_stage', ['Won', 'Lost']);
                if ($salesId) $q->where('sales_user_id', $salesId);
                if ($status)  $q->where('pipeline_stage', $status);
                if ($search)  $q->where('company_name', 'like', "%$search%");
                $reportData = $q->orderBy('updated_at', 'desc')->paginate(15)->withQueryString();
                break;

            case 'performance':
                $reportData = SalesUser::all()->map(function ($s) use ($startDate, $endDate) {
                    $total   = Lead::where('sales_user_id', $s->id)->count();
                    $won     = Lead::where('sales_user_id', $s->id)->where('pipeline_stage', 'Won')->whereBetween('updated_at', [$startDate, $endDate])->count();
                    $revenue = Lead::where('sales_user_id', $s->id)->where('pipeline_stage', 'Won')->sum('potensi_revenue');
                    return [
                        'sales'      => $s,
                        'total'      => $total,
                        'won'        => $won,
                        'revenue'    => $revenue,
                        'conversion' => $total > 0 ? round(($won / $total) * 100, 1) : 0,
                    ];
                })->sortByDesc('revenue');
                break;

            case 'do':
                $q = DeliveryOrder::with(['customer', 'vendor'])->whereBetween('order_date', [$startDate, $endDate]);
                if ($status) $q->where('status', $status);
                if ($search) $q->where('do_number', 'like', "%$search%")->orWhereHas('customer', fn($c) => $c->where('company_name', 'like', "%$search%"));
                $reportData = $q->orderBy('order_date', 'desc')->paginate(15)->withQueryString();
                break;
        }

        $salesUsers = SalesUser::orderBy('name')->get();

        return view('reports.index', compact(
            'reportData', 'revenue', 'totalDeals', 'avgDealValue', 'conversionRate', 'winRate',
            'salesUsers', 'startDate', 'endDate', 'reportType', 'salesId', 'status', 'search'
        ));
    }

    public function export(Request $request)
    {
        $startDate  = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate    = $request->get('end_date', now()->endOfMonth()->toDateString());
        $reportType = $request->get('report_type', 'sales');
        $salesId    = $request->get('sales_user_id');

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="report_' . $reportType . '_' . date('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($startDate, $endDate, $reportType, $salesId) {
            $f = fopen('php://output', 'w');
            fputs($f, "\xEF\xBB\xBF");

            switch ($reportType) {
                case 'sales':
                    fputcsv($f, ['Lead Code', 'Company', 'PIC', 'Service', 'Route', 'Stage', 'Temperature', 'Potensi Revenue', 'Probability', 'Expected Closing', 'Sales PIC', 'Created At']);
                    $leads = Lead::with(['salesUser'])->whereBetween('created_at', [$startDate, $endDate]);
                    if ($salesId) $leads->where('sales_user_id', $salesId);
                    foreach ($leads->get() as $l) {
                        fputcsv($f, [$l->lead_code, $l->company_name, $l->pic_name, $l->service_type, $l->route, $l->pipeline_stage, $l->temperature, $l->potensi_revenue, $l->probability, $l->expected_closing?->format('Y-m-d'), $l->salesUser?->name, $l->created_at->format('Y-m-d H:i')]);
                    }
                    break;

                case 'do':
                    fputcsv($f, ['DO Number', 'Customer', 'Vendor', 'Service Type', 'Route', 'Amount', 'Currency', 'Status', 'Order Date']);
                    $dos = DeliveryOrder::with(['customer', 'vendor'])->whereBetween('order_date', [$startDate, $endDate]);
                    foreach ($dos->get() as $do) {
                        fputcsv($f, [$do->do_number, $do->customer?->company_name, $do->vendor?->vendor_name, $do->service_type, $do->route, $do->amount, $do->currency, $do->status, $do->order_date]);
                    }
                    break;

                case 'customer':
                    fputcsv($f, ['Company Name', 'PIC', 'Phone', 'Email', 'Industry', 'Status', 'Sales PIC', 'Customer Since']);
                    foreach (Customer::with('salesUser')->get() as $c) {
                        fputcsv($f, [$c->company_name, $c->pic_name, $c->phone, $c->email, $c->industry, $c->status, $c->salesUser?->name, $c->customer_since?->format('Y-m-d')]);
                    }
                    break;
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }
}

