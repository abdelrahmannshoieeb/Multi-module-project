<?php

namespace App\Http\Middleware;

use Closure;

class InstallationMiddleware
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
        if (session()->has('purchase_key') == false && env('PURCHASE_CODE') == null) {
           return $next($request);
        }elseif(env('PURCHASE_CODE') != null){
            return $next($request);
        }

        return $next($request);
    }
}
