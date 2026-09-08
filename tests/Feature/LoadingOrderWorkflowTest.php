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

    public function test_independent_letter_workflow_and_versioned_signature(): void
    {
        $this->actingAs($this->user('Sales Executive'));
        $form = $this->get(route('loading-orders.create'))->assertOk();
        $defaults = $form->viewData('order');
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
        $this->get(route('loading-orders.index', ['search' => $order->number]))->assertOk()->assertSee($order->number);
        $pdf = $this->get(route('loading-orders.pdf', [$order, 'download' => 1]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        if (getenv('SPM_REVIEW_PDF')) {
            file_put_contents(base_path('tmp/pdfs/spm-review.pdf'), $pdf->getContent());
        }
        $url = URL::signedRoute('documents.verify', ['kind' => 'loading_order', 'id' => $order->id, 'version' => $order->fingerprint()], absolute: false);
        auth()->forgetGuards();
        $this->get($url)->assertOk()->assertSee('Anggi Sanjaya');
        $this->get($url.'&tampered=1')->assertForbidden();
        $this->actingAs($this->user('Transport Planner'));
        $this->put(route('loading-orders.update', $order), [...$payload, 'driver_name' => 'Driver Pengganti'])->assertSessionHasNoErrors();
        $this->assertSame('Driver Pengganti', $order->fresh()->driver_name);
        $this->get($url)->assertStatus(410);
        $freshUrl = URL::signedRoute('documents.verify', ['kind' => 'loading_order', 'id' => $order->id, 'version' => $order->fresh()->fingerprint()], absolute: false);
        $this->get($freshUrl)->assertOk();
        $this->put(route('loading-orders.update', $order), [...$payload, 'recipient' => ''])->assertSessionHasErrors('recipient');
    }

    public function test_all_roles_except_finance_can_access_and_finance_cannot_bypass_menu(): void
    {
        $order = LoadingOrder::create(['number' => 'SPM-TEST-'.uniqid(), 'letter_date' => today(), 'company' => ['name' => 'Test']]);
        foreach (User::ROLES as $role) {
            $user = $this->user($role);
            $this->actingAs($user);
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
