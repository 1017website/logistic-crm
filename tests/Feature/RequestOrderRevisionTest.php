<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\RequestOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RequestOrderRevisionTest extends TestCase
{
    use DatabaseTransactions;

    private function vendor(): Vendor
    {
        return Vendor::create([
            'vendor_code' => uniqid('VEN-'), 'vendor_name' => 'Vendor Armada Test',
            'pic_name' => 'PIC', 'phone' => '0800000000',
            'vendor_type' => 'External', 'status' => 'Active',
        ]);
    }

    public function test_request_vendor_can_be_created_edited_and_carried_to_do(): void
    {
        [$user, $order, $do, $data] = $this->fixture();
        $vendor = $this->vendor();
        $this->actingAs($user)->post(route('request-orders.store'), $data + [
            'vendor_id' => $vendor->id, 'allow_duplicate' => true,
        ])->assertSessionHasNoErrors();
        $created = RequestOrder::where('customer_id', $order->customer_id)->latest('id')->firstOrFail();
        $this->assertSame($vendor->id, $created->vendor_id);
        $this->put(route('request-orders.update', $created), $data + [
            'vendor_id' => null, 'allow_duplicate' => true,
        ])->assertSessionHasNoErrors();
        $this->assertNull($created->fresh()->vendor_id);
        $this->put(route('request-orders.update', $created), $data + [
            'vendor_id' => $vendor->id, 'allow_duplicate' => true,
        ])->assertSessionHasNoErrors();
        $this->get(route('request-orders.edit', $created))->assertJsonPath('vendor_id', $vendor->id);
        $this->get(route('request-orders.index'))->assertOk()->assertSee('id="epVendor"', false)->assertSee('id="addVendorSelect"', false);
        $created->update(['request_status' => 'approval']);
        $this->post(route('request-orders.approve', $created), ['action' => 'approve'])->assertSessionHasNoErrors();
        $issued = DeliveryOrder::where('request_order_id', $created->id)->firstOrFail();
        $this->assertSame($vendor->id, $issued->vendor_id);
        $this->assertSame('external', $issued->assignment_type);
    }

    public function test_missing_vendor_can_be_completed_without_changing_do_progress(): void
    {
        [$user, $order, $do] = $this->fixture();
        $vendor = $this->vendor();
        $do->update(['status' => 'paid', 'invoice_status' => 'paid']);
        $this->actingAs($user)->get(route('delivery-orders.show', $do))->assertOk()->assertSee('Lengkapi Vendor');
        $this->post(route('delivery-orders.vendor', $do), ['vendor_id' => $vendor->id])->assertSessionHasNoErrors();
        $this->assertSame($vendor->id, $do->fresh()->vendor_id);
        $this->assertSame($vendor->id, $order->fresh()->vendor_id);
        $this->assertSame('external', $do->fresh()->assignment_type);
        $this->assertSame('paid', $do->fresh()->status);
        $this->assertSame('paid', $do->fresh()->invoice_status);
        $this->assertSame('90000', $do->fresh()->actual_cost);
        $this->assertSame('assigned', $order->fresh()->request_status);
        $this->assertTrue($do->statusLogs()->where('user_id', $user->id)->where('note', 'like', 'Vendor armada dilengkapi:%')->exists());
        $this->get(route('delivery-orders.show', $do))->assertDontSee('Lengkapi Vendor');
        $this->post(route('delivery-orders.vendor', $do), ['vendor_id' => $vendor->id])->assertSessionHasErrors('vendor_id');
    }

    public function test_complete_vendor_rejects_invalid_conflicting_and_unauthorized_choices(): void
    {
        [$user, $order, $do] = $this->fixture();
        $vendor = $this->vendor();
        $this->actingAs($user)->post(route('delivery-orders.vendor', $do), ['vendor_id' => 0])->assertSessionHasErrors('vendor_id');
        $order->update(['vendor_id' => $vendor->id]);
        $other = $this->vendor();
        $this->post(route('delivery-orders.vendor', $do), ['vendor_id' => $other->id])->assertSessionHasErrors('vendor_id');
        $this->assertNull($do->fresh()->vendor_id);
        $user->update(['role' => 'Finance']);
        $this->actingAs($user->fresh())->post(route('delivery-orders.vendor', $do), ['vendor_id' => $vendor->id])->assertForbidden();
    }

    private function fixture(): array
    {
        $user = User::create([
            'name' => 'Revision Test', 'email' => uniqid('revision-').'@example.test',
            'password' => 'password', 'role' => 'Admin', 'status' => 'Active',
        ]);
        $customer = Customer::create([
            'customer_code' => uniqid('REV-'), 'company_name' => 'Revision Customer',
            'pic_name' => 'PIC', 'phone' => '0800000000', 'user_id' => $user->id,
            'status' => 'Existing',
        ]);
        $data = [
            'customer_id' => $customer->id, 'user_id' => $user->id, 'currency' => 'IDR',
            'order_date' => '2026-09-15', 'no_container' => 'ABCD1234567',
            'sektor' => 'S-17',
        ];
        $order = RequestOrder::create($data + [
            'do_number' => uniqid('RDO-REV-'), 'request_status' => 'assigned',
            'status' => 'In Progress', 'invoice_status' => 'uninvoiced', 'do_approved' => true,
        ]);
        $do = DeliveryOrder::create([
            'do_number' => $order->do_number, 'request_order_id' => $order->id,
            'customer_id' => $customer->id, 'user_id' => $user->id,
            'status' => 'surat_jalan', 'invoice_status' => 'uninvoiced', 'do_date' => '2026-09-15',
            'assignment_type' => 'internal', 'fleet_info' => 'B 1234 AA', 'actual_cost' => 90000,
        ]);
        return [$user, $order, $do, $data];
    }

    public function test_return_allows_pricing_review_and_restores_same_do_on_reapproval(): void
    {
        [$user, $order, $do] = $this->fixture();
        $this->actingAs($user)->get(route('delivery-orders.show', $do))
            ->assertOk()->assertSee('Kode Sektor')->assertSee('S-17')->assertSee('Kembalikan ke RDO');
        $this->post(route('delivery-orders.return-to-request', $do), ['reason' => 'Biaya belum lengkap'])
            ->assertRedirect(route('request-orders.show', $order));
        $this->assertSoftDeleted($do);
        $this->assertSame('finance', $order->fresh()->request_status);
        $this->assertTrue($order->fresh()->pricing_editable);
        $this->assertFalse($order->fresh()->do_approved);
        $this->post(route('job-details.store', $order), [
            'job_name' => 'Trucking', 'job_code' => 'TR', 'riil_biaya' => 90000, 'riil_jual' => 150000,
        ])->assertSessionHasNoErrors();
        $this->post(route('request-orders.finance-review', $order), [
            'action' => 'approve', 'dp_status' => 'not_taken', 'dp_amount' => 0,
        ])->assertSessionHasNoErrors();
        $this->post(route('request-orders.approve', $order), ['action' => 'approve'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, DeliveryOrder::withTrashed()->where('request_order_id', $order->id)->count());
        $restored = DeliveryOrder::findOrFail($do->id);
        $this->assertSame('surat_jalan', $restored->status);
        $this->assertSame('B 1234 AA', $restored->fleet_info);
        $this->assertSame('internal', $restored->assignment_type);
        $this->assertEquals(90000, $restored->actual_cost);
        $this->assertTrue($restored->statusLogs()->where('to_status', 'returned_to_rdo')->exists());
        $this->post(route('request-orders.approve', $order), ['action' => 'approve'])
            ->assertSessionHasErrors('general');
    }

    public function test_return_rejects_progressed_or_invoiced_orders_and_unauthorized_users(): void
    {
        [$user, $order, $do] = $this->fixture();
        $this->actingAs($user);
        foreach (['pickup', 'in_delivery', 'pod', 'verifikasi_pod', 'closed', 'invoiced', 'paid'] as $status) {
            $do->update(['status' => $status]);
            $this->post(route('delivery-orders.return-to-request', $do), ['reason' => 'Koreksi'])
                ->assertSessionHasErrors('general');
            $this->assertSame('assigned', $order->fresh()->request_status);
            $this->assertNotNull(DeliveryOrder::find($do->id));
        }
        $do->update(['status' => 'surat_jalan', 'invoice_status' => 'partial']);
        $this->post(route('delivery-orders.return-to-request', $do), ['reason' => 'Koreksi'])
            ->assertSessionHasErrors('general');
        $do->update(['invoice_status' => 'uninvoiced']);
        $this->post(route('delivery-orders.return-to-request', $do), [])->assertSessionHasErrors('reason');
        $user->update(['role' => 'Finance']);
        $this->actingAs($user->fresh())->post(route('delivery-orders.return-to-request', $do), ['reason' => 'Koreksi'])
            ->assertForbidden();
    }

    public function test_save_warns_on_normalized_duplicate_and_requires_confirmation(): void
    {
        [$user, $order, $do, $data] = $this->fixture();
        $data['no_container'] = ' abcd 1234567 ';
        $this->actingAs($user)->postJson(route('request-orders.store'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('duplicate');
        $this->assertSame(1, RequestOrder::where('customer_id', $order->customer_id)->count());
        $this->postJson(route('request-orders.store'), $data + ['allow_duplicate' => true])
            ->assertOk()->assertJsonPath('redirect', route('request-orders.index'));
        $this->assertSame(2, RequestOrder::where('customer_id', $order->customer_id)->count());
        $this->assertDatabaseHas('request_orders', ['customer_id' => $order->customer_id, 'request_status' => 'verifikasi', 'sektor' => 'S-17']);
    }

    public function test_edit_excludes_itself_and_sector_is_saved_and_displayed(): void
    {
        [$user, $order, $do, $data] = $this->fixture();
        $order->update(['request_status' => 'finance']);
        $data['sektor'] = 'S-18';
        $this->actingAs($user)->putJson(route('request-orders.update', $order), $data)->assertOk();
        $this->assertSame('S-18', $order->fresh()->sektor);
        $this->get(route('request-orders.show', $order))->assertOk()->assertSee('Kode Sektor')->assertSee('S-18');
        $this->get(route('request-orders.edit', $order))->assertOk()->assertJsonPath('sektor', 'S-18');
        $this->get(route('request-orders.index'))->assertOk()->assertDontSee('name="kode_sektor"', false)->assertSee('name="sektor"', false);
        $data['order_date'] = '2026-09-16';
        $this->postJson(route('request-orders.store'), $data)->assertOk();
    }

    public function test_duplicate_matching_uses_vehicle_and_route_but_ignores_empty_fields(): void
    {
        [$user, $order, $do, $data] = $this->fixture();
        $order->update(['no_pol' => 'B 1234 AA', 'muat' => 'Surabaya', 'bongkar' => 'Jakarta']);
        $data['no_container'] = null;
        $data += ['no_pol' => 'b1234aa', 'muat' => ' SURABAYA ', 'bongkar' => 'jakarta'];
        $this->actingAs($user)->postJson(route('request-orders.store'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('duplicate');
        $data['no_pol'] = null;
        $this->postJson(route('request-orders.store'), $data)->assertOk();
    }
}
