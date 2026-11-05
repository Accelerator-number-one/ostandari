<?php

namespace App\Console\Commands;

use App\Services\ServiceAgreementPages;
use Illuminate\Console\Command;

final class ImportServiceAgreements extends Command
{
    protected $signature = 'portal:import-service-agreements';
    protected $description = 'Store the two supplied service agreements once, preserving future CMS edits and all other data.';

    public function handle(ServiceAgreementPages $pages): int
    {
        try {
            $result = $pages->import();
            $this->info($result['already_imported'] ? 'Service agreements already imported; CMS edits were preserved.' : 'The two service-agreement pages were saved.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('Service agreements were not imported: '.$error->getMessage());
            return self::FAILURE;
        }
    }
}
