<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VisitService
{
    public function __construct(private ProspectService $prospects) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, Prospect $prospect, array $data): Visit
    {
        Gate::forUser($actor)->authorize('update', $prospect);
        $values = $this->validate($data, false);

        return DB::transaction(function () use ($actor, $prospect, $values): Visit {
            $prospect = Prospect::query()->lockForUpdate()->findOrFail($prospect->id);
            Gate::forUser($actor)->authorize('update', $prospect);
            $visit = Visit::create([
                'prospect_id' => $prospect->id, 'listing_id' => $prospect->listing_id,
                'created_by' => $actor->id, 'updated_by' => $actor->id,
                'scheduled_at' => $this->asUtc($values['scheduled_at']), 'status' => 'dijadwalkan',
                'location' => $values['location'] ?? null, 'notes' => $values['notes'] ?? null,
            ]);
            $this->prospects->record($prospect, $actor, 'visit_created', 'Kunjungan dijadwalkan.', ['visit_id' => $visit->id]);

            return $visit;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Visit $visit, array $data): void
    {
        Gate::forUser($actor)->authorize('update', $visit->prospect);
        $values = $this->validate($data, true);
        DB::transaction(function () use ($actor, $visit, $values): void {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            Gate::forUser($actor)->authorize('update', $visit->prospect);
            $before = ['scheduled_at' => $visit->scheduled_at->toIso8601String(), 'status' => $visit->status,
                'location' => $visit->location, 'notes' => $visit->notes];
            $visit->update([
                'scheduled_at' => $this->asUtc($values['scheduled_at']), 'status' => $values['status'],
                'location' => $values['location'] ?? null, 'notes' => $values['notes'] ?? null,
                'updated_by' => $actor->id,
            ]);
            $this->prospects->record($visit->prospect, $actor, 'visit_updated', 'Kunjungan diperbarui.', [
                'visit_id' => $visit->id, 'from' => $before,
                'to' => ['scheduled_at' => $visit->scheduled_at->toIso8601String(), 'status' => $visit->status,
                    'location' => $visit->location, 'notes' => $visit->notes],
            ]);
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validate(array $data, bool $updating): array
    {
        return Validator::make($data, [
            'scheduled_at' => ['required', 'date'],
            'status' => [$updating ? 'required' : 'nullable', Rule::in(Visit::STATUSES)],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();
    }

    private function asUtc(string $local): Carbon
    {
        return Carbon::parse($local, 'Asia/Jakarta')->utc();
    }
}
