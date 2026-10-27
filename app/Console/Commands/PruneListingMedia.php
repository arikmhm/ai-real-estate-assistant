<?php

namespace App\Console\Commands;

use App\Models\ListingMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneListingMedia extends Command
{
    protected $signature = 'listings:prune-media {--disk= : public atau r2} {--delete : Hapus objek yatim yang sudah tua}';

    protected $description = 'Pratinjau atau hapus objek media listing yatim yang lebih tua dari 24 jam';

    public function handle(): int
    {
        $disk = $this->option('disk') ?: config('listings.media_disk');
        if (! in_array($disk, ['public', 'r2'], true)) {
            $this->error('Disk harus public atau r2.');

            return self::FAILURE;
        }

        $filesystem = Storage::disk($disk);
        $cutoff = now()->subDay()->timestamp;
        $count = 0;
        foreach ($filesystem->allFiles('listings') as $key) {
            if ($filesystem->lastModified($key) >= $cutoff
                || ListingMedia::where('disk', $disk)->where('object_key', $key)->exists()) {
                continue;
            }
            $count++;
            if ($this->option('delete')) {
                if (! $filesystem->delete($key)) {
                    $this->error('Gagal menghapus '.$key);

                    return self::FAILURE;
                }
                $this->line('Dihapus: '.$key);
            } else {
                $this->line('Yatim: '.$key);
            }
        }
        $this->info(($this->option('delete') ? 'Dihapus: ' : 'Ditemukan: ').$count.' objek.');

        return self::SUCCESS;
    }
}
