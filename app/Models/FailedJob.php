<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedJob extends Model
{
    use HasFactory;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'failed_jobs';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    protected $fillable = ['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'];

    protected $hidden = ['payload', 'exception'];

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }
}
