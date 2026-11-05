<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PrepareEventAlerts implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public function __construct(public int $contentId) {}
    public function handle(): void { \App\Services\EventAlerts::prepare($this->contentId); }
}
