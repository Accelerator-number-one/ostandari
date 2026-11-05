<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Dotenv\Dotenv;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\Support\PortalTestSeeder;
use Tests\TestCase;

final class SetupEnvironmentTest extends TestCase
{
    use DatabaseMigrations;

    private function fixture(string $environment): string
    {
        $path = sys_get_temp_dir().'/ostandari-setup-'.bin2hex(random_bytes(8));
        mkdir($path, 0700, true);
        foreach (['bootstrap', 'scripts', 'public'] as $directory) mkdir($path.'/'.$directory, 0700, true);
        foreach (['artisan', 'composer.json', '.env.example', 'bootstrap/app.php', 'scripts/setup.php'] as $file) copy(base_path($file), $path.'/'.$file);
        if (is_file(base_path('bootstrap/providers.php'))) copy(base_path('bootstrap/providers.php'), $path.'/bootstrap/providers.php');
        foreach (['app', 'config', 'database', 'resources', 'routes', 'vendor'] as $directory) symlink(base_path($directory), $path.'/'.$directory);
        foreach (['build', 'design'] as $directory) symlink(public_path($directory), $path.'/public/'.$directory);
        file_put_contents($path.'/.env', $environment);
        $this->beforeApplicationDestroyed(fn () => (new Filesystem)->deleteDirectory($path));
        return $path;
    }

    private function runInFixture(string $path, array $command): Process
    {
        $database = config('database.connections.mysql');
        $process = new Process($command, $path, [
            'APP_ENV' => false, 'APP_KEY' => false, 'APP_CONFIG_CACHE' => false,
            'APP_SERVICES_CACHE' => false, 'APP_PACKAGES_CACHE' => false,
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => $database['host'], 'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => 'ostandari_testing', 'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => $database['password'] ?? '',
            'DB_URL' => false, 'DB_SOCKET' => false, 'CACHE_STORE' => false, 'SESSION_DRIVER' => false,
            'ADMIN_PASSWORD' => false, 'ADMIN_EMAIL' => false, 'ADMIN_USERNAME' => false,
        ]);
        $process->setTimeout(30);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        return $process;
    }

    public function test_setup_generates_a_missing_key_and_preserves_imported_credentials_on_repeated_runs(): void
    {
        $this->seed(PortalTestSeeder::class);
        $admin = User::where('username', 'admin')->firstOrFail();
        $hash = $admin->password;
        $path = $this->fixture(file_get_contents(base_path('.env.example'))."\nADMIN_PASSWORD=admin\n");

        $this->runInFixture($path, [PHP_BINARY, 'scripts/setup.php']);
        $key = Dotenv::parse(file_get_contents($path.'/.env'))['APP_KEY'];
        $this->assertStringStartsWith('base64:', $key);
        $this->assertSame(32, strlen(base64_decode(substr($key, 7), true)));
        $this->assertSame($hash, $admin->fresh()->password);
        $this->runInFixture($path, [PHP_BINARY, 'scripts/setup.php']);
        $this->assertSame($key, Dotenv::parse(file_get_contents($path.'/.env'))['APP_KEY']);
        $this->assertSame($hash, $admin->fresh()->password);
        $this->assertDatabaseCount('users', 1);

        $probe = $this->runInFixture($path, [PHP_BINARY, '-r', 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $kernel=$app->make(Illuminate\\Contracts\\Http\\Kernel::class); $response=$kernel->handle(Illuminate\\Http\\Request::create("/")); echo $response->getStatusCode();']);
        $this->assertSame('200', trim($probe->getOutput()));
    }

    public function test_setup_adds_the_key_when_the_setting_is_absent_and_creates_no_password_account(): void
    {
        $environment = preg_replace('/^APP_KEY=.*\R/m', '', file_get_contents(base_path('.env.example')));
        $path = $this->fixture($environment);
        $this->runInFixture($path, [PHP_BINARY, 'scripts/setup.php']);
        $values = Dotenv::parse(file_get_contents($path.'/.env'));
        $this->assertStringStartsWith('base64:', $values['APP_KEY']);
        $this->assertArrayNotHasKey('ADMIN_PASSWORD', $values);
        $this->assertDatabaseCount('users', 0);
    }
}
