<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Validation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'validations';

    protected $fillable = [
        'conge_id',
        'validateur_id',
        'niveau',
        'decision',
        'statut', 
        'commentaire',
        'date_validation',
    ];

    protected $casts = [
        'date_validation' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function conge(): BelongsTo
    {
        return $this->belongsTo(Conge::class, 'conge_id', 'id');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validateur_id', 'id');
    }
}
