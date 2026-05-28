<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Customer;
use App\Models\User;
use App\Models\DeliveryOrder;
use App\Models\Activity;
use App\Models\DeliveryOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->get('end_date', now()->endOfMonth()->toDateString());
        $salesId   = $request->get('user_id');

        // ── KPI Utama dari DO Done ──
        $doneDOs = DeliveryOrder::with('items')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'Done')->where('currency', 'IDR');
        if ($salesId) $doneDOs->where('user_id', $salesId);

        $volumePo    = (clone $doneDOs)->count();
        $totalsQuery = DeliveryOrderItem::join('delivery_orders', 'delivery_orders.id', '=', 'delivery_order_items.delivery_order_id')
            ->whereBetween('delivery_orders.order_date', [$startDate, $endDate])
            ->where('delivery_orders.status', 'Done')
            ->where('delivery_orders.currency', 'IDR');
        if ($salesId) $totalsQuery->where('delivery_orders.user_id', $salesId);
        $totals = $totalsQuery->selectRaw('COALESCE(SUM(qty * sell_price),0) as revenue, COALESCE(SUM(qty * buy_price),0) as total_cost')->first();
        $revenue     = (float) $totals->revenue;
        $totalCost   = (float) $totals->total_cost;
        $grossProfit = $revenue - $totalCost;
        $nettProfit  = $grossProfit; // logistic: nett = gross (belum ada other_cost)

        $leadsQuery = Lead::query();
        if ($salesId) $leadsQuery->where('user_id', $salesId);

        $dealsClosed    = (clone $leadsQuery)->where('pipeline_stage', 'Won')->whereBetween('updated_at', [$startDate, $endDate])->count();
        $totalLeads     = (clone $leadsQuery)->whereBetween('created_at', [$startDate, $endDate])->count();
        $conversionRate = $totalLeads > 0 ? round(($dealsClosed / $totalLeads) * 100, 1) : 0;

        // ── Revenue & profit trend (6 bulan) ──
        $revenueTrend = [];
        $profitAnalysis = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $monthlyQuery = DeliveryOrderItem::join('delivery_orders', 'delivery_orders.id', '=', 'delivery_order_items.delivery_order_id')
                ->whereYear('delivery_orders.order_date', $m->year)
                ->whereMonth('delivery_orders.order_date', $m->month)
                ->where('delivery_orders.currency', 'IDR')
                ->where('delivery_orders.status', 'Done');
            if ($salesId) $monthlyQuery->where('delivery_orders.user_id', $salesId);

            $monthly = $monthlyQuery->selectRaw('COALESCE(SUM(qty * sell_price),0) as revenue, COALESCE(SUM(qty * buy_price),0) as total_cost')->first();
            $rev = (float) $monthly->revenue;
            $cost = (float) $monthly->total_cost;
            $gross = $rev - $cost;

            $revenueTrend[] = ['label' => $m->format('M Y'), 'value' => round($rev / 1000000, 2)];
            $profitAnalysis[] = [
                'label'        => $m->format('M'),
                'revenue'      => round($rev / 1000000, 2),
                'cost'         => round($cost / 1000000, 2),
                'gross_profit' => round($gross / 1000000, 2),
                'profit'       => round($gross / 1000000, 2),
            ];
        }

        // ── Revenue by product (top 5) ──
        $productQuery = DeliveryOrderItem::join('delivery_orders', 'delivery_orders.id', '=', 'delivery_order_items.delivery_order_id')
            ->where('delivery_orders.status', 'Done')->where('delivery_orders.currency', 'IDR')
            ->whereBetween('delivery_orders.order_date', [$startDate, $endDate]);
        if ($salesId) $productQuery->where('delivery_orders.user_id', $salesId);
        $revenueByProduct = $productQuery->selectRaw('service_name, SUM(qty * sell_price) as total')
            ->groupBy('service_name')->orderByDesc('total')->limit(5)->get();

        // ── Pipeline funnel ──
        $funnel = collect(['Identifying','Approaching','Follow Up','Won','Maintaining'])
            ->mapWithKeys(fn($s) => [$s => (clone $leadsQuery)->where('pipeline_stage', $s)->count()]);

        // ── Lead sources ──
        $leadSources = (clone $leadsQuery)->whereNotNull('lead_source')
            ->selectRaw('lead_source, COUNT(*) as count')
            ->groupBy('lead_source')->orderByDesc('count')->get();

        // ── Sales performance: revenue diambil dari Delivery Order Done, bukan potensi revenue lead.
        $salesPerformance = User::orderBy('name')->get()->map(function ($u) use ($startDate, $endDate) {
            $totalLeads = Lead::where('user_id', $u->id)->count();
            $won = Lead::where('user_id', $u->id)
                ->where('pipeline_stage', 'Won')
                ->whereBetween('updated_at', [$startDate, $endDate])
                ->count();

            $doTotals = DeliveryOrderItem::join('delivery_orders', 'delivery_orders.id', '=', 'delivery_order_items.delivery_order_id')
                ->where('delivery_orders.user_id', $u->id)
                ->where('delivery_orders.status', 'Done')
                ->where('delivery_orders.currency', 'IDR')
                ->whereBetween('delivery_orders.order_date', [$startDate, $endDate])
                ->selectRaw('COUNT(DISTINCT delivery_orders.id) as deals, COALESCE(SUM(qty * sell_price),0) as revenue')
                ->first();

            $u->deals_closed = max((int) $won, (int) ($doTotals->deals ?? 0));
            $u->revenue = (float) ($doTotals->revenue ?? 0);
            $u->conversion = $totalLeads > 0 ? round(($won / $totalLeads) * 100, 1) : 0;
            return $u;
        })->filter(fn($u) => $u->revenue > 0 || $u->deals_closed > 0)->sortByDesc('revenue')->values();

        // ── Top customers ──
        $topCustomers = Customer::orderBy('company_name')->get()->map(function($c) use ($startDate, $endDate, $salesId) {
            $poQuery = $c->deliveryOrders()->whereBetween('order_date', [$startDate, $endDate])->where('status', 'Done')->where('currency', 'IDR');
            if ($salesId) $poQuery->whereHas('lead', fn($q) => $q->where('user_id', $salesId));
            $pos = $poQuery->with('items')->get();
            return [
                'customer' => $c,
                'revenue'  => $pos->sum(fn($po) => $po->total_revenue),
                'deals'    => $pos->count(),
                'repeat'   => $pos->count() > 1,
            ];
        })->sortByDesc('revenue')->take(5);

        // ── Recent deals closed ──
        $recentDealsQuery = Lead::with('salesUser')->where('pipeline_stage', 'Won');
        if ($salesId) $recentDealsQuery->where('user_id', $salesId);
        $recentDeals = $recentDealsQuery->orderBy('updated_at', 'desc')->limit(5)->get();

        // ── Avg Gross Margin dari profit analysis 6 bulan ──
        $marginData    = array_filter($profitAnalysis, fn($m) => $m['revenue'] > 0);
        $avgGrossMargin = count($marginData) > 0
            ? round(collect($marginData)->avg(fn($m) => $m['revenue'] > 0 ? (($m['gross_profit'] / $m['revenue']) * 100) : 0), 1)
            : 0;
        $avgNettMargin = $avgGrossMargin; // logistic: nett = gross (belum ada other_cost)
        $serviceQuery = DeliveryOrderItem::join('delivery_orders', 'delivery_orders.id', '=', 'delivery_order_items.delivery_order_id')
            ->where('delivery_orders.status', 'Done')->where('delivery_orders.currency', 'IDR')
            ->whereBetween('delivery_orders.order_date', [$startDate, $endDate]);
        if ($salesId) $serviceQuery->where('delivery_orders.user_id', $salesId);
        $revenueByService = $serviceQuery->selectRaw('service_name as service_type, SUM(qty * sell_price) as total')
            ->groupBy('service_name')->orderByDesc('total')->limit(5)->get();

        // ── Revenue by route per delivery type ──
        $revenueByRoute = collect();

        $salesUsers = User::orderBy('name')->get();

        return view('analytics.index', compact(
            'revenue','grossProfit','nettProfit','volumePo','dealsClosed','conversionRate',
            'revenueTrend','profitAnalysis','revenueByProduct','revenueByService','revenueByRoute',
            'avgGrossMargin','avgNettMargin',
            'funnel','salesPerformance','topCustomers','leadSources',
            'recentDeals','salesUsers','startDate','endDate','salesId'
        ));
    }
}
