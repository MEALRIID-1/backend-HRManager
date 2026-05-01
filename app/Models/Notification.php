<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'type',
        'titre',
        'message',
        'action_url',
        'icone',
        'lu',
        'date_lecture',
        'data',
        'reference_id',
        'reference_type',
    ];

    protected $casts = [
        'date_envoi' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
