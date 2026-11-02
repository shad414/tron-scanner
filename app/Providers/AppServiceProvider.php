<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only actual scans count, not opening the empty search page
        RateLimiter::for('scans', fn (Request $request) => $request->filled('address')
            ? Limit::perMinute((int) env('SCANS_PER_MINUTE', 10))->by($request->ip())
                ->response(fn () => response()->view('scan', [
                    'address' => (string) $request->query('address'), 'result' => null, 'scan' => null, 'watches' => collect(),
                    'error' => 'سکانی زۆرت کردووە، تکایە یەک خولەک چاوەڕێ بکە.',
                ], 429))
            : Limit::none());
    }
}
