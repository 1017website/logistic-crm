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
            "Muat: Gudang Muat, Bongkar: Gudang Bongkar, Depo : Gudang Muat Tujuan: Gudang Bongkar, Komoditas: LOX (No. Container: -, No Seal: -, No. Pol: -, Armada: Trailer 20')",
            $item->exportWorkDescription(),
        );
    }

    public function test_request_only_item_uses_legacy_locations(): void
    {
        $item = new InvoiceItem();
        $item->setRelation('requestOrder', new RequestOrder(['origin' => 'Surabaya', 'destination' => 'Jakarta']));
        $item->setRelation('deliveryOrder', null);

        $this->assertSame(
            'Muat: Surabaya, Bongkar: Jakarta, Depo : Surabaya Tujuan: Jakarta, Komoditas: - (No. Container: -, No Seal: -, No. Pol: -, Armada: -)',
            $item->exportWorkDescription(),
        );
    }

    public function test_operational_locations_are_shown_alongside_depot_and_regional_destination(): void
    {
        $item = new InvoiceItem();
        $item->setRelation('requestOrder', new RequestOrder([
            'muat' => 'PT. MAYORA INDAH TBK, KRIAN',
            'bongkar' => 'TERMINAL SURABAYA',
            'depo' => 'DMS', 'tujuan' => 'BATAM', 'komoditi' => 'BISKUIT',
        ]));
        $item->setRelation('deliveryOrder', null);

        $this->assertSame(
            'Muat: PT. MAYORA INDAH TBK, KRIAN, Bongkar: TERMINAL SURABAYA, Depo : DMS Tujuan: BATAM, Komoditas: BISKUIT (No. Container: -, No Seal: -, No. Pol: -, Armada: -)',
            $item->exportWorkDescription(),
        );
    }

    public function test_delivery_order_locations_are_used_without_request_order(): void
    {
        $item = new InvoiceItem();
        $item->setRelation('requestOrder', null);
        $deliveryOrder = new DeliveryOrder(['origin' => 'Terminal Surabaya', 'destination' => 'PT. Mayora Indah Tbk']);
        $deliveryOrder->setRelation('requestOrder', null);
        $item->setRelation('deliveryOrder', $deliveryOrder);

        $this->assertStringContainsString(
            'Muat: Terminal Surabaya, Bongkar: PT. Mayora Indah Tbk,',
            $item->exportWorkDescription(),
        );
    }
}
