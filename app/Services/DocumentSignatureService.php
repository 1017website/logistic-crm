<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder as QrCodeBuilder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\URL;

class DocumentSignatureService
{
    /** @return array{signatureQr: string, verificationUrl: string} */
    public function make(string $kind, int $id, array $parameters = []): array
    {
        $verificationPath = URL::signedRoute('documents.verify', [
            'kind' => $kind,
            'id' => $id,
            ...$parameters,
        ], absolute: false);
        $verificationUrl = request()->getSchemeAndHttpHost() . $verificationPath;

        $signatureQr = (new QrCodeBuilder(
            writer: new PngWriter(),
            data: $verificationUrl,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 260,
            margin: 8,
        ))->build()->getDataUri();

        return compact('signatureQr', 'verificationUrl');
    }
}
