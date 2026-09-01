<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\RequestOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
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

    public function test_transport_planner_form_prefills_operational_request_data_and_finance_cost(): void
    {
        [, , $requestOrder] = $this->makeOrder('dispatch');
        $transportPlanner = $this->makeUser('Transport Planner');
        $requestOrder->update([
            'jenis_truck' => 'Trailer 40 Feet',
            'no_pol' => 'B 9876 CRM',
            'supir' => 'Driver dari Request',
            'hp_supir' => '081298765432',
            'keterangan' => 'Gunakan jalur operasional A.',
        ]);
        $requestOrder->items()->create([
            'service_name' => 'Trucking',
            'unit' => 'rit',
            'qty' => 1,
            'buy_price' => 1250000,
            'sell_price' => 1750000,
        ]);

        $this->actingAs($transportPlanner)
            ->get(route('request-orders.show', $requestOrder))
            ->assertOk()
            ->assertSee('Data armada, driver, dan estimasi biaya diambil otomatis')
            ->assertSee('value="B 9876 CRM / Trailer 40 Feet"', false)
            ->assertSee('value="Driver dari Request"', false)
            ->assertSee('value="081298765432"', false)
            ->assertSee('value="1250000"', false)
            ->assertSee('Gunakan jalur operasional A.');
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

    public function test_internal_surat_jalan_qr_opens_public_tracking_with_phone_camera_url(): void
    {
        [$salesAdmin, $customer, $requestOrder] = $this->makeOrder('assigned');
        $deliveryOrder = $this->makeDeliveryOrder($salesAdmin, $customer, $requestOrder, 'internal');
        $trackingPath = URL::signedRoute('delivery-orders.track', [
            'deliveryOrder' => $deliveryOrder,
        ], absolute: false);

        $this->actingAs($salesAdmin)
            ->get(route('delivery-orders.surat-jalan.print', $deliveryOrder))
            ->assertOk()
            ->assertSee('SCAN UNTUK TRACKING')
            ->assertSee('Gunakan kamera ponsel')
            ->assertSee('http://localhost' . $trackingPath)
            ->assertDontSee('Dokumen ini ditandatangani secara elektronik oleh:')
            ->assertDontSee('Pindai QR untuk memverifikasi keaslian dokumen.');

        auth()->logout();

        $this->get($trackingPath)
            ->assertOk()
            ->assertSee('TRACKING SURAT JALAN')
            ->assertSee($deliveryOrder->do_number)
            ->assertSee('Surat Jalan')
            ->assertSee($requestOrder->origin)
            ->assertSee($requestOrder->destination)
            ->assertSee('Tidak diperlukan aplikasi scanner khusus.');

        $this->get(route('delivery-orders.track', $deliveryOrder, absolute: false))
            ->assertForbidden();
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

    public function test_sales_admin_can_mark_request_do_pending_with_required_note(): void
    {
        [$salesAdmin, , $requestOrder] = $this->makeOrder('assigned');

        $this->actingAs($salesAdmin)
            ->post(route('request-orders.operational-status', $requestOrder), [
                'operational_status' => 'pending',
                'operational_note' => 'Menunggu konfirmasi jadwal muat dari customer.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $requestOrder->refresh();
        $this->assertSame('pending', $requestOrder->operational_status);
        $this->assertSame('Menunggu konfirmasi jadwal muat dari customer.', $requestOrder->operational_note);
        $this->assertNotNull($requestOrder->operational_status_changed_at);
        $this->assertSame($salesAdmin->id, $requestOrder->operational_status_changed_by);
        $this->assertDatabaseHas('order_status_logs', [
            'loggable_type' => $requestOrder->getMorphClass(),
            'loggable_id' => $requestOrder->id,
            'from_status' => 'operational_running',
            'to_status' => 'operational_pending',
        ]);

        $this->actingAs($salesAdmin)
            ->get(route('request-orders.index', ['operational_status' => 'pending']))
            ->assertOk()
            ->assertSee('Status DO');

        $this->actingAs($salesAdmin)
            ->get(route('request-orders.show', $requestOrder))
            ->assertOk()
            ->assertSee('Status Operasional DO')
            ->assertSee('Status DO: Pending');
    }

    public function test_inactive_operational_status_requires_explanation(): void
    {
        [$salesAdmin, , $requestOrder] = $this->makeOrder('assigned');

        $this->actingAs($salesAdmin)
            ->from(route('request-orders.show', $requestOrder))
            ->post(route('request-orders.operational-status', $requestOrder), [
                'operational_status' => 'cancelled',
            ])
            ->assertRedirect(route('request-orders.show', $requestOrder))
            ->assertSessionHasErrors('operational_note');

        $this->assertSame('running', $requestOrder->fresh()->operational_status);
    }

    public function test_reschedule_requires_and_stores_new_schedule(): void
    {
        [$salesAdmin, , $requestOrder] = $this->makeOrder('assigned');
        $newSchedule = now()->addDays(3)->toDateString();

        $this->actingAs($salesAdmin)
            ->post(route('request-orders.operational-status', $requestOrder), [
                'operational_status' => 'rescheduled',
                'operational_note' => 'Kapal sebelumnya mengalami perubahan jadwal.',
                'rescheduled_for' => $newSchedule,
            ])
            ->assertRedirect();

        $requestOrder->refresh();
        $this->assertSame('rescheduled', $requestOrder->operational_status);
        $this->assertSame($newSchedule, $requestOrder->rescheduled_for?->toDateString());
    }

    public function test_cancelled_do_can_be_reactivated(): void
    {
        [$salesAdmin, , $requestOrder] = $this->makeOrder('assigned');

        $this->actingAs($salesAdmin)
            ->post(route('request-orders.operational-status', $requestOrder), [
                'operational_status' => 'cancelled',
                'operational_note' => 'Pengiriman dibatalkan oleh customer.',
            ])
            ->assertRedirect();

        $requestOrder->refresh();
        $this->assertSame('cancelled', $requestOrder->operational_status);
        $this->assertSame('Cancelled', $requestOrder->status);

        $this->actingAs($salesAdmin)
            ->post(route('request-orders.operational-status', $requestOrder), [
                'operational_status' => 'running',
            ])
            ->assertRedirect();

        $requestOrder->refresh();
        $this->assertSame('running', $requestOrder->operational_status);
        $this->assertSame('In Progress', $requestOrder->status);
        $this->assertNull($requestOrder->operational_note);
    }

    public function test_request_do_flows_from_sales_admin_to_finance_then_sales_manager(): void
    {
        [$salesAdmin, , $requestOrder] = $this->makeOrder('verifikasi');
        $finance = $this->makeUser('Finance');
        $manager = $this->makeUser('Sales Manager');

        $this->actingAs($salesAdmin)
            ->post(route('request-orders.verify', $requestOrder), [
                'action' => 'approve',
                'note' => 'Data customer dan jadwal sudah lengkap.',
            ])
            ->assertRedirect();

        $this->assertSame('finance', $requestOrder->fresh()->request_status);

        $this->actingAs($finance)
            ->post(route('request-orders.finance-review', $requestOrder), [
                'action' => 'approve',
                'dp_status' => 'taken',
                'dp_amount' => 2500000,
                'dp_note' => 'DP diterima melalui transfer.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $requestOrder->refresh();
        $this->assertSame('approval', $requestOrder->request_status);
        $this->assertSame('taken', $requestOrder->dp_status);
        $this->assertSame('2500000', $requestOrder->dp_amount);
        $this->assertSame($finance->id, $requestOrder->dp_reviewed_by);
        $this->assertNotNull($requestOrder->dp_reviewed_at);

        $this->actingAs($manager)
            ->post(route('request-orders.approve', $requestOrder), [
                'action' => 'approve',
                'note' => 'Disetujui Sales Manager.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('assigned', $requestOrder->fresh()->request_status);
        $this->assertDatabaseHas('delivery_orders', [
            'request_order_id' => $requestOrder->id,
            'customer_id' => $requestOrder->customer_id,
            'do_number' => $requestOrder->do_number,
        ]);

        $this->actingAs($finance)
            ->get(route('request-orders.index'))
            ->assertOk()
            ->assertSee('DP Terambil')
            ->assertSee('Rp 2.500.000');
    }

    public function test_finance_must_enter_dp_amount_when_dp_is_taken(): void
    {
        [, , $requestOrder] = $this->makeOrder('finance');
        $finance = $this->makeUser('Finance');

        $this->actingAs($finance)
            ->from(route('request-orders.show', $requestOrder))
            ->post(route('request-orders.finance-review', $requestOrder), [
                'action' => 'approve',
                'dp_status' => 'taken',
            ])
            ->assertRedirect(route('request-orders.show', $requestOrder))
            ->assertSessionHasErrors('dp_amount');

        $requestOrder->refresh();
        $this->assertSame('finance', $requestOrder->request_status);
        $this->assertSame('pending', $requestOrder->dp_status);
    }

    public function test_finance_job_detail_records_and_displays_input_owner(): void
    {
        [, , $requestOrder] = $this->makeOrder('finance');
        $finance = $this->makeUser('Finance');

        $this->actingAs($finance)
            ->post(route('job-details.store', $requestOrder), [
                'job_name' => 'Trucking Finance Audit',
                'job_code' => 'TR',
                'riil_biaya' => 5000000,
                'riil_jual' => 6000000,
                'status_pembayaran' => 'Tempo',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $jobDetail = $requestOrder->jobDetails()->latest('id')->firstOrFail();
        $this->assertSame($finance->id, $jobDetail->created_by);
        $this->assertSame($finance->id, $jobDetail->updated_by);

        $this->actingAs($finance)
            ->get(route('request-orders.show', $requestOrder))
            ->assertOk()
            ->assertSee('Pembaruan Finance terakhir')
            ->assertSee($finance->name)
            ->assertSee('Diinput ' . $finance->name)
            ->assertSee('Total pada tabel ini hanya berasal dari Item Layanan')
            ->assertSeeInOrder(['Total Revenue', 'Rp 0', 'Nilai jual dan HPP utama berasal dari']);
    }

    public function test_finance_cannot_save_empty_job_detail_or_edit_it_outside_finance_stage(): void
    {
        [, , $requestOrder] = $this->makeOrder('finance');
        $finance = $this->makeUser('Finance');

        $this->actingAs($finance)
            ->from(route('request-orders.show', $requestOrder))
            ->post(route('job-details.store', $requestOrder), [
                'riil_biaya' => 0,
                'riil_jual' => 0,
            ])
            ->assertRedirect(route('request-orders.show', $requestOrder))
            ->assertSessionHasErrors(['pekerjaan_id', 'job_name']);

        $this->assertSame(0, $requestOrder->jobDetails()->count());

        $requestOrder->update(['request_status' => 'assigned']);

        $this->actingAs($finance)
            ->get(route('request-orders.show', $requestOrder))
            ->assertOk()
            ->assertDontSee('id="addJobModal"', false);

        $this->actingAs($finance)
            ->post(route('job-details.store', $requestOrder), [
                'job_name' => 'Tidak boleh tersimpan',
                'riil_biaya' => 100000,
                'riil_jual' => 120000,
            ])
            ->assertUnprocessable();

        $this->assertSame(0, $requestOrder->jobDetails()->count());
    }

    public function test_request_order_relations_have_foreign_keys_and_hide_reused_id_history(): void
    {
        foreach (['delivery_orders', 'invoice_items', 'order_assignments', 'order_job_details', 'request_order_items'] as $table) {
            $hasForeignKey = collect(Schema::getForeignKeys($table))->contains(
                fn (array $foreign) => in_array('request_order_id', $foreign['columns'], true)
                    && $foreign['foreign_table'] === 'request_orders'
            );

            $this->assertTrue($hasForeignKey, $table . ' harus memiliki foreign key ke request_orders.');
        }

        [, , $requestOrder] = $this->makeOrder('finance');
        DB::table('order_job_details')->insert([
            'request_order_id' => $requestOrder->id,
            'job_name' => 'Data dari ID lama',
            'riil_biaya' => 900000,
            'riil_jual' => 1000000,
            'status_pembayaran' => 'Tempo',
            'created_at' => $requestOrder->created_at->copy()->subDay(),
            'updated_at' => $requestOrder->created_at->copy()->subDay(),
        ]);

        $this->assertSame(0, $requestOrder->jobDetails()->count());
    }

    public function test_dp_not_taken_value_is_included_in_request_do_recap(): void
    {
        [, , $requestOrder] = $this->makeOrder('finance');
        $finance = $this->makeUser('Finance');

        $this->actingAs($finance)
            ->post(route('request-orders.finance-review', $requestOrder), [
                'action' => 'approve',
                'dp_status' => 'not_taken',
                'dp_amount' => 3000000,
                'dp_note' => 'Customer tidak mengambil fasilitas DP.',
            ])
            ->assertRedirect();

        $requestOrder->refresh();
        $this->assertSame('not_taken', $requestOrder->dp_status);
        $this->assertSame('3000000', $requestOrder->dp_amount);

        $this->actingAs($finance)
            ->get(route('request-orders.index'))
            ->assertOk()
            ->assertSee('DP Tidak Terambil')
            ->assertSee('Rp 3.000.000');
    }

    public function test_finance_can_input_or_update_dp_after_do_is_issued_without_changing_flow(): void
    {
        [$salesAdmin, $customer, $requestOrder] = $this->makeOrder('assigned');
        $finance = $this->makeUser('Finance');
        $deliveryOrder = $this->makeDeliveryOrder($salesAdmin, $customer, $requestOrder, 'internal');

        $this->actingAs($finance)
            ->get(route('request-orders.index'))
            ->assertOk()
            ->assertDontSee($requestOrder->do_number);

        $this->actingAs($finance)
            ->get(route('delivery-orders.show', $deliveryOrder))
            ->assertOk()
            ->assertSee('Request DP');

        $this->actingAs($finance)
            ->post(route('request-orders.dp.update', $requestOrder), [
                'dp_status' => 'taken',
                'dp_amount' => 1750000,
                'dp_note' => 'DP disusulkan setelah DO terbit.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $requestOrder->refresh();
        $this->assertSame('assigned', $requestOrder->request_status);
        $this->assertSame('taken', $requestOrder->dp_status);
        $this->assertSame('1750000', $requestOrder->dp_amount);
        $this->assertSame($finance->id, $requestOrder->dp_reviewed_by);

        $this->actingAs($finance)
            ->get(route('request-orders.show', $requestOrder))
            ->assertOk()
            ->assertSee('Update DP')
            ->assertSee('Rp 1.750.000');
    }

    public function test_operational_work_details_are_visible_in_request_and_delivery_order_views(): void
    {
        [$salesAdmin, $customer, $requestOrder] = $this->makeOrder('finance');
        $requestOrder->update([
            'muat' => 'Gudang Margomulyo',
            'bongkar' => 'Terminal Tanjung Priok',
            'no_container' => 'CONT-CRM-7788',
            'no_seal' => 'SEAL-CRM-9911',
            'no_pol' => 'L 8123 CRM',
            'supir' => 'Budi Operasional',
        ]);

        $this->actingAs($salesAdmin)
            ->get(route('request-orders.index'))
            ->assertOk()
            ->assertSeeInOrder(['Lokasi Muat', 'Lokasi Bongkar', 'No. Container', 'No. Seal'])
            ->assertSee('Gudang Margomulyo')
            ->assertSee('Terminal Tanjung Priok')
            ->assertSee('CONT-CRM-7788')
            ->assertSee('SEAL-CRM-9911')
            ->assertDontSee('<th class="py-2">Revenue</th>', false)
            ->assertDontSee('<th class="py-2">HPP</th>', false);

        $this->actingAs($salesAdmin)
            ->get(route('request-orders.show', $requestOrder))
            ->assertOk()
            ->assertSee('Gudang Margomulyo')
            ->assertSee('Terminal Tanjung Priok')
            ->assertSee('CONT-CRM-7788')
            ->assertSee('SEAL-CRM-9911')
            ->assertSee('L 8123 CRM')
            ->assertSee('Budi Operasional');

        $deliveryOrder = $this->makeDeliveryOrder($salesAdmin, $customer, $requestOrder, 'internal');

        $this->actingAs($salesAdmin)
            ->get(route('delivery-orders.show', $deliveryOrder))
            ->assertOk()
            ->assertSee('Gudang Margomulyo')
            ->assertSee('Terminal Tanjung Priok')
            ->assertSee('CONT-CRM-7788')
            ->assertSee('SEAL-CRM-9911')
            ->assertSee('L 8123 CRM')
            ->assertSee('Budi Operasional');
    }

    public function test_finance_dp_edit_while_awaiting_manager_is_recorded_as_resubmission(): void
    {
        [, , $requestOrder] = $this->makeOrder('approval');
        $finance = $this->makeUser('Finance');
        $manager = $this->makeUser('Sales Manager');

        $this->actingAs($finance)
            ->get(route('request-orders.show', $requestOrder))
            ->assertOk()
            ->assertSee('Perubahan akan dicatat dan diajukan ulang ke Sales Manager.')
            ->assertSee('Simpan & Ajukan Ulang');

        $this->actingAs($finance)
            ->post(route('request-orders.dp.update', $requestOrder), [
                'dp_status' => 'taken',
                'dp_amount' => 950000,
                'dp_note' => 'Nominal DP diperbarui sebelum approval.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Data DP berhasil diperbarui & diajukan ulang ke Sales Manager.');

        $requestOrder->refresh();
        $this->assertSame('approval', $requestOrder->request_status);
        $this->assertSame('950000', $requestOrder->dp_amount);
        $this->assertDatabaseHas('order_status_logs', [
            'loggable_type' => $requestOrder->getMorphClass(),
            'loggable_id' => $requestOrder->id,
            'from_status' => 'approval',
            'to_status' => 'approval',
            'user_id' => $finance->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'request_do_manager_approval',
            'title' => 'Request DO diajukan ulang',
        ]);
    }

    public function test_finance_can_edit_pricing_details_while_awaiting_manager_and_resubmit(): void
    {
        [, , $requestOrder] = $this->makeOrder('approval');
        $finance = $this->makeUser('Finance');
        $manager = $this->makeUser('Sales Manager');
        $item = $requestOrder->items()->create([
            'service_name' => 'Trucking',
            'unit' => 'rit',
            'qty' => 1,
            'buy_price' => 800000,
            'sell_price' => 1000000,
        ]);

        $this->actingAs($finance)
            ->put(route('request-order-items.update', $item), [
                'service_name' => 'Trucking',
                'unit' => 'rit',
                'qty' => 1,
                'buy_price' => 850000,
                'sell_price' => 1100000,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Item layanan dan harga berhasil diperbarui. Perubahan diajukan ulang ke Sales Manager.');

        $this->assertSame('approval', $requestOrder->fresh()->request_status);
        $this->assertSame('1100000', $item->fresh()->sell_price);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'request_do_manager_approval',
            'title' => 'Request DO diajukan ulang',
        ]);

        $this->actingAs($finance)
            ->post(route('job-details.store', $requestOrder), [
                'job_name' => 'Biaya trucking revisi',
                'riil_biaya' => 850000,
                'riil_jual' => 1100000,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Rincian pekerjaan ditambahkan. Perubahan diajukan ulang ke Sales Manager.');

        $this->assertDatabaseHas('order_job_details', [
            'request_order_id' => $requestOrder->id,
            'job_name' => 'Biaya trucking revisi',
            'updated_by' => $finance->id,
        ]);
    }

    public function test_request_dp_can_be_disabled_and_finance_can_continue_without_dp_input(): void
    {
        [, , $requestOrder] = $this->makeOrder('finance');
        $finance = $this->makeUser('Finance');

        $this->actingAs($finance)->post(route('request-orders.dp-active', $requestOrder), [
            'active' => 0,
            'note' => 'Customer tidak memerlukan DP.',
        ])->assertRedirect();

        $this->assertFalse($requestOrder->fresh()->dp_request_active);
        $this->actingAs($finance)->post(route('request-orders.finance-review', $requestOrder), [
            'action' => 'approve',
            'dp_note' => 'Lanjut tanpa DP.',
        ])->assertRedirect()->assertSessionHas('success');

        $requestOrder->refresh();
        $this->assertSame('approval', $requestOrder->request_status);
        $this->assertSame('not_taken', $requestOrder->dp_status);
        $this->assertSame('0', $requestOrder->dp_amount);
    }

    public function test_cancelled_request_has_dedicated_tab_and_can_be_reactivated(): void
    {
        [$salesAdmin, , $requestOrder] = $this->makeOrder('verifikasi');
        $this->actingAs($salesAdmin)->post(route('request-orders.cancel', $requestOrder), [
            'reason' => 'Customer membatalkan pengiriman.',
        ])->assertRedirect(route('request-orders.index', ['tab' => 'cancelled']));

        $requestOrder->refresh();
        $this->assertSame('cancelled', $requestOrder->request_status);
        $this->assertSame($salesAdmin->id, $requestOrder->cancelled_by);
        $this->actingAs($salesAdmin)->get(route('request-orders.index', ['tab' => 'cancelled']))
            ->assertOk()->assertSee($requestOrder->do_number)->assertSee('Customer membatalkan pengiriman.');

        $this->actingAs($salesAdmin)->post(route('request-orders.reactivate', $requestOrder), [
            'note' => 'Customer mengaktifkan kembali pengiriman.',
        ])->assertRedirect(route('request-orders.index'));
        $this->assertSame('verifikasi', $requestOrder->fresh()->request_status);
    }

    public function test_request_do_form_is_grouped_into_compact_primary_and_optional_sections(): void
    {
        $admin = $this->makeUser('Admin');

        $this->actingAs($admin)
            ->get(route('request-orders.index'))
            ->assertOk()
            ->assertSee('request-primary-grid')
            ->assertSee('request-ops-grid')
            ->assertSee('Data utama')
            ->assertSee('Rute & jadwal', false)
            ->assertSee('Opsional · klik untuk buka')
            ->assertSee('name="alamat"', false)
            ->assertSee('id="epAlamat"', false)
            ->assertDontSee('name="vendor_id"', false)
            ->assertDontSee('id="addVendorSelect"', false)
            ->assertDontSee('id="epVendor"', false)
            ->assertDontSee('name="empty_full"', false)
            ->assertDontSee('name="bongkar_empty_full"', false)
            ->assertDontSee('name="kecamatan"', false)
            ->assertDontSee('name="kelurahan"', false);
    }

    public function test_request_do_saves_single_operational_address_and_ignores_removed_fields(): void
    {
        $admin = $this->makeUser('Admin');
        $customer = Customer::create([
            'customer_code' => 'CUST-' . uniqid(),
            'company_name' => 'Customer Alamat Test',
            'pic_name' => 'PIC',
            'phone' => '0800000001',
            'user_id' => $admin->id,
            'status' => 'Existing',
        ]);

        $this->actingAs($admin)
            ->post(route('request-orders.store'), [
                'customer_id' => $customer->id,
                'user_id' => $admin->id,
                'vendor_id' => 999999,
                'currency' => 'IDR',
                'order_date' => now()->toDateString(),
                'alamat' => 'Jl. Raya Industri No. 10, Surabaya',
                'empty_full' => 'Full',
                'bongkar_empty_full' => 'Empty',
                'kecamatan' => 'Kecamatan Lama',
                'kelurahan' => 'Kelurahan Lama',
            ])
            ->assertRedirect(route('request-orders.index'));

        $requestOrder = RequestOrder::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->assertNull($requestOrder->vendor_id);
        $this->assertSame('Jl. Raya Industri No. 10, Surabaya', $requestOrder->alamat);
        $this->assertNull($requestOrder->empty_full);
        $this->assertNull($requestOrder->bongkar_empty_full);
        $this->assertNull($requestOrder->kecamatan);
        $this->assertNull($requestOrder->kelurahan);
    }

    public function test_sales_manager_can_approve_priced_do_while_sales_executive_cannot(): void
    {
        [$salesAdmin, , $requestOrder] = $this->makeOrder('assigned');
        $salesManager = $this->makeUser('Sales Manager');
        $salesExecutive = $this->makeUser('Sales Executive');

        $requestOrder->items()->create([
            'service_name' => 'Trucking',
            'unit' => 'rit',
            'qty' => 1,
            'buy_price' => 1000000,
            'sell_price' => 1500000,
        ]);

        $this->actingAs($salesManager)
            ->get(route('request-orders.show', $requestOrder))
            ->assertOk()
            ->assertSee('Approve DO')
            ->assertSee('Jual Rp 1.500.000 / HPP Rp 1.000.000');

        $this->actingAs($salesManager)
            ->post(route('request-orders.approve-do', $requestOrder), [
                'action' => 'approve',
                'note' => 'Margin dan biaya telah disetujui Sales Manager.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($requestOrder->fresh()->do_approved);
        $this->assertDatabaseHas('order_status_logs', [
            'loggable_type' => $requestOrder->getMorphClass(),
            'loggable_id' => $requestOrder->id,
            'to_status' => 'do_approved',
            'user_id' => $salesManager->id,
        ]);

        $this->actingAs($salesExecutive)
            ->post(route('request-orders.approve-do', $requestOrder), [
                'action' => 'unapprove',
            ])
            ->assertForbidden();

        $this->assertTrue($requestOrder->fresh()->do_approved);
    }

    public function test_sales_manager_can_reject_incorrect_do_price_and_return_it_to_finance(): void
    {
        [, , $requestOrder] = $this->makeOrder('approval');
        $salesManager = $this->makeUser('Sales Manager');
        $finance = $this->makeUser('Finance');

        $requestOrder->items()->create([
            'service_name' => 'Trucking',
            'unit' => 'rit',
            'qty' => 1,
            'buy_price' => 1000000,
            'sell_price' => 800000,
        ]);

        $this->actingAs($salesManager)
            ->get(route('request-orders.show', $requestOrder))
            ->assertOk()
            ->assertSee('Reject DO')
            ->assertSee('Alasan harga tidak benar')
            ->assertSee('Reject & Kembalikan ke Finance', false);

        $this->actingAs($salesManager)
            ->from(route('request-orders.show', $requestOrder))
            ->post(route('request-orders.approve-do', $requestOrder), [
                'action' => 'reject',
            ])
            ->assertRedirect(route('request-orders.show', $requestOrder))
            ->assertSessionHasErrors('note');

        $this->assertSame('approval', $requestOrder->fresh()->request_status);

        $this->actingAs($salesManager)
            ->post(route('request-orders.approve-do', $requestOrder), [
                'action' => 'reject',
                'note' => 'Harga jual lebih rendah dari HPP, mohon koreksi.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'DO ditolak karena harga tidak benar dan dikembalikan ke Finance.');

        $requestOrder->refresh();
        $this->assertSame('finance', $requestOrder->request_status);
        $this->assertFalse($requestOrder->do_approved);
        $this->assertDatabaseHas('order_status_logs', [
            'loggable_type' => $requestOrder->getMorphClass(),
            'loggable_id' => $requestOrder->id,
            'from_status' => 'approval',
            'to_status' => 'finance',
            'user_id' => $salesManager->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $finance->id,
            'type' => 'request_do_price_rejected',
            'title' => 'Harga Request DO perlu diperbaiki',
        ]);
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

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => $role . ' Workflow Test',
            'email' => strtolower(str_replace(' ', '-', $role)) . '-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'Active',
        ]);
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
