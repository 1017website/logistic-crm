<?php
namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Customer;
use App\Models\Activity;
use App\Models\DeliveryOrder;
use App\Models\SalesUser;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $startMonth = $now->copy()->startOfMonth();
        $endMonth = $now->copy()->endOfMonth();

        // KPI Cards
        $revenue = DeliveryOrder::whereBetween('order_date', [$startMonth, $endMonth])
            ->where('status', 'Done')->where('currency', 'IDR')->sum('amount');

        $totalDo = DeliveryOrder::whereBetween('order_date', [$startMonth, $endMonth])->count();
        $activeLeads = Lead::whereNotIn('pipeline_stage', ['Won', 'Lost'])->count();
        $dealClosed = Lead::where('pipeline_stage', 'Won')
            ->whereBetween('updated_at', [$startMonth, $endMonth])->count();

        $totalLeads = Lead::whereNotIn('pipeline_stage', ['Won', 'Lost'])->count();
        $conversionRate = $totalLeads > 0 ? round(($dealClosed / max($totalLeads, 1)) * 100, 1) : 0;

        // Pipeline by stage
        $pipelineStages = [
            'Identifying' => Lead::where('pipeline_stage', 'Identifying')->get(),
            'Approaching' => Lead::where('pipeline_stage', 'Approaching')->get(),
            'Follow Up'   => Lead::where('pipeline_stage', 'Follow Up')->get(),
            'Closing'     => Lead::where('pipeline_stage', 'Closing')->get(),
        ];

        // Today's reminders
        $todayReminders = Activity::whereDate('activity_at', today())
            ->orWhere('status', 'Overdue')
            ->with(['lead', 'customer', 'salesUser'])
            ->orderBy('activity_at')
            ->limit(5)
            ->get();

        // Recent activities
        $recentActivities = Activity::with(['lead', 'customer', 'salesUser'])
            ->orderBy('activity_at', 'desc')
            ->limit(5)
            ->get();

        // Top sales
        $topSales = SalesUser::withCount(['leads as deals_closed' => function ($q) {
                $q->where('pipeline_stage', 'Won');
            }])
            ->get()
            ->sortByDesc('deals_closed')
            ->take(5);

        // Revenue chart data (last 30 days)
        $revenueChart = [];
        for ($i = 30; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $revenueChart[] = [
                'date' => $date->format('d M'),
                'value' => DeliveryOrder::whereDate('order_date', $date)
                    ->where('status', 'Done')->where('currency', 'IDR')->sum('amount'),
            ];
        }

        return view('dashboard.index', compact(
            'revenue', 'totalDo', 'activeLeads', 'dealClosed', 'conversionRate',
            'pipelineStages', 'todayReminders', 'recentActivities', 'topSales', 'revenueChart'
        ));
    }
}
