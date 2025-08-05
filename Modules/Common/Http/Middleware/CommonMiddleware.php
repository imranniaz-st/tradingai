<?php

namespace Modules\Common\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CommonMiddleware
{
    public function handle($request, Closure $next)
    {
        $debugData = [];

        $debugData['Request'] = [
            'Full URL' => $request->fullUrl(),
            'Method' => $request->method(),
            'Route Name' => optional($request->route())->getName(),
            'All Inputs' => $request->all(),
        ];

        // Skip license check if hitting cache-clear route
        if ($request->routeIs('cache-clear')) {
            $debugData['Action'] = 'Route is cache-clear, skipping license check';
            $this->outputDebug($debugData);
            return $next($request);
        }

        $domain = function_exists('domain') ? domain() : $request->getHost();
        $debugData['Domain'] = $domain;

        // Skip for local environment
        $isLocal = Str::endsWith($domain, '.test') ||
                   Str::endsWith($domain, '.local') ||
                   Str::contains($domain, ['127.0.0.1', ':', 'localhost']);
        $debugData['Is Local?'] = $isLocal;

        if ($isLocal) {
            $debugData['Action'] = 'Local environment detected, skipping license check';
            $this->outputDebug($debugData);
            return $next($request);
        }

        $cacheKey = 'license_check_' . $domain;

        // License check and caching
        $license_check = Cache::remember($cacheKey, 60 * 60 * 12, function () use ($domain, &$debugData) {
            $url = function_exists('endpoint') ? endpoint('verify-license') : 'endpoint() missing';
            $debugData['License Endpoint'] = $url;

            $headers = [
                'X-DOMAIN' => $domain,
                'X-CACHE-URL' => route('cache-clear'),
                'X-VERSION' => env('APP_VERSION')
            ];
            $debugData['Headers'] = $headers;

            try {
                $response = Http::withHeaders($headers)->get($url);
                $debugData['HTTP Raw Response'] = $response->body();
                return $response->body();
            } catch (\Exception $e) {
                $debugData['HTTP Error'] = $e->getMessage();
                return json_encode([
                    'status' => 0,
                    'error' => 'License server not reachable.'
                ]);
            }
        });

        $debugData['Cached License Data'] = $license_check;

        $responseData = json_decode($license_check);
        $debugData['Decoded Response'] = $responseData;

        // License validation failed
        if ($responseData !== null && isset($responseData->status) && $responseData->status == 0) {
            $debugData['Action'] = 'License check failed. Blocking host and clearing cache.';

            // Clear cached license data
            Cache::forget($cacheKey);

            $errorMessage = $responseData->error ?? 'We could not verify that you have a valid license';

            Log::warning('License verification failed, host blocked.', [
                'domain' => $domain,
                'endpoint' => $debugData['License Endpoint'],
                'cached_license_data' => $license_check
            ]);

            $this->outputDebug($debugData);
            return response($errorMessage, 403)
                ->header('Content-Type', 'text/plain');
        }

        $debugData['Action'] = 'License valid or no issues found';
        $this->outputDebug($debugData);
        return $next($request);
    }

    /**
     * Outputs debug info to log and screen (use dump only for development)
     */
    protected function outputDebug($debugData)
    {
        Log::info('CommonMiddleware Debug Data', $debugData);
        // Comment out in production if you don't want output on screen
        dump($debugData);
    }
}
