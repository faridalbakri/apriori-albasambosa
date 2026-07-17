<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'waybill_id', 'courier', 'courier_service', 'estimated_arrival',
    ];

    protected function casts(): array
    {
        return ['estimated_arrival' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Set tracking status via webhook.
     * Admin manual override uses this method too.
     */
    public function setTrackingStatus(string $status): void
    {
        $this->tracking_status = $status;
        $this->save();
    }
}
