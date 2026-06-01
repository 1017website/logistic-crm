<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Models\DeliveryOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PipelineController extends Controller
{
    public function index(Request $request)
    {
        $stages = ['Identifying', 'Approaching', 'Follow Up', 'Won', 'Maintaining'];

        $leads = Lead::with(['customer', 'salesUser'])
            ->whereIn('pipeline_stage', $stages)
            ->orderBy('updated_at', 'desc')
            ->get();

        $pipeline = [];
        foreach ($stages as $stage) {
            $pipeline[$stage] = $leads->where('pipeline_stage', $stage)->values();
        }

        // Deal tercapai = Won + Maintaining (lead Won yang sudah dibuatkan DO
        // otomatis berpindah ke Maintaining, jadi keduanya dihitung sebagai closed).
        $closedStages = ['Won', 'Maintaining'];

        $activeLeads = Lead::whereNotIn('pipeline_stage', ['Lost']);
        $totalValue = (clone $activeLeads)->sum('potensi_revenue');
        $totalLeads = Lead::count();
        $potentialDeals = Lead::whereIn('pipeline_stage', ['Follow Up', 'Won'])->count();
        $wonCount = Lead::whereIn('pipeline_stage', $closedStages)->count();
        $winRate = $totalLeads > 0 ? round(($wonCount / $totalLeads) * 100, 1) : 0;

        // Expected/actual revenue: ambil dari Delivery Order (qty × sell_price) status Done,
        // karena revenue riil ada di DO — bukan dari potensi_revenue lead yang sering 0.
        $expectedRevenue = $this->doRevenueQuery()->sum(DB::raw('items.qty * items.sell_price'));

        // Top Sales: deal closed = lead Won/Maintaining; revenue = DO milik sales tsb.
        $doRevenuePerUser = $this->doRevenuePerUser();
        $topSales = User::query()
            ->withCount(['leads as deals_closed' => fn($q) => $q->whereIn('pipeline_stage', $closedStages)])
            ->get()
            ->map(function ($u) use ($doRevenuePerUser) {
                $u->expected_revenue = (float) ($doRevenuePerUser[$u->id] ?? 0);
                return $u;
            })
            ->sortByDesc('deals_closed')
            ->sortByDesc('expected_revenue')
            ->take(5)
            ->values();

        // Recompute sort: utamakan deals_closed lalu revenue
        $topSales = $topSales->sortByDesc(fn($u) => [$u->deals_closed, $u->expected_revenue])->values()->take(5);

        return view('pipeline.index', compact(
            'pipeline', 'totalValue', 'totalLeads', 'potentialDeals',
            'winRate', 'expectedRevenue', 'topSales'
        ));
    }

    /** Base query DO Done (IDR) join items, untuk agregasi revenue riil. */
    private function doRevenueQuery()
    {
        return DB::table('delivery_orders')
            ->join('delivery_order_items as items', 'items.delivery_order_id', '=', 'delivery_orders.id')
            ->where('delivery_orders.status', 'Done')
            ->where('delivery_orders.currency', 'IDR')
            ->whereNull('delivery_orders.deleted_at');
    }

    /** Revenue DO per user_id (sales). */
    private function doRevenuePerUser(): array
    {
        return $this->doRevenueQuery()
            ->select('delivery_orders.user_id as uid', DB::raw('SUM(items.qty * items.sell_price) as total'))
            ->groupBy('delivery_orders.user_id')
            ->pluck('total', 'uid')
            ->toArray();
    }
}

