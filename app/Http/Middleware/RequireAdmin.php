<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * History and Telegram watches show other visitors' scans and chat IDs, so on a public site
 * they sit behind ADMIN_PASSWORD. Without one set (local use) they stay open.
 */
class RequireAdmin
{
    public static function isAdmin(Request $request): bool
    {
        return ! config('services.tron.admin_password') || $request->session()->get('admin_ok');
    }

    public function handle(Request $request, Closure $next)
    {
        if (! self::isAdmin($request)) {
            return redirect()->guest(route('login', ['admin' => 1]));
        }

        return $next($request);
    }
}
