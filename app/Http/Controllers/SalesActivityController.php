<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SalesActivityController extends Controller
{
    public function index(Request $request)
    {
        $date    = $request->get('date'); // kosong = semua tanggal
        $salesId = $request->get('user_id');
        $type    = $request->get('activity_type');

        $query = Activity::with(['lead', 'customer', 'salesUser']);

        if ($date) {
            $query->whereDate('activity_at', $date);
        }

        if ($salesId && $salesId !== 'all') {
            $query->where('user_id', $salesId);
        }
        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }

        $activities = $query->orderBy('activity_at', 'desc')->paginate(20);

        $todayReminders = Activity::with(['lead', 'customer'])
            ->whereDate('activity_at', today())->orderBy('activity_at')->get();

        $overdueActivities = Activity::where('status', 'Overdue')
            ->with(['lead', 'customer'])->get();

        $upcomingActivities = Activity::whereDate('activity_at', '>', today())
            ->with(['lead', 'customer'])->orderBy('activity_at')->limit(5)->get();

        $salesUsers = User::orderBy('name')->get();

        // Pipeline summary for sidebar
        $pipelineSummary = [
            'Identifying' => ['count' => Lead::where('pipeline_stage', 'Identifying')->count(), 'value' => Lead::where('pipeline_stage', 'Identifying')->sum('potensi_revenue')],
            'Approaching' => ['count' => Lead::where('pipeline_stage', 'Approaching')->count(), 'value' => Lead::where('pipeline_stage', 'Approaching')->sum('potensi_revenue')],
            'Follow Up'   => ['count' => Lead::where('pipeline_stage', 'Follow Up')->count(), 'value' => Lead::where('pipeline_stage', 'Follow Up')->sum('potensi_revenue')],
            'Closing'     => ['count' => Lead::where('pipeline_stage', 'Closing')->count(), 'value' => Lead::where('pipeline_stage', 'Closing')->sum('potensi_revenue')],
        ];

        return view('sales.activity', compact(
            'activities',
            'todayReminders',
            'overdueActivities',
            'upcomingActivities',
            'salesUsers',
            'pipelineSummary',
            'date',
            'salesId',
            'type'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id'        => 'nullable|exists:leads,id',
            'customer_id'    => 'nullable|exists:customers,id',
            'type'           => 'required|in:Call,Visit,Email,Note,Others',
            'subject'        => 'required|string|max:255',
            'description'    => 'nullable|string',
            'activity_at'    => 'required|date',
            'status'         => 'required|in:Done,Pending,Planned,Overdue',
            'next_follow_up' => 'nullable|date',
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);

        // Selalu pakai auth user
        $validated['user_id'] = auth()->id();

        // Upload foto — file tidak masuk $validated otomatis, harus eksplisit
        unset($validated['photo']); // hapus entry null dari validated
        if ($request->hasFile('photo') && $request->input('type') === 'Visit') {
            $validated['photo'] = $request->file('photo')->store('activity-photos', 'public');
        }

        // Update pipeline_stage lead jika dikirim
        if (!empty($validated['lead_id']) && $request->filled('pipeline_stage')) {
            \App\Models\Lead::where('id', $validated['lead_id'])
                ->update(['pipeline_stage' => $request->pipeline_stage]);
        }

        Activity::create($validated);
        return redirect()->back()->with('success', 'Aktivitas berhasil disimpan.');
    }
}
