<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Services\ListingMediaService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ImportKudusDemo extends Command
{
    protected $signature = 'demo:import-kudus {--check : Periksa dataset tanpa mengubah data} {--apply : Unggah dan terbitkan listing} {--replace : Ganti media listing yang sudah lengkap}';

    protected $description = 'Periksa atau impor delapan listing sintetis Kudus ke R2 development';

    private const DATASET = 'database/demo/kudus';

    /** @var list<string> */
    private const PHOTO_NAMES = ['01-front.webp', '02-angle.webp', '03-detail.webp'];

    public function handle(ListingMediaService $mediaService): int
    {
        $check = (bool) $this->option('check');
        $apply = (bool) $this->option('apply');
        $replace = (bool) $this->option('replace');

        if ($check === $apply || ($replace && ! $apply)) {
            $this->error('Pilih tepat satu: --check atau --apply. --replace hanya bersama --apply.');

            return self::FAILURE;
        }

        if ($apply && (app()->environment() !== 'local' || config('listings.media_disk') !== 'r2')) {
            $this->error('Impor hanya diizinkan pada APP_ENV=local dengan PROPERTY_MEDIA_DISK=r2.');

            return self::FAILURE;
        }

        try {
            $rows = $this->readAndValidateDataset();
            DB::connection()->getPdo();
            $this->checkR2Configuration();
            Storage::disk('r2')->exists('listings/.import-healthcheck');
        } catch (Throwable $exception) {
            $this->error('Pemeriksaan gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Dataset valid: 8 listing, 24 foto, 8 denah WebP.');
        if ($check) {
            return self::SUCCESS;
        }

        $failures = 0;
        foreach ($rows as $row) {
            try {
                $this->importListing($row, $mediaService, $replace);
            } catch (Throwable $exception) {
                $failures++;
                $this->error($row['key'].': '.$exception->getMessage());
            }
        }

        if ($failures > 0) {
            $this->error($failures.' listing gagal; jalankan --apply lagi untuk melanjutkan.');

            return self::FAILURE;
        }

        $this->info('Impor selesai: 8 listing tersedia untuk katalog development.');

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function readAndValidateDataset(): array
    {
        $root = base_path(self::DATASET);
        $raw = file_get_contents($root.'/listings.json');
        if ($raw === false) {
            throw new RuntimeException('Manifest listing tidak dapat dibaca.');
        }

        $rows = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($rows) || count($rows) !== 8 || ! array_is_list($rows)) {
            throw new RuntimeException('Manifest harus berisi tepat delapan listing.');
        }

        $keys = [];
        $slugs = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('Record manifest tidak valid.');
            }
            foreach (['key', 'slug', 'title', 'type', 'price_idr', 'province', 'city', 'district', 'land_area_m2', 'description', 'photos', 'floorplan'] as $field) {
                if (! isset($row[$field]) || $row[$field] === '') {
                    throw new RuntimeException('Field '.$field.' wajib di setiap listing.');
                }
            }
            if (! preg_match('/^kds0[1-8]$/', (string) $row['key']) || ! preg_match('/^[a-z0-9-]+$/', (string) $row['slug'])) {
                throw new RuntimeException('Key atau slug listing tidak valid.');
            }
            if (in_array($row['key'], $keys, true) || in_array($row['slug'], $slugs, true)) {
                throw new RuntimeException('Key dan slug harus unik.');
            }
            $keys[] = $row['key'];
            $slugs[] = $row['slug'];
            if (! in_array($row['type'], ['rumah', 'tanah', 'kos'], true)
                || $row['province'] !== 'Jawa Tengah' || $row['city'] !== 'Kabupaten Kudus'
                || ! in_array($row['district'], ['Kota Kudus', 'Jati', 'Bae', 'Mejobo', 'Kaliwungu', 'Jekulo'], true)
                || ! is_int($row['price_idr']) || $row['price_idr'] < 1
                || ! is_numeric($row['land_area_m2']) || $row['land_area_m2'] <= 0) {
                throw new RuntimeException($row['key'].': data properti tidak valid.');
            }
            if ($row['type'] !== 'tanah' && (! is_numeric($row['building_area_m2'] ?? null)
                || ! is_int($row['rooms'] ?? null) || $row['rooms'] < 1
                || ! is_int($row['bathrooms'] ?? null))) {
                throw new RuntimeException($row['key'].': spesifikasi bangunan tidak lengkap.');
            }
            if (! is_array($row['photos']) || $row['photos'] !== array_map(fn (string $name): string => $row['key'].'/'.$name, self::PHOTO_NAMES)
                || $row['floorplan'] !== $row['key'].'/04-floorplan.webp') {
                throw new RuntimeException($row['key'].': nama atau urutan berkas tidak valid.');
            }
            foreach ($row['photos'] as $photo) {
                $this->validateImage($root.'/'.$photo, 800 * 1024);
            }
            $this->validateImage($root.'/'.$row['floorplan'], 400 * 1024);
        }

        return $rows;
    }

    private function validateImage(string $path, int $maxBytes): void
    {
        if (! is_file($path)) {
            throw new RuntimeException('Berkas tidak ditemukan: '.basename(dirname($path)).'/'.basename($path));
        }
        $size = filesize($path);
        $image = getimagesize($path);
        if ($size === false || $size > $maxBytes || $image === false
            || $image[0] !== 1536 || $image[1] !== 1024 || $image['mime'] !== 'image/webp') {
            throw new RuntimeException('Ukuran, dimensi, atau format WebP tidak valid: '.basename(dirname($path)).'/'.basename($path));
        }
    }

    private function checkR2Configuration(): void
    {
        foreach (['key', 'secret', 'bucket', 'endpoint', 'url'] as $field) {
            if (! config('filesystems.disks.r2.'.$field)) {
                throw new RuntimeException('Konfigurasi R2 belum lengkap ('.$field.').');
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function importListing(array $row, ListingMediaService $mediaService, bool $replace): void
    {
        $listing = Listing::where('slug', $row['slug'])->first();
        if ($listing && ($listing->title !== $row['title'] || $listing->type !== $row['type'])) {
            throw new RuntimeException('Slug sudah dipakai oleh listing berbeda.');
        }

        if ($listing) {
            $media = $listing->media()->get();
            $photoCount = $media->where('kind', 'photo')->count();
            $floorplanCount = $media->where('kind', 'floorplan')->count();
            if ($photoCount > 3 || $floorplanCount > 1 || $media->count() !== $photoCount + $floorplanCount) {
                throw new RuntimeException('Listing memiliki media tambahan; periksa manual sebelum impor ulang.');
            }
            $complete = $photoCount === 3 && $floorplanCount === 1
                && $media->every(fn ($item): bool => $item->disk === 'r2' && $item->mime_type === 'image/webp'
                    && Storage::disk('r2')->exists($item->object_key));
            if ($complete && ! $replace) {
                if ($listing->publication_status === 'draft') {
                    if (! $listing->canPublish()) {
                        throw new RuntimeException('Data listing tidak memenuhi syarat terbit.');
                    }
                    $listing->update(['publication_status' => 'published']);
                }
                $this->line($row['key'].': sudah lengkap, dilewati.');

                return;
            }
        } else {
            $attributes = collect($row)->only([
                'title', 'type', 'price_idr', 'province', 'city', 'district', 'land_area_m2',
                'building_area_m2', 'rooms', 'bathrooms', 'facilities', 'description',
            ])->all();
            $attributes['availability_status'] = 'available';
            $attributes['publication_status'] = 'draft';
            $listing = Listing::create($attributes);
            $listing->forceFill(['slug' => $row['slug']])->save();
            $media = $listing->media()->get();
        }

        if ($replace) {
            $listing->update(collect($row)->only([
                'title', 'type', 'price_idr', 'province', 'city', 'district', 'land_area_m2',
                'building_area_m2', 'rooms', 'bathrooms', 'facilities', 'description',
            ])->all());
        }

        $listing->update(['publication_status' => 'draft']);
        foreach ($media as $item) {
            $mediaService->remove($item);
        }

        $root = base_path(self::DATASET);
        foreach ($row['photos'] as $path) {
            $mediaService->addPhoto($listing, new UploadedFile($root.'/'.$path, basename($path), 'image/webp', null, true));
        }
        $plan = $row['floorplan'];
        $mediaService->setFloorplan($listing, new UploadedFile($root.'/'.$plan, basename($plan), 'image/webp', null, true));

        if (! $listing->canPublish() || $listing->media()->where('kind', 'photo')->count() !== 3
            || $listing->media()->where('kind', 'floorplan')->count() !== 1) {
            throw new RuntimeException('Media atau data listing tidak lengkap setelah unggah.');
        }
        $listing->update(['publication_status' => 'published']);
        $this->info($row['key'].': 3 foto dan 1 denah terunggah.');
    }
}
