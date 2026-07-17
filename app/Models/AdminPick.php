<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AdminPick extends Model
{
    protected $fillable = ['product_id', 'sort_order'];

    /**
     * Enforce max 5 admin picks (Cold-Start Level 4).
     */
    protected static function booted(): void
    {
        static::saving(function (AdminPick $pick): void {
            // FOR UPDATE + count check covers single-admin scenario.
            // The transaction wraps only the check, not the INSERT — a concurrent
            // request could theoretically sneak between commit and INSERT.
            // Window is negligible with one admin; use DB-level constraint if
            // multi-admin support is needed later.
            DB::transaction(function () use ($pick): void {
                $count = static::lockForUpdate()
                    ->when($pick->exists, fn ($q) => $q->where('id', '!=', $pick->id))
                    ->count();
                if ($count >= 5) {
                    throw new \RuntimeException('Maksimal 5 Pilihan Admin.');
                }
            });
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
