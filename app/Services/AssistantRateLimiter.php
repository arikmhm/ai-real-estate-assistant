<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class AssistantRateLimiter
{
    public function acquire(string $ip): bool
    {
        return Cache::lock('assistant:rate-lock', 5)->block(3, function () use ($ip): bool {
            $minute = 'assistant:global:minute';
            $hour = 'assistant:ip:'.hash('sha256', $ip);
            $now = now('Asia/Jakarta');
            $day = 'assistant:global:day:'.$now->toDateString();

            if (RateLimiter::tooManyAttempts($minute, 5)
                || RateLimiter::tooManyAttempts($hour, 10)
                || RateLimiter::tooManyAttempts($day, 100)) {
                return false;
            }

            RateLimiter::hit($minute, 60);
            RateLimiter::hit($hour, 3600);
            RateLimiter::hit($day, max(1, (int) ceil($now->secondsUntilEndOfDay() + 1)));

            return true;
        });
    }
}
