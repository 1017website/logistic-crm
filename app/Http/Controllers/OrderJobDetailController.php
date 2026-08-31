<?php

namespace App\Http\Controllers;

use App\Models\OrderJobDetail;
use App\Models\Pekerjaan;
use App\Models\RequestOrder;
use Illuminate\Http\Request;

/**
 * Rincian biaya per pekerjaan untuk sebuah Request DO.
 * Inilah sumber HPP (riil_biaya) & Jual (riil_jual) DO.
 */
class OrderJobDetailController extends Controller
{
    public function store(Request $request, RequestOrder $requestOrder)
    {
        $this->ensureEditable($requestOrder);
        $data = $this->validateData($request);
        $this->fillJobName($data);
        $data['request_order_id'] = $requestOrder->id;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        OrderJobDetail::create($data);
        $requestOrder->update(['do_approved' => false]);
        $resubmitted = $this->resubmitIfAwaitingManager($requestOrder, 'Rincian pekerjaan ditambahkan');

        return back()->with('success', 'Rincian pekerjaan ditambahkan.' . $resubmitted);
    }

    public function update(Request $request, OrderJobDetail $jobDetail)
    {
        $this->ensureEditable($jobDetail->requestOrder);
        $data = $this->validateData($request);
        $this->fillJobName($data);
        $data['updated_by'] = auth()->id();
        $jobDetail->update($data);
        $jobDetail->requestOrder->update(['do_approved' => false]);
        $resubmitted = $this->resubmitIfAwaitingManager($jobDetail->requestOrder, 'Rincian pekerjaan diperbarui');

        return back()->with('success', 'Rincian pekerjaan diperbarui.' . $resubmitted);
    }

    public function destroy(OrderJobDetail $jobDetail)
    {
        $requestOrder = $jobDetail->requestOrder;
        $this->ensureEditable($requestOrder);
        $jobDetail->delete();
        $requestOrder->update(['do_approved' => false]);
        $resubmitted = $this->resubmitIfAwaitingManager($requestOrder, 'Rincian pekerjaan dihapus');
        return back()->with('success', 'Rincian pekerjaan dihapus.' . $resubmitted);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'pekerjaan_id'      => 'nullable|required_without:job_name|exists:pekerjaan,id',
            'job_name'          => 'nullable|required_without:pekerjaan_id|string|max:255',
            'job_code'          => 'nullable|string|max:20',
            'tgl_transaksi'     => 'nullable|date',
            'anggaran_biaya'    => 'nullable|numeric|min:0',
            'anggaran_jual'     => 'nullable|numeric|min:0',
            'riil_biaya'        => 'required|numeric|min:0',
            'riil_jual'         => 'required|numeric|min:0',
            'dibayar'           => 'nullable|numeric|min:0',
            'vendor_id'         => 'nullable|exists:vendors,id',
            'status_pembayaran' => 'nullable|in:Lunas,Tempo',
            'tgl_realisasi'     => 'nullable|date',
            'catatan'           => 'nullable|string',
        ]);
    }

    /** Snapshot nama & kode pekerjaan dari master bila dipilih. */
    private function fillJobName(array &$data): void
    {
        if (!empty($data['pekerjaan_id'])) {
            $p = Pekerjaan::find($data['pekerjaan_id']);
            if ($p) {
                $data['job_name'] = $data['job_name'] ?: $p->name;
                $data['job_code'] = $data['job_code'] ?: $p->code;
            }
        }
        foreach (['anggaran_biaya','anggaran_jual','riil_biaya','riil_jual','dibayar'] as $k) {
            $data[$k] = $data[$k] ?? 0;
        }
        $data['status_pembayaran'] = $data['status_pembayaran'] ?? 'Tempo';
    }

    private function ensureEditable(RequestOrder $requestOrder): void
    {
        abort_if(
            $requestOrder->invoice_status !== 'uninvoiced',
            422,
            'Rincian harga tidak dapat diubah karena Request DO sudah masuk invoice.'
        );
        abort_if(
            !$requestOrder->pricing_editable,
            422,
            'Rincian biaya hanya dapat diubah pada tahap review Finance, saat menunggu approval Sales Manager, atau setelah Sales Manager membuka kunci koreksi harga.'
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
