<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Competence extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_competence';

    protected $fillable = [
        'nom',
        'type',
    ];

    public function candidats()
    {
        return $this->belongsToMany(Candidat::class, 'competence_candidat', 'id_competence', 'id_candidat');
    }

    public function experiences()
    {
        return $this->belongsToMany(Experience::class, 'competence_experience', 'id_competence', 'id_experience');
    }

    public function projets()
    {
        return $this->belongsToMany(Projet::class, 'projet_technologie', 'id_competence', 'id_projet');
    }

    public function missions()
    {
        return $this->belongsToMany(MissionFreelance::class, 'mission_freelance_competence', 'id_competence', 'id_mission');
    }

    public function offresEmploi()
    {
        return $this->belongsToMany(OffreEmploi::class, 'offre_emploi_competence', 'id_competence', 'id_offre_emploi');
    }

    public function offresStage()
    {
        return $this->belongsToMany(OffreStage::class, 'offre_stage_competence', 'id_competence', 'id_offre_stage');
    }
}
