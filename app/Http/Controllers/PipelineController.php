<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;

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

        $activeLeads = Lead::whereNotIn('pipeline_stage', ['Lost']);
        $totalValue = (clone $activeLeads)->sum('potensi_revenue');
        $totalLeads = Lead::count();
        $potentialDeals = Lead::whereIn('pipeline_stage', ['Follow Up', 'Won'])->count();
        $wonCount = Lead::where('pipeline_stage', 'Won')->count();
        $winRate = $totalLeads > 0 ? round(($wonCount / $totalLeads) * 100, 1) : 0;
        $expectedRevenue = Lead::where('pipeline_stage', 'Won')->sum('potensi_revenue');

        $topSales = User::query()
            ->withCount(['leads as deals_closed' => fn($q) => $q->where('pipeline_stage', 'Won')])
            ->withSum(['leads as expected_revenue' => fn($q) => $q->where('pipeline_stage', 'Won')], 'potensi_revenue')
            ->orderByDesc('deals_closed')
            ->orderByDesc('expected_revenue')
            ->limit(5)
            ->get();

        return view('pipeline.index', compact(
            'pipeline', 'totalValue', 'totalLeads', 'potentialDeals',
            'winRate', 'expectedRevenue', 'topSales'
        ));
    }

}

