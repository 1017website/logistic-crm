<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year', now()->year);

        $activities = Activity::with(['lead', 'customer', 'salesUser'])
            ->whereYear('activity_at', $year)
            ->whereMonth('activity_at', $month)
            ->orderBy('activity_at')
            ->get();

        $events = $activities->map(fn($a) => [
            'id'          => $a->id,
            'title'       => $a->subject,
            'date'        => $a->activity_at->format('Y-m-d'),
            'time'        => $a->activity_at->format('H:i'),
            'type'        => $a->type,
            'status'      => $a->status,
            'customer'    => $a->customer?->company_name ?? ($a->lead?->company_name ?? '-'),
            'sales'       => $a->salesUser?->name ?? '-',
            'sales_id'    => $a->user_id,
            'description' => $a->description,
        ]);

        // Return JSON untuk AJAX request (navigasi bulan)
        if ($request->ajax() || $request->get('json')) {
            return response()->json($events);
        }

        $upcoming = Activity::with(['lead', 'customer', 'salesUser'])
            ->where('activity_at', '>=', now())
            ->where('activity_at', '<=', now()->addDays(7))
            ->where('status', '!=', 'Done')
            ->orderBy('activity_at')
            ->limit(8)->get();

        $overdue = Activity::with(['lead', 'customer', 'salesUser'])
            ->where('activity_at', '<', now())
            ->where('status', '!=', 'Done')
            ->orderBy('activity_at', 'desc')
            ->limit(5)->get();

        return view('calendar.index', compact('events', 'upcoming', 'overdue', 'month', 'year'));
    }
}
