<?php

namespace Database\Seeders;

use App\Models\AnonymizationLog;
use Illuminate\Database\Seeder;

class AnonymizationLogSeeder extends Seeder
{
    public function run(): void
    {
        // Manual "right to be forgotten" — admin-triggered, always has admin_id + IP
        AnonymizationLog::factory(3)->forgottenManual()->create();

        // Auto-anonymize guest (24 bulan + grace)
        AnonymizationLog::factory(5)->autoGuest()->create();

        // Auto-anonymize registered (36 bulan + grace)
        AnonymizationLog::factory(4)->autoRegistered()->create();
    }
}
