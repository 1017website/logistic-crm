<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\SalesUser;
use App\Models\DeliveryOrder;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $startDate  = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate    = $request->get('end_date', now()->endOfMonth()->toDateString());
        $reportType = $request->get('report_type', 'Sales Report');
        $salesId    = $request->get('sales_user_id');

        $query = Lead::with(['customer', 'salesUser'])->whereBetween('created_at', [$startDate, $endDate]);
        if ($salesId) $query->where('sales_user_id', $salesId);
        $reportData = $query->orderBy('created_at', 'desc')->paginate(10);

        $revenue        = DeliveryOrder::whereBetween('order_date', [$startDate, $endDate])->where('currency', 'IDR')->sum('amount');
        $totalDeals     = Lead::where('pipeline_stage', 'Won')->whereBetween('updated_at', [$startDate, $endDate])->count();
        $avgDealValue   = $totalDeals > 0 ? $revenue / $totalDeals : 0;
        $totalLeads     = Lead::whereBetween('created_at', [$startDate, $endDate])->count();
        $conversionRate = $totalLeads > 0 ? round(($totalDeals / $totalLeads) * 100, 1) : 0;
        $winRate        = Lead::count() > 0
            ? round((Lead::where('pipeline_stage', 'Won')->count() / Lead::count()) * 100, 1)
            : 0;

        $salesUsers = SalesUser::all();

        return view('reports.index', compact(
            'reportData', 'revenue', 'totalDeals', 'avgDealValue', 'conversionRate', 'winRate',
            'salesUsers', 'startDate', 'endDate', 'reportType', 'salesId'
        ));
    }
}
