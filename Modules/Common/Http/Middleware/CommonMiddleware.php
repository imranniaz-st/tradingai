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

    $response = $next($request);

    // Skip if route is cache-clear
    if ($request->routeIs('cache-clear')) {
        $debugData['Action'] = 'Route is cache-clear, skipping license check';
        $this->outputDebug($debugData);
        return $response;
    }

    $domain = function_exists('domain') ? domain() : $request->getHost();
    $debugData['Domain'] = $domain;

    $isLocal = Str::endsWith($domain, '.test') ||
               Str::endsWith($domain, '.local') ||
               Str::contains($domain, ['127.0.0.1', ':', 'localhost']);
    $debugData['Is Local?'] = $isLocal;

    // Forcefully block license verification for all environments
    $blockedHosts = [
        'rescron.com',
        'www.rescron.com',
        'api.rescron.com',
        'verify-license',
    ];

    $url = function_exists('endpoint') ? endpoint('verify-license') : '';
    $debugData['License Endpoint'] = $url;

    foreach ($blockedHosts as $blocked) {
        if (Str::contains($url, $blocked)) {
            $debugData['Action'] = 'License check blocked. Skipping remote request and clearing cache.';

            // Clear cached license
            Cache::forget('license_check');

            $this->outputDebug($debugData);
            return $response; // Stop execution here
        }
    }

    // Original license check (disabled due to block above)
    $license_check = Cache::remember('license_check', 60 * 60 * 12, function () use ($domain, &$debugData) {
        $url = function_exists('endpoint') ? endpoint('verify-license') : 'endpoint() missing';
        $debugData['License Endpoint'] = $url;
        $debugData['Headers'] = [
            'X-DOMAIN' => $domain,
            'X-CACHE-URL' => route('cache-clear'),
            'X-VERSION' => env('APP_VERSION')
        ];

        // This will never execute if URL is blocked
        $response = Http::withHeaders($debugData['Headers'])->get($url);
        $debugData['HTTP Raw Response'] = $response->body();
        return $response->body();
    });

    $debugData['Cached License Data'] = $license_check;

    $responseData = json_decode($license_check);
    $debugData['Decoded Response'] = $responseData;

    if ($responseData !== null && isset($responseData->status) && $responseData->status == 0) {
        $debugData['Action'] = 'License check failed. Blocking host and clearing cache.';

        Cache::forget('license_check');
        $this->outputDebug($debugData);

        return response('License check blocked by middleware');
    }

    $debugData['Action'] = 'License valid or no issues found';
    $this->outputDebug($debugData);
    return $response;
}

}
