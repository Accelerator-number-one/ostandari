<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Schema, Storage};

final class RestoreMediaFiles extends Command
{
    protected $signature = 'portal:restore-media {--overwrite : Replace an existing file only when its database copy has a valid checksum}';
    protected $description = 'Restore public and private originals from the full SQL backup into their standard Laravel storage disks';

    public function handle(): int
    {
        if (! Schema::hasTable('media')) {
            $this->error('Import the full SQL backup and run php artisan migrate first.');
            return self::FAILURE;
        }

        $tables = [];
        foreach (['public_media_files', 'private_media_files'] as $table) {
            if (Schema::hasTable($table)) $tables[] = $table;
        }
        $counts = ['restored' => 0, 'present' => 0, 'missing' => 0, 'invalid' => 0, 'conflict' => 0];
        Media::whereIn('disk', ['public', 'local'])->chunkById(50, function ($items) use ($tables, &$counts) {
            foreach ($items as $media) {
                $disk = Storage::disk($media->disk);
                $path = (string) $media->path;
                if (! $this->safePath($path, $disk->path(''))) {
                    $counts['invalid']++;
                    $this->warn('Unsafe storage path skipped for media '.$media->id.'.');
                    continue;
                }
                $table = $media->disk === 'public' && $media->visibility === 'public'
                    ? 'public_media_files'
                    : ($media->disk === 'local' && $media->visibility === 'private' ? 'private_media_files' : null);
                $row = $table && in_array($table, $tables, true)
                    ? DB::table($table)->where('media_id', $media->id)->first() : null;

                if (! $row) {
                    $counts[$disk->exists($path) ? 'present' : 'missing']++;
                    if (! $disk->exists($path)) $this->warn('Original storage backup needed for media '.$media->id.'.');
                    continue;
                }
                $bytes = base64_decode($row->base64, true);
                if ($bytes === false || ! hash_equals($row->sha256, hash('sha256', $bytes))) {
                    $counts['invalid']++;
                    $this->warn('Invalid database checksum for media '.$media->id.'.');
                    continue;
                }
                if ($disk->exists($path)) {
                    if (hash_equals($row->sha256, hash('sha256', $disk->get($path)))) {
                        $counts['present']++;
                        continue;
                    }
                    if (! $this->option('overwrite')) {
                        $counts['conflict']++;
                        $this->warn('Existing file differs for media '.$media->id.'; it was preserved.');
                        continue;
                    }
                }
                $disk->put($path, $bytes);
                $counts['restored']++;
            }
        });

        $this->table(['Restored', 'Already present', 'Need storage backup', 'Invalid', 'Conflicting'], [array_values($counts)]);
        $this->line('Private uploads remain in storage/app/private. Database rows, users and passwords were not changed.');
        return $counts['missing'] + $counts['invalid'] + $counts['conflict'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function safePath(string $path, string $root): bool
    {
        if ($path === '' || preg_match('~[\\\\:\x00-\x1f\x7f]|^/|(?:^|/)\.{1,2}(?:/|$)~', $path)) return false;
        $directory = rtrim($root, '/\\');
        foreach (explode('/', $path) as $segment) {
            $directory .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($directory)) return false;
        }
        return true;
    }
}
