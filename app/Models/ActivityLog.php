<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'module',
        'description',
        'ip_address',
        'user_agent',
        'reference_id',
        'reference_type',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
