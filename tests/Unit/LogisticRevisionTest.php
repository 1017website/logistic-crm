<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\DeliveryOrder;
use App\Models\OrderJobDetail;
use App\Models\RequestOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class LogisticRevisionTest extends TestCase
{
    public function test_invoice_type_labels_cover_trucking_and_non_trucking(): void
    {
        $trucking = new Invoice(['jenis' => 'TR']);
        $nonTrucking = new Invoice(['jenis' => 'NTR']);

        $this->assertSame('Trucking', $trucking->jenis_label);
        $this->assertSame('Non-Trucking', $nonTrucking->jenis_label);
    }

    public function test_invoice_type_label_covers_combined_invoice(): void
    {
        $invoice = new Invoice(['jenis' => 'MIX']);

        $this->assertSame('Trucking & Non-Trucking', $invoice->jenis_label);
    }

    public function test_delivery_order_breaks_invoice_value_into_trucking_and_non_trucking(): void
    {
        $requestOrder = new RequestOrder();
        $requestOrder->setRelation('jobDetails', new Collection([
            new OrderJobDetail([
                'job_code' => 'TR',
                'job_name' => 'Trucking Surabaya',
                'riil_biaya' => 700000,
                'riil_jual' => 1000000,
            ]),
            new OrderJobDetail([
                'job_code' => 'NTR',
                'job_name' => 'Jasa Bongkar',
                'riil_biaya' => 100000,
                'riil_jual' => 250000,
            ]),
        ]));
        $requestOrder->setRelation('items', new Collection());

        $deliveryOrder = new DeliveryOrder();
        $deliveryOrder->setRelation('requestOrder', $requestOrder);
        $breakdown = $deliveryOrder->invoiceBreakdown();

        $this->assertSame(1000000.0, $breakdown['TR']['jual']);
        $this->assertSame(250000.0, $breakdown['NTR']['jual']);
        $this->assertSame('Jasa Bongkar', $breakdown['NTR']['description']);
    }

    public function test_accounting_owns_request_pricing_while_sales_admin_owns_approval(): void
    {
        $accounting = new User(['role' => 'Finance']);
        $salesAdmin = new User(['role' => 'Sales Admin']);

        $this->assertTrue($accounting->canAccess('request_item_pricing'));
        $this->assertTrue($accounting->canAccess('job_details'));
        $this->assertFalse($salesAdmin->canAccess('request_item_pricing'));
        $this->assertTrue($salesAdmin->canAccess('approve_do'));
    }
}
