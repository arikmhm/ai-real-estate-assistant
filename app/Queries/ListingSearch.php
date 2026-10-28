<?php

namespace App\Queries;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;

class ListingSearch
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Listing>
     */
    public function query(array $filters): Builder
    {
        $query = Listing::publicCatalog();

        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim((string) $filters['q'])).'%';
            $query->where(function (Builder $query) use ($term): void {
                foreach (['title', 'description', 'city', 'district', 'address'] as $column) {
                    $query->orWhere($column, 'ILIKE', $term);
                }
                $query->orWhereRaw('facilities::text ILIKE ?', [$term]);
            });
        }

        foreach (['city', 'district', 'type'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        foreach (['price_min' => 'price_idr', 'land_min' => 'land_area_m2', 'building_min' => 'building_area_m2', 'rooms_min' => 'rooms'] as $filter => $column) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                $query->where($column, '>=', $filters[$filter]);
            }
        }
        foreach (['price_max' => 'price_idr', 'land_max' => 'land_area_m2', 'building_max' => 'building_area_m2'] as $filter => $column) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                $query->where($column, '<=', $filters[$filter]);
            }
        }

        return $query;
    }
}
