<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Auth\Support\EmailNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('email-otp-request', function (Request $request): Limit {
            $email = is_string($request->input('email'))
                ? EmailNormalizer::normalize($request->input('email'))
                : 'unknown';

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('email-otp-verify', function (Request $request): Limit {
            $email = is_string($request->input('email'))
                ? EmailNormalizer::normalize($request->input('email'))
                : 'unknown';

            return Limit::perMinute(10)->by($email.'|'.$request->ip());
        });
    }
}
