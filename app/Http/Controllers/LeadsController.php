<?php
namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Customer;
use App\Models\SalesUser;
use App\Models\Activity;
use App\Models\Quotation;
use Illuminate\Http\Request;

class LeadsController extends Controller
{
    public function index(Request $request)
    {
        $stage = $request->get('stage');
        $temperature = $request->get('temperature');
        $search = $request->get('search');

        $query = Lead::with(['customer', 'salesUser', 'activities'])
            ->whereNotIn('pipeline_stage', ['Won', 'Lost']);

        if ($stage) $query->where('pipeline_stage', $stage);
        if ($temperature) $query->where('temperature', $temperature);
        if ($search) $query->where('company_name', 'like', "%$search%");

        $leads = $query->orderBy('updated_at', 'desc')->paginate(15);
        $salesUsers = SalesUser::all();

        return view('leads.index', compact('leads', 'salesUsers', 'stage', 'temperature', 'search'));
    }

    public function show(Lead $lead)
    {
        $lead->load(['customer', 'salesUser', 'activities.salesUser', 'quotations']);
        return view('leads.show', compact('lead'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name'   => 'required|string|max:255',
            'pic_name'       => 'required|string|max:255',
            'phone'          => 'nullable|string',
            'email'          => 'nullable|email',
            'service_type'   => 'nullable|string',
            'route'          => 'nullable|string',
            'pipeline_stage' => 'required|in:Identifying,Approaching,Follow Up,Closing,Won,Lost',
            'temperature'    => 'required|in:Hot,Warm,Cold',
            'potensi_revenue'=> 'nullable|numeric',
            'sales_user_id'  => 'required|exists:sales_users,id',
        ]);
        $validated['lead_code'] = Lead::generateLeadCode();
        Lead::create($validated);
        return redirect()->route('leads.index')->with('success', 'Lead berhasil ditambahkan.');
    }

    public function update(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'pipeline_stage' => 'sometimes|in:Identifying,Approaching,Follow Up,Closing,Won,Lost',
            'temperature'    => 'sometimes|in:Hot,Warm,Cold',
            'catatan_internal' => 'nullable|string',
        ]);
        $lead->update($validated);
        return redirect()->back()->with('success', 'Lead berhasil diupdate.');
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();
        return redirect()->route('leads.index')->with('success', 'Lead dihapus.');
    }
}
