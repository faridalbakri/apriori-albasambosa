<?php

namespace App\Models;

use App\Enums\AnonymizationActionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnonymizationLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['user_id'];
    // action_type & anonymized_fields set via service layer

    protected function casts(): array
    {
        return [
            'action_type' => AnonymizationActionType::class,
            'anonymized_fields' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
