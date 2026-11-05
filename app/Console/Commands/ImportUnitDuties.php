<?php

namespace App\Console\Commands;

use App\Services\UnitDuties;
use Illuminate\Console\Command;

final class ImportUnitDuties extends Command
{
    protected $signature = 'portal:import-unit-duties';
    protected $description = 'Import complete official unit duties once, preserving CMS edits and reporting unavailable sources.';

    public function handle(UnitDuties $duties): int
    {
        try {
            $result = $duties->import();
            $this->info('Unit duties: '.count($result['content_ids']).' records; '.count($result['pending_source_ids']).' official source texts unavailable.');
            if ($result['already_imported']) $this->info('Existing CMS edits and deletions were preserved.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('Unit duties were not imported: '.$error->getMessage());
            return self::FAILURE;
        }
    }
}
