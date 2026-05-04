<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ArtisanController extends Controller
{
    private string $secret;

    public function __construct()
    {
        // Secret key dari .env
        $this->secret = config('app.artisan_secret', '');
    }

    public function run(Request $request, string $command)
    {
        // Validasi secret key
        if (empty($this->secret) || $request->get('key') !== $this->secret) {
            abort(403, 'Unauthorized');
        }

        // Whitelist command yang boleh dijalankan
        $allowed = [
            'migrate'          => 'migrate --force',
            'migrate-fresh'    => 'migrate:fresh --force',
            'seed'             => 'db:seed --force',
            'seed-user'        => 'db:seed --class=UserSeeder --force',
            'optimize'         => 'optimize:clear',
            'cache-clear'      => 'cache:clear',
            'view-clear'       => 'view:clear',
            'config-clear'     => 'config:clear',
            'route-clear'      => 'route:clear',
            'storage-link'     => 'storage:link',
            'notify'           => 'crm:notify',
        ];

        if (!isset($allowed[$command])) {
            return response()->json([
                'status'   => 'error',
                'message'  => 'Command tidak diizinkan.',
                'allowed'  => array_keys($allowed),
            ], 400);
        }

        try {
            $exitCode = Artisan::call($allowed[$command]);
            $output   = Artisan::output();

            return response()->json([
                'status'    => $exitCode === 0 ? 'success' : 'warning',
                'command'   => $allowed[$command],
                'exit_code' => $exitCode,
                'output'    => $output ?: 'Done.',
                'time'      => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'command' => $allowed[$command],
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
