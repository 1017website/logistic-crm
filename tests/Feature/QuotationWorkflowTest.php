<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
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
            'pic_name' => 'Budi Santoso',
            'pic_position' => 'Purchasing',
            'phone' => '031000000',
            'email' => 'budi@garam.test',
            'address' => 'Gresik',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('quotations.create'))
            ->assertOk()
            ->assertSee('Data customer berhasil dimuat')
            ->assertSee('Nama perusahaan, PIC/jabatan, dan alamat akan terisi otomatis')
            ->assertSee('Nomor Surat')
            ->assertSee('Otomatis setelah disimpan');

        $payload = [
            'customer_id' => $customer->id,
            'quotation_date' => '2026-08-03',
            'recipient_name' => 'Yth.',
            'recipient_title' => 'Bpk/Ibu Pimpinan',
            'company_name' => '',
            'recipient_address' => '',
            'attachment' => '1 Berkas',
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

        $quotation = Quotation::with('items')->where('user_id', $user->id)->sole();
        $this->assertSame('SPH-2608-' . str_pad((string) $quotation->id, 4, '0', STR_PAD_LEFT), $quotation->quotation_number);
        $this->assertCount(1, $quotation->items);
        $this->assertSame('Gresik Segoromadu', $quotation->items->first()->origin);
        $this->assertSame('PT Garam Test', $quotation->company_name);
        $this->assertSame('Bpk/Ibu Budi Santoso — Purchasing', $quotation->recipient_title);
        $this->assertSame('Gresik', $quotation->recipient_address);

        $this->actingAs($user)
            ->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSee($quotation->quotation_number)
            ->assertSee('PT Garam Test')
            ->assertSee('Gresik Segoromadu')
            ->assertSee('Rp 2.500.000');

        $response = $this->actingAs($user)->get(route('quotations.pdf', $quotation));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $response->assertDownload('Surat-Penawaran-' . $quotation->quotation_number . '.pdf');

        $preview = $this->actingAs($user)->get(route('quotations.preview', $quotation));
        $preview->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('inline;', (string) $preview->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', $preview->getContent());

        $this->actingAs($user)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertSee('Print Preview')
            ->assertSee(route('quotations.preview', $quotation), false);

        $verificationUrl = URL::signedRoute('documents.verify', [
            'kind' => 'quotation',
            'id' => $quotation->id,
        ], absolute: false);
        $this->get($verificationUrl)
            ->assertOk()
            ->assertSee('Dokumen Terverifikasi')
            ->assertSee($quotation->quotation_number)
            ->assertSee('Nomor surat')
            ->assertSee('Lampiran')
            ->assertSee('1 Berkas')
            ->assertSee('Perihal')
            ->assertSee('Surat Penawaran Harga')
            ->assertSee('Anggi Sanjaya');
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

    public function test_quotation_signature_uses_the_signers_user_position(): void
    {
        $signer = User::create([
            'name' => 'Rahma Fitri Yeni',
            'email' => 'rahma-signature-' . uniqid() . '@example.test',
            'phone' => '08116611202',
            'password' => 'password',
            'position' => 'Sales Manager',
            'role' => 'Sales Executive',
            'status' => 'Active',
        ]);

        $quotation = Quotation::create([
            'quotation_number' => 'SPH-TEST-' . uniqid(),
            'user_id' => $signer->id,
            'quotation_date' => '2026-09-05',
            'recipient_name' => 'Yth.',
            'recipient_title' => 'Bpk/Ibu Pimpinan',
            'company_name' => 'PT Customer Test',
            'attachment' => '-',
            'subject' => 'Surat Penawaran Harga',
            'city' => 'Surabaya',
            'signatory_name' => 'Rahma Fitri Yeni',
            'signatory_title' => 'PROJECT',
            'status' => 'draft',
        ]);

        $this->assertSame('Sales Manager', $quotation->resolvedSignatoryTitle());
        $this->assertSame('08116611202', $quotation->resolvedSignatoryPhone());

        $html = view('quotations.pdf', [
            'quotation' => $quotation,
            'company' => ['name' => 'PT Print Test', 'logo' => '', 'address' => '', 'phone' => '', 'email' => '', 'website' => ''],
            'signatureQr' => 'data:image/png;base64,',
            'verificationUrl' => 'https://example.test/verify',
        ])->render();
        $this->assertStringContainsString('No. HP: 08116611202', $html);

        $quotation->signatory_name = 'External Signer';
        $quotation->contact_name = 'Different Contact';
        $quotation->contact_phone = '08999999999';
        $this->assertSame('', $quotation->resolvedSignatoryPhone());
        $quotation->contact_name = 'External Signer';
        $this->assertSame('08999999999', $quotation->resolvedSignatoryPhone());
        $quotation->signatory_name = 'Rahma Fitri Yeni';

        $verificationUrl = URL::signedRoute('documents.verify', [
            'kind' => 'quotation',
            'id' => $quotation->id,
        ], absolute: false);

        $this->get($verificationUrl)
            ->assertOk()
            ->assertSee('Rahma Fitri Yeni')
            ->assertSee('Sales Manager')
            ->assertDontSee('PROJECT');
    }
}
