<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\RequestOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OperationalAccessWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sales_admin_can_assign_internal_fleet(): void
    {
        [$salesAdmin, , $requestOrder] = $this->makeOrder('dispatch');

        $this->actingAs($salesAdmin)
            ->post(route('request-orders.dispatch', $requestOrder), [
                'assignment_type' => 'internal',
                'fleet_info' => 'B 1234 CRM',
                'driver_name' => 'Driver Internal',
                'driver_phone' => '08123456789',
                'estimated_cost' => 500000,
            ])
            ->assertRedirect();

        $this->assertSame('approval', $requestOrder->fresh()->request_status);
        $this->assertDatabaseHas('order_assignments', [
            'request_order_id' => $requestOrder->id,
            'assignment_type' => 'internal',
            'planned_by' => $salesAdmin->id,
            'approval_status' => 'pending',
        ]);
    }

    public function test_internal_fleet_uses_printed_surat_jalan_without_upload(): void
    {
        [$salesAdmin, $customer, $requestOrder] = $this->makeOrder('assigned');
        $deliveryOrder = $this->makeDeliveryOrder($salesAdmin, $customer, $requestOrder, 'internal');

        $this->actingAs($salesAdmin)
            ->post(route('delivery-orders.surat-jalan', $deliveryOrder), [
                'note' => 'SJ internal sudah dicetak.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $deliveryOrder->refresh();
        $this->assertSame('pickup', $deliveryOrder->status);
        $this->assertNull($deliveryOrder->surat_jalan_file);
    }

    public function test_external_fleet_still_requires_uploaded_surat_jalan(): void
    {
        [$salesAdmin, $customer, $requestOrder] = $this->makeOrder('assigned');
        $deliveryOrder = $this->makeDeliveryOrder($salesAdmin, $customer, $requestOrder, 'external');

        $this->actingAs($salesAdmin)
            ->from(route('delivery-orders.show', $deliveryOrder))
            ->post(route('delivery-orders.surat-jalan', $deliveryOrder))
            ->assertRedirect(route('delivery-orders.show', $deliveryOrder))
            ->assertSessionHasErrors('surat_jalan_file');

        $this->assertSame('surat_jalan', $deliveryOrder->fresh()->status);
    }

    private function makeOrder(string $requestStatus): array
    {
        $salesAdmin = User::create([
            'name' => 'Sales Admin Operasional',
            'email' => 'sales-admin-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Sales Admin',
            'status' => 'Active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'CUST-' . uniqid(),
            'company_name' => 'Customer Operasional Test',
            'pic_name' => 'PIC',
            'phone' => '0800000000',
            'user_id' => $salesAdmin->id,
        ]);
        $requestOrder = RequestOrder::create([
            'do_number' => 'RDO-' . uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $salesAdmin->id,
            'status' => 'In Progress',
            'request_status' => $requestStatus,
            'order_date' => now()->toDateString(),
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
        ]);

        return [$salesAdmin, $customer, $requestOrder];
    }

    private function makeDeliveryOrder(
        User $user,
        Customer $customer,
        RequestOrder $requestOrder,
        string $assignmentType
    ): DeliveryOrder {
        return DeliveryOrder::create([
            'do_number' => 'DO-' . uniqid(),
            'request_order_id' => $requestOrder->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'surat_jalan',
            'invoice_status' => 'uninvoiced',
            'assignment_type' => $assignmentType,
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
            'do_date' => now()->toDateString(),
        ]);
    }
}
