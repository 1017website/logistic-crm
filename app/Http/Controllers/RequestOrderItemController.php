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
        $resubmitted = $this->resubmitIfAwaitingManager($requestOrder, 'Item layanan dan harga ditambahkan');

        return back()->with('success', 'Item layanan dan harga berhasil ditambahkan.' . $resubmitted);
    }

    public function update(Request $request, RequestOrderItem $requestOrderItem)
    {
        $requestOrder = $requestOrderItem->requestOrder;
        $this->ensureEditable($requestOrder);
        $requestOrderItem->update($this->validateData($request));
        $requestOrder->update(['do_approved' => false]);
        $resubmitted = $this->resubmitIfAwaitingManager($requestOrder, 'Item layanan dan harga diperbarui');

        return back()->with('success', 'Item layanan dan harga berhasil diperbarui.' . $resubmitted);
    }

    public function destroy(RequestOrderItem $requestOrderItem)
    {
        $requestOrder = $requestOrderItem->requestOrder;
        $this->ensureEditable($requestOrder);
        $requestOrderItem->delete();
        $requestOrder->update(['do_approved' => false]);
        $resubmitted = $this->resubmitIfAwaitingManager($requestOrder, 'Item layanan dihapus');

        return back()->with('success', 'Item layanan berhasil dihapus.' . $resubmitted);
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
            !$requestOrder->pricing_editable,
            422,
            'Item layanan dan harga hanya dapat diubah pada tahap review Finance, saat menunggu approval Sales Manager, atau setelah Sales Manager membuka kunci koreksi harga.'
        );
    }

    private function resubmitIfAwaitingManager(RequestOrder $requestOrder, string $action): string
    {
        $actor = auth()->user()->name;

        if ($requestOrder->resubmitManagerApproval(
            $action . ' oleh ' . $actor . ' dan diajukan ulang ke Sales Manager.',
            auth()->id()
        )) {
            return ' Perubahan diajukan ulang ke Sales Manager.';
        }

        if ($requestOrder->notifyPriceCorrection(
            $action . ' oleh ' . $actor . ' lewat jalur koreksi harga.',
            auth()->id()
        )) {
            return ' Harga menunggu approve ulang Sales Manager.';
        }

        return '';
    }
}
