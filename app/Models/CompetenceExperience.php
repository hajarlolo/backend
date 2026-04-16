<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CompetenceExperience extends Pivot
{
    protected $table = 'competence_experience';
    public $timestamps = true;
    protected $fillable = ['id_experience', 'id_competence'];
}
