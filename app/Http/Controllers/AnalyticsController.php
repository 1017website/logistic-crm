<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Customer;
use App\Models\User;
use App\Models\DeliveryOrder;
use App\Models\Activity;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->get('end_date', now()->endOfMonth()->toDateString());
        $salesId   = $request->get('user_id');

        // ── KPI Utama dari PO Done ──
        $doneDOs = DeliveryOrder::with('items')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'Done')->where('currency', 'IDR');
        if ($salesId) $doneDOs->where('user_id', $salesId);

        $allDonePOs  = (clone $doneDOs)->get();
        $revenue     = $allDonePOs->sum(fn($po) => $po->total_revenue);
        $totalCost   = $allDonePOs->sum(fn($po) => $po->total_cost);
        $grossProfit = $revenue - $totalCost;
        $nettProfit  = $grossProfit; // logistic: nett = gross (belum ada other_cost)
        $volumePo    = $allDonePOs->count();

        $leadsQuery = Lead::query();
        if ($salesId) $leadsQuery->where('user_id', $salesId);

        $dealsClosed    = (clone $leadsQuery)->where('pipeline_stage', 'Won')->whereBetween('updated_at', [$startDate, $endDate])->count();
        $totalLeads     = (clone $leadsQuery)->whereBetween('created_at', [$startDate, $endDate])->count();
        $conversionRate = $totalLeads > 0 ? round(($dealsClosed / $totalLeads) * 100, 1) : 0;

        // ── Revenue trend (6 bulan) ──
        $revenueTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $m   = now()->subMonths($i);
            $q   = DeliveryOrder::with('items')
                ->whereYear('order_date', $m->year)->whereMonth('order_date', $m->month)
                ->where('currency', 'IDR')->where('status', 'Done');
            if ($salesId) $q->where('user_id', $salesId);
            $val = $q->get()->sum(fn($po) => $po->total_revenue);
            $revenueTrend[] = ['label' => $m->format('M Y'), 'value' => round($val / 1000000, 2)];
        }

        // ── Profit analysis (6 bulan) ──
        $profitAnalysis = [];
        for ($i = 5; $i >= 0; $i--) {
            $m   = now()->subMonths($i);
            $q   = DeliveryOrder::with('items')
                ->whereYear('order_date', $m->year)->whereMonth('order_date', $m->month)
                ->where('currency', 'IDR')->where('status', 'Done');
            if ($salesId) $q->where('user_id', $salesId);
            $pos   = $q->get();
            $rev   = $pos->sum(fn($po) => $po->total_revenue);
            $cost  = $pos->sum(fn($po) => $po->total_cost);
            $gross = $rev - $cost;
            $profitAnalysis[] = [
                'label'        => $m->format('M'),
                'revenue'      => round($rev   / 1000000, 2),
                'cost'         => round($cost  / 1000000, 2),
                'gross_profit' => round($gross / 1000000, 2),
                'profit'       => round($gross / 1000000, 2),
            ];
        }

        // ── Revenue by product (top 5) ──
        $productQuery = \App\Models\DeliveryOrderItem::join('delivery_orders', 'delivery_orders.id', '=', 'delivery_order_items.delivery_order_id')
            ->where('delivery_orders.status', 'Done')->where('delivery_orders.currency', 'IDR')
            ->whereBetween('delivery_orders.order_date', [$startDate, $endDate]);
        if ($salesId) $productQuery->whereHas('deliveryOrder.lead', fn($q) => $q->where('user_id', $salesId));
        $revenueByProduct = $productQuery->selectRaw('service_name, SUM(qty * sell_price) as total')
            ->groupBy('service_name')->orderByDesc('total')->limit(5)->get();

        // ── Pipeline funnel ──
        $funnel = collect(['Identifying','Approaching','Follow Up','Closing','Won','Maintaining'])
            ->mapWithKeys(fn($s) => [$s => (clone $leadsQuery)->where('pipeline_stage', $s)->count()]);

        // ── Lead sources ──
        $leadSources = (clone $leadsQuery)->whereNotNull('lead_source')
            ->selectRaw('lead_source, COUNT(*) as count')
            ->groupBy('lead_source')->orderByDesc('count')->get();

        // ── Sales performance ──
        $salesPerformance = User::orderBy('name')->get()->map(function ($u) use ($startDate, $endDate) {
            $total   = Lead::where('user_id', $u->id)->count();
            $won     = Lead::where('user_id', $u->id)->where('pipeline_stage', 'Won')->whereBetween('updated_at', [$startDate, $endDate])->count();
            $u->deals_closed = $won;
            $u->revenue      = Lead::where('user_id', $u->id)->where('pipeline_stage', 'Won')->sum('potensi_revenue');
            $u->conversion   = $total > 0 ? round(($won / $total) * 100, 1) : 0;
            return $u;
        })->sortByDesc('revenue');

        // ── Top customers ──
        $topCustomers = Customer::with('deliveryOrders.items')->get()->map(function($c) use ($startDate, $endDate, $salesId) {
            $poQuery = $c->deliveryOrders()->where('status', 'Done')->where('currency', 'IDR');
            if ($salesId) $poQuery->whereHas('lead', fn($q) => $q->where('user_id', $salesId));
            $pos = $poQuery->with('items')->get();
            return [
                'customer' => $c,
                'revenue'  => $pos->sum(fn($po) => $po->total_revenue),
                'deals'    => $c->deliveryOrders()->whereBetween('order_date', [$startDate, $endDate])->count(),
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
        $serviceQuery = \App\Models\DeliveryOrderItem::join('delivery_orders', 'delivery_orders.id', '=', 'delivery_order_items.delivery_order_id')
            ->where('delivery_orders.status', 'Done')->where('delivery_orders.currency', 'IDR')
            ->whereBetween('delivery_orders.order_date', [$startDate, $endDate]);
        if ($salesId) $serviceQuery->whereHas('deliveryOrder.lead', fn($q) => $q->where('user_id', $salesId));
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
