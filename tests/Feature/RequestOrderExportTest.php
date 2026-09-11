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
        $this->assertSame(['No Request DO', 'Tgl order', 'Cust', 'Trucking', 'No Cont', 'No Seal', 'No Pol', 'Driver'], $rows[0]);
        $this->assertSame($order->do_number, $rows[1][0]);
        $this->assertSame($customer->company_name, $rows[1][2]);
        $this->assertSame(array_fill(0, 5, null), array_slice($rows[1], 3));
        $this->assertSame('2026-09-02', $rows[1][1]);
    }

    public function test_export_contains_one_row_per_request_even_with_multiple_services(): void
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

        $this->assertCount(3, $rows);
        $this->assertSame([$withItems->do_number, $withoutItems->do_number], array_column(array_slice($rows, 1), 0));
    }

    public static function truckTypes(): array
    {
        return [['CDD'], ["Trailer 20'"], ["Trailer 40'"]];
    }

    #[DataProvider('truckTypes')]
    public function test_export_maps_operational_details_to_requested_columns(string $truckType): void
    {
        [$user, $customer] = $this->makeCustomer();
        $order = $this->makeOrder($user, $customer, [
            'jenis_truck' => $truckType,
            'no_container' => 'CONT1234567',
            'no_seal' => '001234',
            'no_pol' => 'B 1234 XYZ',
            'supir' => 'Driver Export',
        ]);

        $rows = $this->exportRows($user, $customer);

        $this->assertSame([
            $order->do_number, '2026-09-02', $customer->company_name,
            $truckType, 'CONT1234567', '001234', 'B 1234 XYZ', 'Driver Export',
        ], $rows[1]);
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
