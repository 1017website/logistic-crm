<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        // Simulasi config — bisa diganti dengan DB/config table
        $settings = [
            'company_name'    => 'PT. Logistic Service Indonesia',
            'company_email'   => 'info@logisticservice.co.id',
            'company_phone'   => '+62 21 1234 5678',
            'company_address' => 'Jl. Raya Logistik No. 88, Jakarta Utara',
            'currency'        => 'IDR',
            'timezone'        => 'Asia/Jakarta',
            'logo'            => null,
        ];

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name'  => 'required|string|max:255',
            'company_email' => 'required|email',
            'company_phone' => 'nullable|string|max:20',
        ]);

        // Di sini logika save ke DB/config — untuk sekarang redirect dengan success
        return redirect()->route('settings.index')->with('success', 'Settings berhasil disimpan.');
    }
}
