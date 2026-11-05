<?php

namespace App\Console\Commands;

use App\Services\{StaffPages, StaffPortals};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, DB};

final class PrepareStaffPortals extends Command
{
    protected $signature = 'portal:prepare-staff-portals {--resync : Check for staff added by a direct database import}';
    protected $description = 'Add isolated staff page tables and missing staff pages without changing existing content or accounts.';
    public function handle(): int
    {
        $lock = false;
        try {
            $this->line(config('website.staff_demo_hash') ? 'Staff test account: hash configured.' : 'Staff test account: provisioning disabled.');
            if (StaffPortals::ready() && StaffPages::ready() && StaffPortals::synchronized() && !$this->option('resync')) {
                $this->prepareTestAccount();
                $this->info('Staff pages already prepared; existing content and accounts preserved.');
                return self::SUCCESS;
            }
            $lock = (int) DB::selectOne("SELECT GET_LOCK('portal_prepare_staff_portals', 2) AS acquired")->acquired === 1;
            if (!$lock) { $this->info('Staff page setup is already running.'); return self::SUCCESS; }
            if (!StaffPortals::ready()) {
                $result = Artisan::call('migrate', ['--path' => 'database/migrations/2026_10_30_000001_create_staff_portals.php', '--force' => true, '--no-interaction' => true]);
                $this->output->write(Artisan::output());
                if ($result !== self::SUCCESS) return $result;
            }
            if (!StaffPages::ready()) {
                $result = Artisan::call('migrate', ['--path' => 'database/migrations/2026_10_30_000002_create_staff_portal_pages.php', '--force' => true, '--no-interaction' => true]);
                $this->output->write(Artisan::output());
                if ($result !== self::SUCCESS) return $result;
            }
            $count = StaffPortals::sync((bool) $this->option('resync'));
            $this->prepareTestAccount();
            $this->info('Staff pages ready; '.$count.' added. Existing content and accounts preserved.');
            return self::SUCCESS;
        } finally { if ($lock) DB::select("SELECT RELEASE_LOCK('portal_prepare_staff_portals')"); }
    }

    private function prepareTestAccount(): void
    {
        if (StaffPortals::bootstrapDemo()) { $this->info('Requested staff test account created.'); return; }
        if (!config('website.staff_demo_hash')) return;
        if (\App\Models\Setting::where('key', 'imports.staff-portal-test-account.2026-10-30.v1')->exists()) {
            $this->info('Staff test account already provisioned; password preserved.');
        } elseif (\App\Models\StaffPortal::where('slug', 'it')->whereNotNull('owner_user_id')->exists()) {
            $this->warn('Staff test account skipped: the page already has an owner.');
        } elseif (\App\Models\User::where('username', config('website.staff_demo_username'))->exists()) {
            $this->warn('Staff test account skipped: the requested username is already in use.');
        } else {
            $this->info('Staff test account pending: the IT page is not available yet.');
        }
    }
}
