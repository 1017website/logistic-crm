<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::getAll();
        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name'  => 'required|string|max:255',
            'company_email' => 'required|email|max:255',
            'company_phone' => 'nullable|string|max:20',
        ]);

        $fields = [
            'company_name', 'company_email', 'company_phone',
            'company_address', 'company_website', 'currency',
            'timezone', 'date_format', 'language',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                Setting::set($field, $request->input($field));
            }
        }

        // Notification toggles (checkbox — tidak ada = false)
        $notifFields = ['notif_overdue','notif_new_lead','notif_deal_won','notif_followup','notif_stage','notif_weekly','notif_target'];
        foreach ($notifFields as $field) {
            Setting::set($field, $request->has($field) ? '1' : '0');
        }

        return redirect()->route('settings.index')->with('success', 'Settings berhasil disimpan.');
    }
}

