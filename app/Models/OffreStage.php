<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OffreStage extends Model
{
    use HasFactory;

    protected $table = 'offre_stages';
    protected $primaryKey = 'id_offre_stage';

    protected $fillable = [
        'id_entreprise',
        'titre',
        'description',
        'document_requise',
        'duree_days',
        'city',
        'country',
        'remuneration',
        'statut',
        'date_publication',
    ];

    protected $casts = [
        'remuneration' => 'decimal:2',
        'date_publication' => 'datetime',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class, 'id_entreprise');
    }

    public function competences()
    {
        return $this->belongsToMany(Competence::class, 'offre_stage_competence', 'id_offre_stage', 'id_competence');
    }

    public function postulations()
    {
        return $this->hasMany(PostulationStage::class, 'id_offre_stage');
    }
}
