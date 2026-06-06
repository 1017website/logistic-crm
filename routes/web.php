<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeliveryOrderController;
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

// ── Auth required ──────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search',    [SearchController::class, 'search'])->name('search');

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/',               [NotificationController::class, 'index'])->name('index');
        Route::get('/unread-count',   [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
        Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('mark-read');
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

    // Vendors & Shipment Orders (Admin & Sales Manager only)
    Route::middleware('role:Admin,Sales Manager')->group(function () {
        Route::get('/vendors/export', [VendorController::class, 'export'])->name('vendors.export');
        Route::post('/vendors/{vendor}/services', [VendorController::class, 'storeService'])->name('vendors.services.store');
        Route::post('/vendors/{vendor}/pics', [VendorController::class, 'storePic'])->name('vendors.pics.store');
        Route::resource('vendors', VendorController::class)->only(['index', 'store', 'update']);

        Route::get('/delivery-orders/export', [DeliveryOrderController::class, 'export'])->name('delivery-orders.export');
        Route::get('/delivery-orders/{deliveryOrder}/edit', [DeliveryOrderController::class, 'edit'])->name('delivery-orders.edit');
        Route::resource('delivery-orders', DeliveryOrderController::class)->only(['index', 'store', 'update']);

        Route::resource('service-types', ServiceTypeController::class)->only(['index', 'store', 'update', 'destroy']);
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
