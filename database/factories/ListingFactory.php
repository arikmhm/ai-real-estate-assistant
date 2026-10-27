<?php

namespace Database\Factories;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Listing> */
class ListingFactory extends Factory
{
    protected $model = Listing::class;

    public function definition(): array
    {
        return [
            'title' => 'Rumah Contoh '.$this->faker->unique()->numberBetween(1, 100000),
            'type' => 'rumah',
            'price_idr' => 750000000,
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
            'district' => 'Coblong',
            'land_area_m2' => 100,
            'building_area_m2' => 80,
            'rooms' => 3,
            'bathrooms' => 2,
            'description' => 'Properti contoh dengan data sintetis.',
            'facilities' => ['Carport'],
            'publication_status' => 'draft',
            'availability_status' => 'available',
        ];
    }
}
