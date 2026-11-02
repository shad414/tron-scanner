<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Locks the whole site behind SCANNER_PASSWORD when it is set. */
class RequirePassword
{
    public function handle(Request $request, Closure $next)
    {
        if (config('services.tron.password') && ! $request->session()->get('scanner_ok')) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
