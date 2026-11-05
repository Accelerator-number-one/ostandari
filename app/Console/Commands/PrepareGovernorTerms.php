<?php

namespace App\Console\Commands;

use App\Services\GovernorTerms;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, DB, Schema};

final class PrepareGovernorTerms extends Command
{
    protected $signature = 'portal:prepare-governor-terms';
    protected $description = 'Widen member term dates to preserve historical Solar Hijri governor periods.';

    public function handle(): int
    {
        if (!Schema::hasTable('members')) { $this->error('Run the base database migrations first.'); return self::FAILURE; }
        if (GovernorTerms::ready()) { $this->info('Governor term dates are ready; existing dates preserved.'); return self::SUCCESS; }
        $lock = (int) DB::selectOne("SELECT GET_LOCK('portal_prepare_governor_terms', 2) AS acquired")->acquired === 1;
        if (!$lock) { $this->info('Governor term date setup is already running.'); return self::SUCCESS; }
        try {
            if (!GovernorTerms::ready()) {
                $result = Artisan::call('migrate', ['--path'=>'database/migrations/2026_10_30_000003_expand_governor_term_dates.php', '--force'=>true, '--no-interaction'=>true]);
                $this->output->write(Artisan::output());
                if ($result !== self::SUCCESS) return $result;
            }
            $this->info('Governor term dates are ready; existing dates preserved.');
            return self::SUCCESS;
        } finally { DB::select("SELECT RELEASE_LOCK('portal_prepare_governor_terms')"); }
    }
}
