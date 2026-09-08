<?php

namespace App\Http\Controllers;

use App\Models\LoadingOrder;
use App\Models\Setting;
use App\Services\DocumentSignatureService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoadingOrderController extends Controller
{
    public function index(Request $request)
    {
        $search = mb_substr(trim((string) $request->query('search')), 0, 255);
        $orders = LoadingOrder::query()->when($search !== '', function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                foreach (['number', 'recipient', 'route', 'driver_name', 'vehicle_number', 'po_number'] as $field) {
                    $q->orWhere($field, 'like', "%{$search}%");
                }
            });
        })->latest('id')->paginate(20)->withQueryString();
        return view('loading_orders.index', compact('orders', 'search'));
    }

    public function create(Request $request)
    {
        $language = $request->validate(['language' => ['sometimes', 'in:id,en']])['language'] ?? 'id';
        $order = new LoadingOrder([
            'language' => $language,
            'letter_date' => today(), 'city' => Setting::get('company_document_city', 'Surabaya'),
            'subject' => 'SURAT PERINTAH MUAT',
            'opening' => 'Bersama ini PT. Firman Tangguh Logistik, memutuskan bahwa pelaksanaan transportasi logistik dilakukan oleh PT. Firman Tangguh Logistik dengan rincian sebagai berikut:',
            'terms' => "Supir bertanggung jawab penuh atas kendaraan dan muatan selama proses pengangkutan dari titik muat hingga titik bongkar.\nSegala risiko yang terjadi selama perjalanan, termasuk keterlambatan, kerusakan, atau kehilangan akibat kelalaian supir menjadi tanggung jawab pihak pelaksana pengangkutan.\nPekerjaan ini tidak diperkenankan untuk dialihkan atau disubkontrakkan kepada pihak ketiga tanpa ada persetujuan dari pihak pertama.\nApabila di kemudian hari ditemukan pelanggaran terhadap ketentuan SPM ini, maka pihak pertama berhak memberikan sanksi sesuai kesepakatan yang berlaku.",
            'closing' => 'Demikian Surat Perintah Muat ini dibuat untuk dilaksanakan dengan penuh tanggung jawab. Atas perhatian dan kerja samanya kami ucapkan terima kasih.',
            'signatory_name' => auth()->user()->name,
            'signatory_title' => auth()->user()->position ?: auth()->user()->role,
        ]);
        if ($language === 'en') {
            $order->fill([
                'subject' => 'LOADING ORDER',
                'opening' => 'We hereby confirm that PT. Firman Tangguh Logistik will carry out the transportation services in accordance with the following details:',
                'terms' => "The driver is fully responsible for the vehicle and cargo throughout transportation, from the loading point to the unloading point.\nAny risks arising during the journey, including delays, damage, or loss caused by driver negligence, shall be the responsibility of the transport operator.\nThis work may not be transferred or subcontracted to a third party without prior approval from the first party.\nIf any breach of this loading order is subsequently found, the first party reserves the right to impose sanctions in accordance with the applicable agreement.",
                'closing' => 'This loading order is issued to be carried out with full responsibility. Thank you for your attention and cooperation.',
            ]);
        }
        return view('loading_orders.form', compact('order'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['signatory_name'] = $request->user()->name;
        $data['signatory_title'] = $request->user()->position ?: $request->user()->role;
        $order = DB::transaction(function () use ($data) {
            $order = LoadingOrder::create([...$data, 'number' => 'TMP-'.Str::uuid(), 'company' => $this->company()]);
            $order->update(['number' => 'SPM-'.$order->letter_date->format('ym').'-'.str_pad($order->id, 4, '0', STR_PAD_LEFT)]);
            return $order;
        });
        return redirect()->route('loading-orders.edit', $order)->with('success', 'Surat Perintah Muat berhasil disimpan. PDF sudah dilengkapi tanda tangan elektronik.');
    }

    public function edit(LoadingOrder $loadingOrder)
    {
        return view('loading_orders.form', ['order' => $loadingOrder]);
    }

    public function update(Request $request, LoadingOrder $loadingOrder)
    {
        $loadingOrder->update($this->validated($request));
        return back()->with('success', 'Surat diperbarui. Unduh ulang PDF untuk menggunakan tanda tangan versi terbaru.');
    }

    public function pdf(Request $request, LoadingOrder $loadingOrder, DocumentSignatureService $signature)
    {
        $pdf = Pdf::loadView('loading_orders.pdf', [
            'order' => $loadingOrder, 'company' => $loadingOrder->company,
            ...$signature->make('loading_order', $loadingOrder->id, ['version' => $loadingOrder->fingerprint()]),
        ])->setPaper('a4')->setOptions(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]);
        $filename = $loadingOrder->number.'.pdf';
        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    private function validated(Request $request): array
    {
        if ($request->has('term_items')) {
            $terms = $request->validate([
                'term_items' => ['required', 'array', 'min:1', 'max:50'],
                'term_items.*' => ['required', 'string', 'max:6000'],
            ]);
            $request->merge(['terms' => implode("\n", $terms['term_items'])]);
        }
        $rules = ['letter_date' => ['required', 'date_format:Y-m-d'], 'language' => ['sometimes', 'required', 'in:id,en']];
        foreach (['city', 'recipient', 'subject', 'route', 'driver_name', 'vehicle_number', 'vehicle_type'] as $field) {
            $rules[$field] = ['required', 'string', 'max:255'];
        }
        foreach (['po_number', 'mod_number', 'driver_phone'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }
        foreach (['opening', 'terms', 'closing'] as $field) {
            $rules[$field] = ['required', 'string', 'max:6000'];
        }
        $rules['recipient_address'] = ['nullable', 'string', 'max:1000'];
        return $request->validate($rules);
    }

    private function company(): array
    {
        $company = [];
        foreach (['name', 'address', 'phone', 'email', 'website'] as $field) {
            $company[$field] = Setting::get('company_'.$field, $field === 'name' ? 'PT Firman Tangguh Logistik' : '');
        }
        $path = Setting::get('company_doc_logo') ?: Setting::get('company_logo');
        $company['logo'] = $path && Storage::disk('public')->exists($path)
            ? 'data:'.Storage::disk('public')->mimeType($path).';base64,'.base64_encode(Storage::disk('public')->get($path)) : null;
        return $company;
    }
}
