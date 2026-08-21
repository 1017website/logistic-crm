<?php

namespace App\Http\Controllers;

use App\Models\RequestOrder;
use App\Models\RequestOrderItem;
use Illuminate\Http\Request;

class RequestOrderItemController extends Controller
{
    public function store(Request $request, RequestOrder $requestOrder)
    {
        $this->ensureEditable($requestOrder);
        $requestOrder->items()->create($this->validateData($request));
        $requestOrder->update(['do_approved' => false]);

        return back()->with('success', 'Item layanan dan harga berhasil ditambahkan.');
    }

    public function update(Request $request, RequestOrderItem $requestOrderItem)
    {
        $requestOrder = $requestOrderItem->requestOrder;
        $this->ensureEditable($requestOrder);
        $requestOrderItem->update($this->validateData($request));
        $requestOrder->update(['do_approved' => false]);

        return back()->with('success', 'Item layanan dan harga berhasil diperbarui.');
    }

    public function destroy(RequestOrderItem $requestOrderItem)
    {
        $requestOrder = $requestOrderItem->requestOrder;
        $this->ensureEditable($requestOrder);
        $requestOrderItem->delete();
        $requestOrder->update(['do_approved' => false]);

        return back()->with('success', 'Item layanan berhasil dihapus.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'service_name' => 'required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'tonnage' => 'nullable|numeric|min:0',
            'qty' => 'required|numeric|min:0.001',
            'buy_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
        ]);
    }

    private function ensureEditable(RequestOrder $requestOrder): void
    {
        abort_if(
            $requestOrder->invoice_status !== 'uninvoiced',
            422,
            'Item layanan tidak dapat diubah karena Request DO sudah masuk invoice.'
        );
        abort_if(
            $requestOrder->request_status !== 'finance',
            422,
            'Item layanan dan harga hanya dapat diubah pada tahap review Finance.'
        );
    }
}
