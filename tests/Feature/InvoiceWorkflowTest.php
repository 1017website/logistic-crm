<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\OrderJobDetail;
use App\Models\RequestOrder;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class InvoiceWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_combined_request_is_forced_into_separate_tr_and_ntr_invoices(): void
    {
        [$user, $customer, $deliveryOrder] = $this->makePodReadyOrder();
        $payload = $this->invoicePayload($customer, $deliveryOrder, 'combined');
        $payload['_action_token'] = 'invoice_action_token_123456';

        $this->actingAs($user)
            ->from(route('invoices.index'))
            ->post(route('invoices.store'), $payload)
            ->assertRedirect(route('invoices.index', ['tab' => 'draft']));

        $invoices = Invoice::with('items')->where('customer_id', $customer->id)->get();
        $this->assertCount(2, $invoices);
        $this->assertEqualsCanonicalizing(['TR', 'NTR'], $invoices->pluck('jenis')->all());
        $this->assertTrue($invoices->every(fn(Invoice $invoice) => $invoice->items->count() === 1));
        $this->assertSame('invoiced', $deliveryOrder->fresh()->invoice_status);

        $this->actingAs($user)
            ->from(route('invoices.index'))
            ->post(route('invoices.store'), $payload)
            ->assertSessionHas('warning');

        $this->assertSame(2, Invoice::where('customer_id', $customer->id)->count());
    }

    public function test_separate_mode_creates_one_invoice_per_service_type(): void
    {
        [$user, $customer, $deliveryOrder] = $this->makePodReadyOrder();
        $payload = $this->invoicePayload($customer, $deliveryOrder, 'separate');
        $payload['ppn_types'] = ['TR'];
        $payload['ppn_persen'] = 1.1;

        $this->actingAs($user)
            ->post(route('invoices.store'), $payload)
            ->assertRedirect(route('invoices.index', ['tab' => 'draft']));

        $this->assertSame(2, Invoice::where('customer_id', $customer->id)->count());
        $this->assertEqualsCanonicalizing(['TR', 'NTR'], Invoice::where('customer_id', $customer->id)->pluck('jenis')->all());
        $this->assertSame('1.10', Invoice::where('customer_id', $customer->id)->where('jenis', 'TR')->sole()->ppn_persen);
        $this->assertSame('0.00', Invoice::where('customer_id', $customer->id)->where('jenis', 'NTR')->sole()->ppn_persen);
    }

    public function test_invoice_customer_selector_only_shows_closed_dos_with_unbilled_components(): void
    {
        [$finance, $closedCustomer, $deliveryOrder] = $this->makePodReadyOrder();
        $customerWithoutClosedDo = Customer::create([
            'customer_code' => 'CUST-NO-CLOSED-' . uniqid(),
            'company_name' => 'Customer Tanpa DO Closed ' . uniqid(),
            'pic_name' => 'PIC',
            'phone' => '0800000001',
            'user_id' => $finance->id,
        ]);

        $response = $this->actingAs($finance)->get(route('invoices.index'));
        $response->assertOk();
        $selector = $this->invoiceCustomerSelector($response->getContent());
        $this->assertStringContainsString($closedCustomer->company_name, $selector);
        $this->assertStringNotContainsString($customerWithoutClosedDo->company_name, $selector);

        $this->actingAs($finance)
            ->post(route('invoices.store'), $this->invoicePayload($closedCustomer, $deliveryOrder, 'separate'))
            ->assertSessionHas('success');

        $response = $this->actingAs($finance)->get(route('invoices.index'));
        $response->assertOk();
        $selector = $this->invoiceCustomerSelector($response->getContent());
        $this->assertStringNotContainsString($closedCustomer->company_name, $selector);
        $this->assertStringContainsString('Belum ada customer dengan DO Closed siap tagih', $selector);
    }

    public function test_invoice_list_bundles_customer_and_expands_payment_totals(): void
    {
        [$finance, $customer, $deliveryOrder] = $this->makePodReadyOrder();
        $this->actingAs($finance)
            ->post(route('invoices.store'), $this->invoicePayload($customer, $deliveryOrder, 'separate'));
        $invoices = Invoice::where('customer_id', $customer->id)->orderBy('jenis')->get();

        foreach ($invoices as $invoice) {
            $this->actingAs($finance)->post(route('invoices.submit', $invoice));
            $this->actingAs($finance)->post(route('invoices.pay', $invoice), [
                'payment_type' => 'termin',
                'tgl_pencairan' => '2026-08-01',
                'amount' => $invoice->jenis === 'TR' ? 250000 : 50000,
            ])->assertSessionHas('success');
        }

        $invoices = $invoices->map->fresh()->each->load('payments');
        $totalInvoice = $invoices->sum(fn(Invoice $invoice) => (float) $invoice->grand_total);
        $totalPaid = $invoices->sum(fn(Invoice $invoice) => $invoice->total_paid);
        $outstanding = $invoices->sum(fn(Invoice $invoice) => $invoice->outstanding);

        $response = $this->actingAs($finance)->get(route('invoices.index', ['tab' => 'paid']));
        $response->assertOk()
            ->assertSee('Jumlah Invoice')
            ->assertSee('Sudah Terbayar')
            ->assertSee('Belum Terbayar')
            ->assertSee('Lihat Invoice')
            ->assertSee('2 invoice')
            ->assertSee(idr($totalInvoice))
            ->assertSee(idr($totalPaid))
            ->assertSee(idr($outstanding));

        $html = $response->getContent();
        foreach ($invoices as $invoice) {
            $this->assertStringContainsString($invoice->invoice_number, $html);
        }
    }

    public function test_one_invoice_can_contain_multiple_delivery_orders_for_the_same_customer(): void
    {
        [$user, $customer, $firstDo] = $this->makePodReadyOrder();
        $secondDo = $this->makeAdditionalPodReadyOrder($user, $customer);
        $payload = $this->invoicePayload($customer, $firstDo, 'combined');
        $payload['selections'] = [
            $firstDo->id . ':TR',
            $firstDo->id . ':NTR',
            $secondDo->id . ':TR',
            $secondDo->id . ':NTR',
        ];

        $this->actingAs($user)
            ->post(route('invoices.store'), $payload)
            ->assertRedirect(route('invoices.index', ['tab' => 'draft']))
            ->assertSessionHas('success');

        $invoice = Invoice::with('items')->where('customer_id', $customer->id)->where('jenis', 'TR')->sole();
        $this->assertSame(2, $invoice->do_count);
        $this->assertCount(2, $invoice->items);
        $this->assertEqualsCanonicalizing(
            [$firstDo->id, $secondDo->id],
            $invoice->items->pluck('delivery_order_id')->unique()->values()->all()
        );
        $this->assertSame('1400000', $invoice->total_hpp);
        $this->assertSame('2000000', $invoice->total_jual);

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee($firstDo->do_number)
            ->assertSee($secondDo->do_number);
    }

    public function test_closed_delivery_order_automatically_creates_invoice_drafts(): void
    {
        [$finance, $customer, $deliveryOrder] = $this->makePodReadyOrder();
        $deliveryOrder->update([
            'status' => 'pod',
            'pod_at' => null,
            'pod_file' => null,
        ]);

        $this->assertSame('Menunggu Upload POD', $deliveryOrder->fresh()->flow_label);

        $this->actingAs($finance)
            ->getJson(route('invoices.available-dos', ['customer_id' => $customer->id]))
            ->assertOk()
            ->assertExactJson([]);

        Storage::fake('public');
        $admin = User::create([
            'name' => 'Sales Admin POD Test',
            'email' => 'sales-admin-pod-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Sales Admin',
            'status' => 'Active',
        ]);

        $this->actingAs($admin)
            ->post(route('delivery-orders.pod', $deliveryOrder), [
                'pod_file' => UploadedFile::fake()->create('pod.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHas('success');

        $deliveryOrder->refresh();
        $this->assertNotNull($deliveryOrder->pod_at);
        $this->assertNotNull($deliveryOrder->pod_file);
        $this->assertSame('POD Diterima (Menunggu Verifikasi)', $deliveryOrder->flow_label);
        $this->assertSame(0, Invoice::where('customer_id', $customer->id)->count());

        $this->actingAs($admin)
            ->post(route('delivery-orders.close', $deliveryOrder), [
                'actual_cost' => 800000,
                'other_cost' => 50000,
                'note' => 'POD valid, DO selesai.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'DO ditutup. Draft invoice otomatis sudah tersedia di tab Draft.');

        $deliveryOrder->refresh();
        $this->assertSame('closed', $deliveryOrder->status);
        $this->assertSame('invoiced', $deliveryOrder->invoice_status);
        $drafts = Invoice::with('items')->where('customer_id', $customer->id)->get();
        $this->assertCount(2, $drafts);
        $this->assertEqualsCanonicalizing(['TR', 'NTR'], $drafts->pluck('jenis')->all());
        $this->assertTrue($drafts->every(fn(Invoice $invoice) => $invoice->status === 'draft'));
        $this->assertTrue($drafts->every(fn(Invoice $invoice) => $invoice->items->contains('delivery_order_id', $deliveryOrder->id)));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $finance->id,
            'type' => 'invoice_auto_draft',
            'title' => 'Draft invoice otomatis tersedia',
        ]);

        $duplicateAttempt = app(\App\Services\AutomaticInvoiceDraftService::class)
            ->createForClosedDeliveryOrder($deliveryOrder, $admin->id);
        $this->assertCount(0, $duplicateAttempt);
        $this->assertSame(2, Invoice::where('customer_id', $customer->id)->count());

        $this->actingAs($finance)
            ->get(route('invoices.index', ['tab' => 'draft']))
            ->assertOk()
            ->assertSee($customer->company_name)
            ->assertSee('1 DO');
    }

    public function test_paid_invoice_synchronizes_delivery_order_to_paid(): void
    {
        [$user, $customer, $deliveryOrder] = $this->makePodReadyOrder();

        $this->actingAs($user)
            ->post(route('invoices.store'), $this->invoicePayload($customer, $deliveryOrder, 'combined'));

        $invoices = Invoice::where('customer_id', $customer->id)->get();
        $deliveryOrder->update(['status' => 'closed']);

        foreach ($invoices as $invoice) {
            $this->actingAs($user)->post(route('invoices.submit', $invoice));
            $this->actingAs($user)->post(route('invoices.pay', $invoice), [
                'payment_type' => 'pelunasan',
                'tgl_pencairan' => '2026-07-30',
            ]);
        }

        $this->assertTrue($invoices->every(fn(Invoice $invoice) => $invoice->fresh()->status === 'paid'));
        $this->assertSame('paid', $deliveryOrder->fresh()->status);
        $this->assertSame('paid', $deliveryOrder->fresh()->invoice_status);
        $this->assertSame('paid', $deliveryOrder->requestOrder->fresh()->invoice_status);
    }

    public function test_invoice_can_receive_termin_before_final_payment(): void
    {
        [$user, $customer, $deliveryOrder] = $this->makePodReadyOrder();

        $this->actingAs($user)
            ->post(route('invoices.store'), $this->invoicePayload($customer, $deliveryOrder, 'separate'));

        $invoice = Invoice::where('customer_id', $customer->id)->where('jenis', 'TR')->sole();
        $deliveryOrder->update(['status' => 'closed']);
        $this->actingAs($user)->post(route('invoices.submit', $invoice));

        $this->actingAs($user)->post(route('invoices.pay', $invoice), [
            'payment_type' => 'termin',
            'tgl_pencairan' => '2026-07-15',
            'amount' => 250000,
            'note' => 'Pembayaran titip pertama',
        ])->assertSessionHas('success');

        $invoice->refresh()->load('payments');
        $this->assertSame('termin', $invoice->status);
        $this->assertSame(250000.0, $invoice->total_paid);
        $this->assertSame((float) $invoice->grand_total - 250000, $invoice->outstanding);
        $this->assertNull($invoice->tgl_pencairan);
        $this->assertSame('invoiced', $deliveryOrder->fresh()->status);
        $this->assertSame('invoiced', $deliveryOrder->fresh()->invoice_status);
        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'amount' => 250000,
            'payment_type' => 'termin',
            'note' => 'Pembayaran titip pertama',
            'recorded_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('invoices.index', ['tab' => 'paid']))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('Termin')
            ->assertSee('Terbayar');

        $this->actingAs($user)
            ->get(route('logistic-reports.invoice', [
                'customer_id' => $customer->id,
                'jenis' => 'unpaid',
            ]))
            ->assertOk()
            ->assertViewHas('sumOutstanding', fn ($value) => (float) $value === $invoice->outstanding)
            ->assertSee('Outstanding Belum Terbayar')
            ->assertSee('Outstanding = grand total invoice');

        $this->actingAs($user)->post(route('invoices.pay', $invoice), [
            'payment_type' => 'pelunasan',
            'tgl_pencairan' => '2026-07-30',
            'note' => 'Pelunasan',
        ])->assertSessionHas('success');

        $invoice->refresh()->load('payments');
        $this->assertSame('paid', $invoice->status);
        $this->assertSame((float) $invoice->grand_total, $invoice->total_paid);
        $this->assertSame(0.0, $invoice->outstanding);
        $this->assertSame('2026-07-30', $invoice->tgl_pencairan?->format('Y-m-d'));
        $this->assertCount(2, $invoice->payments);
        $this->assertSame(1, InvoicePayment::where('invoice_id', $invoice->id)->where('payment_type', 'pelunasan')->count());

        // Komponen invoice NTR belum lunas, jadi DO belum boleh berubah menjadi paid.
        $this->assertSame('invoiced', $deliveryOrder->fresh()->status);
        $this->assertSame('invoiced', $deliveryOrder->fresh()->invoice_status);
    }

    public function test_termin_amount_cannot_equal_the_remaining_invoice_balance(): void
    {
        [$user, $customer, $deliveryOrder] = $this->makePodReadyOrder();
        $this->actingAs($user)
            ->post(route('invoices.store'), $this->invoicePayload($customer, $deliveryOrder, 'separate'));

        $invoice = Invoice::where('customer_id', $customer->id)->where('jenis', 'TR')->sole();
        $this->actingAs($user)->post(route('invoices.submit', $invoice));

        $this->actingAs($user)->post(route('invoices.pay', $invoice), [
            'payment_type' => 'termin',
            'tgl_pencairan' => '2026-07-15',
            'amount' => (float) $invoice->grand_total,
        ])->assertSessionHasErrors('amount');

        $this->assertSame('invoice', $invoice->fresh()->status);
        $this->assertDatabaseMissing('invoice_payments', ['invoice_id' => $invoice->id]);
    }

    public function test_printable_documents_use_the_dedicated_logo_and_expected_qr_purpose(): void
    {
        [$user, $customer, $deliveryOrder] = $this->makePodReadyOrder();

        $this->actingAs($user)
            ->post(route('invoices.store'), $this->invoicePayload($customer, $deliveryOrder, 'combined'));

        $invoice = Invoice::where('customer_id', $customer->id)->where('jenis', 'TR')->sole();

        Setting::set('company_name', 'PT Print Test');
        Setting::set('company_doc_logo', 'branding/print-logo.png');
        Setting::set('company_signatory_name', 'Anggi Sanjaya');
        Setting::set('company_signatory_title', 'Direktur');

        $admin = User::create([
            'name' => 'Admin Print Test',
            'email' => 'admin-print-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);

        try {
            $this->actingAs($user)
                ->get(route('invoices.print', $invoice))
                ->assertOk()
                ->assertSee('/storage/branding/print-logo.png', false)
                ->assertSee('data:image/png;base64,', false)
                ->assertSee('ditandatangani secara elektronik', false)
                ->assertSee('Anggi')
                ->assertSee('Sales Manager');

            $this->actingAs($user)
                ->get(route('delivery-orders.surat-jalan.print', $deliveryOrder))
                ->assertOk()
                ->assertSee('/storage/branding/print-logo.png', false)
                ->assertSee('data:image/png;base64,', false)
                ->assertSee('SCAN UNTUK TRACKING')
                ->assertSee('Gunakan kamera ponsel')
                ->assertDontSee('ditandatangani secara elektronik', false);

            $this->actingAs($admin)
                ->get(route('reports.index'))
                ->assertOk()
                ->assertSee('/storage/branding/print-logo.png', false)
                ->assertSee('data:image/png;base64,', false)
                ->assertSee('ditandatangani secara elektronik', false)
                ->assertSee('Anggi Sanjaya')
                ->assertSee('Direktur');

            $documents = [
                ['kind' => 'invoice', 'id' => $invoice->id, 'number' => $invoice->invoice_number, 'signer' => 'Anggi'],
                ['kind' => 'delivery_order', 'id' => $deliveryOrder->id, 'number' => $deliveryOrder->do_number, 'signer' => 'Anggi Sanjaya'],
            ];
            foreach ($documents as $document) {
                $verificationUrl = URL::signedRoute('documents.verify', [
                    'kind' => $document['kind'],
                    'id' => $document['id'],
                ], absolute: false);
                $this->get($verificationUrl)
                    ->assertOk()
                    ->assertSee('Dokumen Terverifikasi')
                    ->assertSee($document['number'])
                    ->assertSee($document['signer']);
            }

            $reportVerificationUrl = URL::signedRoute('documents.verify', [
                'kind' => 'report',
                'id' => 1723968000,
                'report_type' => 'sales',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
            ], absolute: false);
            $this->get($reportVerificationUrl)
                ->assertOk()
                ->assertSee('Dokumen Terverifikasi')
                ->assertSee('Sales Report')
                ->assertSee('2026-08-01 s/d 2026-08-31');

            $this->get(route('documents.verify', ['kind' => 'invoice', 'id' => $invoice->id]))
                ->assertForbidden();
        } finally {
            Cache::flush();
        }
    }

    public function test_finance_can_edit_invoice_only_after_super_admin_approval(): void
    {
        [$finance, $customer, $deliveryOrder] = $this->makePodReadyOrder();
        $this->actingAs($finance)->post(route('invoices.store'), $this->invoicePayload($customer, $deliveryOrder, 'separate'));
        $invoice = Invoice::with('items')->where('customer_id', $customer->id)->where('jenis', 'TR')->sole();
        $item = $invoice->items->firstOrFail();

        $this->actingAs($finance)->put(route('invoices.number', $invoice), [
            'invoice_number' => 'TIDAK-BOLEH',
        ])->assertForbidden();

        $this->actingAs($finance)->post(route('invoices.request-edit', $invoice), [
            'reason' => 'Perlu memperbaiki uraian dan harga.',
        ])->assertRedirect();
        $this->assertSame('pending', $invoice->fresh()->edit_request_status);

        $regularAdmin = User::create([
            'name' => 'Regular Admin Invoice Test',
            'email' => 'regular-admin-invoice-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);
        $this->actingAs($regularAdmin)->post(route('invoices.review-edit', $invoice), [
            'action' => 'approve',
        ])->assertForbidden();
        $this->actingAs($regularAdmin)->put(route('invoices.number', $invoice), [
            'invoice_number' => 'ADMIN-TIDAK-BOLEH',
        ])->assertForbidden();

        $superAdmin = User::create([
            'name' => 'Super Admin Invoice Test',
            'email' => 'super-admin-invoice-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Super Admin',
            'status' => 'Active',
        ]);
        $this->actingAs($superAdmin)->post(route('invoices.review-edit', $invoice), [
            'action' => 'approve',
            'note' => 'Silakan koreksi.',
        ])->assertRedirect();

        $this->actingAs($finance)->put(route('invoices.items.update', [$invoice, $item]), [
            'item_name' => 'Jasa Trucking',
            'description' => 'Surabaya ke Jakarta',
            'truck_type' => 'Trailer 40 Feet',
            'quantity' => 2,
            'unit_price' => 1250000,
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame('Jasa Trucking', $item->item_name);
        $this->assertSame('Trailer 40 Feet', $item->truck_type);
        $this->assertSame('2500000', $item->jual);
        $this->assertSame('2500000', $invoice->fresh()->total_jual);

        $this->actingAs($finance)->post(route('invoices.finish-edit', $invoice))->assertRedirect();
        $this->actingAs($finance)->put(route('invoices.number', $invoice), [
            'invoice_number' => 'TERKUNCI-LAGI',
        ])->assertForbidden();
    }

    public function test_finance_can_set_supported_ppn_rates_on_draft_without_edit_approval(): void
    {
        [$finance, $customer, $deliveryOrder] = $this->makePodReadyOrder();
        $this->actingAs($finance)
            ->post(route('invoices.store'), $this->invoicePayload($customer, $deliveryOrder, 'separate'));

        $invoice = Invoice::where('customer_id', $customer->id)->where('jenis', 'TR')->sole();
        $nonTrInvoice = Invoice::where('customer_id', $customer->id)->where('jenis', 'NTR')->sole();
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('none', $invoice->edit_request_status);

        $this->actingAs($finance)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Trucking (TR)')
            ->assertSee('Non-Trucking (Non-TR)')
            ->assertSee('11%')
            ->assertSee('1,1%');

        $this->actingAs($finance)->put(route('invoices.ppn', $invoice), [
            'ppn_types' => ['TR'],
            'ppn_persen' => 1.1,
        ])->assertSessionHas('success');

        $invoice->refresh();
        $nonTrInvoice->refresh();
        $this->assertSame('1.10', $invoice->ppn_persen);
        $this->assertSame('0.00', $nonTrInvoice->ppn_persen);
        $this->assertSame((string) round((float) $invoice->total_jual * 0.011), $invoice->ppn_nominal);
        $this->assertSame(
            (string) ((float) $invoice->total_jual + round((float) $invoice->total_jual * 0.011)),
            $invoice->grand_total
        );

        $this->actingAs($finance)->put(route('invoices.ppn', $invoice), [
            'ppn_types' => ['NTR'],
            'ppn_persen' => 11,
        ])->assertSessionHas('success');
        $this->assertSame('0.00', $invoice->fresh()->ppn_persen);
        $this->assertSame('11.00', $nonTrInvoice->fresh()->ppn_persen);

        $this->actingAs($finance)->put(route('invoices.ppn', $invoice), [
            'ppn_types' => ['TR'],
            'ppn_persen' => 5,
        ])->assertSessionHasErrors('ppn_persen');
        $this->assertSame('0.00', $invoice->fresh()->ppn_persen);
        $this->assertSame('11.00', $nonTrInvoice->fresh()->ppn_persen);

        $this->actingAs($finance)->put(route('invoices.ppn', $invoice), [
            'ppn_types' => [],
        ])->assertSessionHas('success');
        $invoice->refresh();
        $nonTrInvoice->refresh();
        $this->assertSame('0.00', $invoice->ppn_persen);
        $this->assertSame('0.00', $nonTrInvoice->ppn_persen);
        $this->assertSame('0', $invoice->ppn_nominal);
        $this->assertSame($invoice->total_jual, $invoice->grand_total);
    }

    public function test_invoice_can_be_downloaded_as_pdf_and_excel_with_detail_columns(): void
    {
        [$finance, $customer, $deliveryOrder] = $this->makePodReadyOrder();
        $this->actingAs($finance)->post(route('invoices.store'), $this->invoicePayload($customer, $deliveryOrder, 'separate'));
        $invoice = Invoice::where('customer_id', $customer->id)->where('jenis', 'TR')->sole();

        $this->actingAs($finance)->get(route('invoices.pdf', $invoice))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($finance)->get(route('invoices.excel', $invoice))
            ->assertOk()->assertDownload();
        $this->actingAs($finance)->get(route('invoices.export-pdf', ['customer_id' => $customer->id, 'status' => 'draft']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_excel_export_includes_summary_row_when_legacy_invoice_has_no_items(): void
    {
        [$finance, $customer] = $this->makePodReadyOrder();
        $invoice = Invoice::create([
            'invoice_id' => 'IV-LEGACY-' . uniqid(),
            'invoice_number' => 'LEGACY/EXPORT/VIII/2026',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'tgl_buat' => '2026-08-22',
            'total_hpp' => 750000,
            'total_jual' => 1000000,
            'grand_total' => 1000000,
            'jenis' => 'TR',
            'operator_id' => $finance->id,
        ]);

        $response = $this->actingAs($finance)->get(route('invoices.export', [
            'status' => 'draft',
            'customer_id' => $customer->id,
        ]))->assertOk()->assertDownload();

        $path = tempnam(sys_get_temp_dir(), 'invoice-export-');
        try {
            file_put_contents($path, $response->streamedContent());
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertSame($invoice->invoice_id, $sheet->getCell('A2')->getValue());
            $this->assertSame('LEGACY/EXPORT/VIII/2026', $sheet->getCell('B2')->getValue());
            $this->assertSame(1000000.0, $sheet->getCell('N2')->getValue());
        } finally {
            @unlink($path);
        }
    }

    private function makePodReadyOrder(): array
    {
        $user = User::create([
            'name' => 'Finance Test',
            'email' => 'finance-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Finance',
            'status' => 'Active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'CUST-' . uniqid(),
            'invoice_code' => 'INV' . strtoupper(substr(uniqid(), -5)),
            'company_name' => 'Customer Invoice Test',
            'pic_name' => 'PIC',
            'phone' => '0800000000',
            'user_id' => $user->id,
        ]);
        $deliveryOrder = $this->makeAdditionalPodReadyOrder($user, $customer);

        return [$user, $customer, $deliveryOrder];
    }

    private function makeAdditionalPodReadyOrder(User $user, Customer $customer): DeliveryOrder
    {
        $requestOrder = RequestOrder::create([
            'do_number' => 'RDO-' . uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'In Progress',
            'request_status' => 'assigned',
            'order_date' => '2026-07-29',
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
            'do_approved' => true,
            'invoice_status' => 'uninvoiced',
        ]);

        OrderJobDetail::create([
            'request_order_id' => $requestOrder->id,
            'job_name' => 'Trucking',
            'job_code' => 'TR',
            'riil_biaya' => 700000,
            'riil_jual' => 1000000,
        ]);
        OrderJobDetail::create([
            'request_order_id' => $requestOrder->id,
            'job_name' => 'Jasa Bongkar',
            'job_code' => 'NTR',
            'riil_biaya' => 100000,
            'riil_jual' => 250000,
        ]);

        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-' . uniqid(),
            'request_order_id' => $requestOrder->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'closed',
            'invoice_status' => 'uninvoiced',
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
            'do_date' => '2026-07-29',
            'pod_at' => '2026-07-30 09:00:00',
        ]);

        return $deliveryOrder;
    }

    private function invoicePayload(Customer $customer, DeliveryOrder $deliveryOrder, string $mode): array
    {
        return [
            'customer_id' => $customer->id,
            'tgl_buat' => '2026-07-30',
            'tgl_tempo' => '2026-08-29',
            'selections' => [
                $deliveryOrder->id . ':TR',
                $deliveryOrder->id . ':NTR',
            ],
            'billing_mode' => $mode,
            'ppn_mode' => 'non_ppn',
            'notes' => 'Test invoice',
        ];
    }

    private function invoiceCustomerSelector(string $html): string
    {
        $matched = preg_match('/<select\b[^>]*\bid="invCustomer"[^>]*>.*?<\/select>/s', $html, $matches);
        $this->assertSame(1, $matched, 'Select customer pada modal Buat Invoice tidak ditemukan.');

        return $matches[0];
    }
}
