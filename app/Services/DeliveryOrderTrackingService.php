<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use Endroid\QrCode\Builder\Builder as QrCodeBuilder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\URL;

class DeliveryOrderTrackingService
{
    /** @return array{trackingQr: string, trackingUrl: string} */
    public function make(DeliveryOrder $deliveryOrder): array
    {
        $trackingPath = URL::signedRoute('delivery-orders.track', [
            'deliveryOrder' => $deliveryOrder->getRouteKey(),
        ], absolute: false);
        $trackingUrl = request()->getSchemeAndHttpHost() . $trackingPath;

        $trackingQr = (new QrCodeBuilder(
            writer: new PngWriter(),
            data: $trackingUrl,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 260,
            margin: 8,
        ))->build()->getDataUri();

        return compact('trackingQr', 'trackingUrl');
    }
}
