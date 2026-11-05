<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendEventSms implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 20;
    public function __construct(public int $deliveryId) {}
    public function handle(): void { \App\Services\EventAlerts::deliver($this->deliveryId); }
}
