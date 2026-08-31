<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Notification;
use App\Models\OrderJobDetail;
use App\Models\RequestOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Tiga penutup celah alur:
 *   - Reminder jatuh tempo invoice (H-3, hari-H, dan berkala saat lewat tempo).
 *   - DO yang sudah ditagih tidak dapat dihapus.
 *   - DO yang dibatalkan operasional tidak menggelembungkan Laporan DO.
 */
class FlowGuardsAndRemindersTest extends TestCase
{
    use DatabaseTransactions;

    // ── Reminder jatuh tempo invoice ──────────────────────────────

    public function test_due_date_reminders_are_sent_once_per_stage(): void
    {
        [$finance, $customer] = $this->makeFinanceAndCustomer();
        Invoice::query()->update(['status' => 'paid']);

        $h3      = $this->makeInvoice($finance, $customer, now()->addDays(3));
        $today   = $this->makeInvoice($finance, $customer, now());
        $overdue = $this->makeInvoice($finance, $customer, now()->subDays(10));
        $this->makeInvoice($finance, $customer, now()->addDays(5)); // H-5: belum waktunya

        $this->travelTo(now()->setTime(7, 1), fn() => Artisan::call('crm:notify'));

        $this->assertNotificationSent($finance, 'invoice_due_soon', 'invoice-due-h3-' . $h3->id);
        $this->assertNotificationSent($finance, 'invoice_due_today', 'invoice-due-today-' . $today->id);
        $this->assertNotificationSent($finance, 'invoice_overdue', 'invoice-overdue-' . $overdue->id . '-w1');
        $this->assertSame(3, $this->dueNotificationCount($finance));

        // Dijalankan ulang di hari yang sama tidak boleh menambah notifikasi.
        $this->travelTo(now()->setTime(7, 2), fn() => Artisan::call('crm:notify'));
        $this->assertSame(3, $this->dueNotificationCount($finance));
    }

    public function test_reminders_only_run_in_the_morning_window(): void
    {
        [$finance, $customer] = $this->makeFinanceAndCustomer();
        Invoice::query()->update(['status' => 'paid']);
        $this->makeInvoice($finance, $customer, now());

        $this->travelTo(now()->setTime(15, 0), fn() => Artisan::call('crm:notify'));

        $this->assertSame(0, $this->dueNotificationCount($finance));
    }

    public function test_settled_invoice_is_not_reminded(): void
    {
        [$finance, $customer] = $this->makeFinanceAndCustomer();
        Invoice::query()->update(['status' => 'paid']);
        $this->makeInvoice($finance, $customer, now(), status: 'paid');
        $this->makeInvoice($finance, $customer, now(), status: 'draft');

        $this->travelTo(now()->setTime(7, 1), fn() => Artisan::call('crm:notify'));

        $this->assertSame(0, $this->dueNotificationCount($finance));
    }

    // ── Guard hapus DO ────────────────────────────────────────────

    public function test_billed_delivery_order_cannot_be_deleted(): void
    {
        [$admin, , $deliveryOrder] = $this->makeDeliveryOrder();
        $invoice = Invoice::create([
            'invoice_id' => 'IV-GD-' . uniqid(),
            'invoice_number' => 'GUARD/DO/VIII/2026',
            'customer_id' => $deliveryOrder->customer_id,
            'status' => 'invoice',
            'tgl_buat' => '2026-08-01',
            'tgl_tempo' => '2026-08-31',
            'total_hpp' => 700000,
            'total_jual' => 1000000,
            'grand_total' => 1000000,
            'jenis' => 'TR',
            'operator_id' => $admin->id,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'request_order_id' => $deliveryOrder->request_order_id,
            'delivery_order_id' => $deliveryOrder->id,
            'item_type' => 'TR',
            'item_name' => 'Trucking',
            'quantity' => 1,
            'unit_price' => 1000000,
            'hpp' => 700000,
            'jual' => 1000000,
        ]);

        $this->actingAs($admin)
            ->from(route('delivery-orders.show', $deliveryOrder))
            ->delete(route('delivery-orders.destroy', $deliveryOrder))
            ->assertSessionHasErrors('general');

        // find() menghormati scope soft delete, fresh() tidak.
        $this->assertNotNull(DeliveryOrder::find($deliveryOrder->id), 'DO yang sudah ditagih tidak boleh terhapus.');
    }

    public function test_unbilled_delivery_order_can_still_be_deleted(): void
    {
        [$admin, , $deliveryOrder] = $this->makeDeliveryOrder();

        $this->actingAs($admin)
            ->delete(route('delivery-orders.destroy', $deliveryOrder))
            ->assertSessionHasNoErrors();

        $this->assertNull(DeliveryOrder::find($deliveryOrder->id));
    }

    // ── DO cancel di Laporan DO ───────────────────────────────────

    public function test_cancelled_do_is_excluded_from_report_totals_by_default(): void
    {
        [$admin, , $running] = $this->makeDeliveryOrder();
        [, , $cancelled] = $this->makeDeliveryOrder();
        $cancelled->requestOrder->update([
            'operational_status' => 'cancelled',
            'status' => 'Cancelled',
        ]);

        // Default: DO cancel tidak ikut.
        $rows = $this->actingAs($admin)->get(route('logistic-reports.do'))
            ->assertOk()
            ->viewData('dos');
        $numbers = collect($rows->items())->pluck('do_number');
        $this->assertTrue($numbers->contains($running->requestOrder->do_number));
        $this->assertFalse($numbers->contains($cancelled->requestOrder->do_number));

        // Termasuk cancel: keduanya muncul dan diberi badge.
        $response = $this->actingAs($admin)->get(route('logistic-reports.do', ['operasional' => 'all']))->assertOk();
        $numbers = collect($response->viewData('dos')->items())->pluck('do_number');
        $this->assertTrue($numbers->contains($cancelled->requestOrder->do_number));
        $response->assertSee('Cancel');

        // Hanya cancel.
        $numbers = collect(
            $this->actingAs($admin)->get(route('logistic-reports.do', ['operasional' => 'cancelled']))
                ->assertOk()->viewData('dos')->items()
        )->pluck('do_number');
        $this->assertSame([$cancelled->requestOrder->do_number], $numbers->all());
    }

    // ── Helper ────────────────────────────────────────────────────

    private function assertNotificationSent(User $finance, string $type, string $tag): void
    {
        $this->assertTrue(
            Notification::where('user_id', $finance->id)
                ->where('type', $type)
                ->where('url', 'like', '%#' . $tag)
                ->exists(),
            "Notifikasi {$type} dengan penanda {$tag} tidak terkirim."
        );
    }

    private function dueNotificationCount(User $finance): int
    {
        return Notification::where('user_id', $finance->id)
            ->whereIn('type', ['invoice_due_soon', 'invoice_due_today', 'invoice_overdue'])
            ->count();
    }

    /** @return array{0:User,1:Customer} */
    private function makeFinanceAndCustomer(): array
    {
        $finance = User::create([
            'name' => 'Finance Reminder',
            'email' => 'finance-reminder-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Finance',
            'status' => 'Active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'CUST-RM-' . uniqid(),
            'invoice_code' => 'RM' . strtoupper(substr(uniqid(), -5)),
            'company_name' => 'Customer Reminder',
            'pic_name' => 'PIC',
            'phone' => '0800000008',
            'user_id' => $finance->id,
        ]);

        return [$finance, $customer];
    }

    private function makeInvoice(User $operator, Customer $customer, $dueDate, string $status = 'invoice'): Invoice
    {
        return Invoice::create([
            'invoice_id' => 'IV-RM-' . uniqid(),
            'invoice_number' => 'RM-' . uniqid() . '/VIII/2026',
            'customer_id' => $customer->id,
            'status' => $status,
            'tgl_buat' => now()->subDays(40)->toDateString(),
            'tgl_tempo' => $dueDate,
            'total_hpp' => 0,
            'total_jual' => 1_000_000,
            'grand_total' => 1_000_000,
            'jenis' => 'TR',
            'operator_id' => $operator->id,
        ]);
    }

    /** @return array{0:User,1:RequestOrder,2:DeliveryOrder} */
    private function makeDeliveryOrder(): array
    {
        $admin = User::create([
            'name' => 'Guard Admin',
            'email' => 'guard-admin-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'CUST-GD-' . uniqid(),
            'invoice_code' => 'GD' . strtoupper(substr(uniqid(), -5)),
            'company_name' => 'Customer Guard',
            'pic_name' => 'PIC',
            'phone' => '0800000009',
            'user_id' => $admin->id,
        ]);
        $requestOrder = RequestOrder::create([
            'do_number' => 'RDO-GD-' . uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'status' => 'In Progress',
            'request_status' => 'assigned',
            'order_date' => now()->toDateString(),
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
            'do_approved' => true,
            'invoice_status' => 'uninvoiced',
            'operational_status' => 'running',
        ]);
        OrderJobDetail::create([
            'request_order_id' => $requestOrder->id,
            'job_name' => 'Trucking', 'job_code' => 'TR',
            'riil_biaya' => 700000, 'riil_jual' => 1000000,
        ]);
        $deliveryOrder = DeliveryOrder::create([
            'do_number' => $requestOrder->do_number,
            'request_order_id' => $requestOrder->id,
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'status' => 'closed',
            'invoice_status' => 'uninvoiced',
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
            'do_date' => now()->toDateString(),
            'pod_at' => now()->subDay(),
        ]);

        return [$admin, $requestOrder, $deliveryOrder];
    }
}
