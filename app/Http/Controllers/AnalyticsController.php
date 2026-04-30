<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Customer;
use App\Models\SalesUser;
use App\Models\DeliveryOrder;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->get('end_date', now()->endOfMonth()->toDateString());

        $revenue    = DeliveryOrder::whereBetween('order_date', [$startDate, $endDate])->where('currency', 'IDR')->sum('amount');
        $grossProfit  = $revenue * 0.32;
        $nettProfit   = $revenue * 0.19;
        $dealsClosed  = Lead::where('pipeline_stage', 'Won')->whereBetween('updated_at', [$startDate, $endDate])->count();
        $totalLeads   = Lead::count();
        $conversionRate = $totalLeads > 0 ? round(($dealsClosed / $totalLeads) * 100, 1) : 0;

        $revenueByService = DeliveryOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('currency', 'IDR')
            ->selectRaw('service_type, SUM(amount) as total')
            ->groupBy('service_type')->get();

        $funnel = [
            'Identifying' => Lead::where('pipeline_stage', 'Identifying')->count(),
            'Approaching' => Lead::where('pipeline_stage', 'Approaching')->count(),
            'Follow Up'   => Lead::where('pipeline_stage', 'Follow Up')->count(),
            'Closing'     => Lead::where('pipeline_stage', 'Closing')->count(),
            'Won'         => Lead::where('pipeline_stage', 'Won')->count(),
        ];

        $salesPerformance = SalesUser::withCount([
            'leads as deals_closed' => fn($q) => $q->where('pipeline_stage', 'Won'),
        ])->get()->map(function ($s) {
            $s->revenue    = Lead::where('sales_user_id', $s->id)->where('pipeline_stage', 'Won')->sum('potensi_revenue');
            $total         = Lead::where('sales_user_id', $s->id)->count();
            $s->conversion = $total > 0 ? round(($s->deals_closed / $total) * 100, 1) : 0;
            return $s;
        })->sortByDesc('revenue');

        $topCustomers = Customer::with('deliveryOrders')->get()
            ->map(fn($c) => ['customer' => $c, 'revenue' => $c->total_revenue, 'deals' => $c->deliveryOrders->count()])
            ->sortByDesc('revenue')->take(6);

        $leadSources = Lead::selectRaw('lead_source, COUNT(*) as count')->groupBy('lead_source')->get();

        $revenueByRoute = DeliveryOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('currency', 'IDR')
            ->selectRaw('route, SUM(amount) as total')
            ->groupBy('route')->orderByDesc('total')->limit(5)->get();

        return view('analytics.index', compact(
            'revenue', 'grossProfit', 'nettProfit', 'dealsClosed', 'conversionRate',
            'revenueByService', 'funnel', 'salesPerformance', 'topCustomers',
            'leadSources', 'revenueByRoute', 'startDate', 'endDate'
        ));
    }
}
