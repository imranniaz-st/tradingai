<?php

namespace Modules\Common\Http\Middleware;

use Closure;
// use Illuminate\Support\Facades\Cache;//imran
use Illuminate\Support\Facades\Http;
// use Illuminate\Support\Facades\View;
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
        $response = $next($request);






}
