    <?php

    namespace Modules\Common\Http\Middleware;

    use Closure;
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Facades\Http;
    use Illuminate\Support\Facades\View;
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
            $domain = function_exists('domain') ? domain() : $request->getHost();

            $debugData = [
                'Request URL' => $request->fullUrl(),
                'Request Method' => $request->method(),
                'Route Name' => optional($request->route())->getName(),
                'Domain' => $domain,
                'Is Local?' => Str::endsWith($domain, ['.test', '.local']) ||
                               Str::contains($domain, ['127.0.0.1', ':', 'localhost']),
                'Cache Exists?' => Cache::has('license_check'),
                'Cached License Data' => Cache::get('license_check'),
                'APP_VERSION' => env('APP_VERSION'),
                'License Endpoint' => function_exists('endpoint') ? endpoint('verify-license') : 'endpoint() missing',
            ];

            // Display variables on page reload and stop execution
            dd($debugData);

            // If you want request to continue, comment the dd() above and leave below:
            // return $next($request);
        }

    }
