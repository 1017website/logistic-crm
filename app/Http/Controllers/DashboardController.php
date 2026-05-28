<?php
namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Customer;
use App\Models\Activity;
use App\Models\DeliveryOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $now        = Carbon::now();
        $startMonth = $now->copy()->startOfMonth();
        $endMonth   = $now->copy()->endOfMonth();
        $startPrev  = $now->copy()->subMonth()->startOfMonth();
        $endPrev    = $now->copy()->subMonth()->endOfMonth();
        $prevLabel  = $now->copy()->subMonth()->format('M Y');

        // ── KPI bulan ini ──
        $doneDOs    = DeliveryOrder::with('items')->whereBetween('order_date', [$startMonth, $endMonth])->where('status','Done')->where('currency','IDR')->get();
        $revenue    = $doneDOs->sum(fn($po) => $po->total_revenue);
        $totalDo    = DeliveryOrder::whereBetween('order_date', [$startMonth, $endMonth])->count();
        $activeLeads = Lead::whereNotIn('pipeline_stage', ['Won','Lost'])->count();
        $dealClosed  = Lead::where('pipeline_stage','Won')->whereBetween('updated_at', [$startMonth, $endMonth])->count();
        $totalLeads  = Lead::count();
        $conversionRate = $totalLeads > 0 ? round(($dealClosed / max($totalLeads, 1)) * 100, 1) : 0;

        // ── KPI bulan lalu (growth %) ──
        $doneDOsPrev    = DeliveryOrder::with('items')->whereBetween('order_date', [$startPrev, $endPrev])->where('status','Done')->where('currency','IDR')->get();
        $revenuePrev    = $doneDOsPrev->sum(fn($po) => $po->total_revenue);
        $totalDoPrev    = DeliveryOrder::whereBetween('order_date', [$startPrev, $endPrev])->count();
        $dealClosedPrev = Lead::where('pipeline_stage','Won')->whereBetween('updated_at', [$startPrev, $endPrev])->count();
        $activeLeadsPrev = Lead::whereNotIn('pipeline_stage', ['Won','Lost'])->where('created_at','<',$startMonth)->count();

        $growth = fn($now, $prev) => $prev > 0 ? round((($now - $prev) / $prev) * 100, 1) : ($now > 0 ? 100 : 0);
        $revenueGrowth = $growth($revenue, $revenuePrev);
        $doGrowth      = $growth($totalDo, $totalDoPrev);
        $dealGrowth    = $growth($dealClosed, $dealClosedPrev);
        $leadsGrowth   = $growth($activeLeads, $activeLeadsPrev);

        // ── Pipeline by stage ──
        $pipelineStages = [
            'Identifying' => Lead::where('pipeline_stage','Identifying')->get(),
            'Approaching' => Lead::where('pipeline_stage','Approaching')->get(),
            'Follow Up'   => Lead::where('pipeline_stage','Follow Up')->get(),
            'Won'         => Lead::where('pipeline_stage','Won')->get(),
            'Maintaining' => Lead::where('pipeline_stage','Maintaining')->get(),
        ];

        // ── Today reminders ──
        $todayReminders = Activity::where(function($q) {
                $q->whereDate('activity_at', today())->where('status','!=','Done');
            })->orWhere('status','Overdue')
            ->with(['lead','customer','salesUser'])
            ->orderBy('activity_at')
            ->limit(5)->get();

        // ── Recent activities ──
        $recentActivities = Activity::with(['lead','customer','salesUser'])
            ->orderBy('activity_at','desc')->limit(5)->get();

        // ── Top sales ──
        $topSales = User::withCount(['leads as deals_closed' => fn($q) => $q->where('pipeline_stage','Won')])
            ->get()->sortByDesc('deals_closed')->take(5);

        // ── Revenue & Volume DO chart (30 hari terakhir) ──
        $chartStart = $now->copy()->subDays(30)->toDateString();
        $chartEnd   = $now->copy()->toDateString();

        $revenueRows = DB::table('delivery_order_items')
            ->join('delivery_orders', 'delivery_orders.id', '=', 'delivery_order_items.delivery_order_id')
            ->whereBetween('delivery_orders.order_date', [$chartStart, $chartEnd])
            ->where('delivery_orders.status', 'Done')
            ->where('delivery_orders.currency', 'IDR')
            ->selectRaw('DATE(delivery_orders.order_date) as date_key, COALESCE(SUM(delivery_order_items.qty * delivery_order_items.sell_price),0) as total')
            ->groupBy('date_key')
            ->pluck('total', 'date_key');

        $volumeRows = DeliveryOrder::whereBetween('order_date', [$chartStart, $chartEnd])
            ->selectRaw('DATE(order_date) as date_key, COUNT(*) as total')
            ->groupBy('date_key')
            ->pluck('total', 'date_key');

        $revenueChart = [];
        $volumeChart = [];
        for ($i = 30; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $key = $date->toDateString();
            $revenueChart[] = ['date' => $date->format('d M'), 'value' => (float) ($revenueRows[$key] ?? 0)];
            $volumeChart[]  = ['date' => $date->format('d M'), 'value' => (int) ($volumeRows[$key] ?? 0)];
        }

        // ── Trend Won/Lost chart ──
        $wonRows = Lead::where('pipeline_stage', 'Won')
            ->whereBetween('updated_at', [$chartStart, $chartEnd . ' 23:59:59'])
            ->selectRaw('DATE(updated_at) as date_key, COUNT(*) as total')
            ->groupBy('date_key')
            ->pluck('total', 'date_key');
        $lostRows = Lead::where('pipeline_stage', 'Lost')
            ->whereBetween('updated_at', [$chartStart, $chartEnd . ' 23:59:59'])
            ->selectRaw('DATE(updated_at) as date_key, COUNT(*) as total')
            ->groupBy('date_key')
            ->pluck('total', 'date_key');

        $trendWon  = [];
        $trendLost = [];
        for ($i = 30; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $key = $date->toDateString();
            $trendWon[]  = ['date' => $date->format('d M'), 'value' => (int) ($wonRows[$key] ?? 0)];
            $trendLost[] = ['date' => $date->format('d M'), 'value' => (int) ($lostRows[$key] ?? 0)];
        }

        return view('dashboard.index', compact(
            'revenue','totalDo','activeLeads','dealClosed','conversionRate',
            'revenueGrowth','doGrowth','dealGrowth','leadsGrowth','prevLabel',
            'pipelineStages','todayReminders','recentActivities','topSales',
            'revenueChart','volumeChart','trendWon','trendLost'
        ));
    }
}