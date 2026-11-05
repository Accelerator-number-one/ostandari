<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, DB, Schema};

class PrepareEventAlerts extends Command
{
    protected $signature = 'portal:prepare-events';
    protected $description = 'Create only the additive event notification tables; leave imported portal data intact.';

    public function handle(): int
    {
        $lock = false;
        try {
            if (Schema::hasTable('event_subscriptions') && Schema::hasTable('event_deliveries')) return self::SUCCESS;
            if (DB::connection()->getDriverName() === 'mysql') {
                $lock = (int) DB::selectOne("SELECT GET_LOCK('portal_prepare_event_alerts', 10) AS acquired")->acquired === 1;
                if (! $lock) return self::FAILURE;
            }
            $status = Artisan::call('migrate', ['--path' => 'database/migrations/2026_10_25_000001_create_event_alert_tables.php',
                '--force' => true, '--no-interaction' => true]);
            $this->output->write(Artisan::output());
            return $status;
        } finally {
            if ($lock) DB::select("SELECT RELEASE_LOCK('portal_prepare_event_alerts')");
        }
    }
}
