<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Contracts\View\View;

class CatalogController extends Controller
{
    public function home(): View
    {
        return view('public.home', [
            'listings' => Listing::publicCatalog()->with('media')->latest()->limit(6)->get(),
        ]);
    }

    public function index(): View
    {
        return view('public.index', [
            'listings' => Listing::publicCatalog()->with('media')->latest()->paginate(12),
        ]);
    }

    public function show(string $slug): View
    {
        return view('public.show', [
            'listing' => Listing::publicDetail()->with('media')->where('slug', $slug)->firstOrFail(),
        ]);
    }
}
