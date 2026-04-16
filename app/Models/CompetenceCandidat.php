<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CompetenceCandidat extends Pivot
{
    protected $table = 'competence_candidat';
    public $timestamps = false;
    protected $fillable = ['id_candidat', 'id_competence', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
