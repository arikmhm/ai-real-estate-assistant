<?php

namespace App\Http\Controllers;

use App\Services\AssistantRateLimiter;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AssistantController extends Controller
{
    public function message(Request $request, AssistantRateLimiter $limiter, AssistantService $assistant): JsonResponse
    {
        abort_unless(config('assistant.enabled') && app()->environment(['local', 'testing']), 404);

        $input = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:500'],
            'history' => ['sometimes', 'array', 'max:4'],
            'history.*' => ['string', 'max:500'],
            'last_listing_ids' => ['sometimes', 'array', 'max:3'],
            'last_listing_ids.*' => ['integer', 'distinct', 'min:1'],
        ]);

        if (! $limiter->acquire($request->ip())) {
            return response()->json(['error' => 'Batas penggunaan demo tercapai. Coba lagi nanti atau gunakan filter katalog.', 'catalog_url' => route('properties.index')], 429);
        }

        try {
            return response()->json($assistant->reply($input['message'], $input['history'] ?? [], $input['last_listing_ids'] ?? []));
        } catch (Throwable) {
            return response()->json(['error' => 'Asisten sedang tidak tersedia. Gunakan filter katalog.', 'catalog_url' => route('properties.index')], 503);
        }
    }
}
