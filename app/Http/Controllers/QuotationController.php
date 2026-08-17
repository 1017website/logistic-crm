<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\Setting;
use App\Services\DocumentSignatureService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));
        $status = (string) $request->get('status', 'all');

        $query = Quotation::with(['customer', 'user', 'items'])->withCount('items');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('quotation_number', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%");
            });
        }

        if (array_key_exists($status, Quotation::STATUSES)) {
            $query->where('status', $status);
        }

        $quotations = $query->orderByDesc('quotation_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('quotations.index', compact('quotations', 'search', 'status'));
    }

    public function create()
    {
        return view('quotations.create', [
            'quotation' => new Quotation([
                'quotation_date' => now(),
                'recipient_name' => 'Yth.',
                'recipient_title' => 'Bpk/Ibu Pimpinan',
                'attachment' => '-',
                'subject' => 'Surat Penawaran Harga',
                'city' => Setting::get('company_document_city', 'Surabaya'),
                'contact_name' => auth()->user()->name,
                'contact_phone' => auth()->user()->phone,
                'signatory_name' => Setting::get('company_signatory_name', auth()->user()->name),
                'signatory_title' => Setting::get('company_signatory_title', auth()->user()->position ?: 'Direktur'),
                'status' => 'draft',
            ]),
            'customers' => $this->customers(),
            'defaultTerms' => $this->defaultTerms(),
        ]);
    }

    public function show(Quotation $quotation)
    {
        $quotation->load(['items', 'customer', 'user']);
        $opening = $quotation->opening
            ?: 'Berikut kami memberikan pengajuan penawaran harga kepada Bapak/Ibu Pimpinan '
                . $quotation->company_name . ' untuk pekerjaan berdasarkan detail di bawah ini :';
        $contact = trim(collect([$quotation->contact_name, $quotation->contact_phone])->filter()->join(' di '));
        $closing = $quotation->closing
            ?: 'Demikian Surat Penawaran Harga ini kami buat, selanjutnya kami tunggu kabar baik dari Bapak/Ibu'
                . ($contact ? ', atau Bapak/Ibu bisa menghubungi ' . $contact : '') . '. Terima kasih.';

        return view('quotations.show', compact('quotation', 'opening', 'closing'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $quotation = DB::transaction(function () use ($data) {
            $items = $data['items'];
            unset($data['items']);
            $data['terms'] = $this->cleanTerms($data['terms']);
            $data['user_id'] = auth()->id();
            $data['quotation_number'] = 'TMP-' . Str::uuid();

            $quotation = Quotation::create($data);
            $quotation->update([
                'quotation_number' => 'SPH-'
                    . $quotation->quotation_date->format('ym') . '-'
                    . str_pad((string) $quotation->id, 4, '0', STR_PAD_LEFT),
            ]);
            $this->syncItems($quotation, $items);

            return $quotation;
        });

        return redirect()->route('quotations.index')
            ->with('success', "Penawaran {$quotation->quotation_number} berhasil dibuat.");
    }

    public function edit(Quotation $quotation)
    {
        $quotation->load('items');

        return view('quotations.edit', [
            'quotation' => $quotation,
            'customers' => $this->customers(),
            'defaultTerms' => $this->defaultTerms(),
        ]);
    }

    public function update(Request $request, Quotation $quotation)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($quotation, $data) {
            $items = $data['items'];
            unset($data['items']);
            $data['terms'] = $this->cleanTerms($data['terms']);
            $quotation->update($data);
            $quotation->items()->delete();
            $this->syncItems($quotation, $items);
        });

        return redirect()->route('quotations.index')
            ->with('success', "Penawaran {$quotation->quotation_number} berhasil diperbarui.");
    }

    public function pdf(Quotation $quotation, DocumentSignatureService $documentSignature)
    {
        $quotation->load(['items', 'customer', 'user']);
        $company = [
            'name' => Setting::get('company_name', 'PT Firman Tangguh Logistik'),
            'address' => Setting::get('company_address', ''),
            'phone' => Setting::get('company_phone', ''),
            'email' => Setting::get('company_email', ''),
            'website' => Setting::get('company_website', ''),
            'logo' => $this->imageDataUrl(
                Setting::get('company_doc_logo') ?: Setting::get('company_logo')
            ),
        ];
        $signature = $documentSignature->make('quotation', $quotation->getKey());

        $pdf = Pdf::loadView('quotations.pdf', [
            'quotation' => $quotation,
            'company' => $company,
            ...$signature,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ]);

        $filename = 'Surat-Penawaran-' . str_replace(['/', '\\'], '-', $quotation->quotation_number) . '.pdf';

        return $pdf->download($filename);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'quotation_date' => ['required', 'date'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'recipient_title' => ['required', 'string', 'max:150'],
            'company_name' => ['required', 'string', 'max:255'],
            'recipient_address' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:255'],
            'opening' => ['nullable', 'string', 'max:2000'],
            'terms' => ['required', 'array', 'min:1', 'max:15'],
            'terms.*' => ['required', 'string', 'max:500'],
            'closing' => ['nullable', 'string', 'max:2000'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'city' => ['required', 'string', 'max:100'],
            'signatory_name' => ['required', 'string', 'max:150'],
            'signatory_title' => ['required', 'string', 'max:150'],
            'status' => ['required', Rule::in(array_keys(Quotation::STATUSES))],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.origin' => ['required', 'string', 'max:255'],
            'items.*.destination' => ['required', 'string', 'max:255'],
            'items.*.commodity' => ['required', 'string', 'max:255'],
            'items.*.tonnage' => ['required', 'string', 'max:100'],
            'items.*.unit' => ['required', 'string', 'max:150'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
        ]);
    }

    private function syncItems(Quotation $quotation, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            $quotation->items()->create([
                ...$item,
                'sort_order' => $index,
            ]);
        }
    }

    private function cleanTerms(array $terms): array
    {
        return collect($terms)
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->values()
            ->all();
    }

    private function customers()
    {
        return Customer::orderBy('company_name')
            ->get(['id', 'company_name', 'pic_name', 'pic_position', 'phone', 'address']);
    }

    private function defaultTerms(): array
    {
        return [
            'Tarif di atas sudah termasuk biaya buruh dan asuransi.',
            'Hal-hal yang bersifat Force Majeure, kami dibebaskan dari klaim. Contoh: tsunami, gunung meletus, kebanjiran, tanah longsor, dan lain-lain.',
            'Pengiriman barang harap dikonfirmasikan terlebih dahulu.',
            'Pembayaran oleh pihak PENGIRIM.',
            'Pembayaran dilakukan 14 hari setelah terima invoice.',
            'Harga tersebut sudah termasuk PPN 1,1%.',
        ];
    }

    private function imageDataUrl(?string $path): ?string
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';
        $contents = Storage::disk('public')->get($path);

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
