<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\PublicImages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, DB, Schema, Storage};

final class PreparePublicImages extends Command
{
    protected $signature = 'portal:prepare-public-images';
    protected $description = 'Create durable public-image storage and preserve existing local images without changing media IDs.';

    public function handle(): int
    {
        if (!Schema::hasTable('media')) { $this->error('Run the base database migrations first.'); return self::FAILURE; }
        $lock = (int) DB::selectOne("SELECT GET_LOCK('portal_prepare_public_images', 2) AS acquired")->acquired === 1;
        if (!$lock) { $this->info('Public-image preparation is already running.'); return self::SUCCESS; }
        try {
            if (!PublicImages::ready()) {
                $result = Artisan::call('migrate', ['--path'=>'database/migrations/2026_10_30_000004_create_public_media_files_table.php', '--force'=>true, '--no-interaction'=>true]);
                $this->output->write(Artisan::output());
                if ($result !== self::SUCCESS) return $result;
            }
            $saved = 0;
            $missing = 0;
            Media::where('disk', 'public')->where('visibility', 'public')->whereIn('mime', PublicImages::MIMES)
                ->whereNotIn('id', DB::table('public_media_files')->select('media_id'))->chunkById(100, function ($files) use (&$saved, &$missing) {
                    foreach ($files as $media) {
                        if (!Storage::disk('public')->exists($media->path)) {
                            $missing++;
                            $this->warn('Original image unavailable: media '.$media->id.' ('.$media->path.'). Re-upload the original or restore its storage backup.');
                            continue;
                        }
                        PublicImages::save($media, Storage::disk('public')->get($media->path));
                        $saved++;
                    }
                });
            $this->info('Public images ready; '.$saved.' originals preserved, '.$missing.' originals unavailable locally.');
            return self::SUCCESS;
        } finally { DB::select("SELECT RELEASE_LOCK('portal_prepare_public_images')"); }
    }
}
