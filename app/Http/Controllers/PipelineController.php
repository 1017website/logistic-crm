<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function index(Request $request)
    {
        $stages   = ['Identifying', 'Approaching', 'Follow Up', 'Closing', 'Won', 'Maintaining'];
        $pipeline = [];
        foreach ($stages as $stage) {
            $pipeline[$stage] = Lead::where('pipeline_stage', $stage)
                ->with(['customer', 'salesUser'])
                ->orderBy('updated_at', 'desc')
                ->get();
        }

        $totalValue      = Lead::whereNotIn('pipeline_stage', ['Lost'])->sum('potensi_revenue');
        $totalLeads      = Lead::count();
        $potentialDeals  = Lead::whereIn('pipeline_stage', ['Follow Up', 'Closing'])->count();
        $winRate         = $totalLeads > 0
            ? round((Lead::where('pipeline_stage', 'Won')->count() / $totalLeads) * 100, 1)
            : 0;
        $expectedRevenue = Lead::where('pipeline_stage', 'Won')->sum('potensi_revenue');

        $topSales = User::withCount(['leads as expected_revenue' => function ($q) {
            $q->selectRaw('sum(potensi_revenue)');
        }])->get()->sortByDesc('expected_revenue')->take(5);

        return view('pipeline.index', compact(
            'pipeline', 'totalValue', 'totalLeads', 'potentialDeals',
            'winRate', 'expectedRevenue', 'topSales'
        ));
    }
}
