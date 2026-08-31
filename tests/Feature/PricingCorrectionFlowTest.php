<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\OrderJobDetail;
use App\Models\RequestOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Jalur koreksi harga setelah DO final terbit.
 *
 * Sebelumnya harga beku begitu Request DO berstatus assigned, sementara DO tidak
 * dapat ditutup selama harga belum disetujui — kombinasi yang membuat DO dengan
 * harga salah tertahan permanen. Sekarang Sales Manager membuka kuncinya lewat
 * Unapprove, dan kunci tertutup lagi saat harga disetujui.
 */
class PricingCorrectionFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pricing_stays_frozen_after_do_is_issued_until_manager_unlocks_it(): void
    {
        [$finance, $manager, $requestOrder] = $this->makeIssuedDo();

        // Kondisi awal: DO sudah terbit, harga belum disetujui, kunci masih tertutup.
        $this->assertFalse($requestOrder->pricing_editable);

        $this->actingAs($finance)
            ->post(route('job-details.store', $requestOrder), [
                'job_name' => 'Percobaan sebelum kunci dibuka',
                'riil_biaya' => 100000,
                'riil_jual' => 120000,
            ])
            ->assertUnprocessable();

        // Sales Manager membuka kunci koreksi lewat Unapprove.
        $this->actingAs($manager)
            ->post(route('request-orders.approve-do', $requestOrder), ['action' => 'unapprove'])
            ->assertSessionHas('success', fn(string $msg) => str_contains($msg, 'kunci koreksi harga dibuka'));

        $requestOrder->refresh();
        $this->assertTrue($requestOrder->price_correction_open);
        $this->assertTrue($requestOrder->pricing_editable);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $finance->id,
            'type' => 'request_do_price_correction_open',
        ]);

        // Finance sekarang dapat memperbaiki harga, dan Manager diberi tahu.
        $this->actingAs($finance)
            ->post(route('job-details.store', $requestOrder), [
                'job_name' => 'Trucking harga revisi',
                'job_code' => 'TR',
                'riil_biaya' => 900000,
                'riil_jual' => 1400000,
            ])
            ->assertSessionHas('success', fn(string $msg) => str_contains($msg, 'menunggu approve ulang'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'request_do_price_correction',
        ]);
        $this->assertDatabaseHas('order_status_logs', [
            'loggable_type' => $requestOrder->getMorphClass(),
            'loggable_id' => $requestOrder->id,
            'to_status' => 'price_correction',
        ]);

        // Approve harga menutup kembali kuncinya.
        $this->actingAs($manager)
            ->post(route('request-orders.approve-do', $requestOrder), ['action' => 'approve'])
            ->assertSessionHas('success');

        $requestOrder->refresh();
        $this->assertTrue($requestOrder->do_approved);
        $this->assertFalse($requestOrder->price_correction_open);
        $this->assertFalse($requestOrder->pricing_editable);
    }

    public function test_do_can_be_closed_after_price_is_corrected_and_reapproved(): void
    {
        [$finance, $manager, $requestOrder, $deliveryOrder] = $this->makeIssuedDo(returnDo: true);
        $deliveryOrder->update(['status' => 'verifikasi_pod']);
        // Penutupan DO adalah wewenang Sales Admin, bukan Finance.
        $salesAdmin = $this->makeUser('Sales Admin');

        // Tanpa approval harga, penutupan DO ditolak.
        $this->actingAs($salesAdmin)
            ->post(route('delivery-orders.close', $deliveryOrder), ['actual_cost' => 900000])
            ->assertSessionHasErrors('general');
        $this->assertSame('verifikasi_pod', $deliveryOrder->fresh()->status);

        $this->actingAs($manager)->post(route('request-orders.approve-do', $requestOrder), ['action' => 'unapprove']);
        $this->actingAs($finance)->post(route('job-details.store', $requestOrder), [
            'job_name' => 'Trucking harga revisi',
            'job_code' => 'TR',
            'riil_biaya' => 900000,
            'riil_jual' => 1400000,
        ])->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('request-orders.approve-do', $requestOrder), ['action' => 'approve']);

        $this->actingAs($salesAdmin)
            ->post(route('delivery-orders.close', $deliveryOrder), ['actual_cost' => 950000, 'other_cost' => 50000])
            ->assertSessionHasNoErrors();

        $this->assertSame('closed', $deliveryOrder->fresh()->status);
    }

    public function test_unapprove_before_do_is_issued_does_not_open_correction_lock(): void
    {
        $manager = $this->makeUser('Sales Manager');
        $requestOrder = $this->makeRequestOrder($manager, 'approval');

        $this->actingAs($manager)
            ->post(route('request-orders.approve-do', $requestOrder), ['action' => 'unapprove'])
            ->assertSessionHas('success', 'Approval DO dibatalkan.');

        $requestOrder->refresh();
        $this->assertFalse($requestOrder->price_correction_open);
        // Tahap approval memang sudah boleh edit harga, tanpa perlu kunci koreksi.
        $this->assertTrue($requestOrder->pricing_editable);
    }

    public function test_billed_request_can_never_be_edited_even_with_correction_lock(): void
    {
        [$finance, $manager, $requestOrder] = $this->makeIssuedDo();
        $this->actingAs($manager)->post(route('request-orders.approve-do', $requestOrder), ['action' => 'unapprove']);
        $requestOrder->refresh();
        $this->assertTrue($requestOrder->pricing_editable);

        $requestOrder->update(['invoice_status' => 'invoiced']);

        $this->assertFalse($requestOrder->fresh()->pricing_editable);
        $this->actingAs($finance)
            ->post(route('job-details.store', $requestOrder), [
                'job_name' => 'Tidak boleh tersimpan',
                'riil_biaya' => 1, 'riil_jual' => 2,
            ])
            ->assertUnprocessable();
    }

    /** @return array{0:User,1:User,2:RequestOrder,3?:DeliveryOrder} */
    private function makeIssuedDo(bool $returnDo = false): array
    {
        $finance = $this->makeUser('Finance');
        $manager = $this->makeUser('Sales Manager');
        $requestOrder = $this->makeRequestOrder($finance, 'assigned');

        OrderJobDetail::create([
            'request_order_id' => $requestOrder->id,
            'job_name' => 'Trucking', 'job_code' => 'TR',
            'riil_biaya' => 700000, 'riil_jual' => 1000000,
        ]);

        $deliveryOrder = DeliveryOrder::create([
            'do_number' => $requestOrder->do_number,
            'request_order_id' => $requestOrder->id,
            'customer_id' => $requestOrder->customer_id,
            'user_id' => $finance->id,
            'status' => 'in_delivery',
            'invoice_status' => 'uninvoiced',
            'origin' => 'Surabaya', 'destination' => 'Jakarta',
            'do_date' => '2026-08-01',
            'pod_at' => '2026-08-05 09:00:00',
        ]);

        return $returnDo
            ? [$finance, $manager, $requestOrder, $deliveryOrder]
            : [$finance, $manager, $requestOrder];
    }

    private function makeRequestOrder(User $owner, string $requestStatus): RequestOrder
    {
        $customer = Customer::create([
            'customer_code' => 'CUST-PC-' . uniqid(),
            'invoice_code' => 'PC' . strtoupper(substr(uniqid(), -5)),
            'company_name' => 'Customer Koreksi Harga',
            'pic_name' => 'PIC',
            'phone' => '0800000006',
            'user_id' => $owner->id,
        ]);

        return RequestOrder::create([
            'do_number' => 'RDO-PC-' . uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'status' => 'In Progress',
            'request_status' => $requestStatus,
            'order_date' => '2026-08-01',
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
            'do_approved' => false,
            'invoice_status' => 'uninvoiced',
        ]);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => $role . ' Koreksi Harga',
            'email' => strtolower(str_replace(' ', '-', $role)) . '-pc-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'Active',
        ]);
    }
}
