<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Customer;
use App\Models\User;
use App\Models\DeliveryOrder;
use App\Models\Activity;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->get('end_date', now()->endOfMonth()->toDateString());
        $salesId   = $request->get('user_id');

        // ── KPI Utama ──
        $doQuery = DeliveryOrder::whereBetween('order_date', [$startDate, $endDate])->where('currency', 'IDR');
        if ($salesId) $doQuery->whereHas('lead', fn($q) => $q->where('user_id', $salesId));

        $revenue     = (clone $doQuery)->where('status', 'Done')->sum('amount');
        $totalCost   = (clone $doQuery)->where('status', 'Done')->selectRaw('SUM(cost + other_cost) as total')->value('total') ?? 0;
        $vendorCost  = (clone $doQuery)->where('status', 'Done')->sum('cost');
        $grossProfit = $revenue - $vendorCost;
        $nettProfit  = $revenue - $totalCost;
        $volumeDo    = (clone $doQuery)->where('status', 'Done')->count();

        $leadsQuery = Lead::query();
        if ($salesId) $leadsQuery->where('user_id', $salesId);

        $dealsClosed    = (clone $leadsQuery)->where('pipeline_stage', 'Won')->whereBetween('updated_at', [$startDate, $endDate])->count();
        $totalLeads     = (clone $leadsQuery)->whereBetween('created_at', [$startDate, $endDate])->count();
        $conversionRate = $totalLeads > 0 ? round(($dealsClosed / $totalLeads) * 100, 1) : 0;

        // ── Revenue trend (6 bulan terakhir) ──
        $revenueTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $val   = DeliveryOrder::whereYear('order_date', $month->year)
                ->whereMonth('order_date', $month->month)
                ->where('currency', 'IDR')->where('status', 'Done')->sum('amount');
            $revenueTrend[] = ['label' => $month->format('M Y'), 'value' => (float)($val / 1000000)];
        }

        // ── Revenue by service type ──
        $revenueByService = DeliveryOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('currency', 'IDR')
            ->selectRaw('service_type, SUM(amount) as total')
            ->groupBy('service_type')->orderByDesc('total')->get();

        // ── Revenue by route (top 5) ──
        $revenueByRoute = DeliveryOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('currency', 'IDR')
            ->selectRaw('route, SUM(amount) as total')
            ->groupBy('route')->orderByDesc('total')->limit(5)->get();

        // ── Pipeline funnel ──
        $funnel = collect(['Identifying','Approaching','Follow Up','Closing','Won','Maintaining'])
            ->mapWithKeys(fn($s) => [$s => (clone $leadsQuery)->where('pipeline_stage', $s)->count()]);

        // ── Sales performance ──
        $salesPerformance = User::orderBy('name')->get()->map(function ($s) use ($startDate, $endDate) {
            $total   = Lead::where('user_id', $s->id)->count();
            $won     = Lead::where('user_id', $s->id)->where('pipeline_stage', 'Won')
                ->whereBetween('updated_at', [$startDate, $endDate])->count();
            $revenue = Lead::where('user_id', $s->id)->where('pipeline_stage', 'Won')->sum('potensi_revenue');
            $s->deals_closed = $won;
            $s->revenue      = $revenue;
            $s->conversion   = $total > 0 ? round(($won / $total) * 100, 1) : 0;
            return $s;
        })->sortByDesc('revenue');

        // ── Top customers ──
        $topCustomers = Customer::all()->map(fn($c) => [
            'customer' => $c,
            'revenue'  => $c->total_revenue,
            'deals'    => $c->deliveryOrders()->whereBetween('order_date', [$startDate, $endDate])->count(),
            'repeat'   => $c->deliveryOrders()->count() > 1,
        ])->sortByDesc('revenue')->take(5);

        // ── Lead sources ──
        $leadSources = Lead::whereNotNull('lead_source')
            ->selectRaw('lead_source, COUNT(*) as count')
            ->groupBy('lead_source')->orderByDesc('count')->get();

        // ── Recent deals closed ──
        $recentDeals = Lead::with(['salesUser'])
            ->where('pipeline_stage', 'Won')
            ->orderBy('updated_at', 'desc')
            ->limit(5)->get();

        // ── Profit analysis (6 bulan) ──
        $profitAnalysis = [];
        for ($i = 5; $i >= 0; $i--) {
            $m    = now()->subMonths($i);
            $rev  = DeliveryOrder::whereYear('order_date', $m->year)
                ->whereMonth('order_date', $m->month)->where('currency', 'IDR')
                ->where('status', 'Done')->sum('amount');
            $cogs = DeliveryOrder::whereYear('order_date', $m->year)
                ->whereMonth('order_date', $m->month)->where('currency', 'IDR')
                ->where('status', 'Done')->selectRaw('SUM(cost) as vendor, SUM(cost + other_cost) as total')->first();
            $grossP = $rev - ($cogs->vendor ?? 0);
            $nettP  = $rev - ($cogs->total  ?? 0);
            $profitAnalysis[] = [
                'label'        => $m->format('M'),
                'revenue'      => round($rev / 1000000, 2),
                'cost'         => round(($cogs->total ?? 0) / 1000000, 2),
                'gross_profit' => round($grossP / 1000000, 2),
                'profit'       => round($nettP  / 1000000, 2),
            ];
        }

        $salesUsers = User::orderBy('name')->get();

        return view('analytics.index', compact(
            'revenue', 'grossProfit', 'nettProfit', 'volumeDo', 'dealsClosed', 'conversionRate',
            'revenueTrend', 'revenueByService', 'revenueByRoute',
            'funnel', 'salesPerformance', 'topCustomers', 'leadSources',
            'recentDeals', 'profitAnalysis', 'salesUsers', 'startDate', 'endDate', 'salesId'
        ));
    }
}

