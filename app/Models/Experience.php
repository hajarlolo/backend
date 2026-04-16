<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Experience extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_experience';

    protected $fillable = [
        'id_candidat',
        'type',
        'titre',
        'entreprise_nom',
        'description',
        'date_debut',
        'date_fin',
        'en_cours',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'en_cours' => 'boolean',
    ];

    public function candidat()
    {
        return $this->belongsTo(Candidat::class, 'id_candidat');
    }

    public function competences()
    {
        return $this->belongsToMany(Competence::class, 'competence_experience', 'id_experience', 'id_competence');
    }
}
