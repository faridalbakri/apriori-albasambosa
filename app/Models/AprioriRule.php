<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AprioriRule extends Model
{
    public $timestamps = false;

    protected $fillable = ['antecedent', 'consequent', 'support', 'confidence', 'lift'];

    protected function casts(): array
    {
        return [
            'antecedent' => 'array',
            'consequent' => 'array',
            'support' => 'float',
            'confidence' => 'float',
            'lift' => 'float',
            'created_at' => 'datetime',
        ];
    }
}
