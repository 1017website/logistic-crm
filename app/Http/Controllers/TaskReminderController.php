<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;

class TaskReminderController extends Controller
{
    public function index(Request $request)
    {
        $filter   = $request->get('filter', 'all');   // all | today | overdue | upcoming
        $salesId  = $request->get('user_id');
        $type     = $request->get('type');

        $query = Activity::with(['lead', 'customer', 'salesUser']);

        if ($salesId) $query->where('user_id', $salesId);
        if ($type)    $query->where('type', $type);

        match ($filter) {
            'today'    => $query->whereDate('activity_at', today()),
            'overdue'  => $query->where('activity_at', '<', now())->where('status', '!=', 'Done'),
            'upcoming' => $query->where('activity_at', '>=', now())->where('activity_at', '<=', now()->addDays(7)),
            default    => null,
        };

        $tasks = $query->orderBy('activity_at')->paginate(15);

        // Summary counts
        $totalToday    = Activity::whereDate('activity_at', today())->count();
        $totalOverdue  = Activity::where('activity_at', '<', now())->where('status', '!=', 'Done')->count();
        $totalUpcoming = Activity::where('activity_at', '>=', now())->where('activity_at', '<=', now()->addDays(7))->where('status', '!=', 'Done')->count();
        $totalDone     = Activity::where('status', 'Done')->count();

        $salesUsers = User::orderBy('name')->get();

        return view('tasks.index', compact(
            'tasks',
            'filter',
            'salesId',
            'type',
            'totalToday',
            'totalOverdue',
            'totalUpcoming',
            'totalDone',
            'salesUsers'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id'        => 'nullable|exists:leads,id',
            'customer_id'    => 'nullable|exists:customers,id',
            'user_id'  => 'required|exists:users,id',
            'type'           => 'required|in:Call,Visit,Email,Note,Task',
            'subject'        => 'required|string|max:255',
            'description'    => 'nullable|string',
            'activity_at'    => 'required|date',
            'status'         => 'required|in:Planned,Pending,Done,Overdue',
            'next_follow_up' => 'nullable|date',
        ]);

        if (auth()->user()->isSalesExecutive()) {
            $validated['user_id'] = auth()->id();
        }
        Activity::create($validated);
        return redirect()->route('tasks.index')->with('success', 'Task berhasil ditambahkan.');
    }

    public function update(Request $request, Activity $activity)
    {
        $activity->update($request->validate([
            'status'  => 'required|in:Planned,Pending,Done,Overdue',
            'subject' => 'sometimes|string|max:255',
        ]));
        return redirect()->back()->with('success', 'Task diupdate.');
    }

    public function destroy(Activity $activity)
    {
        $activity->delete();
        return redirect()->route('tasks.index')->with('success', 'Task dihapus.');
    }
}
