<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** @property Carbon|null $followup_on */
class Prospect extends Model
{
    public const STAGES = ['baru', 'dihubungi', 'kunjungan', 'negosiasi', 'booking', 'berhasil', 'gagal'];

    /** Flux badge colour per stage, shared by the dashboard, prospect, and visit pages. */
    public const STAGE_COLORS = [
        'baru' => 'zinc', 'dihubungi' => 'sky', 'kunjungan' => 'indigo', 'negosiasi' => 'amber',
        'booking' => 'violet', 'berhasil' => 'green', 'gagal' => 'red',
    ];

    protected $fillable = ['listing_id', 'owner_id', 'name', 'phone_e164', 'source', 'stage', 'followup_on'];

    protected function casts(): array
    {
        return ['followup_on' => 'date'];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<ProspectActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(ProspectActivity::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<Visit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->orderBy('scheduled_at')->orderBy('id');
    }

    /** @param Builder<Prospect> $query
     * @return Builder<Prospect>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('owner_id', $user->id);
    }
}
