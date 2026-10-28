<?php

namespace App\Ai;

use Laravel\Ai\Gateway\Gemini\GeminiGateway;
use Throwable;

class NoRetryGeminiGateway extends GeminiGateway
{
    protected function retryAfterSeconds(Throwable $exception): ?int
    {
        return null;
    }
}
