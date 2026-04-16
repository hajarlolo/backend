<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissionFreelance extends Model
{
    use HasFactory;

    protected $table = 'mission_freelances';
    protected $primaryKey = 'id_mission';

    protected $fillable = [
        'id_entreprise',
        'titre',
        'description',
        'budget',
        'date_debut',
        'date_fin',
        'duree_days',
        'city',
        'country',
        'statut',
        'date_publication',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_publication' => 'datetime',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class, 'id_entreprise');
    }

    public function competences()
    {
        return $this->belongsToMany(Competence::class, 'mission_freelance_competence', 'id_mission', 'id_competence');
    }

    public function postulations()
    {
        return $this->hasMany(PostulationFreelance::class, 'id_mission');
    }
}
