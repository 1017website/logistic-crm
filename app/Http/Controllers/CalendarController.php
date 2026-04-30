<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Lead;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        // Ambil semua activities untuk bulan ini sebagai events
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        $activities = Activity::with(['lead', 'customer', 'salesUser'])
            ->whereYear('activity_at', $year)
            ->whereMonth('activity_at', $month)
            ->orderBy('activity_at')
            ->get();

        // Data untuk kalender JSON (dipakai JS)
        $events = $activities->map(function ($a) {
            return [
                'id'       => $a->id,
                'title'    => $a->subject,
                'date'     => $a->activity_at->format('Y-m-d'),
                'time'     => $a->activity_at->format('H:i'),
                'type'     => $a->type,
                'status'   => $a->status,
                'customer' => $a->customer?->company_name ?? ($a->lead?->company_name ?? '-'),
                'sales'    => $a->salesUser?->name ?? '-',
            ];
        });

        // Upcoming activities (7 hari ke depan)
        $upcoming = Activity::with(['lead', 'customer', 'salesUser'])
            ->where('activity_at', '>=', now())
            ->where('activity_at', '<=', now()->addDays(7))
            ->where('status', '!=', 'Done')
            ->orderBy('activity_at')
            ->limit(10)
            ->get();

        // Overdue
        $overdue = Activity::with(['lead', 'customer', 'salesUser'])
            ->where('activity_at', '<', now())
            ->where('status', '!=', 'Done')
            ->orderBy('activity_at', 'desc')
            ->limit(5)
            ->get();

        return view('calendar.index', compact('events', 'upcoming', 'overdue', 'month', 'year'));
    }
}
