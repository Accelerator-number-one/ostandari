<?php

namespace App\Console\Commands;

use App\Services\MemberDirectory;
use Illuminate\Console\Command;

final class ImportMemberDirectory extends Command
{
    protected $signature = 'portal:import-member-directory {--photo : Retry downloading the shared portrait after an earlier import}';
    protected $description = 'Import the supplied 198-person directory once, preserving existing edits and county data.';

    public function handle(MemberDirectory $directory): int
    {
        try{
            $result=$directory->import();
            \App\Services\StaffPortals::sync(!$result['already_imported']);
            \App\Services\StaffPortals::bootstrapDemo();
            $this->info($result['already_imported']?'Directory already imported; existing edits were preserved.':
                'Directory imported: '.$result['created'].' added, '.$result['existing'].' existing, '.$result['skipped'].' inactive/deleted records preserved.');
            if((!$result['already_imported'] || $this->option('photo')) && !$directory->downloadPortrait())
                $this->warn('Shared photo could not be downloaded. The supplied image URL remains the temporary portrait; individual uploaded photos take precedence.');
            return self::SUCCESS;
        }catch(\Throwable $error){
            $this->error('Directory import was not completed: '.$error->getMessage());
            return self::FAILURE;
        }
    }
}
