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
        $data = $this->validateData($request);
        $this->fillJobName($data);
        $data['request_order_id'] = $requestOrder->id;

        OrderJobDetail::create($data);

        return back()->with('success', 'Rincian pekerjaan ditambahkan.');
    }

    public function update(Request $request, OrderJobDetail $jobDetail)
    {
        $data = $this->validateData($request);
        $this->fillJobName($data);
        $jobDetail->update($data);

        return back()->with('success', 'Rincian pekerjaan diperbarui.');
    }

    public function destroy(OrderJobDetail $jobDetail)
    {
        $jobDetail->delete();
        return back()->with('success', 'Rincian pekerjaan dihapus.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'pekerjaan_id'      => 'nullable|exists:pekerjaan,id',
            'job_name'          => 'nullable|string|max:255',
            'job_code'          => 'nullable|string|max:20',
            'tgl_transaksi'     => 'nullable|date',
            'anggaran_biaya'    => 'nullable|numeric|min:0',
            'anggaran_jual'     => 'nullable|numeric|min:0',
            'riil_biaya'        => 'nullable|numeric|min:0',
            'riil_jual'         => 'nullable|numeric|min:0',
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
}
