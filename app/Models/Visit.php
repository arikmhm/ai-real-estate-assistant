<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon $scheduled_at */
class Visit extends Model
{
    public const STATUSES = ['dijadwalkan', 'selesai', 'dibatalkan'];

    protected $fillable = ['prospect_id', 'listing_id', 'created_by', 'updated_by', 'scheduled_at', 'status', 'location', 'notes'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    /** @return BelongsTo<Prospect, $this> */
    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
