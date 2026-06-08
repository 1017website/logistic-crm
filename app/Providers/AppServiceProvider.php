<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\Paginator;
use App\Models\DeletionRequest;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        // Blade directive: @idr(value) — format IDR lengkap: Rp 100.000.000
        Blade::directive('idr', function ($expression) {
            return "<?php echo idr($expression); ?>";
        });

        // Blade directive: @idrm(value) — format IDR singkat: Rp 100M / Rp 1,5M
        Blade::directive('idrm', function ($expression) {
            return "<?php echo idrm($expression); ?>";
        });

        // Badge jumlah permintaan hapus pending (hanya relevan untuk Admin).
        View::composer('layouts.app', function ($view) {
            $count = 0;
            try {
                if (Schema::hasTable('deletion_requests') && auth()->check() && auth()->user()->isAdmin()) {
                    $count = DeletionRequest::where('status', 'pending')->count();
                }
            } catch (\Throwable $e) {
                $count = 0;
            }
            $view->with('pendingDeletionCount', $count);
        });
    }
}
