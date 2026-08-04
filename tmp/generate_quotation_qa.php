<?php

use App\Http\Controllers\QuotationController;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

DB::beginTransaction();

try {
    $user = User::query()->whereIn('role', ['Admin', 'Sales Manager', 'Sales Executive', 'Sales Admin'])->first()
        ?: User::create([
            'name' => 'Sales QA',
            'email' => 'sales-qa@example.test',
            'password' => 'password',
            'phone' => '0822-4444-3085',
            'position' => 'Sales Executive',
            'role' => 'Sales Executive',
            'status' => 'Active',
        ]);

    Auth::login($user);
    $quotation = Quotation::create([
        'quotation_number' => 'SPH-2608-QA01',
        'user_id' => $user->id,
        'quotation_date' => '2026-08-03',
        'recipient_name' => 'Yth.',
        'recipient_title' => 'Bpk/Ibu Pimpinan',
        'company_name' => 'PT. GARAM',
        'recipient_address' => 'Di tempat.',
        'attachment' => '-',
        'subject' => 'Surat Penawaran Harga',
        'terms' => [
            'Tarif di atas sudah termasuk biaya buruh dan asuransi.',
            'Hal-hal yang bersifat Force Majeure, kami dibebaskan dari klaim. Contoh: tsunami, gunung meletus, kebanjiran, tanah longsor, dan lain-lain.',
            'Pengiriman barang harap dikonfirmasikan terlebih dahulu.',
            'Pembayaran oleh pihak PENGIRIM.',
            'Pembayaran dilakukan 14 hari setelah terima invoice.',
            'Harga tersebut sudah termasuk PPN 1,1%.',
        ],
        'contact_name' => 'Bapak Anggi Sanjaya',
        'contact_phone' => '0822-4444-3085',
        'city' => 'Surabaya',
        'signatory_name' => 'Anggi Sanjaya',
        'signatory_title' => 'Direktur',
        'status' => 'draft',
    ]);
    $quotation->items()->create([
        'origin' => 'Gresik Segoromadu',
        'destination' => 'Sidoarjo',
        'commodity' => 'Garam halus',
        'tonnage' => '10 Ton',
        'unit' => 'Fuso Three Way',
        'rate' => 2500000,
        'sort_order' => 0,
    ]);

    $response = app(QuotationController::class)->pdf($quotation);
    file_put_contents(__DIR__ . '/quotation-qa.pdf', $response->getContent());
} finally {
    DB::rollBack();
}
