<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SalesActivityController;
use App\Http\Controllers\LeadsController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\TaskReminderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;

// ── Auth (Guest only) ──────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::get('/', fn() => redirect()->route('dashboard'));

// ── Semua role (auth) ──────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search',    [SearchController::class, 'search'])->name('search');

    // Sales
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/activity', [SalesActivityController::class, 'index'])->name('activity');
        Route::post('/activity', [SalesActivityController::class, 'store'])->name('activity.store');
    });

    // Leads & Pipeline
    Route::resource('leads', LeadsController::class);
    Route::get('/pipeline', [PipelineController::class, 'index'])->name('pipeline.index');

    // Calendar & Tasks
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/tasks',               [TaskReminderController::class, 'index'])->name('tasks.index');
    Route::post('/tasks',              [TaskReminderController::class, 'store'])->name('tasks.store');
    Route::patch('/tasks/{activity}',  [TaskReminderController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{activity}', [TaskReminderController::class, 'destroy'])->name('tasks.destroy');

    // CRM Data
    Route::resource('customers', CustomerController::class);
    Route::resource('vendors', VendorController::class)->only(['index', 'show']);

    // ── Manager & Admin only ───────────────────────
    Route::middleware('role:Admin,Sales Manager')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/reports',   [ReportsController::class, 'index'])->name('reports.index');
        Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);
    });

    // ── Admin only ─────────────────────────────────
    Route::middleware('role:Admin')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
