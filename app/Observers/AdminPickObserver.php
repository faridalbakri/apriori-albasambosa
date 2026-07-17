<?php

namespace App\Observers;

use App\Models\AdminPick;
use Illuminate\Support\Facades\Cache;

class AdminPickObserver
{
    /**
     * Handle the AdminPick "saved" event (created + updated).
     */
    public function saved(AdminPick $adminPick): void
    {
        Cache::increment('catalog_version');
    }

    /**
     * Handle the AdminPick "deleted" event.
     */
    public function deleted(AdminPick $adminPick): void
    {
        Cache::increment('catalog_version');
    }
}
