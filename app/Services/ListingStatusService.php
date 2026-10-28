<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ListingStatusService
{
    public function publish(User $actor, Listing $listing): string
    {
        Gate::forUser($actor)->authorize('update', $listing);

        return DB::transaction(function () use ($actor, $listing): string {
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            if ($locked->publication_status === 'archived') {
                return 'archived';
            }
            if (! $locked->canPublish()) {
                return 'incomplete';
            }
            $this->recordChange($locked, $actor, 'publication_status', 'published');

            return 'published';
        });
    }

    public function archive(User $actor, Listing $listing): void
    {
        $this->setPublication($actor, $listing, 'archived');
    }

    public function restoreDraft(User $actor, Listing $listing): void
    {
        $this->setPublication($actor, $listing, 'draft');
    }

    public function setAvailability(User $actor, Listing $listing, string $status): bool
    {
        Gate::forUser($actor)->authorize('update', $listing);
        if (! in_array($status, ['available', 'reserved', 'sold'], true)) {
            return false;
        }

        DB::transaction(function () use ($actor, $listing, $status): void {
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $this->recordChange($locked, $actor, 'availability_status', $status);
        });

        return true;
    }

    private function setPublication(User $actor, Listing $listing, string $status): void
    {
        Gate::forUser($actor)->authorize('update', $listing);
        DB::transaction(function () use ($actor, $listing, $status): void {
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $this->recordChange($locked, $actor, 'publication_status', $status);
        });
    }

    private function recordChange(Listing $listing, User $actor, string $field, string $to): void
    {
        $from = $listing->$field;
        if ($from === $to) {
            return;
        }

        $listing->update([$field => $to]);
        $listing->statusChanges()->create([
            'actor_id' => $actor->id,
            'field' => $field,
            'from_value' => $from,
            'to_value' => $to,
            'created_at' => now(),
        ]);
    }
}
