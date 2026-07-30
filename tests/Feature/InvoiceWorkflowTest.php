<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\OrderJobDetail;
use App\Models\RequestOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InvoiceWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_combined_invoice_can_contain_multiple_types_and_duplicate_token_is_rejected(): void
    {
        [$user, $customer, $deliveryOrder] = $this->makePodReadyOrder();
        $payload = $this->invoicePayload($customer, $deliveryOrder, 'combined');
        $payload['_action_token'] = 'invoice_action_token_123456';

        $this->actingAs($user)
            ->from(route('invoices.index'))
            ->post(route('invoices.store'), $payload)
            ->assertRedirect(route('invoices.index', ['tab' => 'draft']));

        $invoice = Invoice::with('items')->sole();
        $this->assertSame('MIX', $invoice->jenis);
        $this->assertCount(2, $invoice->items);
        $this->assertSame('invoiced', $deliveryOrder->fresh()->invoice_status);

        $this->actingAs($user)
            ->from(route('invoices.index'))
            ->post(route('invoices.store'), $payload)
            ->assertSessionHas('warning');

        $this->assertSame(1, Invoice::count());
    }

    public function test_separate_mode_creates_one_invoice_per_service_type(): void
    {
        [$user, $customer, $deliveryOrder] = $this->makePodReadyOrder();

        $this->actingAs($user)
            ->post(route('invoices.store'), $this->invoicePayload($customer, $deliveryOrder, 'separate'))
            ->assertRedirect(route('invoices.index', ['tab' => 'draft']));

        $this->assertSame(2, Invoice::count());
        $this->assertEqualsCanonicalizing(['TR', 'NTR'], Invoice::pluck('jenis')->all());
    }

    public function test_paid_invoice_synchronizes_delivery_order_to_paid(): void
    {
        [$user, $customer, $deliveryOrder] = $this->makePodReadyOrder();

        $this->actingAs($user)
            ->post(route('invoices.store'), $this->invoicePayload($customer, $deliveryOrder, 'combined'));

        $invoice = Invoice::sole();
        $deliveryOrder->update(['status' => 'closed']);

        $this->actingAs($user)->post(route('invoices.submit', $invoice));
        $this->actingAs($user)->post(route('invoices.pay', $invoice), [
            'tgl_pencairan' => '2026-07-30',
        ]);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('paid', $deliveryOrder->fresh()->status);
        $this->assertSame('paid', $deliveryOrder->fresh()->invoice_status);
        $this->assertSame('paid', $deliveryOrder->requestOrder->fresh()->invoice_status);
    }

    private function makePodReadyOrder(): array
    {
        $user = User::create([
            'name' => 'Finance Test',
            'email' => 'finance-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Finance',
            'status' => 'Active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'CUST-' . uniqid(),
            'invoice_code' => 'INV' . strtoupper(substr(uniqid(), -5)),
            'company_name' => 'Customer Invoice Test',
            'pic_name' => 'PIC',
            'phone' => '0800000000',
            'user_id' => $user->id,
        ]);
        $requestOrder = RequestOrder::create([
            'do_number' => 'RDO-' . uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'In Progress',
            'request_status' => 'assigned',
            'order_date' => '2026-07-29',
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
            'do_approved' => true,
            'invoice_status' => 'uninvoiced',
        ]);

        OrderJobDetail::create([
            'request_order_id' => $requestOrder->id,
            'job_name' => 'Trucking',
            'job_code' => 'TR',
            'riil_biaya' => 700000,
            'riil_jual' => 1000000,
        ]);
        OrderJobDetail::create([
            'request_order_id' => $requestOrder->id,
            'job_name' => 'Jasa Bongkar',
            'job_code' => 'NTR',
            'riil_biaya' => 100000,
            'riil_jual' => 250000,
        ]);

        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-' . uniqid(),
            'request_order_id' => $requestOrder->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'verifikasi_pod',
            'invoice_status' => 'uninvoiced',
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
            'do_date' => '2026-07-29',
            'pod_at' => '2026-07-30 09:00:00',
        ]);

        return [$user, $customer, $deliveryOrder];
    }

    private function invoicePayload(Customer $customer, DeliveryOrder $deliveryOrder, string $mode): array
    {
        return [
            'customer_id' => $customer->id,
            'tgl_buat' => '2026-07-30',
            'tgl_tempo' => '2026-08-29',
            'selections' => [
                $deliveryOrder->id . ':TR',
                $deliveryOrder->id . ':NTR',
            ],
            'billing_mode' => $mode,
            'ppn_mode' => 'non_ppn',
            'notes' => 'Test invoice',
        ];
    }
}
