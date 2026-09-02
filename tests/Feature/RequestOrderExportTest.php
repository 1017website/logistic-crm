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
        $this->assertCount(29, $rows[0]);
        $this->assertSame('ETA', $rows[0][28]);
        $this->assertSame($order->do_number, $rows[1][0]);
        $this->assertSame($customer->company_name, $rows[1][1]);
        $this->assertSame($order->flow_label, $rows[1][2]);
        $this->assertSame('Jalan / Aktif', $rows[1][3]);
        $this->assertSame('Belum Direview', $rows[1][8]);
        $this->assertSame('Surabaya', $rows[1][13]);
        $this->assertSame('Jakarta', $rows[1][14]);
        $this->assertSame(array_fill(0, 9, null), array_slice($rows[1], 16, 9));
        $this->assertSame('IDR', $rows[1][25]);
        $this->assertSame('In Progress', $rows[1][26]);
        $this->assertSame('2026-09-02', $rows[1][27]);
        $this->assertSame('2026-09-05', $rows[1][28]);
    }

    public function test_export_keeps_item_rows_and_requests_without_items_together(): void
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
        $this->assertEqualsCanonicalizing(['Trucking', 'Bongkar'], [$rows[1][16], $rows[2][16]]);
        foreach ([$rows[1], $rows[2]] as $row) {
            $this->assertSame('rit', $row[17]);
            $this->assertEquals([2.5, 2, 100000, 150000, 300000, 200000, 100000], array_slice($row, 18, 7));
        }
        $this->assertSame(array_fill(0, 9, null), array_slice($rows[3], 16, 9));
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
