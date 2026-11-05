<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\StreamHandler;
use Throwable;

final class CheckRuntime extends Command
{
    protected $signature = 'portal:check {--database : Also test the configured database connection}';
    protected $description = 'Verify that the active Laravel logger actually writes to a local file';

    public function handle(): int
    {
        try {
            $logger = Log::driver()->getLogger();
            $handlers = array_values(array_filter($logger->getHandlers(), function ($handler) {
                return $handler instanceof StreamHandler
                    && is_string($handler->getUrl())
                    && ! str_contains($handler->getUrl(), '://');
            }));
            if (! $handlers) {
                $this->error('No file log is active. Set LOG_CHANNEL=stack and LOG_STACK=single, then run php artisan config:clear.');
                return self::FAILURE;
            }

            // Use the configured threshold so this check also works with LOG_LEVEL=warning.
            $level = $handlers[0]->getLevel();
            foreach ($handlers as $handler) {
                if ($handler->getLevel()->value > $level->value) $level = $handler->getLevel();
            }
            $probe = bin2hex(random_bytes(8));
            Log::log(strtolower($level->name), 'PORTAL_LOG_CHECK (diagnostic)', ['probe' => $probe]);

            foreach ($handlers as $handler) {
                $path = $handler->getUrl();
                $file = @fopen($path, 'rb');
                if ($file === false) throw new \RuntimeException('Log file is not readable.');
                try {
                    $size = fstat($file)['size'];
                    fseek($file, max(0, $size - 65536));
                    $tail = stream_get_contents($file);
                } finally {
                    fclose($file);
                }
                if (! str_contains($tail, $probe)) throw new \RuntimeException('The log probe was not written.');
                $this->info('File logging verified: '.$path);
            }
        } catch (Throwable $exception) {
            $this->error('Logging check failed. Ensure storage/logs is writable and the configured file channel is enabled.');
            return self::FAILURE;
        }

        if ($this->option('database')) {
            try {
                DB::select('select 1');
                $this->info('Database connection verified.');
            } catch (Throwable $exception) {
                report($exception);
                $this->error('Database connection failed. See the Laravel log for details and check DB_* in .env.');
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
