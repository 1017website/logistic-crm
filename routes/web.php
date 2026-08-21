<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeliveryOrderController;
use App\Http\Controllers\RequestOrderController;
use App\Http\Controllers\RequestOrderItemController;
use App\Http\Controllers\OrderJobDetailController;
use App\Http\Controllers\PekerjaanController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LogisticReportController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SalesActivityController;
use App\Http\Controllers\LeadsController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\TaskReminderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ArtisanController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ServiceTypeController;
use App\Http\Controllers\DeletionRequestController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\DocumentVerificationController;

// ── Artisan runner (shared hosting) ──
Route::get('/run/{command}', [ArtisanController::class, 'run'])
    ->name('artisan.run')
    ->middleware(['auth', 'role:Admin,Sales Manager', 'throttle:10,1']);

// ── Auth ──────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Tujuan publik QR tanda tangan elektronik. Signature URL mencegah parameter diubah.
Route::get('/documents/verify/{kind}/{id}', [DocumentVerificationController::class, 'show'])
    ->whereIn('kind', ['quotation', 'invoice', 'delivery_order', 'report'])
    ->whereNumber('id')
    ->middleware('signed:relative')
    ->name('documents.verify');

// ── Auth required ──────────────────────────────────
Route::middleware(['auth', 'prevent.duplicate'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search',    [SearchController::class, 'search'])->name('search');

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/',               [NotificationController::class, 'index'])->name('index');
        Route::get('/unread-count',   [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
        Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('mark-read');
    });

    // Permintaan Hapus (deletion request)
    // store: semua role (mengajukan); index/approve/reject: Admin only.
    Route::post('/deletion-requests', [DeletionRequestController::class, 'store'])->name('deletion-requests.store');
    Route::middleware('role:Admin')->group(function () {
        Route::get('/deletion-requests', [DeletionRequestController::class, 'index'])->name('deletion-requests.index');
        Route::post('/deletion-requests/{deletionRequest}/approve', [DeletionRequestController::class, 'approve'])->name('deletion-requests.approve');
        Route::post('/deletion-requests/{deletionRequest}/reject', [DeletionRequestController::class, 'reject'])->name('deletion-requests.reject');
    });

    // Sales Activity
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/activity',  [SalesActivityController::class, 'index'])->name('activity');
        Route::post('/activity', [SalesActivityController::class, 'store'])->name('activity.store');
    });

    // Leads & Pipeline
    Route::get('/leads/export',           [LeadsController::class, 'export'])->name('leads.export');
    Route::get('/leads/template',         [LeadsController::class, 'template'])->name('leads.template');
    Route::post('/leads/import',          [LeadsController::class, 'import'])->name('leads.import');
    Route::post('/leads/{lead}/activity', [LeadsController::class, 'storeActivity'])->name('leads.activity.store');
    Route::post('/leads/{lead}/products', [LeadsController::class, 'storeProduct'])->name('leads.products.store');
    Route::post('/leads/{lead}/pics',     [LeadsController::class, 'storePic'])->name('leads.pics.store');
    Route::resource('leads', LeadsController::class)->except(['destroy']);
    Route::get('/pipeline', [PipelineController::class, 'index'])->name('pipeline.index');

    // Penawaran harga (Sales)
    Route::middleware('role:Admin,Sales Manager,Sales Executive,Sales Admin')->group(function () {
        Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
        Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
        Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
        Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
        Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
        Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
        Route::get('/quotations/{quotation}/preview', [QuotationController::class, 'preview'])->name('quotations.preview');
        Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    });

    // Calendar & Tasks
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/tasks',               [TaskReminderController::class, 'index'])->name('tasks.index');
    Route::post('/tasks',              [TaskReminderController::class, 'store'])->name('tasks.store');
    Route::patch('/tasks/{activity}',  [TaskReminderController::class, 'update'])->name('tasks.update');

    // CRM Data
    Route::get('/customers/export',               [CustomerController::class, 'export'])->name('customers.export');
    Route::get('/customers/template',             [CustomerController::class, 'template'])->name('customers.template');
    Route::post('/customers/import',              [CustomerController::class, 'import'])->name('customers.import');
    Route::post('/customers/{customer}/activity', [CustomerController::class, 'storeActivity'])->name('customers.activity.store');
    Route::post('/customers/{customer}/pics',     [CustomerController::class, 'storePic'])->name('customers.pics.store');
    Route::patch('/customers/{customer}/transfer-sales', [CustomerController::class, 'transferSales'])->name('customers.transfer-sales');
    Route::resource('customers', CustomerController::class)->except(['destroy']);

    // Vendors (termasuk Sales Admin dan Finance/Accounting)
    Route::middleware('role:Admin,Sales Manager,Sales Admin,Transport Planner,Finance')->group(function () {
        Route::get('/vendors/export', [VendorController::class, 'export'])->name('vendors.export');
        Route::get('/vendors/template', [VendorController::class, 'template'])->name('vendors.template');
        Route::post('/vendors/import', [VendorController::class, 'import'])->name('vendors.import');
        Route::post('/vendors/{vendor}/services', [VendorController::class, 'storeService'])->name('vendors.services.store');
        Route::post('/vendors/{vendor}/pics', [VendorController::class, 'storePic'])->name('vendors.pics.store');
        Route::resource('vendors', VendorController::class)->only(['index', 'store', 'update']);
    });

    // Master Service Type tetap khusus role operasional/pengelola master data.
    Route::middleware('role:Admin,Sales Manager,Transport Planner')->group(function () {
        Route::resource('service-types', ServiceTypeController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    // ── REQUEST DO (tahap 1: order → verifikasi → dispatch → approval) ──
    Route::middleware('role:Admin,Sales Manager,Sales Executive,Sales Admin,Transport Planner,Finance')->group(function () {
        Route::get('/request-orders/export', [RequestOrderController::class, 'export'])->name('request-orders.export');
        Route::get('/request-orders/{requestOrder}/edit', [RequestOrderController::class, 'edit'])->name('request-orders.edit');
        Route::resource('request-orders', RequestOrderController::class)
            ->parameters(['request-orders' => 'requestOrder'])
            ->only(['index', 'show', 'store', 'update']);
    });

    // Verifikasi data (Sales Admin / Admin)
    Route::middleware('role:Admin,Sales Admin')->group(function () {
        Route::post('/request-orders/{requestOrder}/verify', [RequestOrderController::class, 'verify'])->name('request-orders.verify');
        Route::post('/request-orders/{requestOrder}/cancel', [RequestOrderController::class, 'cancel'])->name('request-orders.cancel');
        Route::post('/request-orders/{requestOrder}/reactivate', [RequestOrderController::class, 'reactivate'])->name('request-orders.reactivate');
    });
    // Accounting/Finance melengkapi layanan dan harga setelah request dibuat.
    Route::middleware('role:Admin,Finance')->group(function () {
        Route::post('/request-orders/{requestOrder}/items', [RequestOrderItemController::class, 'store'])->name('request-order-items.store');
        Route::put('/request-order-items/{requestOrderItem}', [RequestOrderItemController::class, 'update'])->name('request-order-items.update');
        Route::delete('/request-order-items/{requestOrderItem}', [RequestOrderItemController::class, 'destroy'])->name('request-order-items.destroy');
        Route::post('/request-orders/{requestOrder}/finance-review', [RequestOrderController::class, 'financeReview'])->name('request-orders.finance-review');
        Route::post('/request-orders/{requestOrder}/dp', [RequestOrderController::class, 'updateDp'])->name('request-orders.dp.update');
        Route::post('/request-orders/{requestOrder}/dp-active', [RequestOrderController::class, 'updateDpActive'])->name('request-orders.dp-active');
    });
    // Dispatch / penugasan armada (Sales Admin / Transport Planner / Admin)
    Route::middleware('role:Admin,Sales Admin,Transport Planner')->group(function () {
        Route::post('/request-orders/{requestOrder}/dispatch', [RequestOrderController::class, 'dispatchAssign'])->name('request-orders.dispatch');
    });
    // Status pelaksanaan DO dapat diubah oleh operasional dan manager.
    Route::middleware('role:Admin,Sales Manager,Sales Admin,Transport Planner')->group(function () {
        Route::post('/request-orders/{requestOrder}/operational-status', [RequestOrderController::class, 'updateOperationalStatus'])->name('request-orders.operational-status');
    });
    // Approval penugasan (Sales Manager / Admin)
    Route::middleware('role:Admin,Sales Manager')->group(function () {
        Route::post('/request-orders/{requestOrder}/approve', [RequestOrderController::class, 'approveAssign'])->name('request-orders.approve');
    });

    // ── DELIVERY ORDER (tahap 2: surat jalan → pickup → delivery → POD → tutup → finance) ──
    Route::middleware('role:Admin,Sales Manager,Sales Admin,Transport Planner,Finance')->group(function () {
        Route::get('/delivery-orders/export', [DeliveryOrderController::class, 'export'])->name('delivery-orders.export');
        Route::get('/delivery-orders/{deliveryOrder}/surat-jalan/print', [DeliveryOrderController::class, 'printSuratJalan'])->name('delivery-orders.surat-jalan.print');
        Route::resource('delivery-orders', DeliveryOrderController::class)->only(['index', 'show']);
    });
    // Aksi lapangan & tutup DO (Sales Admin / Admin)
    Route::middleware('role:Admin,Sales Admin')->group(function () {
        Route::post('/delivery-orders/{deliveryOrder}/surat-jalan', [DeliveryOrderController::class, 'uploadSuratJalan'])->name('delivery-orders.surat-jalan');
        Route::post('/delivery-orders/{deliveryOrder}/pickup', [DeliveryOrderController::class, 'markPickup'])->name('delivery-orders.pickup');
        Route::post('/delivery-orders/{deliveryOrder}/delivered', [DeliveryOrderController::class, 'markDelivered'])->name('delivery-orders.delivered');
        Route::post('/delivery-orders/{deliveryOrder}/pod', [DeliveryOrderController::class, 'uploadPod'])->name('delivery-orders.pod');
        Route::post('/delivery-orders/{deliveryOrder}/close', [DeliveryOrderController::class, 'closeDo'])->name('delivery-orders.close');
    });
    // Finance: invoice & payment (Finance / Admin)
    Route::middleware('role:Admin,Finance')->group(function () {
        Route::post('/delivery-orders/{deliveryOrder}/invoice', [DeliveryOrderController::class, 'invoice'])->name('delivery-orders.invoice');
        Route::post('/delivery-orders/{deliveryOrder}/pay', [DeliveryOrderController::class, 'pay'])->name('delivery-orders.pay');
    });

    // ── RINCIAN BIAYA PER PEKERJAAN (Accounting/Finance / Admin) ──
    Route::middleware('role:Admin,Finance')->group(function () {
        Route::post('/request-orders/{requestOrder}/job-details', [OrderJobDetailController::class, 'store'])->name('job-details.store');
        Route::put('/job-details/{jobDetail}', [OrderJobDetailController::class, 'update'])->name('job-details.update');
    });
    Route::middleware('role:Admin,Sales Admin,Sales Manager')->group(function () {
        // Approval DO (bandingkan jual vs hpp) — siapkan DO untuk ditagih
        Route::post('/request-orders/{requestOrder}/approve-do', [RequestOrderController::class, 'approveDo'])->name('request-orders.approve-do');
    });
    // Hapus rincian (Admin only — destructive)
    Route::middleware('role:Admin')->group(function () {
        Route::delete('/job-details/{jobDetail}', [OrderJobDetailController::class, 'destroy'])->name('job-details.destroy');
    });

    // ── MASTER PEKERJAAN (Admin / Sales Manager / Transport Planner) ──
    Route::middleware('role:Admin,Sales Manager,Transport Planner')->group(function () {
        Route::resource('pekerjaan', PekerjaanController::class)->only(['index', 'store', 'update']);
        Route::delete('/pekerjaan/{pekerjaan}', [PekerjaanController::class, 'destroy'])->name('pekerjaan.destroy')->middleware('role:Admin');
    });

    // ── INVOICE (Finance / Admin / Sales Manager) ──
    Route::middleware('role:Admin,Finance,Sales Manager')->group(function () {
        Route::get('/invoices/available-dos', [InvoiceController::class, 'availableDos'])->name('invoices.available-dos');
        Route::get('/invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
        Route::get('/invoices/export/pdf', [InvoiceController::class, 'exportPdf'])->name('invoices.export-pdf');
        Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::get('/invoices/{invoice}/excel', [InvoiceController::class, 'exportInvoice'])->name('invoices.excel');
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/invoices/{invoice}/submit', [InvoiceController::class, 'submit'])->name('invoices.submit');
        Route::post('/invoices/{invoice}/unsubmit', [InvoiceController::class, 'unsubmit'])->name('invoices.unsubmit');
        Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
        Route::put('/invoices/{invoice}/number', [InvoiceController::class, 'updateNumber'])->name('invoices.number');
        Route::put('/invoices/{invoice}/ppn', [InvoiceController::class, 'updatePpn'])->name('invoices.ppn');
        Route::put('/invoices/{invoice}/items/{invoiceItem}', [InvoiceController::class, 'updateItem'])->name('invoices.items.update');
    });
    Route::middleware('role:Finance')->group(function () {
        Route::post('/invoices/{invoice}/request-edit', [InvoiceController::class, 'requestEdit'])->name('invoices.request-edit');
        Route::post('/invoices/{invoice}/finish-edit', [InvoiceController::class, 'finishEdit'])->name('invoices.finish-edit');
    });
    Route::middleware('role:Super Admin')->group(function () {
        Route::post('/invoices/{invoice}/review-edit', [InvoiceController::class, 'reviewEdit'])->name('invoices.review-edit');
    });
    Route::middleware('role:Admin,Finance')->group(function () {
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    });

    // ── LAPORAN LOGISTIK: DO & Invoice (Admin/Sales Manager/Finance) ──
    Route::middleware('role:Admin,Sales Manager,Finance')->group(function () {
        Route::get('/laporan-logistik', [LogisticReportController::class, 'index'])->name('logistic-reports.index');
        Route::get('/laporan-logistik/do', [LogisticReportController::class, 'do'])->name('logistic-reports.do');
        Route::get('/laporan-logistik/do/export', [LogisticReportController::class, 'doExport'])->name('logistic-reports.do.export');
        Route::get('/laporan-logistik/invoice', [LogisticReportController::class, 'invoice'])->name('logistic-reports.invoice');
        Route::get('/laporan-logistik/invoice/export', [LogisticReportController::class, 'invoiceExport'])->name('logistic-reports.invoice.export');
    });

    // ── Manager & Admin only ───────────────────────
    Route::middleware('role:Admin,Sales Manager')->group(function () {
        Route::get('/analytics',      [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/reports',        [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportsController::class, 'export'])->name('reports.export');
    });

    // ── DELETE / DESTROY — ADMIN ONLY ──────────────
    // Sales Manager & Sales Executive tidak boleh menghapus apapun.
    Route::middleware('role:Admin')->group(function () {
        Route::delete('/leads/{lead}', [LeadsController::class, 'destroy'])->name('leads.destroy');
        Route::delete('/leads/{lead}/products/{product}', [LeadsController::class, 'destroyProduct'])->name('leads.products.destroy');
        Route::delete('/leads/{lead}/pics/{pic}', [LeadsController::class, 'destroyPic'])->name('leads.pics.destroy');

        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::delete('/customers/{customer}/pics/{pic}', [CustomerController::class, 'destroyPic'])->name('customers.pics.destroy');

        Route::delete('/tasks/{activity}', [TaskReminderController::class, 'destroy'])->name('tasks.destroy');

        Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');
        Route::delete('/vendors/{vendor}/services/{service}', [VendorController::class, 'destroyService'])->name('vendors.services.destroy');
        Route::delete('/vendors/{vendor}/pics/{pic}', [VendorController::class, 'destroyPic'])->name('vendors.pics.destroy');

        Route::delete('/request-orders/{requestOrder}', [RequestOrderController::class, 'destroy'])->name('request-orders.destroy');
        Route::delete('/delivery-orders/{deliveryOrder}', [DeliveryOrderController::class, 'destroy'])->name('delivery-orders.destroy');
    });

    // ── Admin only ─────────────────────────────────
    Route::middleware('role:Admin')->group(function () {
        Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/delete-image', [SettingsController::class, 'deleteLogo'])->name('settings.delete-image');
    });
});
