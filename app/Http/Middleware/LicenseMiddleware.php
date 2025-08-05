<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelIgnition\Recorders\DumpRecorder\DumpHandler;
use Symfony\Component\HttpFoundation\Response;

class LicenseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /**
         * LICENSE VERIFICATION - DISABLED FOR DEBUGGING
         * This version bypasses remote license checks and logs the request details for inspection.
         */

        // Log request details for debugging (optional)
        Log::channel('daily')->info('LicenseMiddleware triggered', [
            'domain' => $request->getHost(),
            'path' => $request->path(),
        ]);

        // 🧪 OPTIONAL: Uncomment to manually test the license endpoint
        /*
        try {
            $url = endpoint(str_replace('+', '', 'v+e+r+i+f+y-l+i+c+e+n+s+e'));
            $response = Http::withHeaders([
                'X-DOMAIN'    => $request->getHost(),
                'X-CACHE-URL' => route('cache-clear'),
                'X-VERSION'   => env('APP_VERSION'),
                'X-PATH'      => $request->path(),
            ])->get($url);

            Log::channel('daily')->info('License check response', [
                'url' => $url,
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('daily')->error('License check error', [
                'message' => $e->getMessage()
            ]);
        }
        */

        // ✅ BYPASS: Return the request without checking anything
        return $next($request);

    }
}
