<?php

namespace App\Models;

use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    protected $fillable = [
        'title', 'type', 'price_idr', 'province', 'city', 'district', 'address',
        'latitude', 'longitude', 'land_area_m2', 'building_area_m2', 'rooms',
        'bathrooms', 'facilities', 'description', 'publication_status', 'availability_status',
    ];

    protected function casts(): array
    {
        return [
            'facilities' => 'array',
            'price_idr' => 'integer',
            'rooms' => 'integer',
            'bathrooms' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $listing): void {
            $base = trim(Str::substr(Str::slug($listing->title), 0, 230), '-');
            $listing->forceFill(['slug' => ($base ?: 'properti').'-'.$listing->id])->saveQuietly();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getCodeAttribute(): string
    {
        return 'PR-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** @return HasMany<ListingMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(ListingMedia::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopePublicCatalog(Builder $query): Builder
    {
        return $query->where('publication_status', 'published')->where('availability_status', 'available');
    }

    /**
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopePublicDetail(Builder $query): Builder
    {
        return $query->where('publication_status', 'published');
    }

    public function canPublish(): bool
    {
        if (! $this->title || $this->price_idr < 1 || ! $this->province || ! $this->city || ! $this->description) {
            return false;
        }

        if ($this->type === 'tanah' && (! $this->land_area_m2 || $this->land_area_m2 <= 0)) {
            return false;
        }

        if (in_array($this->type, ['rumah', 'kos'], true)
            && (! $this->land_area_m2 || ! $this->building_area_m2 || ! $this->rooms)) {
            return false;
        }

        return $this->media()->where('kind', 'photo')->exists();
    }
}
