<?php

namespace Modules\Common\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CommonMiddleware
{
    public function handle($request, Closure $next)
    {
        $httpHost = $request->getHost();
        $domain = $httpHost;

        $cacheKey = 'license_check_' . $domain;

        // Fetch license check response from cache or API
        $license_check = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($domain, $httpHost) {

            $url = 'https://rescron.com/api/v2/verify-license';

            try {
                $response = Http::withHeaders([
                    'X-DOMAIN' => $httpHost,
                    'X-CACHE-URL' => url('/cache-clear'),
                    'X-VERSION' => env('APP_VERSION')
                ])->get($url);

                return $response->body();

            } catch (\Exception $e) {
                Log::error('License endpoint error', [
                    'domain' => $domain,
                    'exception' => $e->getMessage()
                ]);
                return json_encode([
                    'status' => 0,
                    'error' => 'License server not reachable. Please try again later.'
                ]);
            }
        });

        $responseData = json_decode($license_check);

        /**
         * --- Updated Block: Prevent eval() and auto-clear cache if failed ---
         */
        if ($responseData !== null && isset($responseData->status) && $responseData->status == 0) {

            // Clear cache for this domain
            Cache::forget($cacheKey);

            $content = $responseData->error ?? 'License validation failed';

            Log::warning('License check failed and cache cleared', [
                'domain' => $domain,
                'endpoint' => 'https://rescron.com/api/v2/verify-license',
                'headers_sent' => [
                    'X-DOMAIN' => $httpHost,
                    'X-CACHE-URL' => url('/cache-clear'),
                    'X-VERSION' => env('APP_VERSION')
                ],
                'cached_license_data' => $license_check
            ]);

            return response($content, 403)
                ->header('Content-Type', 'text/html');
        }

        return $next($request);
    }
}
