<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Notification;

class LeadsController extends Controller
{
    public function index(Request $request)
    {
        $stage  = $request->get('stage');
        $search = $request->get('search');

        $query = Lead::with(['salesUser', 'activities'])
            ->whereNotIn('pipeline_stage', ['Won', 'Lost']);

        if (auth()->user()->isSalesExecutive()) {
            $query->where('user_id', auth()->id());
        }

        if ($stage)  $query->where('pipeline_stage', $stage);
        if ($search) $query->where('company_name', 'like', "%$search%");

        $leads      = $query->orderBy('updated_at', 'desc')->paginate(15);
        $salesUsers = User::orderBy('name')->get();

        return view('leads.index', compact('leads', 'salesUsers', 'stage', 'search'));
    }

    public function show(Lead $lead)
    {
        $lead->load(['salesUser', 'activities.salesUser', 'quotations']);
        $salesUsers = User::orderBy('name')->get();
        return view('leads.show', compact('lead', 'salesUsers'));
    }

    public function store(Request $request)
    {
        // Strip format IDR (titik pemisah ribuan) sebelum validasi
        if ($request->filled('potensi_revenue')) {
            $request->merge([
                'potensi_revenue' => str_replace(['.', ','], ['', '.'], $request->potensi_revenue)
            ]);
        }

        $validated = $request->validate([
            'company_name'    => 'required|string|max:255',
            'pic_name'        => 'required|string|max:255',
            'pic_position'    => 'nullable|string|max:100',
            'phone'           => 'nullable|string|max:20',
            'email'           => 'nullable|email|max:255',
            'address'         => 'nullable|string',
            'industry'        => 'nullable|string|max:100',
            'pipeline_stage'  => 'nullable|in:Identifying,Approaching,Follow Up,Closing,Won,Lost,Maintaining',
            'temperature'     => 'nullable|in:Hot,Warm,Cold',
            'service_type'    => 'nullable|string|max:100',
            'route'           => 'nullable|string|max:255',
            'commodity'       => 'nullable|string|max:255',
            'volume_estimate' => 'nullable|string|max:100',
            'potensi_revenue' => 'nullable|numeric|min:0',
            'probability'     => 'nullable|integer|min:0|max:100',
            'lead_source'     => 'nullable|string|max:100',
            'competitor'      => 'nullable|string|max:255',
            'expected_closing' => 'nullable|date',
            'user_id'         => 'required|exists:users,id',
            'notes_kebutuhan' => 'nullable|string',
        ]);

        $validated['lead_code']      = Lead::generateLeadCode();
        $validated['pipeline_stage'] = $validated['pipeline_stage'] ?? 'Identifying';

        if (auth()->user()->isSalesExecutive()) {
            $validated['user_id'] = auth()->id();
        }

        $lead = Lead::create($validated);

        // Auto-sync ke database customer
        $this->syncToCustomer($lead);

        // Notifikasi: Lead Baru
        Notification::broadcast(
            'new_lead',
            'Lead Baru: ' . $lead->company_name,
            $lead->company_name . ' ditambahkan oleh ' . auth()->user()->name,
            route('leads.show', $lead)
        );

        return redirect()->route('leads.show', $lead)->with('success', 'Lead berhasil ditambahkan.');
    }

    public function update(Request $request, Lead $lead)
    {
        // Strip format IDR (titik pemisah ribuan) sebelum validasi
        if ($request->filled('potensi_revenue')) {
            $request->merge([
                'potensi_revenue' => str_replace(['.', ','], ['', '.'], $request->potensi_revenue)
            ]);
        }

        $validated = $request->validate([
            'company_name'    => 'sometimes|string|max:255',
            'pic_name'        => 'sometimes|string|max:255',
            'pic_position'    => 'nullable|string|max:100',
            'phone'           => 'nullable|string|max:20',
            'email'           => 'nullable|email|max:255',
            'address'         => 'nullable|string',
            'industry'        => 'nullable|string|max:100',
            'pipeline_stage'  => 'sometimes|in:Identifying,Approaching,Follow Up,Closing,Won,Lost,Maintaining',
            'temperature'     => 'nullable|in:Hot,Warm,Cold',
            'service_type'    => 'nullable|string|max:100',
            'route'           => 'nullable|string|max:255',
            'commodity'       => 'nullable|string|max:255',
            'volume_estimate' => 'nullable|string|max:100',
            'potensi_revenue' => 'nullable|numeric|min:0',
            'probability'     => 'nullable|integer|min:0|max:100',
            'lead_source'     => 'nullable|string|max:100',
            'competitor'      => 'nullable|string|max:255',
            'expected_closing' => 'nullable|date',
            'user_id'         => 'sometimes|exists:users,id',
            'notes_kebutuhan' => 'nullable|string',
            'catatan_internal' => 'nullable|string',
            'next_follow_up'  => 'nullable|date',
            'next_follow_up_notes' => 'nullable|string',
        ]);

        $lead->update($validated);

        // Auto-sync ke database customer setiap kali stage berubah
        if (isset($validated['pipeline_stage'])) {
            $this->syncToCustomer($lead);
        }

        // Notifikasi: Deal Won
        if (isset($validated['pipeline_stage']) && $validated['pipeline_stage'] === 'Won') {
            Notification::sendAll(
                'deal_won',
                'Deal Won: ' . $lead->company_name,
                $lead->company_name . ' berhasil di-close oleh ' . auth()->user()->name . ' — ' . idrm($lead->potensi_revenue),
                route('leads.show', $lead)
            );
        }
        // Notifikasi: Stage Change (bukan Won/Lost)
        elseif (isset($validated['pipeline_stage']) && !in_array($validated['pipeline_stage'], ['Won', 'Lost'])) {
            Notification::broadcast(
                'stage_change',
                'Stage Berubah: ' . $lead->company_name,
                $lead->company_name . ' pindah ke stage ' . $validated['pipeline_stage'],
                route('leads.show', $lead)
            );
        }

        if ($request->expectsJson() || $request->isJson()) {
            return response()->json(['success' => true, 'stage' => $lead->pipeline_stage]);
        }

        return redirect()->back()->with('success', 'Lead berhasil diupdate.');
    }

    /**
     * Sync lead ke database customer otomatis berdasarkan pipeline stage:
     * - Identifying / Approaching / Follow Up → Potential
     * - Closing / Won → Existing
     * - Lost → hapus dari customer jika ada (opsional: biarkan)
     */
    private function syncToCustomer(Lead $lead): void
    {
        $stage = $lead->pipeline_stage;

        if ($stage === 'Lost') {
            return;
        }

        $status = in_array($stage, ['Closing', 'Won', 'Maintaining']) ? 'Existing' : 'Potential';

        $customerData = [
            'company_name'   => $lead->company_name,
            'pic_name'       => $lead->pic_name,
            'pic_position'   => $lead->pic_position,
            'phone'          => $lead->phone ?? '',
            'email'          => $lead->email,
            'address'        => $lead->address,
            'industry'       => $lead->industry,
            'status'         => $status,
            'user_id'        => $lead->user_id,
            'customer_since' => $status === 'Existing' ? now()->toDateString() : null,
        ];

        if ($lead->customer_id) {
            \App\Models\Customer::where('id', $lead->customer_id)->update($customerData);
        } else {
            $customer = \App\Models\Customer::where('company_name', $lead->company_name)->first();
            if ($customer) {
                $customer->update($customerData);
                $lead->updateQuietly(['customer_id' => $customer->id]);
            } else {
                $customer = \App\Models\Customer::create($customerData);
                $lead->updateQuietly(['customer_id' => $customer->id]);
            }
        }
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();
        return redirect()->route('leads.index')->with('success', 'Lead dihapus.');
    }

    // ── Add Activity ke Lead ──
    public function storeActivity(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'type'           => 'required|in:Call,Visit,Email,Note,Task',
            'subject'        => 'required|string|max:255',
            'description'    => 'nullable|string',
            'activity_at'    => 'required|date',
            'status'         => 'required|in:Planned,Pending,Done,Overdue',
            'user_id'  => 'required|exists:users,id',
            'next_follow_up' => 'nullable|date',
        ]);

        $validated['lead_id'] = $lead->id;
        if (auth()->user()->isSalesExecutive()) {
            $validated['user_id'] = auth()->id();
        }
        Activity::create($validated);

        return redirect()->route('leads.show', $lead)->with('success', 'Activity berhasil ditambahkan.');
    }

    // ── Export CSV ──
    public function export(Request $request)
    {
        $leads = Lead::with(['salesUser'])->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="leads_' . date('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($leads) {
            $file = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fputs($file, "\xEF\xBB\xBF");
            // Header row
            fputcsv($file, [
                'Lead Code',
                'Company Name',
                'PIC Name',
                'Phone',
                'Email',
                'Pipeline Stage',
                'Temperature',
                'Service Type',
                'Route',
                'Potensi Revenue',
                'Probability %',
                'Expected Closing',
                'Sales PIC',
                'Lead Source',
                'Created At',
            ]);
            foreach ($leads as $lead) {
                fputcsv($file, [
                    $lead->lead_code,
                    $lead->company_name,
                    $lead->pic_name,
                    $lead->phone,
                    $lead->email,
                    $lead->pipeline_stage,
                    $lead->temperature,
                    $lead->service_type,
                    $lead->route,
                    $lead->potensi_revenue,
                    $lead->probability,
                    $lead->expected_closing?->format('Y-m-d'),
                    $lead->salesUser?->name,
                    $lead->lead_source,
                    $lead->created_at->format('Y-m-d H:i'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Import CSV ──
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $file    = $request->file('file');
        $handle  = fopen($file->getRealPath(), 'r');
        $header  = fgetcsv($handle); // skip header
        $imported = 0;
        $errors  = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 5) continue;

            try {
                // Cari sales user by name
                $salesUser = User::where('name', trim($row[12] ?? ''))->first();

                Lead::create([
                    'lead_code'      => Lead::generateLeadCode(),
                    'company_name'   => trim($row[1] ?? ''),
                    'pic_name'       => trim($row[2] ?? ''),
                    'phone'          => trim($row[3] ?? ''),
                    'email'          => trim($row[4] ?? ''),
                    'pipeline_stage' => in_array(trim($row[5] ?? ''), ['Identifying', 'Approaching', 'Follow Up', 'Closing', 'Won', 'Lost', 'Maintaining']) ? trim($row[5]) : 'Identifying',
                    'temperature'    => in_array(trim($row[6] ?? ''), ['Hot', 'Warm', 'Cold']) ? trim($row[6]) : 'Cold',
                    'service_type'   => trim($row[7] ?? ''),
                    'route'          => trim($row[8] ?? ''),
                    'potensi_revenue' => is_numeric($row[9] ?? '') ? $row[9] : 0,
                    'probability'    => is_numeric($row[10] ?? '') ? $row[10] : 0,
                    'expected_closing' => !empty($row[11]) ? $row[11] : null,
                    'user_id'  => $salesUser?->id,
                    'lead_source'    => trim($row[13] ?? ''),
                ]);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = 'Baris ' . ($imported + count($errors) + 2) . ': ' . $e->getMessage();
            }
        }

        fclose($handle);

        $msg = "Berhasil import {$imported} leads.";
        if ($errors) $msg .= ' ' . count($errors) . ' baris gagal.';

        return redirect()->route('leads.index')->with('success', $msg);
    }
}
