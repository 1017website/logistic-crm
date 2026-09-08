<?php

namespace Tests\Feature;

use App\Models\LoadingOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LoadingOrderWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role): User
    {
        return User::create(['name' => 'SPM Tester', 'email' => uniqid().'@example.test', 'password' => 'password', 'role' => $role, 'status' => 'Active']);
    }

    public function test_english_template_can_be_edited_saved_and_exported(): void
    {
        $this->actingAs($this->user('Sales Executive'));
        $form = $this->get(route('loading-orders.create', ['language' => 'en']))->assertOk()->assertSee('LOADING ORDER');
        $payload = [...$form->viewData('order')->getAttributes(),
            'letter_date' => '2026-09-08', 'recipient' => 'PT. Catur Sentosa Adiprana Tbk.',
            'route' => 'Subang - Pekanbaru', 'driver_name' => 'Mulyana',
            'vehicle_number' => 'B 9515 YB', 'vehicle_type' => '35-ton Trailer',
        ];
        $this->post(route('loading-orders.store'), $payload)->assertSessionHasNoErrors();
        $order = LoadingOrder::latest('id')->firstOrFail();
        $this->assertSame('en', $order->language);
        $this->assertStringContainsString('The driver is fully responsible', $order->terms);
        $order->update(['company' => [
            'name' => 'PT Firman Tangguh Logistik', 'address' => "Griya Kebraon Barat Blok CK Nomor 23 RT 002/RW 009\nKebraon Karang pilang Surabaya 60222",
            'phone' => '031-76800968', 'website' => 'www.ft-logistik.com', 'email' => 'info@ft-logistik.com', 'logo' => null,
        ]]);
        $html = view('loading_orders.pdf', ['order' => $order, 'company' => $order->company,
            'signatureQr' => '', 'verificationUrl' => 'https://example.test',
        ])->render();
        $this->assertStringContainsString('Terms and Conditions', $html);
        $this->assertStringContainsString('This document is electronically signed by:', $html);
        $this->assertStringNotContainsString('Dengan Hormat', $html);
        $pdf = $this->get(route('loading-orders.pdf', $order))->assertOk()->assertHeader('content-type', 'application/pdf');
        if (getenv('SPM_REVIEW_PDF')) {
            file_put_contents(base_path('tmp/pdfs/spm-en-review.pdf'), $pdf->getContent());
        }
        $this->put(route('loading-orders.update', $order), [...$payload, 'opening' => 'Please load the cargo as detailed below.'])->assertSessionHasNoErrors();
        $this->assertSame('Please load the cargo as detailed below.', $order->fresh()->opening);
        $this->assertSame('en', $order->fresh()->language);
        $this->get(route('loading-orders.create', ['language' => 'invalid']))->assertSessionHasErrors('language');
    }

    public function test_independent_letter_workflow_and_versioned_signature(): void
    {
        $creator = $this->user('Sales Executive');
        $creator->update(['position' => 'Marketing Executive']);
        $this->actingAs($creator);
        $form = $this->get(route('loading-orders.create'))->assertOk();
        $defaults = $form->viewData('order');
        $this->assertSame($creator->name, $defaults->signatory_name);
        $this->assertSame('Marketing Executive', $defaults->signatory_title);
        $payload = array_merge($defaults->getAttributes(), [
            'letter_date' => '2026-06-26', 'recipient' => 'PT. Catur Sentosa Adiprana Tbk.',
            'route' => 'CALS Pekanbaru', 'po_number' => 'PDBL00154209', 'mod_number' => 'MORD-26P1-00011531',
            'driver_name' => 'Mulyana', 'vehicle_number' => 'B 9515 YB', 'driver_phone' => '0852-1543-9696',
            'vehicle_type' => 'Trailer 35 Ton', 'signatory_name' => 'Anggi Sanjaya',
        ]);
        $this->post(route('loading-orders.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $order = LoadingOrder::latest('id')->firstOrFail();
        $this->assertStringStartsWith('SPM-2606-', $order->number);
        $this->assertSame($payload['recipient'], $order->recipient);
        $this->assertEquals($creator->id, $order->created_by);
        $this->assertSame($creator->name, $order->signatory_name);
        $this->assertSame('Marketing Executive', $order->signatory_title);
        $this->get(route('loading-orders.index', ['search' => $order->number]))->assertOk()->assertSee($order->number);
        $pdf = $this->get(route('loading-orders.pdf', [$order, 'download' => 1]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        if (getenv('SPM_REVIEW_PDF')) {
            file_put_contents(base_path('tmp/pdfs/spm-review.pdf'), $pdf->getContent());
        }
        $url = URL::signedRoute('documents.verify', ['kind' => 'loading_order', 'id' => $order->id, 'version' => $order->fingerprint()], absolute: false);
        auth()->forgetGuards();
        $this->get($url)->assertOk()->assertSee($creator->name)->assertSee('Marketing Executive');
        $this->get($url.'&tampered=1')->assertForbidden();
        $this->actingAs($this->user('Transport Planner'));
        $this->put(route('loading-orders.update', $order), [...$payload, 'driver_name' => 'Driver Pengganti'])->assertSessionHasNoErrors();
        $this->assertSame('Driver Pengganti', $order->fresh()->driver_name);
        $this->assertEquals($creator->id, $order->fresh()->created_by);
        $this->assertSame($creator->name, $order->fresh()->signatory_name);
        $this->assertSame('Marketing Executive', $order->fresh()->signatory_title);
        $edit = $this->get(route('loading-orders.edit', $order))->assertOk();
        if (getenv('SPM_REVIEW_HTML')) {
            file_put_contents(base_path('tmp/spm-form-review.html'), $edit->getContent());
            file_put_contents(base_path('tmp/spm-index-review.html'), $this->get(route('loading-orders.index'))->getContent());
        }
        $this->get($url)->assertStatus(410);
        $freshUrl = URL::signedRoute('documents.verify', ['kind' => 'loading_order', 'id' => $order->id, 'version' => $order->fresh()->fingerprint()], absolute: false);
        $this->get($freshUrl)->assertOk();
        $this->put(route('loading-orders.update', $order), [...$payload, 'term_items' => ['Syarat pertama', 'Syarat kedua']])->assertSessionHasNoErrors();
        $this->assertSame("Syarat pertama\nSyarat kedua", $order->fresh()->terms);
        $this->put(route('loading-orders.update', $order), [...$payload, 'recipient' => ''])->assertSessionHasErrors('recipient');
    }

    public function test_all_roles_except_finance_can_access_and_finance_cannot_bypass_menu(): void
    {
        $order = LoadingOrder::create(['number' => 'SPM-TEST-'.uniqid(), 'letter_date' => today(), 'company' => ['name' => 'Test']]);
        foreach (User::ROLES as $role) {
            $user = $this->user($role);
            $this->actingAs($user);
            if ($role !== 'Finance') {
                $this->assertSame($role, $this->get(route('loading-orders.create'))->viewData('order')->signatory_title);
            }
            $this->assertSame($role !== 'Finance', $user->canAccess('loading_orders'));
            foreach (['loading-orders.index', 'loading-orders.create'] as $route) {
                $response = $this->get(route($route));
                $role === 'Finance' ? $response->assertForbidden() : $response->assertOk()->assertSee('Surat Perintah Muat');
            }
        }
        $this->post(route('loading-orders.store'), [])->assertForbidden();
        $this->get(route('loading-orders.edit', $order))->assertForbidden();
        $this->put(route('loading-orders.update', $order), [])->assertForbidden();
        $this->get(route('loading-orders.pdf', $order))->assertForbidden();
        auth()->forgetGuards();
        $this->get(route('loading-orders.index'))->assertRedirect(route('login'));
    }
}
