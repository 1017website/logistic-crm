<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PreventDuplicateSubmission
{
    /**
     * Tolak pengiriman ulang form yang memakai token aksi yang sama.
     *
     * Token ditambahkan oleh layout utama pada setiap form mutasi. Cache::add
     * bersifat atomic, sehingga dua request yang tiba bersamaan hanya akan
     * mengizinkan request pertama berjalan.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $token = (string) $request->input('_action_token', '');
        if ($token === '') {
            return $next($request);
        }

        if (!preg_match('/^[A-Za-z0-9_-]{16,100}$/', $token)) {
            abort(422, 'Token aksi tidak valid. Silakan muat ulang halaman.');
        }

        $actor = $request->user()?->getAuthIdentifier()
            ?? $request->session()->getId()
            ?? $request->ip();
        $key = 'action-once:' . hash('sha256', $actor . '|' . $token);

        if (!Cache::add($key, true, now()->addMinutes(30))) {
            $message = 'Aksi yang sama sudah diproses. Data tidak dikirim ulang.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 409);
            }

            return back()->with('warning', $message);
        }

        return $next($request);
    }
}
