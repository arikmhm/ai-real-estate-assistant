<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectActivity extends Model
{
    public $timestamps = false;

    protected $fillable = ['prospect_id', 'actor_id', 'type', 'body', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<Prospect, $this> */
    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
