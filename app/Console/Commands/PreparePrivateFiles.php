<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\PrivateFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, DB, Schema, Storage};

final class PreparePrivateFiles extends Command
{
    protected $signature='portal:prepare-private-files';
    protected $description='Prepare durable private uploads and preserve available originals without changing ownership or access.';

    public function handle(): int
    {
        if (!Schema::hasTable('media')) { $this->error('Run the base database migrations first.'); return self::FAILURE; }
        $lock=(int)DB::selectOne("SELECT GET_LOCK('portal_prepare_private_files', 2) AS acquired")->acquired===1;
        if (!$lock) { $this->info('Private-file preparation is already running.'); return self::SUCCESS; }
        try {
            if (!PrivateFiles::ready()) {
                $result=Artisan::call('migrate',['--path'=>'database/migrations/2026_10_31_000001_create_private_media_files_table.php','--force'=>true,'--no-interaction'=>true]);
                $this->output->write(Artisan::output());
                if ($result!==self::SUCCESS) return $result;
            }
            $saved=0;
            $missing=0;
            Media::where('disk','local')->where('visibility','private')->whereNotIn('id',DB::table('private_media_files')->select('media_id'))
                ->chunkById(100,function($files)use(&$saved,&$missing){
                    foreach($files as $media) {
                        if (!Storage::disk('local')->exists($media->path)) {
                            $missing++;
                            $this->warn('Private original unavailable: media '.$media->id.'. Restore its original storage backup.');
                            continue;
                        }
                        PrivateFiles::save($media,Storage::disk('local')->get($media->path));
                        $saved++;
                    }
                });
            $this->info('Private files ready; '.$saved.' originals preserved, '.$missing.' originals unavailable locally.');
            return self::SUCCESS;
        } finally { DB::select("SELECT RELEASE_LOCK('portal_prepare_private_files')"); }
    }
}
