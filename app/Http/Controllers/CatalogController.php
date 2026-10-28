<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\User;
use App\Queries\ListingSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function home(): View
    {
        return view('public.home', [
            'listings' => Listing::publicCatalog()->with('media')->latest()->limit(6)->get(),
        ]);
    }

    public function index(Request $request, ListingSearch $search): View
    {
        $fields = ['q', 'city', 'district', 'type', 'price_min', 'price_max', 'land_min', 'land_max', 'building_min', 'building_max', 'rooms_min'];
        $input = $request->only($fields);
        $filters = collect($input)->map(fn ($value): string => is_scalar($value) ? (string) $value : '')->all();
        $validator = Validator::make($input, [
            'q' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['rumah', 'tanah', 'kos'])],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'land_min' => ['nullable', 'numeric', 'min:0'],
            'land_max' => ['nullable', 'numeric', 'min:0'],
            'building_min' => ['nullable', 'numeric', 'min:0'],
            'building_max' => ['nullable', 'numeric', 'min:0'],
            'rooms_min' => ['nullable', 'integer', 'min:1'],
        ]);
        $locations = Listing::publicCatalog()->whereNotNull('district')
            ->select('city', 'district')->distinct()->orderBy('city')->orderBy('district')->get();
        $cities = Listing::publicCatalog()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city');

        $validator->after(function ($validator) use ($filters, $locations): void {
            if (! empty($filters['district']) && empty($filters['city'])) {
                $validator->errors()->add('district', 'Pilih kabupaten/kota terlebih dahulu.');
            } elseif (! empty($filters['district']) && ! $locations->contains(fn ($location): bool => $location->city === $filters['city'] && $location->district === $filters['district'])) {
                $validator->errors()->add('district', 'Kecamatan tidak tersedia pada kabupaten/kota yang dipilih.');
            }
            foreach ([['price_min', 'price_max'], ['land_min', 'land_max'], ['building_min', 'building_max']] as [$min, $max]) {
                if (is_numeric($filters[$min] ?? null) && is_numeric($filters[$max] ?? null)
                    && (float) $filters[$min] > (float) $filters[$max]) {
                    $validator->errors()->add($max, 'Batas maksimum harus lebih besar atau sama dengan minimum.');
                }
            }
        });

        return view('public.index', [
            'listings' => $validator->fails()
                ? new LengthAwarePaginator([], 0, 12)
                : $search->query($validator->validated())->with('media')->latest()->paginate(12)->withQueryString(),
            'filters' => $filters,
            'filterErrors' => $validator->errors(),
            'hasFilters' => collect($filters)->contains(fn ($value): bool => $value !== ''),
            'cities' => $cities,
            'locations' => $locations,
        ]);
    }

    public function show(string $slug): View
    {
        return view('public.show', [
            'listing' => Listing::publicDetail()->with('media')->where('slug', $slug)->firstOrFail(),
            'salesContacts' => User::activeSales()->orderBy('name')->get(),
        ]);
    }
}
