<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** These checks intentionally do not need a database connection. */
final class LocalRuntimeTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logPath = sys_get_temp_dir().'/ostandari-log-test-'.bin2hex(random_bytes(8)).'.log';
        config([
            'logging.default' => 'stack',
            'logging.channels.stack.channels' => ['single'],
            'logging.channels.single.path' => $this->logPath,
            'logging.channels.single.level' => 'debug',
        ]);
        Log::forgetChannel('stack');
        Log::forgetChannel('single');
    }

    protected function tearDown(): void
    {
        Log::forgetChannel('stack');
        Log::forgetChannel('single');
        Log::forgetChannel('daily');
        if (is_file($this->logPath)) unlink($this->logPath);
        foreach (glob(substr($this->logPath, 0, -4).'-*.log') as $path) unlink($path);
        parent::tearDown();
    }

    public function test_runtime_check_writes_and_reads_the_actual_default_log(): void
    {
        $this->artisan('portal:check')->expectsOutputToContain('File logging verified:')->assertSuccessful();
        $this->assertStringContainsString('PORTAL_LOG_CHECK', file_get_contents($this->logPath));
    }

    public function test_file_logging_check_also_respects_a_production_warning_threshold(): void
    {
        config(['logging.channels.single.level' => 'warning']);
        Log::forgetChannel('single');
        Log::forgetChannel('stack');
        $this->artisan('portal:check')->assertSuccessful();
        $this->assertStringContainsString('.WARNING: PORTAL_LOG_CHECK', file_get_contents($this->logPath));
    }

    public function test_reported_exceptions_are_recorded_when_debug_is_disabled(): void
    {
        config(['app.debug' => false]);
        $this->app->make(ExceptionHandler::class)->report(new RuntimeException('runtime-exception-probe'));
        $this->assertStringContainsString('runtime-exception-probe', file_get_contents($this->logPath));
    }

    public function test_server_daily_logging_is_written_and_verified(): void
    {
        config([
            'logging.channels.stack.channels' => ['daily'],
            'logging.channels.daily.path' => $this->logPath,
            'logging.channels.daily.level' => 'warning',
        ]);
        Log::forgetChannel('stack');
        $this->artisan('portal:check')->assertSuccessful();
        $files = glob(substr($this->logPath, 0, -4).'-*.log');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('PORTAL_LOG_CHECK', file_get_contents($files[0]));
    }

    public function test_a_disabled_file_logger_is_not_reported_as_working(): void
    {
        config(['logging.default' => 'null']);
        $this->artisan('portal:check')->expectsOutputToContain('No file log is active.')->assertFailed();
        $this->assertFileDoesNotExist($this->logPath);
    }
}
