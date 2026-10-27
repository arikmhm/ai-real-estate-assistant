<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ListingMedia extends Model
{
    protected $table = 'listing_media';

    protected $fillable = ['kind', 'disk', 'object_key', 'mime_type', 'position'];

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->object_key);
    }
}
