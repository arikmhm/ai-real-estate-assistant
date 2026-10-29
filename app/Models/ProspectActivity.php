<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectActivity extends Model
{
    public $timestamps = false;

    public const TYPE_LABELS = [
        'created' => 'Prospek dibuat', 'note' => 'Catatan', 'followup' => 'Tindak lanjut',
        'stage_changed' => 'Tahap diubah', 'visit_created' => 'Kunjungan dijadwalkan', 'visit_updated' => 'Kunjungan diperbarui',
        'details_updated' => 'Data diperbarui', 'followup_changed' => 'Tindak lanjut diubah', 'owner_changed' => 'Dialihkan',
    ];

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
