<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class QuotationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sales_user_can_create_and_download_a_quotation_pdf(): void
    {
        $user = User::create([
            'name' => 'Sales Penawaran',
            'email' => 'quotation-' . uniqid() . '@example.test',
            'password' => 'password',
            'phone' => '082244443085',
            'position' => 'Sales Executive',
            'role' => 'Sales Executive',
            'status' => 'Active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'CUST-' . uniqid(),
            'company_name' => 'PT Garam Test',
            'pic_name' => 'Pimpinan',
            'phone' => '031000000',
            'address' => 'Gresik',
            'user_id' => $user->id,
        ]);

        $payload = [
            'customer_id' => $customer->id,
            'quotation_date' => '2026-08-03',
            'recipient_name' => 'Yth.',
            'recipient_title' => 'Bpk/Ibu Pimpinan',
            'company_name' => 'PT Garam Test',
            'recipient_address' => 'Di tempat.',
            'attachment' => '-',
            'subject' => 'Surat Penawaran Harga',
            'opening' => null,
            'terms' => ['Tarif termasuk biaya buruh.', 'Pembayaran 14 hari setelah invoice.'],
            'closing' => null,
            'contact_name' => 'Sales Penawaran',
            'contact_phone' => '082244443085',
            'city' => 'Surabaya',
            'signatory_name' => 'Anggi Sanjaya',
            'signatory_title' => 'Direktur',
            'status' => 'draft',
            'items' => [[
                'origin' => 'Gresik Segoromadu',
                'destination' => 'Sidoarjo',
                'commodity' => 'Garam halus',
                'tonnage' => '10 Ton',
                'unit' => 'Fuso Three Way',
                'rate' => 2500000,
            ]],
        ];

        $this->actingAs($user)
            ->post(route('quotations.store'), $payload)
            ->assertRedirect(route('quotations.index'));

        $quotation = Quotation::with('items')->sole();
        $this->assertSame('SPH-2608-' . str_pad((string) $quotation->id, 4, '0', STR_PAD_LEFT), $quotation->quotation_number);
        $this->assertCount(1, $quotation->items);
        $this->assertSame('Gresik Segoromadu', $quotation->items->first()->origin);

        $response = $this->actingAs($user)->get(route('quotations.pdf', $quotation));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $response->assertDownload('Surat-Penawaran-' . $quotation->quotation_number . '.pdf');
    }

    public function test_finance_user_cannot_access_quotation_menu(): void
    {
        $finance = User::create([
            'name' => 'Finance Penawaran',
            'email' => 'finance-quotation-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Finance',
            'status' => 'Active',
        ]);

        $this->actingAs($finance)->get(route('quotations.index'))->assertForbidden();
    }
}
