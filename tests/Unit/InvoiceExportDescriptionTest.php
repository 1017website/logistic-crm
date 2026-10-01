<?php

namespace Tests\Unit;

use App\Models\DeliveryOrder;
use App\Models\InvoiceItem;
use App\Models\RequestOrder;
use PHPUnit\Framework\TestCase;

class InvoiceExportDescriptionTest extends TestCase
{
    public function test_legacy_item_without_order_keeps_its_description(): void
    {
        $item = new InvoiceItem(['description' => 'Biaya bongkar manual']);
        $item->setRelation('requestOrder', null);
        $item->setRelation('deliveryOrder', null);

        $this->assertSame('Biaya bongkar manual', $item->exportWorkDescription());
    }

    public function test_request_order_is_resolved_through_delivery_order_with_missing_values(): void
    {
        $requestOrder = new RequestOrder([
            'depo' => ' ', 'muat' => 'Gudang Muat', 'tujuan' => '-',
            'bongkar' => 'Gudang Bongkar', 'komoditi' => 'LOX',
        ]);
        $deliveryOrder = new DeliveryOrder(['fleet_info' => 'Nama Vendor']);
        $deliveryOrder->setRelation('requestOrder', $requestOrder);
        $item = new InvoiceItem(['truck_type' => "Trailer 20'"]);
        $item->setRelation('requestOrder', null);
        $item->setRelation('deliveryOrder', $deliveryOrder);

        $this->assertSame(
            "Depo : Gudang Muat Tujuan: Gudang Bongkar, Komoditas: LOX (No. Container: -, No Seal: -, No. Pol: -, Armada: Trailer 20')",
            $item->exportWorkDescription(),
        );
    }

    public function test_request_only_item_uses_legacy_locations(): void
    {
        $item = new InvoiceItem();
        $item->setRelation('requestOrder', new RequestOrder(['origin' => 'Surabaya', 'destination' => 'Jakarta']));
        $item->setRelation('deliveryOrder', null);

        $this->assertSame(
            'Depo : Surabaya Tujuan: Jakarta, Komoditas: - (No. Container: -, No Seal: -, No. Pol: -, Armada: -)',
            $item->exportWorkDescription(),
        );
    }
}
