<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;
use App\Support\ProspectPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProspectService
{
    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data, bool $allowDuplicate = false): Prospect
    {
        Gate::forUser($actor)->authorize('create', Prospect::class);
        $values = Validator::make($data, [
            'listing_id' => ['required', 'integer'], 'owner_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:40'],
            'source' => ['required', 'string', 'max:100'], 'stage' => ['required', Rule::in(Prospect::STAGES)],
            'followup_on' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:5000'],
        ])->validate();
        $phone = ProspectPhone::normalize($values['phone']);
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => 'Masukkan nomor ponsel Indonesia berawalan 08 atau +62.']);
        }
        $ownerId = $actor->isAdmin() ? ($values['owner_id'] ?? null) : $actor->id;
        if (! $ownerId || ! User::activeSales()->whereKey($ownerId)->exists()) {
            throw ValidationException::withMessages(['owner_id' => 'Pilih sales aktif sebagai penanggung jawab.']);
        }

        return DB::transaction(function () use ($actor, $values, $phone, $ownerId, $allowDuplicate): Prospect {
            if (! User::activeSales()->whereKey($ownerId)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['owner_id' => 'Pilih sales aktif sebagai penanggung jawab.']);
            }
            $listing = Listing::query()->whereKey($values['listing_id'])->lockForUpdate()->first();
            if (! $listing || $listing->publication_status !== 'published') {
                throw ValidationException::withMessages(['listing_id' => 'Pilih properti yang diterbitkan.']);
            }
            $this->checkDuplicate($listing->id, $phone, null, $allowDuplicate);

            $prospect = Prospect::create([
                'listing_id' => $listing->id, 'owner_id' => $ownerId,
                'name' => trim($values['name']), 'phone_e164' => $phone,
                'source' => trim($values['source']), 'stage' => $values['stage'],
                'followup_on' => $values['followup_on'] ?? null,
            ]);
            $this->record($prospect, $actor, 'created', 'Prospek dibuat.');
            if (! empty(trim($values['note'] ?? ''))) {
                $this->record($prospect, $actor, 'note', trim($values['note']));
            }

            return $prospect;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateDetails(User $actor, Prospect $prospect, array $data, bool $allowDuplicate = false): void
    {
        Gate::forUser($actor)->authorize('update', $prospect);
        $values = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:40'],
            'source' => ['required', 'string', 'max:100'], 'followup_on' => ['nullable', 'date'],
        ])->validate();
        $phone = ProspectPhone::normalize($values['phone']);
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => 'Masukkan nomor ponsel Indonesia berawalan 08 atau +62.']);
        }

        DB::transaction(function () use ($actor, $prospect, $values, $phone, $allowDuplicate): void {
            Listing::query()->whereKey($prospect->listing_id)->lockForUpdate()->firstOrFail();
            $prospect = Prospect::query()->lockForUpdate()->findOrFail($prospect->id);
            Gate::forUser($actor)->authorize('update', $prospect);
            if ($phone !== $prospect->phone_e164) {
                $this->checkDuplicate($prospect->listing_id, $phone, $prospect->id, $allowDuplicate);
            }
            $previousFollowup = $prospect->followup_on?->toDateString();
            $prospect->update([
                'name' => trim($values['name']), 'phone_e164' => $phone,
                'source' => trim($values['source']), 'followup_on' => $values['followup_on'] ?? null,
            ]);
            $this->record($prospect, $actor, 'details_updated', 'Data prospek diperbarui.');
            $newFollowup = $prospect->followup_on?->toDateString();
            if ($previousFollowup !== $newFollowup) {
                $this->record($prospect, $actor, 'followup_changed', 'Tanggal tindak lanjut diperbarui.', [
                    'from' => $previousFollowup, 'to' => $newFollowup,
                ]);
            }
        });
    }

    public function changeStage(User $actor, Prospect $prospect, string $stage): void
    {
        Gate::forUser($actor)->authorize('update', $prospect);
        if (! in_array($stage, Prospect::STAGES, true)) {
            throw ValidationException::withMessages(['stage' => 'Tahap tidak dikenal.']);
        }
        DB::transaction(function () use ($actor, $prospect, $stage): void {
            $prospect = Prospect::query()->lockForUpdate()->findOrFail($prospect->id);
            Gate::forUser($actor)->authorize('update', $prospect);
            if ($prospect->stage === $stage) {
                return;
            }
            $before = $prospect->stage;
            $prospect->update(['stage' => $stage]);
            $this->record($prospect, $actor, 'stage_changed', 'Tahap prospek diperbarui.', ['from' => $before, 'to' => $stage]);
        });
    }

    public function reassign(User $actor, Prospect $prospect, int $ownerId): void
    {
        Gate::forUser($actor)->authorize('reassign', $prospect);
        DB::transaction(function () use ($actor, $prospect, $ownerId): void {
            $prospect = Prospect::query()->lockForUpdate()->findOrFail($prospect->id);
            $newOwner = User::activeSales()->whereKey($ownerId)->lockForUpdate()->first();
            if (! $newOwner) {
                throw ValidationException::withMessages(['owner_id' => 'Pilih sales aktif sebagai penanggung jawab.']);
            }
            if ($prospect->owner_id === $ownerId) {
                return;
            }
            $before = $prospect->owner_id;
            $oldOwnerName = User::findOrFail($before)->name;
            $prospect->update(['owner_id' => $ownerId]);
            $this->record($prospect, $actor, 'owner_changed', 'Penanggung jawab diperbarui.', [
                'from' => $before, 'to' => $ownerId,
                'from_name' => $oldOwnerName, 'to_name' => $newOwner->name,
            ]);
        });
    }

    public function addActivity(User $actor, Prospect $prospect, string $type, string $body): ProspectActivity
    {
        if (! in_array($type, ['note', 'followup'], true) || trim($body) === '' || mb_strlen($body) > 5000) {
            throw ValidationException::withMessages(['activity' => 'Pilih jenis aktivitas dan isi catatan maksimal 5000 karakter.']);
        }

        return DB::transaction(function () use ($actor, $prospect, $type, $body): ProspectActivity {
            $prospect = Prospect::query()->lockForUpdate()->findOrFail($prospect->id);
            Gate::forUser($actor)->authorize('update', $prospect);

            return $this->record($prospect, $actor, $type, trim($body));
        });
    }

    /** @param array<string, mixed>|null $details */
    public function record(Prospect $prospect, User $actor, string $type, ?string $body = null, ?array $details = null): ProspectActivity
    {
        return $prospect->activities()->create([
            'actor_id' => $actor->id, 'type' => $type, 'body' => $body, 'details' => $details,
        ]);
    }

    private function checkDuplicate(int $listingId, string $phone, ?int $excludeId, bool $allowDuplicate): void
    {
        if ($allowDuplicate) {
            return;
        }
        $duplicate = Prospect::query()->where('listing_id', $listingId)->where('phone_e164', $phone)
            ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['duplicate' => 'Kemungkinan duplikat nomor pada properti ini. Pilih Simpan tetap untuk melanjutkan.']);
        }
    }
}
