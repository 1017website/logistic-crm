<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\RequestOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RequestOrderExportTest extends TestCase
{
    use DatabaseTransactions;

    public static function requestTabs(): array
    {
        return [
            'active request without items' => ['active', 'finance'],
            'cancelled request without items' => ['cancelled', 'cancelled'],
        ];
    }

    #[DataProvider('requestTabs')]
    public function test_export_includes_requests_without_items(string $tab, string $requestStatus): void
    {
        [$user, $customer] = $this->makeCustomer();
        $order = $this->makeOrder($user, $customer, ['request_status' => $requestStatus]);
        $this->makeOrder($user, $customer, ['request_status' => 'assigned']);
        $this->makeOrder($user, $customer, ['request_status' => $requestStatus, 'order_date' => '2026-08-31']);
        $this->makeOrder($user, $customer, ['request_status' => $tab === 'active' ? 'cancelled' : 'finance']);

        $rows = $this->exportRows($user, $customer, ['tab' => $tab, 'page' => 2]);

        $this->assertCount(2, $rows);
        $this->assertSame(['Request DO', 'Customer', 'Flow', 'Status Operasional', 'Keterangan Status', 'Alasan Batal', 'Jadwal Reschedule', 'Request DP', 'Status DP', 'Nominal DP', 'Catatan DP', 'Direview Finance', 'Delivery Type', 'Lokasi Muat', 'Lokasi Bongkar', 'Tracking', 'Kode Sektor', 'Service', 'Unit', 'Tonase', 'Qty', 'Buy Price', 'Sell Price', 'Subtotal Revenue', 'Subtotal HPP', 'Gross Profit', 'Currency', 'Status', 'Tgl Order', 'ETA'], $rows[0]);
        $this->assertSame($order->do_number, $rows[1][0]);
        $this->assertSame($customer->company_name, $rows[1][1]);
        $this->assertSame(array_fill(0, 9, null), array_slice($rows[1], 17, 9));
        $this->assertSame('2026-09-02', $rows[1][28]);
    }

    public function test_export_restores_service_rows_and_keeps_requests_without_items(): void
    {
        [$user, $customer] = $this->makeCustomer();
        $withoutItems = $this->makeOrder($user, $customer, ['order_date' => '2026-09-01']);
        $withItems = $this->makeOrder($user, $customer);
        foreach (['Trucking', 'Bongkar'] as $service) {
            $withItems->items()->create([
                'service_name' => $service,
                'unit' => 'rit',
                'tonnage' => 2.5,
                'qty' => 2,
                'buy_price' => 100000,
                'sell_price' => 150000,
            ]);
        }

        $rows = $this->exportRows($user, $customer);

        $this->assertCount(4, $rows);
        $this->assertSame([$withItems->do_number, $withItems->do_number, $withoutItems->do_number], array_column(array_slice($rows, 1), 0));
    }

    public function test_export_maps_locations_tracking_and_sector(): void
    {
        [$user, $customer] = $this->makeCustomer();
        $this->makeOrder($user, $customer, [
            'delivery_type' => 'Trucking Trailer',
            'muat' => 'Gudang Muat',
            'bongkar' => 'Gudang Bongkar',
            'tracking_number' => 'TRACK-001',
            'sektor' => '0017',
            'kode_sektor' => 'LEGACY',
        ]);

        $rows = $this->exportRows($user, $customer);

        $this->assertSame([
            'Trucking Trailer', 'Gudang Muat', 'Gudang Bongkar', 'TRACK-001', '0017',
        ], array_slice($rows[1], 12, 5));
    }

    public function test_export_uses_legacy_locations_when_operational_locations_are_empty(): void
    {
        [$user, $customer] = $this->makeCustomer();
        $this->makeOrder($user, $customer);

        $rows = $this->exportRows($user, $customer);

        $this->assertSame(['Surabaya', 'Jakarta'], array_slice($rows[1], 13, 2));
    }
    private function exportRows(User $user, Customer $customer, array $filters = []): array
    {
        $response = $this->actingAs($user)->get(route('request-orders.export', array_merge([
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'search' => $customer->company_name,
        ], $filters)))->assertOk()->assertDownload();

        $path = tempnam(sys_get_temp_dir(), 'request-export-');
        $spreadsheet = null;
        try {
            file_put_contents($path, $response->streamedContent());
            $spreadsheet = IOFactory::load($path);

            return $spreadsheet->getActiveSheet()->toArray(formatData: false);
        } finally {
            $spreadsheet?->disconnectWorksheets();
            unlink($path);
        }
    }

    private function makeCustomer(): array
    {
        $user = User::create([
            'name' => 'Request Export Test',
            'email' => 'request-export-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'CUST-EXPORT-' . uniqid(),
            'company_name' => 'Request Export Customer ' . uniqid(),
            'pic_name' => 'PIC',
            'phone' => '0800000000',
            'user_id' => $user->id,
        ]);

        return [$user, $customer];
    }

    private function makeOrder(User $user, Customer $customer, array $attributes = []): RequestOrder
    {
        return RequestOrder::create(array_merge([
            'do_number' => 'RDO-EXPORT-' . uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'currency' => 'IDR',
            'status' => 'In Progress',
            'request_status' => 'finance',
            'order_date' => '2026-09-02',
            'estimated_arrival' => '2026-09-05',
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
        ], $attributes));
    }
}
