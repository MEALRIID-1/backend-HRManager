<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Departement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nom',
        'description',
        'responsable_id',
    ];

    public function employes()
    {
        return $this->hasMany(User::class, 'departement_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}