<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class TestFailingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function handle(): void
    {
        $shouldSucceed = Cache::get('test-failing-job:success', false);

        if (! $shouldSucceed) {
            throw new \RuntimeException(
                'Test job sengaja gagal — jalankan `php artisan tinker --execute "Cache::put(\'test-failing-job:success\', true, 60)"` lalu Retry.'
            );
        }

        Cache::forget('test-failing-job:success');
    }
}
