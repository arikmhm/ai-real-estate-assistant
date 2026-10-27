<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ListingMediaService
{
    public function addPhoto(Listing $listing, UploadedFile $file): ListingMedia
    {
        Validator::make(['file' => $file], [
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
        ])->validate();

        $created = null;
        try {
            return DB::transaction(function () use ($listing, $file, &$created): ListingMedia {
                $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
                $count = $locked->media()->where('kind', 'photo')->count();
                if ($count >= 20) {
                    throw ValidationException::withMessages(['photos' => 'Maksimal 20 foto per properti.']);
                }

                $created = $this->store($locked, $file, 'photo', $count);

                return $created;
            });
        } catch (Throwable $exception) {
            if ($created) {
                $this->deleteOrLog($created->disk, $created->object_key);
            }
            throw $exception;
        }
    }

    public function setFloorplan(Listing $listing, UploadedFile $file): ListingMedia
    {
        Validator::make(['file' => $file], [
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:10240'],
        ])->validate();

        $new = null;
        try {
            [$new, $old] = DB::transaction(function () use ($listing, $file, &$new): array {
                $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
                $old = $locked->media()->where('kind', 'floorplan')->get();
                $new = $this->store($locked, $file, 'floorplan', 0);
                $locked->media()->where('kind', 'floorplan')->whereKeyNot($new->id)->delete();

                return [$new, $old];
            });
        } catch (Throwable $exception) {
            if ($new) {
                $this->deleteOrLog($new->disk, $new->object_key);
            }
            throw $exception;
        }
        foreach ($old as $media) {
            $this->deleteOrLog($media->disk, $media->object_key);
        }

        return $new;
    }

    private function store(Listing $listing, UploadedFile $file, string $kind, int $position): ListingMedia
    {
        $disk = (string) config('listings.media_disk');
        if (! in_array($disk, ['public', 'r2'], true)) {
            throw new RuntimeException('Disk media tidak valid.');
        }

        $extension = $file->extension();
        $name = Str::uuid().'.'.$extension;
        $objectKey = Storage::disk($disk)->putFileAs('listings/'.$listing->id, $file, $name);
        if ($objectKey === false) {
            throw new RuntimeException('Penyimpanan media gagal.');
        }

        try {
            return $listing->media()->create([
                'kind' => $kind,
                'disk' => $disk,
                'object_key' => $objectKey,
                'mime_type' => (string) $file->getMimeType(),
                'position' => $position,
            ]);
        } catch (Throwable $exception) {
            $this->deleteOrLog($disk, $objectKey);
            throw $exception;
        }
    }

    public function remove(ListingMedia $media): void
    {
        $removed = DB::transaction(function () use ($media): ListingMedia {
            $listing = Listing::query()->lockForUpdate()->findOrFail($media->listing_id);
            $current = $listing->media()->findOrFail($media->id);
            if ($current->kind === 'photo' && $listing->publication_status === 'published'
                && $listing->media()->where('kind', 'photo')->count() <= 1) {
                throw ValidationException::withMessages(['photos' => 'Listing terbit harus memiliki minimal satu foto.']);
            }

            $current->delete();
            if ($current->kind === 'photo') {
                $this->normalizePositions($listing);
            }

            return $current;
        });
        $this->deleteOrLog($removed->disk, $removed->object_key);
    }

    private function deleteOrLog(string $disk, string $objectKey): void
    {
        try {
            if (Storage::disk($disk)->delete($objectKey)) {
                return;
            }
        } catch (Throwable) {
            // Pruner will retry orphaned objects after 24 hours.
        }

        Log::warning('Objek media yatim perlu dibersihkan.', ['disk' => $disk, 'object_key' => $objectKey]);
    }

    public function makePrimary(ListingMedia $media): void
    {
        DB::transaction(function () use ($media): void {
            $listing = Listing::query()->lockForUpdate()->findOrFail($media->listing_id);
            $current = $listing->media()->findOrFail($media->id);
            if ($current->kind !== 'photo') {
                return;
            }
            $ids = $listing->media()->where('kind', 'photo')->pluck('id')->all();
            $ids = array_values(array_diff($ids, [$current->id]));
            array_unshift($ids, $current->id);
            $this->setPositions($ids);
        });
    }

    public function movePhoto(ListingMedia $media, int $direction): void
    {
        if (! in_array($direction, [-1, 1], true)) {
            return;
        }
        DB::transaction(function () use ($media, $direction): void {
            $listing = Listing::query()->lockForUpdate()->findOrFail($media->listing_id);
            $current = $listing->media()->findOrFail($media->id);
            if ($current->kind !== 'photo') {
                return;
            }
            $ids = $listing->media()->where('kind', 'photo')->pluck('id')->all();
            $index = array_search($current->id, $ids, true);
            if (! is_int($index) || ! isset($ids[$index + $direction])) {
                return;
            }
            [$ids[$index], $ids[$index + $direction]] = [$ids[$index + $direction], $ids[$index]];
            $this->setPositions($ids);
        });
    }

    private function normalizePositions(Listing $listing): void
    {
        $this->setPositions($listing->media()->where('kind', 'photo')->pluck('id')->all());
    }

    /** @param array<int, int> $ids */
    private function setPositions(array $ids): void
    {
        foreach ($ids as $position => $id) {
            ListingMedia::whereKey($id)->update(['position' => $position]);
        }
    }
}
