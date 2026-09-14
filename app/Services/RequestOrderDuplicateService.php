<?php

namespace App\Services;

use App\Models\RequestOrder;
use Illuminate\Support\Collection;

class RequestOrderDuplicateService
{
    public function find(array $data, ?int $exceptId = null): Collection
    {
        $normalize = fn ($value) => mb_strtoupper(preg_replace('/\s+/u', '', trim((string) $value)));

        return RequestOrder::where('customer_id', $data['customer_id'])
            ->whereDate('order_date', $data['order_date'])
            ->where('request_status', '!=', 'cancelled')
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->get()
            ->filter(function (RequestOrder $order) use ($data, $normalize) {
                foreach (['no_container', 'no_seal', 'tracking_number'] as $field) {
                    $value = $normalize($data[$field] ?? '');
                    if ($value !== '' && $value === $normalize($order->$field)) {
                        return true;
                    }
                }

                $plate = $normalize($data['no_pol'] ?? '');
                $origin = $normalize(($data['muat'] ?? null) ?: ($data['origin'] ?? ''));
                $destination = $normalize(($data['bongkar'] ?? null) ?: ($data['destination'] ?? ''));

                return $plate !== '' && $origin !== '' && $destination !== ''
                    && $plate === $normalize($order->no_pol)
                    && $origin === $normalize($order->muat ?: $order->origin)
                    && $destination === $normalize($order->bongkar ?: $order->destination);
            })->values();
    }
}
